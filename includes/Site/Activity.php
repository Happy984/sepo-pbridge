<?php
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Custom plugin tables: names are built from $wpdb->prefix plus a fixed suffix, values go through $wpdb->prepare(), and dynamic fragments come from fixed allow-lists.
namespace SepoWB\Site;

use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * Page views and account activity, kept in two tables of this site's database.
 *
 * Views are sent by a small script after the page loads, so cached pages are
 * counted too. No cookie is set and no address is stored: a visitor is a
 * one-way hash of the address, browser and day, so the same person is not
 * recognised from one day to the next. Everything is switched off until the
 * master switch on the Activity tab is on, and only the roles ticked there
 * are recorded.
 */
final class Activity
{
    public const CRON = 'sepo_wb_activity_purge';
    public const CAP = 'sepo_wb_view_activity';
    private const DB_OPTION = 'sepo_wb_activity_db';
    private const DB_VERSION = '1';

    public static function views(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sepo_wb_views';
    }

    public static function log(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sepo_wb_activity';
    }

    public static function install(): void
    {
        if (get_option(self::DB_OPTION) === self::DB_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta('CREATE TABLE ' . self::views() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created datetime NOT NULL,
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  path varchar(255) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  role varchar(60) NOT NULL DEFAULT '',
  visitor char(32) NOT NULL DEFAULT '',
  referrer varchar(120) NOT NULL DEFAULT '',
  device varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY created (created),
  KEY post_id (post_id),
  KEY role (role)
) {$c};");
        dbDelta('CREATE TABLE ' . self::log() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created datetime NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  role varchar(60) NOT NULL DEFAULT '',
  action varchar(40) NOT NULL DEFAULT '',
  object_type varchar(20) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(190) NOT NULL DEFAULT '',
  details varchar(255) NOT NULL DEFAULT '',
  visitor char(32) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY created (created),
  KEY user_id (user_id),
  KEY role (role),
  KEY action (action)
) {$c};");
        update_option(self::DB_OPTION, self::DB_VERSION);
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
        }
    }

    /** @return array<string,mixed> */
    private static function settings(): array
    {
        return SiteSettings::section('activity');
    }

    public static function on(): bool
    {
        return !empty(self::settings()['enabled']);
    }

    public static function hook(): void
    {
        add_action(self::CRON, [self::class, 'purge']);
        add_action('rest_api_init', static function (): void {
            register_rest_route('sepo-wb/v1', '/hit', [
                'methods'             => 'POST',
                // Anyone may send a page view; nothing is returned and the input is checked below.
                'permission_callback' => '__return_true',
                'callback'            => [self::class, 'hit'],
                'args'                => [
                    'p' => ['type' => 'integer', 'default' => 0],
                    'u' => ['type' => 'string', 'default' => ''],
                    'r' => ['type' => 'string', 'default' => ''],
                ],
            ]);
        });
        if (!self::on()) {
            return;
        }
        $s = self::settings();
        if (!empty($s['views'])) {
            add_action('wp_enqueue_scripts', [self::class, 'beacon']);
        }
        if (!empty($s['actions'])) {
            self::actionHooks();
        }
        // Other plugins can add their own lines: do_action('sepo_wb_activity', 'enrolled', 'course', 12, 'Course title').
        add_action('sepo_wb_activity', static function (string $action, string $type = '', int $id = 0, string $title = '', string $details = ''): void {
            self::record(get_current_user_id(), sanitize_key($action), sanitize_key($type), $id, $title, $details);
        }, 10, 5);
    }

    public static function beacon(): void
    {
        if (is_admin() || is_preview() || is_customize_preview() || is_404()) {
            return;
        }
        Assets::front();
        wp_localize_script('sepo-wb-site', 'SepoWBView', [
            'url'   => esc_url_raw(rest_url('sepo-wb/v1/hit')),
            'post'  => is_singular() ? (int) get_queried_object_id() : 0,
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
        ]);
    }

    /** The role recorded for a person: their first role, or "guest". */
    public static function roleOf(int $userId): string
    {
        if ($userId <= 0) {
            return 'guest';
        }
        $u = get_userdata($userId);
        $roles = $u ? array_values((array) $u->roles) : [];
        return $roles !== [] ? (string) $roles[0] : 'none';
    }

