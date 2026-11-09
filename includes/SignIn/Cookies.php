<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/** Short-lived, HttpOnly cookies for the sign-in steps that happen before WordPress's own login cookie exists. */
final class Cookies
{
    public static function set(string $name, string $value, int $ttl): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($name, $value, [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'domain'   => defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '',
            'secure'   => is_ssl(),
            'httponly' => true,
            // Lax: the provider sends the browser back with a top-level GET, which Lax allows.
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
    }

    public static function get(string $name): string
    {
        $v = isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) ? sanitize_text_field(wp_unslash($_COOKIE[$name])) : '';
        return preg_replace('/[^A-Za-z0-9|._-]/', '', $v) ?? '';
    }

    public static function clear(string $name): void
    {
        if (!headers_sent()) {
            setcookie($name, '', ['expires' => time() - 3600, 'path' => '/', 'domain' => defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
        }
        unset($_COOKIE[$name]);
    }
}
