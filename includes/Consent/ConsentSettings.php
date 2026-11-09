<?php
namespace SepoWB\Consent;

defined('ABSPATH') || exit;

/**
 * Cookie banner settings, the site's cookie list and the blocking rules, in
 * one option.
 */
final class ConsentSettings
{
    public const OPTION = 'sepo_wb_consent';
    public const CATEGORIES = ['necessary', 'preferences', 'analytics', 'marketing'];
    private const MAX_COOKIES = 300;
    private const MAX_RULES = 60;

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** @return array<string,string> */
    public static function defaultTexts(): array
    {
        return [
            'title'       => __('We value your privacy', 'sepo-bridge'),
            'message'     => __('We use necessary cookies to make this site work. With your permission we would also like to use cookies that remember your preferences, help us understand how the site is used and show you relevant content. You can change your mind at any time.', 'sepo-bridge'),
            'accept'      => __('Accept all', 'sepo-bridge'),
            'reject'      => __('Reject all', 'sepo-bridge'),
            'manage'      => __('Choose cookies', 'sepo-bridge'),
            'save'        => __('Save my choices', 'sepo-bridge'),
            'prefsTitle'  => __('Cookie preferences', 'sepo-bridge'),
            'prefsIntro'  => __('Choose which cookies this site may use. Necessary cookies are always on because the site cannot work without them. Rejecting the others does not stop you using the site.', 'sepo-bridge'),
            'policy'      => __('Cookie policy', 'sepo-bridge'),
            'revisit'     => __('Cookie settings', 'sepo-bridge'),
            /* translators: %1$s: value, %2$s: value. */
            'blocked'     => __('This content is from %1$s, which uses %2$s cookies.', 'sepo-bridge'),
            'allowBlocked'=> __('Allow and show it', 'sepo-bridge'),
        ];
    }

