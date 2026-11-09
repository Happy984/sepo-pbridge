<?php
namespace SepoWB\SignIn;

use WP_User;

defined('ABSPATH') || exit;

/**
 * Signing in, or connecting an account, through a provider.
 *
 *   start      wp-login.php?action=sepo_social&provider=google
 *   callback   /wp-json/sepo-wb/v1/auth/callback/google  (the redirect URI)
 *
 * THE STATE VALUE IS THE CALLBACK'S CSRF PROTECTION.
 *   It is random, single-use, expires in ten minutes, names the provider, and
 *   is bound to the browser that started the sign-in by a hash of an HttpOnly
 *   cookie. Without that binding, someone could start a sign-in with their own
 *   provider account and get a victim's browser to finish it, leaving the
 *   victim signed in as the attacker.
 *
 * PKCE, where the provider supports it, makes an intercepted authorisation
 * code useless without the verifier that never leaves this server.
 */
final class OAuth
{
    private const BIND_COOKIE = 'sepo_wb_oauth';
    private const TTL = 600;

    public static function hook(): void
    {
        add_action('login_form_sepo_social', [self::class, 'start']);
    }

    /** The address a button points at. */
    public static function startUrl(string $provider, string $redirect = '', bool $connect = false, string $return = ''): string
    {
        $args = ['action' => 'sepo_social', 'provider' => $provider];
        if ($redirect !== '') {
            $args['redirect_to'] = $redirect;
        }
        if ($return !== '') {
            $args['sepo_wb_return'] = $return;
        }
        if ($connect) {
            $args['connect'] = 1;
            $args['_wpnonce'] = wp_create_nonce('sepo_wb_connect');
        }
        return add_query_arg(array_map('rawurlencode', $args), wp_login_url());
    }

    // ---------------------------------------------------------------- start

