<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/**
 * Sign-in settings, kept in one option.
 *
 * Provider credentials are pasted on the Sign-in tab; client secrets are
 * stored encrypted. Hosts that manage secrets in wp-config.php or the server
 * environment can set them there instead, and those then take precedence:
 *
 *   wp-config.php   SEPO_WB_GOOGLE_CLIENT_ID, SEPO_WB_GOOGLE_CLIENT_SECRET
 *   environment     GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET
 *
 * (and the same for FACEBOOK, GITHUB, MICROSOFT and LINKEDIN).
 */
final class Config
{
    public const OPTION = 'sepo_wb_signin';

    /** Roles a sign-in may create an account with. Administrator is never offered. */
    public const SIGNUP_ROLES = ['subscriber', 'contributor', 'author', 'editor'];

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        $d = [
            'enabled'           => 0,
            'show_on_login'     => 1,
            'allow_signup'      => 0,
            'signup_role'       => 'subscriber',
            'link_existing'     => 1,
            'twofactor_enabled' => 1,
            'twofactor_issuer'  => '',
            'twofactor_roles'   => [],
            'remember_days'     => 0,
            'security_page'     => '',
        ];
        foreach (Providers::keys() as $p) {
            $d[$p . '_enabled'] = 0;
            $d[$p . '_client_id'] = '';
            $d[$p . '_client_secret'] = '';
        }
        return $d;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    /** @return mixed */
    public static function get(string $key)
    {
        return self::all()[$key] ?? null;
    }

    /** Social sign-in buttons and flows are switched on. */
    public static function socialOn(): bool
    {
        return !empty(self::get('enabled'));
    }

    /** Two-factor is offered. It works whether or not social sign-in is on. */
    public static function twoFactorOn(): bool
    {
        return !empty(self::get('twofactor_enabled'));
    }

    public static function issuer(): string
    {
        $i = trim((string) self::get('twofactor_issuer'));
        return $i !== '' ? $i : wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
    }

    public static function rememberDays(): int
    {
        return max(0, min(90, (int) self::get('remember_days')));
    }

    /** @return list<string> */
    public static function requiredRoles(): array
    {
        $r = self::get('twofactor_roles');
        return is_array($r) ? array_values(array_filter(array_map('sanitize_key', $r))) : [];
    }

    public static function signupRole(): string
    {
        $r = (string) self::get('signup_role');
        return in_array($r, self::SIGNUP_ROLES, true) ? $r : 'subscriber';
    }

    // ---------------------------------------------------------------- credentials

    private static function env(string $name): string
    {
        // Server environment, set by the host; never request data.
        $v = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- secrets are used as given, and sanitising would alter them.
        return is_string($v) ? trim($v) : '';
    }

    /**
     * A provider's client id and secret, and where each came from.
     *
     * @return array{id:string,secret:string,id_from:string,secret_from:string}
     */
    public static function credentials(string $provider): array
    {
        $out = ['id' => '', 'secret' => '', 'id_from' => '', 'secret_from' => ''];
        $upper = strtoupper($provider);
        foreach (['id' => 'CLIENT_ID', 'secret' => 'CLIENT_SECRET'] as $k => $suffix) {
            $const = 'SEPO_WB_' . $upper . '_' . $suffix;
            if (defined($const) && is_string(constant($const)) && trim((string) constant($const)) !== '') {
                $out[$k] = trim((string) constant($const));
                $out[$k . '_from'] = 'constant';
                continue;
            }
            $env = self::env($upper . '_' . $suffix);
            if ($env !== '') {
                $out[$k] = $env;
                $out[$k . '_from'] = 'environment';
                continue;
            }
            $stored = trim((string) self::get($provider . '_client_' . $k));
            // Secrets pasted on the Sign-in tab are kept encrypted.
            if ($k === 'secret' && str_starts_with($stored, 'v1.')) {
                $stored = Crypto::decrypt($stored);
            }
            if ($stored !== '') {
                $out[$k] = $stored;
                $out[$k . '_from'] = 'settings';
            }
        }
        return $out;
    }

    /** Switched on and carrying both credentials. */
    public static function providerReady(string $provider): bool
    {
        if (!Providers::exists($provider) || empty(self::get($provider . '_enabled'))) {
            return false;
        }
        $c = self::credentials($provider);
        return $c['id'] !== '' && $c['secret'] !== '';
    }

    /** @return array<string,array<string,mixed>> the providers a button can be shown for */
    public static function readyProviders(): array
    {
        if (!self::socialOn()) {
            return [];
        }
        $out = [];
        foreach (Providers::all() as $key => $spec) {
            if (self::providerReady($key)) {
                $out[$key] = $spec;
            }
        }
        return $out;
    }

