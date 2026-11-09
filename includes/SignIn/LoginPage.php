<?php
namespace SepoWB\SignIn;

use WP_Error;

defined('ABSPATH') || exit;

/** The provider buttons and sign-in messages on wp-login.php. */
final class LoginPage
{
    public static function hook(): void
    {
        add_action('login_enqueue_scripts', [Buttons::class, 'enqueue']);
        // Before the styled login page adds its heading, so the order is heading, buttons, form.
        add_filter('login_message', [self::class, 'buttons'], 5);
        add_filter('wp_login_errors', [self::class, 'errors']);
    }

    private static function action(): string
    {
        return isset($_REQUEST['action']) ? sanitize_key(wp_unslash((string) $_REQUEST['action'])) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
    }

    /** @param string $message */
    public static function buttons($message)
    {
        if (self::action() !== 'login' || empty(Config::get('show_on_login')) || isset($_GET['interim-login'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return $message;
        }
        $redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash((string) $_REQUEST['redirect_to'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $html = Buttons::render($redirect, '', __('or use your password', 'sepo-bridge'));
        return $html === '' ? $message : $message . $html;
    }

    /**
     * Turn ?sepo_wb_error=code into a sentence. Only known codes become
     * words; anything else gets the general one.
     */
    public static function errors(WP_Error $errors): WP_Error
    {
        // phpcs:disable WordPress.Security.NonceVerification
        if (isset($_GET['sepo_wb_error'])) {
            $code = sanitize_key(wp_unslash((string) $_GET['sepo_wb_error']));
            $errors->add('sepo_wb_' . $code, '<span class="sepo-si-msg">' . esc_html(Auth::message($code)) . '</span>');
        }
        // phpcs:enable WordPress.Security.NonceVerification
        return $errors;
    }
}
