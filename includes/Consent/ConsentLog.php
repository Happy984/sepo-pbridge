<?php
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom plugin tables: names are built from $wpdb->prefix plus a fixed suffix, values go through $wpdb->prepare(), and dynamic fragments come from fixed allow-lists.
namespace SepoWB\Consent;

defined('ABSPATH') || exit;

/**
 * Proof of consent: one row per choice a visitor makes. IP addresses are
 * shortened before they are stored (last part of IPv4, last 80 bits of
 * IPv6 removed). Rows older than the retention period are removed daily.
 */
final class ConsentLog
{
    public const CRON = 'sepo_wb_consent_purge';
    public const ACTIONS = ['accept_all', 'reject_all', 'custom', 'revoke'];

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sepo_wb_consent_log';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$t} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  consent_id varchar(40) NOT NULL DEFAULT '',
  categories varchar(120) NOT NULL DEFAULT '',
  action varchar(20) NOT NULL DEFAULT '',
  version int(10) unsigned NOT NULL DEFAULT 1,
  ip varchar(64) NOT NULL DEFAULT '',
  url varchar(255) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY consent_id (consent_id),
  KEY created (created)
) {$charset};");
    }

    public static function hook(): void
    {
        add_action(self::CRON, [self::class, 'purge']);
        add_action('init', static function (): void {
            if (!wp_next_scheduled(self::CRON)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
            }
        });
    }

    public static function anonymiseIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (string) preg_replace('/\.\d+$/', '.0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);
            if ($bin !== false) {
                $bin = substr($bin, 0, 6) . str_repeat("\0", 10);
                return (string) inet_ntop($bin);
            }
        }
        return '';
    }

    /** @param list<string> $categories */
    public static function add(string $consentId, array $categories, string $action, int $version, string $url): void
    {
        global $wpdb;
        $wpdb->insert(self::table(), [ // phpcs:ignore WordPress.DB
            'consent_id' => substr($consentId, 0, 40),
            'categories' => implode(',', $categories),
            'action'     => $action,
            'version'    => $version,
            'ip'         => self::anonymiseIp(sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')))),
            'url'        => substr(esc_url_raw($url), 0, 255),
            'user_id'    => get_current_user_id(),
            'created'    => current_time('mysql', true),
        ]);
    }

    public static function purge(): void
    {
        global $wpdb;
        $days = (int) ConsentSettings::get('log_days');
        $t = self::table();
        $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE created < %s", gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS))); // phpcs:ignore WordPress.DB
    }

    /** Totals for the last 30 days, and the 20 most recent choices. @return array<string,mixed> */
    public static function stats(): array
    {
        global $wpdb;
        $t = self::table();
        $since = gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
        $counts = array_fill_keys(self::ACTIONS, 0);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT action, COUNT(*) n FROM {$t} WHERE created >= %s GROUP BY action", $since), ARRAY_A); // phpcs:ignore WordPress.DB
        foreach ((array) $rows as $r) {
            if (isset($counts[$r['action']])) {
                $counts[$r['action']] = (int) $r['n'];
            }
        }
        $cats = array_fill_keys(ConsentSettings::CATEGORIES, 0);
        $all = $wpdb->get_col($wpdb->prepare("SELECT categories FROM {$t} WHERE created >= %s AND action <> 'revoke'", $since)); // phpcs:ignore WordPress.DB
        foreach ((array) $all as $c) {
            foreach (explode(',', (string) $c) as $k) {
                if (isset($cats[$k])) {
                    $cats[$k]++;
                }
            }
        }
        $recent = $wpdb->get_results("SELECT consent_id, categories, action, version, ip, created FROM {$t} ORDER BY id DESC LIMIT 20", ARRAY_A); // phpcs:ignore WordPress.DB
        return [
            'counts'  => $counts,
            'total'   => array_sum($counts),
            'byCategory' => $cats,
            'recent'  => array_map(static fn(array $r): array => $r + ['created' => mysql2date('Y-m-d H:i', get_date_from_gmt($r['created']))], (array) $recent),
        ];
    }

    /** Send the whole log as a CSV download. */
    public static function exportCsv(): void
    {
        global $wpdb;
        $t = self::table();
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="consent-log-' . gmdate('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV stream to the browser.
        fputcsv($out, ['consent_id', 'action', 'categories', 'version', 'ip', 'url', 'user_id', 'created_utc']);
        $offset = 0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT consent_id, action, categories, version, ip, url, user_id, created FROM {$t} ORDER BY id ASC LIMIT %d, 1000", $offset), ARRAY_N); // phpcs:ignore WordPress.DB
            foreach ((array) $rows as $r) {
                // A leading = + - @ would run as a formula in spreadsheet apps.
                fputcsv($out, array_map(static fn($v): string => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, $r));
            }
            $offset += 1000;
        } while (count((array) $rows) === 1000);
        exit;
    }
}
