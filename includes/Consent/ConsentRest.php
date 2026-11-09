<?php
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are returned as JSON data or escaped at the point they are displayed.
namespace SepoWB\Consent;

use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * The Cookies tab's API, under /wp-json/sepo-wb/v1/cookies. Every route
 * needs manage_options and the wp_rest nonce.
 */
final class ConsentRest
{
    private const NS = 'sepo-wb/v1';
    private const SCAN_LIMIT = 30;

    public static function register(): void
    {
        $routes = [
            ['GET',  '/cookies',              'overview'],
            ['POST', '/cookies/save',         'save'],
            ['POST', '/cookies/list',         'saveList'],
            ['GET',  '/cookies/scan-urls',    'scanUrls'],
            ['POST', '/cookies/scan-results', 'scanResults'],
            ['POST', '/cookies/report',       'report'],
            ['POST', '/cookies/version',      'bumpVersion'],
            ['GET',  '/cookies/log',          'log'],
        ];
        foreach ($routes as [$method, $path, $cb]) {
            register_rest_route(self::NS, $path, [
                'methods'             => $method,
                'callback'            => self::wrap($cb),
                'permission_callback' => static fn() => current_user_can('manage_options'),
            ]);
        }
    }

    private static function wrap(string $method): callable
    {
        return static function (WP_REST_Request $req) use ($method) {
            try {
                return self::{$method}($req);
            } catch (InvalidArgumentException $e) {
                return new WP_Error('sepo_wb_invalid', $e->getMessage(), ['status' => 422]);
            } catch (\Throwable $e) {
                do_action('sepo_wb_error', 'cookies ' . $method, $e);
                return new WP_Error('sepo_wb_error', __('Something went wrong. Nothing was changed.', 'sepo-bridge'), ['status' => 500]);
            }
        };
    }