    public static function start(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification -- signing in starts signed out; connect mode checks its nonce below
        $provider = isset($_GET['provider']) ? sanitize_key(wp_unslash((string) $_GET['provider'])) : '';
        $redirect = isset($_GET['redirect_to']) ? wp_validate_redirect(esc_url_raw(wp_unslash((string) $_GET['redirect_to'])), '') : '';
        $return = isset($_GET['sepo_wb_return']) ? wp_validate_redirect(esc_url_raw(wp_unslash((string) $_GET['sepo_wb_return'])), '') : '';
        $connect = !empty($_GET['connect']);
        // phpcs:enable WordPress.Security.NonceVerification

        $spec = Providers::get($provider);
        if ($spec === null || !Config::providerReady($provider) || !Config::socialOn()) {
            Auth::fail('provider', $return);
        }

        $uid = 0;
        if ($connect) {
            if (!is_user_logged_in() || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])), 'sepo_wb_connect')) {
                Auth::fail('state', $return);
            }
            $uid = get_current_user_id();
        }

        $bind = Cookies::get(self::BIND_COOKIE);
        if (strlen($bind) < 32) {
            $bind = bin2hex(random_bytes(24));
        }
        Cookies::set(self::BIND_COOKIE, $bind, self::TTL);

        $state = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        set_transient('sepo_wb_oauth_' . $state, [
            'provider' => $provider,
            'redirect' => $redirect,
            'return'   => $return,
            'verifier' => $verifier,
            'bind'     => hash('sha256', $bind),
            'uid'      => $uid,
            'issued'   => time(),
        ], self::TTL);

        $creds = Config::credentials($provider);
        $params = [
            'client_id'     => $creds['id'],
            'redirect_uri'  => Config::redirectUri($provider),
            'response_type' => 'code',
            'scope'         => (string) $spec['scope'],
            'state'         => $state,
        ];
        if (!empty($spec['pkce'])) {
            $params['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $params['code_challenge_method'] = 'S256';
        }
        foreach ((array) $spec['extra'] as $k => $v) {
            $params[(string) $k] = (string) $v;
        }
        $url = (string) $spec['authorize'];
        nocache_headers();
        // The provider is another site, so wp_safe_redirect() would refuse it.
        wp_redirect($url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986)); // phpcs:ignore WordPress.Security.SafeRedirect
        exit;
    }

    // ---------------------------------------------------------------- callback

    /** @return array<string,mixed>|null the state, used up whether or not it is valid */
    private static function takeState(string $state, string $provider): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $state)) {
            return null;
        }
        $key = 'sepo_wb_oauth_' . $state;
        $s = get_transient($key);
        delete_transient($key);
        $bind = Cookies::get(self::BIND_COOKIE);
        Cookies::clear(self::BIND_COOKIE);
        if (!is_array($s) || time() - (int) ($s['issued'] ?? 0) > self::TTL || ($s['provider'] ?? '') !== $provider) {
            return null;
        }
        if ($bind === '' || !hash_equals((string) $s['bind'], hash('sha256', $bind))) {
            return null;
        }
        return $s;
    }

    /** /wp-json/sepo-wb/v1/auth/callback/{provider} */
    public static function callback(string $provider): void
    {
        // Read from the raw query string: WordPress removes $_GET['error'] while
        // parsing the request, and "error" is how a provider says the person
        // pressed Cancel. The state value is this request's CSRF check.
        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $q); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- parsed as a query string; each value is sanitised below.
        $state = is_string($q['state'] ?? null) ? sanitize_text_field($q['state']) : '';
        $code = is_string($q['code'] ?? null) ? sanitize_text_field($q['code']) : '';
        $denied = isset($q['error']);

        $s = self::takeState($state, $provider);
        $return = is_array($s) ? (string) $s['return'] : '';
        if ($denied) {
            Auth::fail('cancelled', $return);
        }
        if ($s === null || $code === '') {
            Auth::fail('state');
        }
        if (!Config::providerReady($provider) || !Config::socialOn()) {
            Auth::fail('provider', $return);
        }

        $identity = self::exchange($provider, $code, (string) $s['verifier'], $fail);
        if ($identity === null) {
            Auth::fail($fail, $return);
        }

        if ((int) $s['uid'] > 0) {
            self::connect((int) $s['uid'], $provider, $identity, (string) $s['redirect'], $return);
        }

        $user = self::resolve($provider, $identity, $fail);
        if ($user === null) {
            Auth::fail($fail, $return);
        }
        /** Lets a plugin refuse a provider sign-in, for example a suspended account. */
        if (!apply_filters('sepo_wb_social_login_allowed', true, $user, $provider)) {
            Auth::fail('refused', $return);
        }
        Store::touch($user->ID, $provider);
        Auth::complete($user, true, (string) $s['redirect'], $provider);
    }

    /**
     * The code becomes a token, the token a profile, the profile an identity.
     *
     * @return array{subject:string,email:string,email_verified:bool,name:string}|null
     */
    private static function exchange(string $provider, string $code, string $verifier, ?string &$fail): ?array
    {
        $spec = (array) Providers::get($provider);
        $creds = Config::credentials($provider);
        $post = [
            'client_id'     => $creds['id'],
            'client_secret' => $creds['secret'],
            'code'          => $code,
            'redirect_uri'  => Config::redirectUri($provider),
            'grant_type'    => 'authorization_code',
        ];
        if (!empty($spec['pkce'])) {
            $post['code_verifier'] = $verifier;
        }
        $token = Providers::request((string) $spec['token'], [], $post);
        if ($token === null) {
            $fail = 'network';
            return null;
        }
        if (isset($token['error']) || empty($token['access_token'])) {
            // The provider's own words go to the log only: they often name the
            // client id and the redirect address.
            Log::write('warning', 'token exchange refused', ['provider' => $provider, 'error' => is_scalar($token['error'] ?? null) ? (string) $token['error'] : 'no access token']);
            $fail = 'exchange';
            return null;
        }
        $headers = ['Authorization' => 'Bearer ' . (string) $token['access_token']];
        $profile = Providers::request((string) $spec['userinfo'], $headers);
        if (!is_array($profile) || $profile === []) {
            $fail = 'profile';
            return null;
        }
        $emails = [];
        if (!empty($spec['emails'])) {
            $e = Providers::request((string) $spec['emails'], $headers);
            $emails = is_array($e) && isset($e[0]) ? $e : [];
        }
        $identity = Providers::identity($provider, $profile, $emails);
        if ($identity === null) {
            $fail = 'profile';
        }
        return $identity;
    }

    /**
     * The WordPress account behind an identity: already linked, matched on a
     * verified address, or newly made when that is switched on.
     *
     * @param array{subject:string,email:string,email_verified:bool,name:string} $identity
     */
    private static function resolve(string $provider, array $identity, ?string &$fail): ?WP_User
    {
        $linked = Store::userForSubject($provider, $identity['subject']);
        if ($linked > 0) {
            $u = get_user_by('id', $linked);
            if ($u instanceof WP_User) {
                return $u;
            }
            // The account is gone; the stale link must not attach to anything else.
            delete_user_meta($linked, Store::META_SUBJECT . $provider);
        }

        // Everything past here matches on the address, so it has to be verified.
        if ($identity['email'] === '' || !$identity['email_verified']) {
            Log::write('warning', 'refused an unverified provider address', ['provider' => $provider]);
            $fail = 'unverified';
            return null;
        }

        $u = get_user_by('email', $identity['email']);
        if ($u instanceof WP_User) {
            if (empty(Config::get('link_existing'))) {
                $fail = 'linking_off';
                return null;
            }
            if (!Store::link($u->ID, $provider, $identity['subject'], $identity)) {
                $fail = 'taken';
                return null;
            }
            return $u;
        }

        if (empty(Config::get('allow_signup'))) {
            $fail = 'no_account';
            return null;
        }
        $u = self::createUser($identity);
        if ($u === null) {
            $fail = 'create';
            return null;
        }
        Store::link($u->ID, $provider, $identity['subject'], $identity);
        return $u;
    }

    /**
     * A new account for a verified address. The login name comes from the
     * address, the password is random and never shown (the lost-password
     * screen gives one to anyone who wants it), and the role is the one
     * chosen in settings, which can never be administrator.
     *
     * @param array{email:string,name:string} $identity
     */
    private static function createUser(array $identity): ?WP_User
    {
        $local = (string) strstr($identity['email'], '@', true);
        $login = substr(sanitize_user($local !== '' ? $local : 'user', true), 0, 48);
        if ($login === '') {
            $login = 'user' . wp_rand(1000, 9999);
        }
        $candidate = $login;
        for ($i = 2; username_exists($candidate) && $i < 100; $i++) {
            $candidate = substr($login, 0, 44) . $i;
        }
        $name = $identity['name'] !== '' ? $identity['name'] : $candidate;
        $parts = preg_split('/\s+/', $identity['name'], 2) ?: [];
        $id = wp_insert_user([
            'user_login'   => $candidate,
            'user_email'   => $identity['email'],
            'user_pass'    => wp_generate_password(32, true, true),
            'display_name' => $name,
            'nickname'     => $name,
            'first_name'   => (string) ($parts[0] ?? ''),
            'last_name'    => (string) ($parts[1] ?? ''),
            'role'         => Config::signupRole(),
        ]);
        if (is_wp_error($id)) {
            Log::write('warning', 'account could not be created', ['detail' => $id->get_error_message()]);
            return null;
        }
        update_user_meta((int) $id, '_sepo_wb_social_created', time());
        /** Fires after a provider sign-in creates an account. */
        do_action('sepo_wb_social_user_created', (int) $id);
        $u = get_user_by('id', (int) $id);
        return $u instanceof WP_User ? $u : null;
    }

    /**
     * Connect a provider to the account that started the flow. The person is
     * already signed in, so the address does not need to match; the provider
     * account must not be connected to anybody else.
     *
     * @param array{subject:string,email:string,email_verified:bool,name:string} $identity
     */
    private static function connect(int $uid, string $provider, array $identity, string $redirect, string $return): void
    {
        $back = $redirect !== '' ? $redirect : ($return !== '' ? $return : admin_url('profile.php#sepo-wb-security'));
        if (!get_user_by('id', $uid) instanceof WP_User) {
            Auth::fail('state', $return);
        }
        if (!Store::link($uid, $provider, $identity['subject'], $identity)) {
            Auth::go(add_query_arg('sepo_wb_error', 'taken', $back));
        }
        Auth::go(add_query_arg('sepo_wb_linked', rawurlencode($provider), $back));
    }
}
