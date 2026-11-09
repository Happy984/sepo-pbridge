<?php
namespace SepoWB\SignIn;

use WP_User;

defined('ABSPATH') || exit;

/**
 * The end of every sign-in: a second factor when the account has one, then
 * WordPress's own login cookie, then the page the person was going to.
 *
 * Password and provider sign-ins both finish here, so there is one place that
 * decides whether a code is still owed and one place that sets the session.
 */
final class Auth
{
    /** Visitor-facing wording for the short reason codes carried in a redirect. */
    public static function message(string $code): string
    {
        $m = [
            'cancelled'   => __('Sign-in was cancelled.', 'sepo-bridge'),
            'state'       => __('That sign-in took too long or was started in another browser. Please try again.', 'sepo-bridge'),
            'expired'     => __('That took too long. Please sign in again.', 'sepo-bridge'),
            'provider'    => __('That sign-in method is not available.', 'sepo-bridge'),
            'network'     => __('The sign-in provider could not be reached. Please try again.', 'sepo-bridge'),
            'exchange'    => __('The sign-in provider refused the request.', 'sepo-bridge'),
            'profile'     => __('Your profile could not be read from the provider.', 'sepo-bridge'),
            'unverified'  => __('That account has no verified email address, so it cannot be matched to an account here.', 'sepo-bridge'),
            'linking_off' => __('An account already exists for that address. Sign in with your password, then connect the provider from your account security settings.', 'sepo-bridge'),
            'no_account'  => __('There is no account for that address here. Please contact the site administrator.', 'sepo-bridge'),
            'create'      => __('Your account could not be created.', 'sepo-bridge'),
            'refused'     => __('That account cannot sign in at the moment.', 'sepo-bridge'),
            'taken'       => __('That provider account is already connected to a different account here.', 'sepo-bridge'),
            'locked'      => __('Too many incorrect codes. Please sign in again.', 'sepo-bridge'),
            'twofactor'   => __('This account uses two-factor authentication. Please sign in on the login page.', 'sepo-bridge'),
        ];
        return $m[$code] ?? __('That sign-in could not be completed.', 'sepo-bridge');
    }

    /** Send the browser somewhere and stop. */
    public static function go(string $url): void
    {
        nocache_headers();
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Back to where the sign-in began, with a reason. The reason is a short
     * code; the page turns it into words, so nothing the provider said ever
     * reaches the screen.
     */
    public static function fail(string $code, string $return = ''): void
    {
        Log::write('info', 'sign-in stopped', ['reason' => $code]);
        $base = $return !== '' ? wp_validate_redirect($return, wp_login_url()) : wp_login_url();
        self::go(add_query_arg('sepo_wb_error', rawurlencode($code), $base));
    }

    /**
     * Where to land after signing in. The requested address must be on this
     * site; otherwise people who cannot write posts go to the front page,
     * and everyone else to the dashboard. login_redirect is applied, so
     * plugins that steer their customers somewhere still do.
     */
    public static function destination(WP_User $user, string $requested): string
    {
        $requested = $requested !== '' ? wp_validate_redirect($requested, '') : '';
        $default = $user->has_cap('edit_posts') ? admin_url() : home_url('/');
        $to = $requested !== '' ? $requested : $default;
        $to = (string) apply_filters('login_redirect', $to, $requested, $user); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, so other plugins can steer sign-in.
        // A login page is not a destination: it would loop.
        if ($to === '' || str_contains($to, 'wp-login.php')) {
            $to = $default;
        }
        if (!$user->has_cap('edit_posts') && (rtrim($to, '/') === rtrim(admin_url(), '/') || $to === admin_url('index.php'))) {
            $to = home_url('/');
        }
        return wp_validate_redirect($to, $default);
    }

    /**
     * Finish a sign-in. Asks for a code first when the account has two-factor
     * on and this browser is not a remembered one.
     */
    public static function complete(WP_User $user, bool $remember, string $requested, string $source): void
    {
        if (TwoFactor::owed($user)) {
            TwoFactor::challenge($user, $remember, $requested, $source);
        }
        self::login($user, $remember);
        self::go(self::destination($user, $requested));
    }

    /** Set WordPress's login cookie and announce the login the way wp_signon() does. */
    public static function login(WP_User $user, bool $remember): void
    {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember, is_ssl());
        do_action('wp_login', $user->user_login, $user); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, fired as wp_signon() does.
    }
}
