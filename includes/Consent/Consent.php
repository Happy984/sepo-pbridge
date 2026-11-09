<?php
namespace SepoWB\Consent;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * The cookie banner on the front of the site.
 *
 * The visitor's choice is kept in a first-party cookie written by the banner
 * script: {"v":version,"c":[categories],"t":unix time,"id":consent id}. It is
 * read here so themes and plugins can ask Consent::allows('analytics').
 *
 * Ways to reopen the choices, anywhere on the site:
 *   - the floating button (Cookies → Appearance);
 *   - any link to #cookie-settings (works as a menu item);
 *   - any element with the class sepo-cookie-settings;
 *   - the [sepo_cookie_settings] shortcode;
 *   - window.SepoConsent.open() from a script.
 */
final class Consent
{
    public const COOKIE = 'sepo_wb_consent';
    public const SCAN_PARAM = 'sepo_wb_scan';
    public const PREVIEW_PARAM = 'sepo_cookie_preview';
    private const RATE_LIMIT = 40;

    /** WP Consent API category names for each banner category. */
    private const WP_CONSENT_MAP = [
        'necessary'   => ['functional'],
        'preferences' => ['preferences'],
        'analytics'   => ['statistics', 'statistics-anonymous'],
        'marketing'   => ['marketing'],
    ];

    /** @var array{v:int,c:list<string>,t:int,id:string}|null|false */
    private static $current = false;

    public static function hook(): void
    {
        ConsentLog::hook();
        add_action('rest_api_init', [self::class, 'publicRoutes']);
        add_action('init', [self::class, 'shortcodes']);
        add_action('admin_post_sepo_wb_consent_csv', [self::class, 'csv']);
        // On init, so the signed-in user and translations are ready.
        add_action('init', [self::class, 'frontHooks']);
    }

    public static function frontHooks(): void
    {
        if (is_admin() || (!ConsentSettings::get('enabled') && !self::isPreview() && !self::isScan())) {
            return;
        }
        add_action('template_redirect', [self::class, 'startBuffer'], -1000);
        add_action('wp_head', [self::class, 'consentDefaults'], -1000);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('wp_get_consent_type', static fn() => 'optin');
        add_filter('wp_consent_api_registered_' . SEPO_WB_BASENAME, '__return_true');
    }

    // ------------------------------------------------------------ visitor's choice