    /** @param array<string,mixed> $data */
    private static function ok(array $data = []): WP_REST_Response
    {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    // ------------------------------------------------------------ settings

    public static function overview(): WP_REST_Response
    {
        $s = ConsentSettings::all();
        $pages = [];
        foreach (get_pages(['post_status' => 'publish', 'number' => 300, 'sort_column' => 'post_title']) as $p) {
            $pages[] = ['value' => (int) $p->ID, 'label' => $p->post_title !== '' ? $p->post_title : '#' . $p->ID];
        }
        return self::ok([
            'settings'   => array_diff_key($s, array_flip(['cookies'])),
            'cookies'    => $s['cookies'],
            'defaults'   => ['texts' => ConsentSettings::defaultTexts(), 'categories' => ConsentSettings::defaultCategories()],
            'pages'      => $pages,
            'privacyPage'=> (int) get_option('wp_page_for_privacy_policy'),
            'previewUrl' => add_query_arg(Consent::PREVIEW_PARAM, '1', home_url('/')),
            'csvUrl'     => add_query_arg(['action' => 'sepo_wb_consent_csv', '_wpnonce' => wp_create_nonce('sepo_wb_consent_csv')], admin_url('admin-post.php')),
            'stats'      => ConsentLog::stats(),
            'services'   => array_map(static fn(array $v): array => ['name' => $v['name'], 'category' => $v['category']], CookieCatalog::services()),
        ]);
    }

    public static function save(WP_REST_Request $req): WP_REST_Response
    {
        $in = (array) $req->get_json_params();
        unset($in['cookies'], $in['version']);
        $clean = ConsentSettings::sanitize($in);
        ConsentSettings::update($clean);
        return self::ok(['settings' => array_diff_key(ConsentSettings::all(), array_flip(['cookies']))]);
    }

    public static function saveList(WP_REST_Request $req): WP_REST_Response
    {
        $rows = $req->get_param('cookies');
        if (!is_array($rows)) {
            throw new InvalidArgumentException(__('No cookie list was sent.', 'sepo-bridge'));
        }
        ConsentSettings::update(['cookies' => ConsentSettings::cleanCookies($rows)]);
        return self::ok(['cookies' => ConsentSettings::get('cookies')]);
    }

    public static function bumpVersion(): WP_REST_Response
    {
        $v = (int) ConsentSettings::get('version') + 1;
        ConsentSettings::update(['version' => $v]);
        return self::ok(['version' => $v]);
    }

    public static function log(): WP_REST_Response
    {
        return self::ok(['stats' => ConsentLog::stats()]);
    }

    // ------------------------------------------------------------ scanning

    /** A sample of the site's public pages to load during a scan. */
    public static function scanUrls(): WP_REST_Response
    {
        $urls = [home_url('/')];
        $types = get_post_types(['public' => true, 'publicly_queryable' => true], 'names');
        $types[] = 'page';
        foreach (array_unique($types) as $type) {
            if ($type === 'attachment') {
                continue;
            }
            $ids = get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => $type === 'page' ? 8 : 2, 'fields' => 'ids', 'orderby' => 'modified', 'has_password' => false]);
            foreach ($ids as $id) {
                $urls[] = (string) get_permalink($id);
            }
            if ($type !== 'page' && ($archive = get_post_type_archive_link($type))) {
                $urls[] = (string) $archive;
            }
        }
        if (function_exists('wc_get_page_permalink')) {
            foreach (['shop', 'cart', 'checkout', 'myaccount'] as $p) {
                $urls[] = (string) wc_get_page_permalink($p);
            }
        }
        $policy = ConsentSettings::policyUrl();
        if ($policy !== '') {
            $urls[] = $policy;
        }
        /** Change the pages a cookie scan loads. */
        $urls = (array) apply_filters('sepo_wb_cookie_scan_urls', $urls);
        $home = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $nonce = wp_create_nonce('sepo_wb_scan');
        $out = [];
        foreach (array_unique(array_filter(array_map('strval', $urls))) as $u) {
            if ((string) wp_parse_url($u, PHP_URL_HOST) !== $home) {
                continue;
            }
            $out[] = add_query_arg(Consent::SCAN_PARAM, $nonce, $u);
            if (count($out) >= self::SCAN_LIMIT) {
                break;
            }
        }
        return self::ok(['urls' => $out]);
    }

    /**
     * Merge what a scan found into the cookie list. Rows the administrator
     * edited keep their wording; new cookies are described from the
     * catalogue, or marked unclassified.
     */
    public static function scanResults(WP_REST_Request $req): WP_REST_Response
    {
        $names = array_map('strval', (array) $req->get_param('cookies'));
        // Cookies the browser sent with this request, including ones scripts cannot read.
        $names = array_merge($names, array_keys($_COOKIE));
        $hits = (array) $req->get_param('hits');
        $hosts = array_values(array_unique(array_filter(array_map(static fn($h) => strtolower(preg_replace('/[^a-z0-9\.\-]/i', '', (string) $h) ?? ''), (array) $req->get_param('hosts')))));
        $services = CookieCatalog::services();
        $found = [];
        foreach ($names as $n) {
            $n = trim($n);
            if ($n !== '' && strlen($n) <= 120) {
                $found[] = ['name' => $n];
            }
        }
        $detected = [];
        foreach (array_keys($hits) as $svc) {
            $svc = (string) $svc;
            if (!isset($services[$svc])) {
                continue;
            }
            $detected[] = $svc;
            foreach ($services[$svc]['cookies'] as $c) {
                $found[] = ['name' => $c['name'], 'service' => $svc];
            }
        }
        $result = self::merge(ConsentSettings::get('cookies'), $found);
        ConsentSettings::update([
            'cookies'    => $result['cookies'],
            'services'   => array_values(array_unique($detected)),
            'last_scan'  => current_time('mysql'),
            'scan_pages' => max(0, (int) $req->get_param('pages')),
        ]);
        return self::ok([
            'cookies' => ConsentSettings::get('cookies'),
            'added'   => $result['added'],
            'services'=> array_map(static fn(string $id): string => $services[$id]['name'], array_values(array_unique($detected))),
            'hosts'   => array_slice($hosts, 0, 40),
            'lastScan'=> ConsentSettings::get('last_scan'),
            'pages'   => ConsentSettings::get('scan_pages'),
        ]);
    }

    /** Cookies noticed while an administrator browses the site. */
    public static function report(WP_REST_Request $req): WP_REST_Response
    {
        if (!ConsentSettings::get('detect')) {
            return self::ok(['added' => 0]);
        }
        $found = [];
        foreach (array_slice((array) $req->get_param('cookies'), 0, 50) as $n) {
            $found[] = ['name' => trim((string) $n)];
        }
        $result = self::merge(ConsentSettings::get('cookies'), $found);
        if ($result['added'] > 0) {
            ConsentSettings::update(['cookies' => $result['cookies']]);
        }
        return self::ok(['added' => $result['added']]);
    }

    /**
     * @param list<array<string,string>> $list
     * @param list<array{name:string,service?:string}> $found
     * @return array{cookies:list<array<string,string>>,added:int}
     */
    private static function merge(array $list, array $found): array
    {
        $today = current_time('Y-m-d');
        $index = [];
        foreach ($list as $i => $c) {
            $index[strtolower($c['name'])] = $i;
        }
        $added = 0;
        foreach ($found as $f) {
            $name = $f['name'];
            if ($name === '' || !preg_match('/^[A-Za-z0-9_\-\.\*\$\[\]@:]+$/', $name)) {
                continue;
            }
            $info = isset($f['service']) ? self::serviceCookie($f['service'], $name) : CookieCatalog::lookup($name);
            // A known name with a changing ending (wordpress_logged_in_<hash>) is listed once, under its pattern.
            $listed = $info !== null ? $info['pattern'] : $name;
            // An existing row may already cover it by pattern.
            $hit = $index[strtolower($listed)] ?? null;
            if ($hit === null) {
                foreach ($list as $i => $c) {
                    if (str_contains($c['name'], '*') && CookieCatalog::matches($c['name'], $name)) {
                        $hit = $i;
                        break;
                    }
                }
            }
            if ($hit !== null) {
                $list[$hit]['seen'] = $today;
                if ($list[$hit]['category'] === 'unclassified' && $info !== null) {
                    $list[$hit] = array_merge($list[$hit], self::row($info, $listed, $today));
                }
                continue;
            }
            $list[] = $info !== null ? self::row($info, $listed, $today) : [
                'name' => $name, 'category' => 'unclassified', 'provider' => '', 'purpose' => '', 'duration' => '',
                'domain' => '', 'service' => '', 'source' => 'scan', 'seen' => $today,
            ];
            $index[strtolower($listed)] = count($list) - 1;
            $added++;
        }
        usort($list, static fn(array $a, array $b): int => [array_search($a['category'], [...ConsentSettings::CATEGORIES, 'unclassified'], true), $a['name']] <=> [array_search($b['category'], [...ConsentSettings::CATEGORIES, 'unclassified'], true), $b['name']]);
        return ['cookies' => ConsentSettings::cleanCookies($list), 'added' => $added];
    }

    /** @return array<string,string>|null */
    private static function serviceCookie(string $service, string $pattern): ?array
    {
        $s = CookieCatalog::services()[$service] ?? null;
        if ($s === null) {
            return null;
        }
        foreach ($s['cookies'] as $c) {
            if ($c['name'] === $pattern) {
                return ['service' => $service, 'pattern' => $c['name'], 'category' => $s['category'], 'provider' => $s['provider'], 'purpose' => $c['purpose'], 'duration' => $c['duration'], 'domain' => $c['domain'] ?? ''];
            }
        }
        return null;
    }

    /** @param array<string,string> $info @return array<string,string> */
    private static function row(array $info, string $name, string $today): array
    {
        return [
            'name' => $name, 'category' => $info['category'], 'provider' => $info['provider'], 'purpose' => $info['purpose'],
            'duration' => $info['duration'], 'domain' => $info['domain'], 'service' => $info['service'], 'source' => 'scan', 'seen' => $today,
        ];
    }
}