    private static function tracks(string $role): bool
    {
        return in_array($role, (array) self::settings()['roles'], true);
    }

    private static function visitor(): string
    {
        $ip = sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
        $ua = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
        return md5(wp_salt('auth') . '|' . gmdate('Y-m-d') . '|' . $ip . '|' . $ua);
    }

    private static function isBot(): bool
    {
        $ua = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
        return $ua === '' || (bool) preg_match('~bot|crawl|spider|slurp|facebookexternalhit|preview|headless|monitor|curl|wget|python|scrapy~i', $ua);
    }

    public static function hit(WP_REST_Request $r): WP_REST_Response
    {
        $s = self::settings();
        if (!self::on() || empty($s['views']) || self::isBot()) {
            return new WP_REST_Response(null, 204);
        }
        $uid = get_current_user_id();
        $role = self::roleOf($uid);
        if (!self::tracks($role) || (!empty($s['skip_admin_views']) && user_can($uid, 'manage_options'))) {
            return new WP_REST_Response(null, 204);
        }
        $path = (string) wp_parse_url(esc_url_raw((string) $r->get_param('u')), PHP_URL_PATH);
        $path = mb_substr($path !== '' ? $path : '/', 0, 255);
        $visitor = self::visitor();
        // A reload within half a minute is the same view.
        $key = 'sepo_wb_v_' . md5($visitor . $path);
        if (get_transient($key)) {
            return new WP_REST_Response(null, 204);
        }
        set_transient($key, 1, 30);
        $post = absint($r->get_param('p'));
        if ($post > 0 && get_post_status($post) !== 'publish') {
            $post = 0;
        }
        $ref = (string) wp_parse_url(esc_url_raw((string) $r->get_param('r')), PHP_URL_HOST);
        $home = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $ua = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
        $device = preg_match('~ipad|tablet|kindle|playbook|silk~i', $ua) ? 'tablet' : (wp_is_mobile() ? 'phone' : 'desktop');
        global $wpdb;
        $wpdb->insert(self::views(), [
            'created'  => gmdate('Y-m-d H:i:s'),
            'post_id'  => $post,
            'path'     => $path,
            'user_id'  => $uid,
            'role'     => $role,
            'visitor'  => $visitor,
            'referrer' => $ref !== $home ? mb_substr(preg_replace('~^www\.~', '', $ref), 0, 120) : '',
            'device'   => $device,
        ]);
        return new WP_REST_Response(null, 204);
    }

    /** Write one line to the activity log, when that person's role is recorded. */
    public static function record(int $userId, string $action, string $type = '', int $objectId = 0, string $title = '', string $details = ''): void
    {
        if (!self::on() || empty(self::settings()['actions'])) {
            return;
        }
        $role = self::roleOf($userId);
        if (!self::tracks($role)) {
            return;
        }
        global $wpdb;
        $wpdb->insert(self::log(), [
            'created'     => gmdate('Y-m-d H:i:s'),
            'user_id'     => max(0, $userId),
            'role'        => $role,
            'action'      => mb_substr($action, 0, 40),
            'object_type' => mb_substr($type, 0, 20),
            'object_id'   => max(0, $objectId),
            'title'       => mb_substr(wp_strip_all_tags($title), 0, 190),
            'details'     => mb_substr(wp_strip_all_tags($details), 0, 255),
            'visitor'     => self::visitor(),
        ]);
    }

    /** What each action is called on screen. @return array<string,string> */
    public static function actionLabels(): array
    {
        return [
            'login'        => __('Signed in', 'sepo-bridge'),
            'logout'       => __('Signed out', 'sepo-bridge'),
            'login_failed' => __('Sign-in failed', 'sepo-bridge'),
            'registered'   => __('Account created', 'sepo-bridge'),
            'profile'      => __('Profile updated', 'sepo-bridge'),
            'role_changed' => __('Role changed', 'sepo-bridge'),
            'user_deleted' => __('Account deleted', 'sepo-bridge'),
            'published'    => __('Published', 'sepo-bridge'),
            'updated'      => __('Updated', 'sepo-bridge'),
            'trashed'      => __('Moved to the bin', 'sepo-bridge'),
            'deleted'      => __('Deleted for good', 'sepo-bridge'),
            'uploaded'     => __('Uploaded a file', 'sepo-bridge'),
            'commented'    => __('Commented', 'sepo-bridge'),
            'plugin_on'    => __('Plugin activated', 'sepo-bridge'),
            'plugin_off'   => __('Plugin deactivated', 'sepo-bridge'),
            'theme'        => __('Theme changed', 'sepo-bridge'),
        ];
    }

