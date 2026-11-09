<?php
/**
 * The plugin's admin screens in the flat style shared by the Sepo plugins.
 * Adds a body class on this plugin's own screens (not the post editor) and
 * loads assets/css/admin-skin.css there.
 */

defined('ABSPATH') || exit;

/** Is the current admin screen one of this plugin's own? */
function sepo_bridge_admin_skin_screen(): bool
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen === null || $screen->base === 'post') {
        return false;
    }
    foreach (['sepo-bridge', 'sepo-wb'] as $part) {
        if (str_contains((string) $screen->id, $part)) {
            return true;
        }
    }
    return in_array((string) $screen->post_type, [], true) && in_array((string) $screen->base, ['edit', 'edit-tags', 'term'], true);
}

add_filter('admin_body_class', static function (string $classes): string {
    return sepo_bridge_admin_skin_screen() ? $classes . ' sepo-wb-skin' : $classes;
});

add_action('admin_enqueue_scripts', static function (): void {
    if (sepo_bridge_admin_skin_screen()) {
        wp_enqueue_style('sepo_wb-admin-skin', SEPO_WB_URL . 'assets/css/admin-skin.css', [], SEPO_WB_VERSION);
    }
});
