<?php
namespace SepoWB\SignIn;

use WP_Error;
use WP_User;

defined('ABSPATH') || exit;

/**
 * The second step at sign-in.
 *
 * Password sign-ins: after WordPress has checked the password, and before any
 * login cookie exists, an account with two-factor on is sent to a code prompt
 * instead. Nothing is signed in until the code passes, so closing the prompt
 * leaves the person signed out.
 *
 * Provider sign-ins arrive through Auth::complete() and meet the same prompt.
 *
 * The prompt is a step of wp-login.php (action=sepo_2fa), drawn with
 * WordPress's own login screen functions, so it looks like the rest of the
 * login screens, styled or not.
 *
 * Application passwords are left alone: they are separate, revocable
 * credentials made for programs, which cannot answer a prompt.
 */
final class TwoFactor
{
    private const PENDING_COOKIE = 'sepo_wb_2fa';
    private const DEVICE_COOKIE  = 'sepo_wb_td_';
    private const TTL = 600;
    private const MAX_TRIES = 5;

    public static function hook(): void
    {
        add_filter('authenticate', [self::class, 'intercept'], 100, 3);
        add_action('login_form_sepo_2fa', [self::class, 'page']);
        add_action('admin_init', [self::class, 'enforceAdmin'], 1);
        add_action('template_redirect', [self::class, 'enforceFront'], 1);
        add_action('admin_notices', [self::class, 'requiredNotice']);
    }

    /** Whether this account must enter a code to finish signing in on this browser. */
    public static function owed(WP_User $user): bool
    {
        return Config::twoFactorOn() && Store::enabled($user->ID) && !self::remembered($user->ID);
    }

    // ---------------------------------------------------------------- password sign-in

