<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/** The provider buttons, for wp-login.php and the [sepo_social_login] shortcode. */
final class Buttons
{
    /**
     * @param string $redirect where to go after signing in
     * @param string $return   where to come back to with a reason if it fails ('' for the login page)
     * @param string $rule     the text on the divider line, '' for none
     */
    public static function render(string $redirect = '', string $return = '', string $rule = ''): string
    {
        $providers = Config::readyProviders();
        if ($providers === []) {
            return '';
        }
        $out = '<div class="sepo-si-buttons" role="group" aria-label="' . esc_attr__('Sign in with', 'sepo-bridge') . '">';
        foreach ($providers as $key => $spec) {
            /* translators: %s: provider name, e.g. Google */
            $label = sprintf(__('Continue with %s', 'sepo-bridge'), (string) $spec['label']);
            $out .= '<a class="sepo-si-btn sepo-si-btn--' . esc_attr($key) . '" rel="nofollow" href="' . esc_url(OAuth::startUrl($key, $redirect, false, $return)) . '"'
                . ' style="--si-bg:' . esc_attr((string) $spec['colour']) . ';--si-fg:' . esc_attr((string) $spec['text']) . ';--si-line:' . esc_attr((string) $spec['border']) . '">'
                . self::icon($key) . '<span>' . esc_html($label) . '</span></a>';
        }
        $out .= '</div>';
        if ($rule !== '') {
            $out .= '<div class="sepo-si-rule"><span>' . esc_html($rule) . '</span></div>';
        }
        return $out;
    }

    /** A provider's mark, inline: a login page should not load images from the providers' servers. */
    public static function icon(string $provider): string
    {
        $icons = [
            'google'    => '<path fill="#4285F4" d="M23 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.2a5.3 5.3 0 0 1-2.3 3.5v2.9h3.7c2.2-2 3.4-5 3.4-8.6z"/><path fill="#34A853" d="M12 24c3.1 0 5.7-1 7.6-2.8l-3.7-2.9c-1 .7-2.3 1.1-3.9 1.1-3 0-5.5-2-6.4-4.7H1.8v3C3.7 21.4 7.6 24 12 24z"/><path fill="#FBBC05" d="M5.6 14.7a7.2 7.2 0 0 1 0-4.6v-3H1.8a12 12 0 0 0 0 10.6l3.8-3z"/><path fill="#EA4335" d="M12 4.8c1.7 0 3.2.6 4.4 1.7l3.3-3.3C17.7 1.2 15.1 0 12 0 7.6 0 3.7 2.6 1.8 6.1l3.8 3C6.5 6.4 9 4.8 12 4.8z"/>',
            'facebook'  => '<path fill="currentColor" d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-1.9.9-1.9 1.8V12h3.3l-.5 3.5h-2.8v8.4A12 12 0 0 0 24 12z"/>',
            'github'    => '<path fill="currentColor" d="M12 .3a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1-.7.1-.7.1-.7 1.2 0 1.8 1.2 1.8 1.2 1 1.8 2.8 1.3 3.5 1 0-.8.4-1.3.7-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2 0-.3-.5-1.5.2-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.3 4.7 18.3 5 18.3 5c.7 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.5.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .3z"/>',
            'microsoft' => '<path fill="#F25022" d="M1 1h10v10H1z"/><path fill="#7FBA00" d="M13 1h10v10H13z"/><path fill="#00A4EF" d="M1 13h10v10H1z"/><path fill="#FFB900" d="M13 13h10v10H13z"/>',
            'linkedin'  => '<path fill="currentColor" d="M20.4 20.4h-3.6v-5.6c0-1.3 0-3-1.9-3s-2.1 1.4-2.1 2.9v5.7H9.4V9h3.4v1.6h.1a3.7 3.7 0 0 1 3.4-1.8c3.6 0 4.2 2.4 4.2 5.5v6.1zM5.3 7.4a2.1 2.1 0 1 1 0-4.2 2.1 2.1 0 0 1 0 4.2zm1.8 13H3.5V9h3.6v11.4zM22.2 0H1.8C.8 0 0 .8 0 1.7v20.6c0 .9.8 1.7 1.8 1.7h20.4c1 0 1.8-.8 1.8-1.7V1.7c0-.9-.8-1.7-1.8-1.7z"/>',
        ];
        $body = $icons[$provider] ?? '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 12h8M12 8v8" stroke="currentColor" stroke-width="2"/>';
        return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }

    /** The styles for buttons and the code prompt, wherever they appear. */
    public static function enqueue(): void
    {
        wp_enqueue_style('sepo-wb-signin', SEPO_WB_URL . 'assets/css/signin.css', [], SEPO_WB_VERSION . '.' . (int) @filemtime(SEPO_WB_DIR . 'assets/css/signin.css'));
    }
}