    private static function actionHooks(): void
    {
        add_action('wp_login', static function (string $login, \WP_User $user): void {
            self::record((int) $user->ID, 'login', 'user', (int) $user->ID, $user->display_name);
        }, 10, 2);
        add_action('wp_logout', static function (int $userId = 0): void {
            self::record($userId, 'logout', 'user', $userId);
        });
        add_action('wp_login_failed', static function (string $login): void {
            self::record(0, 'login_failed', 'user', 0, '', mb_substr(sanitize_user($login), 0, 60));
        });
        add_action('user_register', static function (int $userId): void {
            $u = get_userdata($userId);
            self::record(get_current_user_id() ?: $userId, 'registered', 'user', $userId, $u ? $u->display_name : '');
        });
        add_action('profile_update', static function (int $userId): void {
            $u = get_userdata($userId);
            self::record(get_current_user_id() ?: $userId, 'profile', 'user', $userId, $u ? $u->display_name : '');
        });
        add_action('set_user_role', static function (int $userId, string $role, array $old): void {
            $u = get_userdata($userId);
            self::record(get_current_user_id(), 'role_changed', 'user', $userId, $u ? $u->display_name : '', implode(', ', $old) . ' → ' . $role);
        }, 10, 3);
        add_action('delete_user', static function (int $userId): void {
            $u = get_userdata($userId);
            self::record(get_current_user_id(), 'user_deleted', 'user', $userId, $u ? $u->display_name : '');
        });
        add_action('transition_post_status', static function (string $new, string $old, \WP_Post $post): void {
            if (!self::loggable($post)) {
                return;
            }
            if ($new === 'publish' && $old !== 'publish') {
                self::record(get_current_user_id(), 'published', $post->post_type, (int) $post->ID, $post->post_title);
            } elseif ($new === 'trash' && $old !== 'trash') {
                self::record(get_current_user_id(), 'trashed', $post->post_type, (int) $post->ID, $post->post_title);
            }
        }, 10, 3);
        add_action('post_updated', static function (int $id, \WP_Post $after, \WP_Post $before): void {
            if (self::loggable($after) && $after->post_status === 'publish' && $before->post_status === 'publish') {
                self::record(get_current_user_id(), 'updated', $after->post_type, $id, $after->post_title);
            }
        }, 10, 3);
        add_action('before_delete_post', static function (int $id): void {
            $post = get_post($id);
            if ($post && self::loggable($post)) {
                self::record(get_current_user_id(), 'deleted', $post->post_type, $id, $post->post_title);
            }
        });
        add_action('add_attachment', static function (int $id): void {
            self::record(get_current_user_id(), 'uploaded', 'attachment', $id, (string) get_the_title($id));
        });
        add_action('wp_insert_comment', static function (int $id, \WP_Comment $c): void {
            self::record((int) $c->user_id, 'commented', 'comment', (int) $c->comment_post_ID, (string) get_the_title((int) $c->comment_post_ID));
        }, 10, 2);
        add_action('activated_plugin', static function (string $file): void {
            self::record(get_current_user_id(), 'plugin_on', 'plugin', 0, $file);
        });
        add_action('deactivated_plugin', static function (string $file): void {
            self::record(get_current_user_id(), 'plugin_off', 'plugin', 0, $file);
        });
        add_action('switch_theme', static function (string $name): void {
            self::record(get_current_user_id(), 'theme', 'theme', 0, $name);
        });
    }

    private static function loggable(\WP_Post $post): bool
    {
        return !wp_is_post_revision($post) && !wp_is_post_autosave($post)
            && !in_array($post->post_type, ['nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_global_styles', 'wp_navigation', 'wp_template', 'wp_template_part', 'attachment'], true)
            && $post->post_status !== 'auto-draft';
    }