    /**
     * @param WP_User|WP_Error|null $user
     * @return WP_User|WP_Error|null
     */
    public static function intercept($user, $username = '', $password = '')
    {
        if (!$user instanceof WP_User || (string) $username === '' || (string) $password === '' || !self::owed($user)) {
            return $user;
        }
        // An application password authenticated this request: let it through.
        if (did_action('application_password_did_authenticate')) {
            return $user;
        }
        if ((defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax() || wp_doing_cron()) {
            return new WP_Error('sepo_wb_2fa_required', Auth::message('twofactor'));
        }
        // phpcs:disable WordPress.Security.NonceVerification -- the login form has no nonce; the password has just been checked
        $remember = !empty($_POST['rememberme']);
        $requested = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash((string) $_REQUEST['redirect_to'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification
        self::challenge($user, $remember, $requested, 'password');
        return $user;
    }

    // ---------------------------------------------------------------- the prompt

    /**
     * Park the sign-in and send the browser to the code prompt. The waiting
     * sign-in is tied to this browser by an HttpOnly cookie and lasts ten
     * minutes.
     */
    public static function challenge(WP_User $user, bool $remember, string $requested, string $source): void
    {
        $token = bin2hex(random_bytes(24));
        set_transient(self::key($token), [
            'uid'      => $user->ID,
            'remember' => $remember ? 1 : 0,
            'redirect' => $requested,
            'source'   => $source,
            'issued'   => time(),
            'tries'    => 0,
        ], self::TTL);
        Cookies::set(self::PENDING_COOKIE, $token, self::TTL);
        Auth::go(add_query_arg('action', 'sepo_2fa', wp_login_url()));
    }

    private static function key(string $token): string
    {
        return 'sepo_wb_2fa_' . substr(hash('sha256', $token), 0, 40);
    }

    /** @return array<string,mixed>|null */
    private static function pending(): ?array
    {
        $token = Cookies::get(self::PENDING_COOKIE);
        if ($token === '') {
            return null;
        }
        $p = get_transient(self::key($token));
        if (!is_array($p) || time() - (int) ($p['issued'] ?? 0) > self::TTL) {
            return null;
        }
        return $p;
    }

    private static function drop(): void
    {
        $token = Cookies::get(self::PENDING_COOKIE);
        if ($token !== '') {
            delete_transient(self::key($token));
        }
        Cookies::clear(self::PENDING_COOKIE);
    }

    /** wp-login.php?action=sepo_2fa */
    public static function page(): void
    {
        $pending = self::pending();
        if ($pending === null) {
            self::drop();
            Auth::fail('expired');
        }
        $user = get_user_by('id', (int) $pending['uid']);
        if (!$user instanceof WP_User || !Store::enabled($user->ID)) {
            self::drop();
            Auth::fail('expired');
        }

        $error = '';
        if ('POST' === sanitize_key(wp_unslash((string) ($_SERVER['REQUEST_METHOD'] ?? '')))) {
            if (!isset($_POST['_sepo_wb_2fa']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['_sepo_wb_2fa'])), 'sepo_wb_2fa')) {
                $error = __('The page had expired. Please enter the code again.', 'sepo-bridge');
            } else {
                $pending['tries'] = (int) $pending['tries'] + 1;
                $code = isset($_POST['sepo_wb_code']) ? sanitize_text_field(wp_unslash((string) $_POST['sepo_wb_code'])) : '';
                if (self::check($user->ID, $code)) {
                    self::drop();
                    if (!empty($_POST['sepo_wb_trust']) && Config::rememberDays() > 0) {
                        self::rememberDevice($user->ID);
                    }
                    if ($pending['source'] !== 'password') {
                        Store::touch($user->ID, (string) $pending['source']);
                    }
                    Auth::login($user, !empty($pending['remember']));
                    Auth::go(Auth::destination($user, (string) $pending['redirect']));
                }
                if ($pending['tries'] >= self::MAX_TRIES) {
                    Store::event($user->ID, 'locked', $user->ID);
                    Log::write('warning', 'two-factor attempts used up', ['user' => $user->ID]);
                    self::drop();
                    Auth::fail('locked');
                }
                set_transient(self::key(Cookies::get(self::PENDING_COOKIE)), $pending, self::TTL);
                $left = self::MAX_TRIES - (int) $pending['tries'];
                /* translators: %d: attempts left */
                $error = sprintf(_n('That code was not right. %d attempt left.', 'That code was not right. %d attempts left.', $left, 'sepo-bridge'), $left);
            }
        }

        self::render($user, $error);
        exit;
    }

    /** A current code or an unused recovery code. Either is used up on success. */
    public static function check(int $uid, string $code): bool
    {
        $secret = Store::secret($uid);
        if ($secret === '') {
            return false;
        }
        $counter = Totp::verify($secret, $code, Store::lastCounter($uid));
        if ($counter !== null && Store::spendCounter($uid, $counter)) {
            return true;
        }
        if (strlen(preg_replace('/[^0-9A-Za-z]/', '', $code) ?? '') === 10 && Store::spendRecovery($uid, $code)) {
            Log::write('warning', 'signed in with a recovery code', ['user' => $uid, 'left' => count(Store::recoveryHashes($uid))]);
            return true;
        }
        return false;
    }

    private static function render(WP_User $user, string $error): void
    {
        $errors = new WP_Error();
        if ($error !== '') {
            $errors->add('sepo_wb_2fa', '<span class="sepo-si-msg">' . esc_html($error) . '</span>');
        }
        if (!function_exists('login_header')) {
            return;
        }
        login_header(__('Verification code', 'sepo-bridge'), '', $errors);
        $days = Config::rememberDays();
        $cancel = wp_login_url();
        ?>
        <form name="sepo-wb-2fa" id="loginform" class="sepo-si-2fa" action="<?php echo esc_url(add_query_arg('action', 'sepo_2fa', wp_login_url())); ?>" method="post" autocomplete="off">
            <p class="sepo-si-2fa__lead"><?php esc_html_e('Enter the six-digit code from your authenticator app.', 'sepo-bridge'); ?></p>
            <p>
                <label for="sepo_wb_code"><?php esc_html_e('Code', 'sepo-bridge'); ?></label>
                <input type="text" name="sepo_wb_code" id="sepo_wb_code" class="input sepo-si-2fa__code" inputmode="numeric" autocomplete="one-time-code" maxlength="11" spellcheck="false" autocapitalize="off" required aria-describedby="sepo-wb-2fa-hint" autofocus>
            </p>
            <p class="description sepo-si-2fa__hint" id="sepo-wb-2fa-hint"><?php esc_html_e('Lost your phone? Enter one of your recovery codes instead. Each one works once.', 'sepo-bridge'); ?></p>
            <?php if ($days > 0) : ?>
                <p class="forgetmenot"><input name="sepo_wb_trust" type="checkbox" id="sepo_wb_trust" value="1"> <label for="sepo_wb_trust"><?php
                    /* translators: %d: days */
                    echo esc_html(sprintf(_n('Don’t ask again on this device for %d day', 'Don’t ask again on this device for %d days', $days, 'sepo-bridge'), $days)); ?></label></p>
            <?php endif; ?>
            <?php wp_nonce_field('sepo_wb_2fa', '_sepo_wb_2fa'); ?>
            <p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Verify', 'sepo-bridge'); ?>"></p>
        </form>
        <p id="nav"><a href="<?php echo esc_url($cancel); ?>"><?php esc_html_e('Cancel and sign in again', 'sepo-bridge'); ?></a></p>
        <script>
        (function () {
            var input = document.getElementById('sepo_wb_code');
            var form = document.getElementById('loginform');
            var sent = false;
            if (!input || !form) { return; }
            // Six digits is a whole code: send it straight away, since each code only lives thirty seconds.
            input.addEventListener('input', function () {
                if (!sent && /^\d{6}$/.test(input.value.replace(/\s/g, ''))) { sent = true; form.submit(); }
            });
        })();
        </script>
        <?php
        login_footer('sepo_wb_code');
    }

    // ---------------------------------------------------------------- remembered devices

    private static function deviceCookie(int $uid): string
    {
        return self::DEVICE_COOKIE . substr(hash('sha256', $uid . wp_salt('auth')), 0, 12);
    }

    private static function deviceMac(int $uid, int $expires): string
    {
        // The generation changes when two-factor is turned off or reset, so a
        // remembered browser stops counting the moment that happens.
        return hash_hmac('sha256', $uid . '|' . $expires . '|' . Store::generation($uid), wp_salt('secure_auth'));
    }

    private static function rememberDevice(int $uid): void
    {
        $days = Config::rememberDays();
        $expires = time() + $days * DAY_IN_SECONDS;
        Cookies::set(self::deviceCookie($uid), $expires . '.' . self::deviceMac($uid, $expires), $days * DAY_IN_SECONDS);
    }

    private static function remembered(int $uid): bool
    {
        $days = Config::rememberDays();
        if ($days <= 0) {
            return false;
        }
        $v = Cookies::get(self::deviceCookie($uid));
        if ($v === '' || !str_contains($v, '.')) {
            return false;
        }
        [$expires, $mac] = explode('.', $v, 2);
        $expires = (int) $expires;
        // The setting may have been shortened since the cookie was made.
        if ($expires < time() || $expires > time() + $days * DAY_IN_SECONDS) {
            return false;
        }
        return hash_equals(self::deviceMac($uid, $expires), $mac);
    }

    // ---------------------------------------------------------------- required roles

    public static function required(WP_User $user): bool
    {
        if (!Config::twoFactorOn() || !Crypto::available()) {
            return false;
        }
        return array_intersect((array) $user->roles, Config::requiredRoles()) !== [];
    }

    private static function owesSetup(): ?WP_User
    {
        if (!is_user_logged_in()) {
            return null;
        }
        $u = wp_get_current_user();
        return self::required($u) && !Store::enabled($u->ID) ? $u : null;
    }

    /** People in a required role who have not set it up are kept on their profile until they do. */
    public static function enforceAdmin(): void
    {
        if (wp_doing_ajax() || self::owesSetup() === null) {
            return;
        }
        global $pagenow;
        if ($pagenow === 'profile.php') {
            return;
        }
        Auth::go(admin_url('profile.php#sepo-wb-security'));
    }

    public static function enforceFront(): void
    {
        $page = (string) Config::get('security_page');
        if ($page === '' || self::owesSetup() === null) {
            return;
        }
        $here = (string) wp_parse_url(home_url(add_query_arg([])), PHP_URL_PATH);
        if (untrailingslashit($here) === untrailingslashit((string) wp_parse_url($page, PHP_URL_PATH))) {
            return;
        }
        Auth::go($page);
    }

    public static function requiredNotice(): void
    {
        if (self::owesSetup() === null) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Two-factor authentication is required for your account.', 'sepo-bridge') . '</strong> '
            . esc_html__('Set it up below under Sign-in security. The rest of the dashboard opens once it is on.', 'sepo-bridge') . '</p></div>';
    }
}