    /** @return array{v:int,c:list<string>,t:int,id:string}|null The visitor's choice for the current banner version. */
    public static function current(): ?array
    {
        if (self::$current !== false) {
            return self::$current;
        }
        self::$current = null;
        // The cookie holds URL-encoded JSON; only the version number and known category names are read from it.
        $raw = isset($_COOKIE[self::COOKIE]) ? sanitize_text_field(rawurldecode(wp_unslash((string) $_COOKIE[self::COOKIE]))) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($data) && (int) ($data['v'] ?? 0) === (int) ConsentSettings::get('version') && is_array($data['c'] ?? null)) {
            self::$current = [
                'v'  => (int) $data['v'],
                'c'  => array_values(array_intersect(ConsentSettings::CATEGORIES, $data['c'])),
                't'  => (int) ($data['t'] ?? 0),
                'id' => preg_replace('/[^a-f0-9\-]/', '', strtolower((string) ($data['id'] ?? ''))) ?? '',
            ];
        }
        return self::$current;
    }

    /**
     * Has the visitor allowed a category? Necessary is always allowed. When
     * the banner is switched off, everything is allowed.
     */
    public static function allows(string $category): bool
    {
        if ($category === 'necessary' || !ConsentSettings::get('enabled')) {
            return true;
        }
        $c = self::current();
        return $c !== null && in_array($category, $c['c'], true);
    }

    // ------------------------------------------------------------ request modes

    public static function isScan(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification -- the value is the nonce.
        $token = isset($_GET[self::SCAN_PARAM]) ? sanitize_text_field(wp_unslash((string) $_GET[self::SCAN_PARAM])) : '';
        return $token !== '' && is_user_logged_in() && current_user_can('manage_options') && wp_verify_nonce($token, 'sepo_wb_scan');
    }

    public static function isPreview(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification -- read-only preview for administrators.
        return isset($_GET[self::PREVIEW_PARAM]) && is_user_logged_in() && current_user_can('manage_options');
    }

    public static function startBuffer(): void
    {
        $scan = self::isScan();
        if ($scan) {
            nocache_headers();
        }
        if (!Blocker::shouldRun() || (!$scan && !ConsentSettings::get('block'))) {
            return;
        }
        (new Blocker($scan))->start();
    }

    // ------------------------------------------------------------ page output

    /**
     * Google Consent Mode v2: everything except security storage starts
     * denied, then the saved choice is applied at once, before any Google tag
     * loads.
     */
    public static function consentDefaults(): void
    {
        if (!ConsentSettings::get('consent_mode') || self::isScan()) {
            return;
        }
        $cfg = wp_json_encode(['n' => self::COOKIE, 'v' => (int) ConsentSettings::get('version')]);
        $js = <<<'JS'
window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'denied',personalization_storage:'denied',security_storage:'granted',wait_for_update:500});
gtag('set','ads_data_redaction',true);gtag('set','url_passthrough',false);
(function(o){try{var m=document.cookie.match(new RegExp('(?:^|; )'+o.n+'=([^;]*)'));if(!m)return;var d=JSON.parse(decodeURIComponent(m[1]));if(+d.v!==o.v)return;var h=function(c){return d.c.indexOf(c)>-1?'granted':'denied';};
gtag('consent','update',{ad_storage:h('marketing'),ad_user_data:h('marketing'),ad_personalization:h('marketing'),analytics_storage:h('analytics'),functionality_storage:h('preferences'),personalization_storage:h('preferences')});}catch(e){}})(__CFG__);
JS;
        wp_print_inline_script_tag(str_replace('__CFG__', (string) $cfg, $js), ['id' => 'sepo-wb-consent-default']);
    }

    public static function enqueue(): void
    {
        if (self::isScan()) {
            return;
        }
        $s = ConsentSettings::all();
        wp_enqueue_style('sepo-wb-consent', SEPO_WB_URL . 'assets/css/consent.css', [], self::assetVersion('assets/css/consent.css'));
        if ($s['accent'] !== '') {
            $dark = self::lighten($s['accent']);
            wp_add_inline_style('sepo-wb-consent', ':root{--sepo-cc-accent-custom:' . $s['accent'] . ';--sepo-cc-on-accent-custom:' . self::textOn($s['accent'])
                . ';--sepo-cc-accent-dark:' . $dark . ';--sepo-cc-on-accent-dark:' . self::textOn($dark) . ';}');
        }
        wp_enqueue_script('sepo-wb-consent', SEPO_WB_URL . 'assets/js/consent.js', [], self::assetVersion('assets/js/consent.js'), true);
        wp_add_inline_script('sepo-wb-consent', 'window.SepoConsentConfig=' . wp_json_encode(self::config()) . ';', 'before');
    }

    /** Everything the banner script needs. @return array<string,mixed> */
    public static function config(): array
    {
        $s = ConsentSettings::all();
        $byCat = ConsentSettings::cookiesByCategory();
        $cats = [];
        foreach (ConsentSettings::CATEGORIES as $k) {
            $cats[] = [
                'id'          => $k,
                'label'       => $s['categories'][$k]['label'],
                'description' => $s['categories'][$k]['description'],
                'locked'      => $k === 'necessary',
                'cookies'     => array_map(static fn(array $c): array => [
                    'name' => $c['name'], 'provider' => $c['provider'], 'purpose' => $c['purpose'], 'duration' => $c['duration'], 'domain' => $c['domain'],
                ], $byCat[$k]),
            ];
        }
        // Patterns used to clear cookies from categories the visitor turns off.
        $clear = array_fill_keys(['preferences', 'analytics', 'marketing'], []);
        foreach (CookieCatalog::services() as $svc) {
            if (isset($clear[$svc['category']])) {
                foreach ($svc['cookies'] as $c) {
                    if (empty($c['domain'])) {
                        $clear[$svc['category']][] = $c['name'];
                    }
                }
            }
        }
        foreach ($s['cookies'] as $c) {
            if (isset($clear[$c['category']]) && $c['domain'] === '') {
                $clear[$c['category']][] = $c['name'];
            }
        }
        $admin = is_user_logged_in() && current_user_can('manage_options');
        $known = [];
        if ($admin && $s['detect']) {
            foreach (CookieCatalog::services() as $svc) {
                foreach ($svc['cookies'] as $c) {
                    $known[] = $c['name'];
                }
            }
            foreach ($s['cookies'] as $c) {
                $known[] = $c['name'];
            }
        }
        return [
            'cookie'      => self::COOKIE,
            'version'     => (int) $s['version'],
            'lifetime'    => (int) $s['lifetime'] * 30,
            'path'        => COOKIEPATH ?: '/',
            'domain'      => (string) (COOKIE_DOMAIN ?: ''),
            'secure'      => is_ssl(),
            'layout'      => $s['layout'],
            'position'    => $s['position'],
            'revisit'     => $s['revisit'],
            'policyUrl'   => ConsentSettings::policyUrl(),
            'consentMode' => (bool) $s['consent_mode'],
            'log'         => $s['log'] ? esc_url_raw(rest_url('sepo-wb/v1/consent')) : '',
            'preview'     => self::isPreview(),
            'texts'       => $s['texts'],
            'i18n'        => self::i18n(),
            'categories'  => array_values($cats),
            'clear'       => array_map(static fn(array $l): array => array_values(array_unique($l)), $clear),
            'wpConsent'   => self::WP_CONSENT_MAP,
            'report'      => $admin && $s['detect'] ? ['url' => esc_url_raw(rest_url('sepo-wb/v1/cookies/report')), 'nonce' => wp_create_nonce('wp_rest'), 'known' => array_values(array_unique($known))] : null,
        ];
    }

    /** White or near-black, whichever reads better on the colour. */
    private static function textOn(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $lin = static function (int $c): float {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $lin((int) hexdec(substr($hex, 0, 2))) + 0.7152 * $lin((int) hexdec(substr($hex, 2, 2))) + 0.0722 * $lin((int) hexdec(substr($hex, 4, 2)));
        return (1.05 / ($l + 0.05)) >= (($l + 0.05) / 0.0575) ? '#ffffff' : '#0b1215';
    }

    /** The colour raised to at least 64% lightness, so it stands out on a dark surface. */
    private static function lighten(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        [$r, $g, $b] = array_map(static fn(string $p): float => hexdec($p) / 255, str_split($hex, 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $h = $sat = 0.0;
        if ($max !== $min) {
            $d = $max - $min;
            $sat = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            $h = $max === $r ? fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6) : ($max === $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4);
            $h /= 6;
        }
        $l = max($l, 0.64);
        $q = $l < 0.5 ? $l * (1 + $sat) : $l + $sat - $l * $sat;
        $p = 2 * $l - $q;
        $f = static function (float $t) use ($p, $q): float {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);
            return $t < 1 / 6 ? $p + ($q - $p) * 6 * $t : ($t < 1 / 2 ? $q : ($t < 2 / 3 ? $p + ($q - $p) * (2 / 3 - $t) * 6 : $p));
        };
        return sprintf('#%02x%02x%02x', (int) round($f($h + 1 / 3) * 255), (int) round($f($h) * 255), (int) round($f($h - 1 / 3) * 255));
    }

    /** @return array<string,string> */
    private static function i18n(): array
    {
        return [
            'close'      => __('Close', 'sepo-bridge'),
            'alwaysOn'   => __('Always on', 'sepo-bridge'),
            /* translators: %d: number of cookies */
            'count'      => __('%d cookies', 'sepo-bridge'),
            'countOne'   => __('1 cookie', 'sepo-bridge'),
            'showList'   => __('Show cookies', 'sepo-bridge'),
            'hideList'   => __('Hide cookies', 'sepo-bridge'),
            'none'       => __('No cookies of this kind are used at the moment.', 'sepo-bridge'),
            'provider'   => __('Provider', 'sepo-bridge'),
            'purpose'    => __('Purpose', 'sepo-bridge'),
            'duration'   => __('Duration', 'sepo-bridge'),
            'necessaryOnly' => __('Necessary only', 'sepo-bridge'),
            'statusNone' => __('You have not made a choice yet.', 'sepo-bridge'),
            /* translators: 1: list of categories, 2: date */
            'statusSet'  => __('You allowed: %1$s. Chosen on %2$s.', 'sepo-bridge'),
            /* translators: %s: consent id */
            'consentId'  => __('Consent ID: %s', 'sepo-bridge'),
            'allowed'    => __('Allowed', 'sepo-bridge'),
            'notAllowed' => __('Not allowed', 'sepo-bridge'),
            'saved'      => __('Your cookie choices were saved.', 'sepo-bridge'),
            'preview'    => __('Preview: choices made here are not recorded.', 'sepo-bridge'),
        ];
    }

    private static function assetVersion(string $rel): string
    {
        $file = SEPO_WB_DIR . $rel;
        return SEPO_WB_VERSION . (is_file($file) ? '.' . filemtime($file) : '');
    }

    // ------------------------------------------------------------ shortcodes

    public static function shortcodes(): void
    {
        add_shortcode('sepo_cookie_settings', [self::class, 'settingsShortcode']);
        add_shortcode('sepo_cookie_list', [self::class, 'listShortcode']);
    }

    /** [sepo_cookie_settings label="Cookie settings" style="link|button"] */
    public static function settingsShortcode($atts): string
    {
        $a = shortcode_atts(['label' => ConsentSettings::all()['texts']['revisit'], 'style' => 'link'], (array) $atts, 'sepo_cookie_settings');
        $cls = 'sepo-cookie-settings sepo-cc-trigger' . ($a['style'] === 'button' ? ' sepo-cc-trigger--button' : '');
        return '<button type="button" class="' . esc_attr($cls) . '">' . esc_html((string) $a['label']) . '</button>';
    }

    /** [sepo_cookie_list] — every listed cookie, by category, for a cookie policy page. */
    public static function listShortcode($atts): string
    {
        $s = ConsentSettings::all();
        $byCat = ConsentSettings::cookiesByCategory();
        $h = '<div class="sepo-cc-list">';
        $h .= '<p class="sepo-cc-list__status" data-sepo-consent-status>' . esc_html__('Your current choice is shown here once the page has loaded.', 'sepo-bridge') . '</p>';
        foreach (ConsentSettings::CATEGORIES as $k) {
            $h .= '<section class="sepo-cc-list__cat" data-sepo-list-cat="' . esc_attr($k) . '">';
            $h .= '<h3>' . esc_html($s['categories'][$k]['label']) . ' <span class="sepo-cc-list__state" data-sepo-cat-state="' . esc_attr($k) . '"></span></h3>';
            $h .= '<p>' . esc_html($s['categories'][$k]['description']) . '</p>';
            if (!$byCat[$k]) {
                $h .= '<p class="sepo-cc-list__none">' . esc_html__('No cookies of this kind are used.', 'sepo-bridge') . '</p></section>';
                continue;
            }
            $h .= '<div class="sepo-cc-list__scroll"><table><thead><tr><th>' . esc_html__('Cookie', 'sepo-bridge') . '</th><th>' . esc_html__('Provider', 'sepo-bridge') . '</th><th>' . esc_html__('Purpose', 'sepo-bridge') . '</th><th>' . esc_html__('Duration', 'sepo-bridge') . '</th></tr></thead><tbody>';
            foreach ($byCat[$k] as $c) {
                $h .= '<tr><td><code>' . esc_html($c['name']) . '</code></td><td>' . esc_html($c['provider'] . ($c['domain'] !== '' ? ' (' . $c['domain'] . ')' : '')) . '</td><td>' . esc_html($c['purpose']) . '</td><td>' . esc_html($c['duration']) . '</td></tr>';
            }
            $h .= '</tbody></table></div></section>';
        }
        $h .= '<p>' . self::settingsShortcode(['style' => 'button']) . '</p></div>';
        return $h;
    }

    // ------------------------------------------------------------ public endpoint

    public static function publicRoutes(): void
    {
        register_rest_route('sepo-wb/v1', '/consent', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'record'],
            // Visitors are not signed in, and cached pages carry stale nonces.
            'permission_callback' => '__return_true',
        ]);
    }

    /** @return WP_REST_Response|WP_Error */
    public static function record(WP_REST_Request $req)
    {
        if (!ConsentSettings::get('enabled') || !ConsentSettings::get('log')) {
            return new WP_REST_Response(['status' => 'success'], 200);
        }
        $key = 'sepo_wb_cc_' . md5(ConsentLog::anonymiseIp(sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')))) . wp_salt('nonce'));
        $n = (int) get_transient($key);
        if ($n >= self::RATE_LIMIT) {
            return new WP_Error('sepo_wb_rate', __('Too many requests.', 'sepo-bridge'), ['status' => 429]);
        }
        set_transient($key, $n + 1, HOUR_IN_SECONDS);

        $id = (string) $req->get_param('id');
        $action = (string) $req->get_param('action');
        $cats = $req->get_param('c');
        if (!preg_match('/^[a-f0-9\-]{16,40}$/', $id) || !in_array($action, ConsentLog::ACTIONS, true) || !is_array($cats)) {
            return new WP_Error('sepo_wb_invalid', __('Invalid consent record.', 'sepo-bridge'), ['status' => 400]);
        }
        $cats = array_values(array_intersect(ConsentSettings::CATEGORIES, array_map('strval', $cats)));
        if (!in_array('necessary', $cats, true)) {
            array_unshift($cats, 'necessary');
        }
        $url = (string) $req->get_param('url');
        $home = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        if ((string) wp_parse_url($url, PHP_URL_HOST) !== $home) {
            $url = '';
        }
        ConsentLog::add($id, $cats, $action, (int) ConsentSettings::get('version'), $url);
        /**
         * Fires when a visitor's choice is recorded.
         *
         * @param list<string> $cats   Allowed categories.
         * @param string       $action accept_all, reject_all, custom or revoke.
         * @param string       $id     Consent id kept in the visitor's cookie.
         */
        do_action('sepo_wb_consent_saved', $cats, $action, $id);
        return new WP_REST_Response(['status' => 'success'], 200);
    }

    public static function csv(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('sepo_wb_consent_csv')) {
            wp_die(esc_html__('Access denied.', 'sepo-bridge'));
        }
        ConsentLog::exportCsv();
    }
}