    /**
     * The address to register with a provider. Compared character for
     * character by every provider, so it is printed on the settings screen
     * rather than described.
     */
    public static function redirectUri(string $provider): string
    {
        return rest_url('sepo-wb/v1/auth/callback/' . rawurlencode($provider));
    }

    // ---------------------------------------------------------------- saving

    /**
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public static function save(array $in): array
    {
        $cur = self::all();
        $out = $cur;
        foreach (['enabled', 'show_on_login', 'allow_signup', 'link_existing', 'twofactor_enabled'] as $b) {
            if (array_key_exists($b, $in)) {
                $out[$b] = !empty($in[$b]) ? 1 : 0;
            }
        }
        if (array_key_exists('signup_role', $in)) {
            $r = sanitize_key((string) $in['signup_role']);
            $out['signup_role'] = in_array($r, self::SIGNUP_ROLES, true) ? $r : 'subscriber';
        }
        if (array_key_exists('twofactor_issuer', $in)) {
            $out['twofactor_issuer'] = mb_substr(sanitize_text_field((string) $in['twofactor_issuer']), 0, 60);
        }
        if (array_key_exists('twofactor_roles', $in)) {
            $valid = array_keys(wp_roles()->roles);
            $out['twofactor_roles'] = array_values(array_intersect($valid, array_map('sanitize_key', (array) $in['twofactor_roles'])));
        }
        if (array_key_exists('remember_days', $in)) {
            $out['remember_days'] = max(0, min(90, (int) $in['remember_days']));
        }
        if (array_key_exists('security_page', $in)) {
            $out['security_page'] = esc_url_raw(trim((string) $in['security_page']));
        }
        foreach (Providers::keys() as $p) {
            if (array_key_exists($p . '_enabled', $in)) {
                $out[$p . '_enabled'] = !empty($in[$p . '_enabled']) ? 1 : 0;
            }
            if (array_key_exists($p . '_client_id', $in)) {
                $out[$p . '_client_id'] = mb_substr(sanitize_text_field((string) $in[$p . '_client_id']), 0, 255);
            }
            // A secret is never sent back to the page, so an empty field means
            // "keep the one saved". Clearing it is a separate, explicit request.
            if (!empty($in[$p . '_clear_secret'])) {
                $out[$p . '_client_secret'] = '';
            } elseif (isset($in[$p . '_client_secret']) && trim((string) $in[$p . '_client_secret']) !== '') {
                $plain = mb_substr(trim(sanitize_text_field((string) $in[$p . '_client_secret'])), 0, 512);
                // Encrypted with keys derived from the site's salts, so a copy of the
                // database alone does not give the secret away.
                $enc = Crypto::encrypt($plain);
                $out[$p . '_client_secret'] = $enc !== '' ? $enc : $plain;
            }
        }
        update_option(self::OPTION, $out, false);
        return self::all();
    }

    /**
     * The settings as the admin screen sees them: never a secret, only
     * whether one is set and where it came from.
     *
     * @return array<string,mixed>
     */
    public static function forScreen(): array
    {
        $s = self::all();
        $providers = [];
        foreach (Providers::all() as $key => $spec) {
            $c = self::credentials($key);
            unset($s[$key . '_client_secret']);
            $providers[] = [
                'key'          => $key,
                'label'        => $spec['label'],
                'console'      => $spec['console'],
                'enabled'      => (int) $s[$key . '_enabled'],
                'client_id'    => (string) $s[$key . '_client_id'],
                'id_from'      => $c['id_from'],
                'has_secret'   => $c['secret'] !== '',
                'secret_from'  => $c['secret_from'],
                'ready'        => self::providerReady($key),
                'redirect_uri' => self::redirectUri($key),
                'scope'        => $spec['scope'],
            ];
        }
        $roles = [];
        foreach (wp_roles()->roles as $key => $r) {
            $roles[] = ['value' => $key, 'label' => translate_user_role($r['name'])];
        }
        return [
            'settings'   => $s,
            'providers'  => $providers,
            'roles'      => $roles,
            'signupRoles' => array_map(static fn($r) => ['value' => $r, 'label' => translate_user_role(ucfirst($r))], self::SIGNUP_ROLES),
            'https'      => str_starts_with(rest_url(), 'https://'),
            'pretty'     => (string) get_option('permalink_structure') !== '',
            'encryption' => Crypto::available(),
            'loginUrl'   => wp_login_url(),
            'siteName'   => wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES),
            'enrolled'   => Store::countEnrolled(),
            'registration' => (bool) get_option('users_can_register'),
        ];
    }
}
