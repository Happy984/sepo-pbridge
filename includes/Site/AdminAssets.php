<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/** Styles and the small helper script for the Search, Sharing, Contents, Activity and Roles screens. */
final class AdminAssets
{
    public static function enqueue(): void
    {
        $v = static fn(string $rel): string => SEPO_WB_VERSION . (is_file(SEPO_WB_DIR . $rel) ? '.' . filemtime(SEPO_WB_DIR . $rel) : '');
        wp_enqueue_style('sepo-wb-admin', SEPO_WB_URL . 'assets/css/admin.css', [], $v('assets/css/admin.css'));
        wp_enqueue_style('sepo-wb-site-admin', SEPO_WB_URL . 'assets/css/site-admin.css', ['sepo-wb-admin'], $v('assets/css/site-admin.css'));
        wp_enqueue_script('sepo-wb-site-admin', SEPO_WB_URL . 'assets/js/site-admin.js', ['wp-i18n'], $v('assets/js/site-admin.js'), true);
        wp_set_script_translations('sepo-wb-site-admin', 'sepo-bridge');
        wp_enqueue_media();
    }
}
