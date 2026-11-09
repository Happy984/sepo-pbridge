<?php
namespace SepoWB\SignIn;

use WP_User;

defined('ABSPATH') || exit;

/**
 * Where people manage their own sign-in security: their Profile screen, and
 * the [sepo_account_security] shortcode for sites whose members never see the
 * dashboard. Administrators see another user's status on the user edit screen
 * and can reset two-factor for someone who has lost their phone and codes.
 */
final class Account
{
    public static function hook(): void
    {
        add_action('show_user_profile', [self::class, 'ownSection'], 5);
        add_action('edit_user_profile', [self::class, 'otherSection'], 5);
        add_action('admin_enqueue_scripts', [self::class, 'adminAssets']);
        add_filter('manage_users_columns', [self::class, 'column']);
        add_filter('manage_users_custom_column', [self::class, 'columnValue'], 10, 3);
        add_shortcode('sepo_account_security', [self::class, 'shortcode']);
        add_shortcode('sepo_social_login', [self::class, 'loginShortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'frontAssets']);
    }

    private static function wanted(): bool
    {
        return Config::twoFactorOn() || Config::readyProviders() !== [] ;
    }

    // ---------------------------------------------------------------- assets

    public static function registerScript(): void
    {
        $ver = SEPO_WB_VERSION . '.' . (int) @filemtime(SEPO_WB_DIR . 'assets/js/signin-account.js');
        wp_register_script('sepo-wb-signin-account', SEPO_WB_URL . 'assets/js/signin-account.js', ['wp-i18n'], $ver, true);
        wp_set_script_translations('sepo-wb-signin-account', 'sepo-bridge');
    }

    public static function adminAssets(string $hook): void
    {
        if (!in_array($hook, ['profile.php', 'user-edit.php'], true) || !self::wanted()) {
            return;
        }
        Buttons::enqueue();
        self::registerScript();
        wp_enqueue_script('sepo-wb-signin-account');
    }

    public static function frontAssets(): void
    {
        $post = get_post();
        if ($post && (has_shortcode((string) $post->post_content, 'sepo_account_security') || has_shortcode((string) $post->post_content, 'sepo_social_login'))) {
            Buttons::enqueue();
        }
    }

    // ---------------------------------------------------------------- panels

    /** The container the account script fills in. */
    private static function panel(string $return, string $heading = 'h2'): string
    {
        return '<div id="sepo-wb-security" class="sepo-si-scope sepo-si-account" data-sepo-si-account'
            . ' data-api="' . esc_url(rest_url('sepo-wb/v1')) . '" data-nonce="' . esc_attr(wp_create_nonce('wp_rest')) . '"'
            . ' data-return="' . esc_url($return) . '">'
            . '<' . $heading . ' class="sepo-si-account__title">' . esc_html__('Sign-in security', 'sepo-bridge') . '</' . $heading . '>'
            . '<div class="sepo-si-account__body" data-si-body aria-live="polite"><p class="sepo-si-muted">' . esc_html__('Loading…', 'sepo-bridge') . '</p></div>'
            . '<noscript><p>' . esc_html__('This section needs JavaScript.', 'sepo-bridge') . '</p></noscript>'
            . '</div>';
    }

    public static function ownSection(WP_User $user): void
    {
        if (!self::wanted()) {
            return;
        }
        echo self::panel(admin_url('profile.php#sepo-wb-security')); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
    }

    /** Another user's status, read-only apart from the reset. */
    public static function otherSection(WP_User $user): void
    {
        if (!self::wanted() && !Store::enabled($user->ID) && Store::links($user->ID) === []) {
            return;
        }
        $on = Store::enabled($user->ID);
        $links = Store::links($user->ID);
        $names = [];
        foreach ($links as $p => $l) {
            $spec = Providers::get((string) $p);
            $names[] = ($spec['label'] ?? ucfirst((string) $p)) . (!empty($l['email']) ? ' (' . $l['email'] . ')' : '');
        }
        $fmt = get_option('date_format') . ' ' . get_option('time_format');
        echo '<div id="sepo-wb-security" class="sepo-si-scope sepo-si-account" data-sepo-si-admin data-api="' . esc_url(rest_url('sepo-wb/v1')) . '" data-nonce="' . esc_attr(wp_create_nonce('wp_rest')) . '" data-user="' . (int) $user->ID . '">';
        echo '<h2 class="sepo-si-account__title">' . esc_html__('Sign-in security', 'sepo-bridge') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('Two-factor', 'sepo-bridge') . '</th><td><span class="sepo-si-pill' . ($on ? ' is-on' : '') . '" data-si-state>' . esc_html($on ? __('On', 'sepo-bridge') : __('Off', 'sepo-bridge')) . '</span>';
        if ($on && current_user_can('edit_user', $user->ID) && $user->ID !== get_current_user_id()) {
            echo ' <button type="button" class="button" data-si-reset>' . esc_html__('Reset two-factor', 'sepo-bridge') . '</button>'
                . '<p class="description">' . esc_html__('For someone who has lost both their phone and their recovery codes. It turns two-factor off; they set it up again from their own profile. Your name is recorded against the reset.', 'sepo-bridge') . '</p>'
                . '<div class="sepo-si-confirm" data-si-reset-confirm hidden><p>' . esc_html__('Reset two-factor for this user? They will be able to sign in with just their password until they set it up again.', 'sepo-bridge') . '</p>'
                . '<button type="button" class="button button-primary" data-si-reset-yes>' . esc_html__('Yes, reset it', 'sepo-bridge') . '</button> <button type="button" class="button" data-si-reset-no>' . esc_html__('Cancel', 'sepo-bridge') . '</button></div>'
                . '<p class="sepo-si-feedback" data-si-feedback role="status" aria-live="polite"></p>';
        }
        echo '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Connected providers', 'sepo-bridge') . '</th><td>' . ($names === [] ? '<span class="description">' . esc_html__('None', 'sepo-bridge') . '</span>' : esc_html(implode(', ', $names))) . '</td></tr>';
        $history = Store::history($user->ID);
        if ($history !== []) {
            echo '<tr><th scope="row">' . esc_html__('Recent activity', 'sepo-bridge') . '</th><td><ul class="sepo-si-history">';
            foreach (array_slice($history, 0, 8) as $h) {
                $by = (int) $h['by'] !== $user->ID && (int) $h['by'] > 0 ? get_userdata((int) $h['by']) : null;
                echo '<li>' . esc_html(wp_date($fmt, (int) $h['at'])) . ' · ' . esc_html(Store::eventLabel((string) $h['event']))
                    /* translators: %s: name */
                    . ($by ? ' · ' . esc_html(sprintf(__('by %s', 'sepo-bridge'), $by->display_name)) : '') . '</li>';
            }
            echo '</ul></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    // ---------------------------------------------------------------- users list

    /** @param array<string,string> $cols */
    public static function column(array $cols): array
    {
        if (self::wanted()) {
            $cols['sepo_wb_signin'] = __('Sign-in', 'sepo-bridge');
        }
        return $cols;
    }

    /** @param string $out */
    public static function columnValue($out, string $col, int $uid)
    {
        if ($col !== 'sepo_wb_signin') {
            return $out;
        }
        $bits = [];
        if (Store::enabled($uid)) {
            $bits[] = '<span class="sepo-si-tag">' . esc_html__('2FA', 'sepo-bridge') . '</span>';
        }
        foreach (array_keys(Store::links($uid)) as $p) {
            $bits[] = esc_html((string) (Providers::get((string) $p)['label'] ?? ucfirst((string) $p)));
        }
        return $bits === [] ? '<span aria-hidden="true">—</span>' : implode(' ', $bits);
    }

    // ---------------------------------------------------------------- shortcodes

    private static function here(): string
    {
        return home_url(add_query_arg([]));
    }

    /** @param array<string,string>|string $atts */
    public static function shortcode($atts = []): string
    {
        Buttons::enqueue();
        $here = remove_query_arg(['sepo_wb_error', 'sepo_wb_linked'], self::here());
        if (!is_user_logged_in()) {
            $out = '<div class="sepo-si-scope sepo-si-card">' . self::notice()
                . '<p>' . esc_html__('Sign in to manage your sign-in security.', 'sepo-bridge') . '</p>'
                . '<p><a class="sepo-si-action sepo-si-action--primary" href="' . esc_url(wp_login_url($here)) . '">' . esc_html__('Sign in', 'sepo-bridge') . '</a></p>'
                . Buttons::render($here, $here) . '</div>';
            return $out;
        }
        self::registerScript();
        wp_enqueue_script('sepo-wb-signin-account');
        // The panel's script shows any message carried back in the address.
        return self::panel($here, 'h2');
    }

    /** @param array<string,string>|string $atts */
    public static function loginShortcode($atts = []): string
    {
        $a = shortcode_atts(['redirect' => '', 'title' => ''], is_array($atts) ? $atts : [], 'sepo_social_login');
        if (is_user_logged_in()) {
            return '';
        }
        Buttons::enqueue();
        $here = remove_query_arg(['sepo_wb_error', 'sepo_wb_linked'], self::here());
        $redirect = $a['redirect'] !== '' ? wp_validate_redirect((string) $a['redirect'], $here) : $here;
        $buttons = Buttons::render($redirect, $here);
        if ($buttons === '') {
            return '';
        }
        return '<div class="sepo-si-scope sepo-si-login">' . ($a['title'] !== '' ? '<p class="sepo-si-login__title">' . esc_html((string) $a['title']) . '</p>' : '') . self::notice() . $buttons . '</div>';
    }

    /** A message carried back to a front-end page from a sign-in or connect attempt. */
    private static function notice(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification -- display only
        if (isset($_GET['sepo_wb_error'])) {
            return '<p class="sepo-si-note sepo-si-note--bad" role="alert">' . esc_html(Auth::message(sanitize_key(wp_unslash((string) $_GET['sepo_wb_error'])))) . '</p>';
        }
        if (isset($_GET['sepo_wb_linked'])) {
            $spec = Providers::get(sanitize_key(wp_unslash((string) $_GET['sepo_wb_linked'])));
            /* translators: %s: provider name */
            return $spec ? '<p class="sepo-si-note sepo-si-note--good" role="status">' . esc_html(sprintf(__('%s is now connected.', 'sepo-bridge'), (string) $spec['label'])) . '</p>' : '';
        }
        // phpcs:enable WordPress.Security.NonceVerification
        return '';
    }
}
