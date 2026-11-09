<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/** The small stylesheet and script used by share buttons, contents boxes and page views. */
final class Assets
{
    public static function front(): void
    {
        $v = static fn(string $rel): string => SEPO_WB_VERSION . (is_file(SEPO_WB_DIR . $rel) ? '.' . filemtime(SEPO_WB_DIR . $rel) : '');
        if (!wp_style_is('sepo-wb-site', 'registered')) {
            wp_register_style('sepo-wb-site', SEPO_WB_URL . 'assets/css/site.css', [], $v('assets/css/site.css'));
            wp_register_script('sepo-wb-site', SEPO_WB_URL . 'assets/js/site.js', [], $v('assets/js/site.js'), ['in_footer' => true, 'strategy' => 'defer']);
        }
        wp_enqueue_style('sepo-wb-site');
        wp_enqueue_script('sepo-wb-site');
    }
}