    /** @return array<string,array{label:string,description:string}> */
    public static function defaultCategories(): array
    {
        return [
            'necessary'   => ['label' => __('Necessary', 'sepo-bridge'), 'description' => __('Needed for the site to work: keeping you signed in, your basket and checkout, security, and remembering your cookie choice. They cannot be switched off.', 'sepo-bridge')],
            'preferences' => ['label' => __('Preferences', 'sepo-bridge'), 'description' => __('Remember choices such as your language or the details you entered in a comment form, and run features such as live chat.', 'sepo-bridge')],
            'analytics'   => ['label' => __('Analytics', 'sepo-bridge'), 'description' => __('Help us understand how visitors use the site, such as which pages are popular, so we can improve it.', 'sepo-bridge')],
            'marketing'   => ['label' => __('Marketing', 'sepo-bridge'), 'description' => __('Used by advertising and social media services to show you relevant adverts and measure campaigns. Embedded videos and maps also set these.', 'sepo-bridge')],
        ];
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'enabled'      => 0,
            'version'      => 1,
            'layout'       => 'card',
            'position'     => 'left',
            'accent'       => '',
            'revisit'      => 'floating',
            'policy_page'  => 0,
            'policy_url'   => '',
            'lifetime'     => 6,
            'block'        => 1,
            'consent_mode' => 1,
            'log'          => 1,
            'log_days'     => 365,
            'detect'       => 1,
            'texts'        => self::defaultTexts(),
            'categories'   => self::defaultCategories(),
            'cookies'      => [],
            'services'     => [],
            'rules'        => [],
            'last_scan'    => '',
            'scan_pages'   => 0,
        ];
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $d = self::defaults();
        $out = array_merge($d, $saved);
        $out['texts'] = array_merge($d['texts'], is_array($saved['texts'] ?? null) ? array_filter($saved['texts'], 'strlen') : []);
        $cats = $d['categories'];
        foreach (is_array($saved['categories'] ?? null) ? $saved['categories'] : [] as $k => $c) {
            if (isset($cats[$k]) && is_array($c)) {
                $cats[$k] = ['label' => ($c['label'] ?? '') !== '' ? (string) $c['label'] : $cats[$k]['label'], 'description' => ($c['description'] ?? '') !== '' ? (string) $c['description'] : $cats[$k]['description']];
            }
        }
        $out['categories'] = $cats;
        return self::$cache = $out;
    }

    /** @return mixed */
    public static function get(string $key)
    {
        return self::all()[$key] ?? null;
    }

    /** @param array<string,mixed> $values */
    public static function update(array $values): void
    {
        $current = get_option(self::OPTION, []);
        $current = is_array($current) ? $current : [];
        update_option(self::OPTION, array_merge($current, $values), false);
        self::$cache = null;
        /** Fires after the cookie banner settings change. */
        do_action('sepo_wb_consent_settings_saved');
    }

    /**
     * Clean settings posted from the admin panel. Only the keys present are
     * returned, so a partial save leaves the rest alone.
     *
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public static function sanitize(array $in): array
    {
        $out = [];
        foreach (['enabled', 'block', 'consent_mode', 'log', 'detect'] as $k) {
            if (array_key_exists($k, $in)) {
                $out[$k] = empty($in[$k]) ? 0 : 1;
            }
        }
        if (isset($in['layout'])) {
            $out['layout'] = in_array($in['layout'], ['card', 'bar', 'modal'], true) ? $in['layout'] : 'card';
        }
        if (isset($in['position'])) {
            $out['position'] = $in['position'] === 'right' ? 'right' : 'left';
        }
        if (isset($in['revisit'])) {
            $out['revisit'] = in_array($in['revisit'], ['floating', 'none'], true) ? $in['revisit'] : 'floating';
        }
        if (array_key_exists('accent', $in)) {
            $out['accent'] = (string) (sanitize_hex_color((string) $in['accent']) ?? '');
        }
        if (isset($in['policy_page'])) {
            $id = absint($in['policy_page']);
            $out['policy_page'] = $id > 0 && get_post_status($id) === 'publish' ? $id : 0;
        }
        if (isset($in['policy_url'])) {
            $out['policy_url'] = esc_url_raw(trim((string) $in['policy_url']));
        }
        if (isset($in['lifetime'])) {
            $out['lifetime'] = max(1, min(13, (int) $in['lifetime']));
        }
        if (isset($in['log_days'])) {
            $out['log_days'] = max(30, min(3650, (int) $in['log_days']));
        }
        if (isset($in['texts']) && is_array($in['texts'])) {
            $t = [];
            foreach (array_keys(self::defaultTexts()) as $k) {
                $v = (string) ($in['texts'][$k] ?? '');
                $t[$k] = in_array($k, ['message', 'prefsIntro'], true) ? sanitize_textarea_field($v) : sanitize_text_field($v);
            }
            $out['texts'] = $t;
        }
        if (isset($in['categories']) && is_array($in['categories'])) {
            $c = [];
            foreach (self::CATEGORIES as $k) {
                $row = is_array($in['categories'][$k] ?? null) ? $in['categories'][$k] : [];
                $c[$k] = ['label' => sanitize_text_field((string) ($row['label'] ?? '')), 'description' => sanitize_textarea_field((string) ($row['description'] ?? ''))];
            }
            $out['categories'] = $c;
        }
        if (isset($in['cookies']) && is_array($in['cookies'])) {
            $out['cookies'] = self::cleanCookies($in['cookies']);
        }
        if (isset($in['rules']) && is_array($in['rules'])) {
            $rules = [];
            foreach (array_slice(array_values($in['rules']), 0, self::MAX_RULES) as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $pattern = trim(sanitize_text_field((string) ($r['pattern'] ?? '')));
                $cat = (string) ($r['category'] ?? '');
                if (strlen($pattern) < 4 || !in_array($cat, ['preferences', 'analytics', 'marketing'], true)) {
                    continue;
                }
                $rules[] = ['pattern' => $pattern, 'category' => $cat, 'name' => sanitize_text_field((string) ($r['name'] ?? ''))];
            }
            $out['rules'] = $rules;
        }
        return $out;
    }

    /**
     * @param array<int,mixed> $rows
     * @return list<array<string,string>>
     */
    public static function cleanCookies(array $rows): array
    {
        $out = [];
        $seen = [];
        foreach (array_slice(array_values($rows), 0, self::MAX_COOKIES) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $name = trim(preg_replace('/[^A-Za-z0-9_\-\.\*\$\[\]@:]/', '', (string) ($r['name'] ?? '')) ?? '');
            if ($name === '' || isset($seen[strtolower($name)])) {
                continue;
            }
            $seen[strtolower($name)] = true;
            $cat = (string) ($r['category'] ?? '');
            $out[] = [
                'name'     => substr($name, 0, 120),
                'category' => in_array($cat, self::CATEGORIES, true) ? $cat : 'unclassified',
                'provider' => substr(sanitize_text_field((string) ($r['provider'] ?? '')), 0, 80),
                'purpose'  => substr(sanitize_text_field((string) ($r['purpose'] ?? '')), 0, 300),
                'duration' => substr(sanitize_text_field((string) ($r['duration'] ?? '')), 0, 40),
                'domain'   => substr(preg_replace('/[^a-z0-9\.\-]/', '', strtolower((string) ($r['domain'] ?? ''))) ?? '', 0, 80),
                'service'  => sanitize_key((string) ($r['service'] ?? '')),
                'source'   => in_array($r['source'] ?? '', ['scan', 'manual'], true) ? (string) $r['source'] : 'manual',
                'seen'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($r['seen'] ?? '')) ? (string) $r['seen'] : '',
            ];
        }
        return $out;
    }

    /** The cookie policy address: the chosen page, else the typed address, else the privacy policy page. */
    public static function policyUrl(): string
    {
        $s = self::all();
        if ($s['policy_page'] > 0) {
            return (string) get_permalink((int) $s['policy_page']);
        }
        if ($s['policy_url'] !== '') {
            return (string) $s['policy_url'];
        }
        return (string) get_privacy_policy_url();
    }

    /** Cookies grouped by category, for the banner and the cookie list. @return array<string,list<array<string,string>>> */
    public static function cookiesByCategory(): array
    {
        $out = array_fill_keys(self::CATEGORIES, []);
        foreach ((array) self::get('cookies') as $c) {
            if (isset($out[$c['category']])) {
                $out[$c['category']][] = $c;
            }
        }
        return $out;
    }

    /**
     * Blocking rules: the known services' script and embed addresses, plus the
     * site's own rules. Necessary services are never held back.
     *
     * @return list<array{pattern:string,category:string,service:string,name:string,kind:string}>
     */
    public static function blockRules(): array
    {
        $rules = [];
        foreach (CookieCatalog::services() as $id => $s) {
            if ($s['category'] === 'necessary') {
                continue;
            }
            foreach ($s['scripts'] as $p) {
                $rules[] = ['pattern' => $p, 'category' => $s['category'], 'service' => (string) $id, 'name' => $s['name'], 'kind' => 'script'];
            }
            foreach ($s['embeds'] as $p) {
                $rules[] = ['pattern' => $p, 'category' => $s['category'], 'service' => (string) $id, 'name' => $s['name'], 'kind' => 'embed'];
            }
        }
        foreach ((array) self::get('rules') as $i => $r) {
            $rules[] = ['pattern' => $r['pattern'], 'category' => $r['category'], 'service' => 'custom' . $i, 'name' => $r['name'] !== '' ? $r['name'] : $r['pattern'], 'kind' => 'any'];
        }
        return $rules;
    }
}
