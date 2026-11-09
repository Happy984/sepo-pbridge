<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/**
 * Sign-in diagnostics for the PHP error log. Warnings and errors are always
 * written; info lines only with WP_DEBUG on. Nothing secret is ever passed in:
 * no codes, tokens or provider error text that could name a client id.
 */
final class Log
{
    /** @param array<string,mixed> $context */
    public static function write(string $level, string $message, array $context = []): void
    {
        if ($level === 'info' && !(defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }
        do_action('sepo_wb_sign_in_log', $level, $message, $context);
    }
}