    public static function purge(): void
    {
        global $wpdb;
        $before = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) self::settings()['keep_days']);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::views() . ' WHERE created < %s', $before));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::log() . ' WHERE created < %s', $before));
    }

    public static function clear(): void
    {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . self::views());
        $wpdb->query('TRUNCATE TABLE ' . self::log());
    }

    // ---------------------------------------------------------------- reports

    /** @return array{views:int,visitors:int,people:int,days:list<array{day:string,views:int}>,pages:list<array<string,mixed>>,roles:list<array<string,mixed>>,devices:list<array<string,mixed>>,referrers:list<array<string,mixed>>} */
    public static function summary(int $days, string $role = ''): array
    {
        global $wpdb;
        $t = self::views();
        $from = gmdate('Y-m-d 00:00:00', time() - DAY_IN_SECONDS * ($days - 1));
        $where = $wpdb->prepare('created >= %s', $from) . ($role !== '' ? $wpdb->prepare(' AND role = %s', $role) : '');
        $tot = $wpdb->get_row("SELECT COUNT(*) AS v, COUNT(DISTINCT visitor) AS u, COUNT(DISTINCT NULLIF(user_id, 0)) AS p FROM {$t} WHERE {$where}", ARRAY_A) ?: [];
        $byDay = [];
        foreach ((array) $wpdb->get_results("SELECT DATE(created) AS d, COUNT(*) AS v FROM {$t} WHERE {$where} GROUP BY DATE(created)", ARRAY_A) as $r) {
            $byDay[(string) $r['d']] = (int) $r['v'];
        }
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', time() - DAY_IN_SECONDS * $i);
            $series[] = ['day' => $d, 'views' => $byDay[$d] ?? 0];
        }
        return [
            'views'     => (int) ($tot['v'] ?? 0),
            'visitors'  => (int) ($tot['u'] ?? 0),
            'people'    => (int) ($tot['p'] ?? 0),
            'days'      => $series,
            'pages'     => (array) $wpdb->get_results("SELECT path, MAX(post_id) AS post_id, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM {$t} WHERE {$where} GROUP BY path ORDER BY views DESC LIMIT 15", ARRAY_A),
            'roles'     => (array) $wpdb->get_results("SELECT role, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM {$t} WHERE {$where} GROUP BY role ORDER BY views DESC", ARRAY_A),
            'devices'   => (array) $wpdb->get_results("SELECT device, COUNT(*) AS views FROM {$t} WHERE {$where} GROUP BY device ORDER BY views DESC", ARRAY_A),
            'referrers' => (array) $wpdb->get_results("SELECT referrer, COUNT(*) AS views FROM {$t} WHERE {$where} AND referrer <> '' GROUP BY referrer ORDER BY views DESC LIMIT 10", ARRAY_A),
        ];
    }

    /**
     * @param array{role?:string,action?:string,user?:int,from?:string,to?:string} $f
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function entries(array $f, int $page, int $per = 50): array
    {
        global $wpdb;
        $t = self::log();
        $w = ['1=1'];
        if (($f['role'] ?? '') !== '') {
            $w[] = $wpdb->prepare('role = %s', $f['role']);
        }
        if (($f['action'] ?? '') !== '') {
            $w[] = $wpdb->prepare('action = %s', $f['action']);
        }
        if ((int) ($f['user'] ?? 0) > 0) {
            $w[] = $wpdb->prepare('user_id = %d', (int) $f['user']);
        }
        if (($f['from'] ?? '') !== '') {
            $w[] = $wpdb->prepare('created >= %s', $f['from'] . ' 00:00:00');
        }
        if (($f['to'] ?? '') !== '') {
            $w[] = $wpdb->prepare('created <= %s', $f['to'] . ' 23:59:59');
        }
        $where = implode(' AND ', $w);
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE {$where}");
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per, max(0, ($page - 1) * $per)), ARRAY_A);
        return ['rows' => $rows, 'total' => $total];
    }

    /** Lines per role in the period, for the role cards. @return array<string,int> */
    public static function actionsByRole(int $days): array
    {
        global $wpdb;
        $from = gmdate('Y-m-d 00:00:00', time() - DAY_IN_SECONDS * ($days - 1));
        $out = [];
        foreach ((array) $wpdb->get_results($wpdb->prepare('SELECT role, COUNT(*) AS n FROM ' . self::log() . ' WHERE created >= %s GROUP BY role', $from), ARRAY_A) as $r) {
            $out[(string) $r['role']] = (int) $r['n'];
        }
        return $out;
    }
}
