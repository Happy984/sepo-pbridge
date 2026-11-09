<?php
/**
 * Plugin Name:       Sepo Bridge
 * Description:       Feature toggles, custom post types, taxonomies, meta-field groups, membership gates, a cookie consent banner, social sign-in with two-factor authentication, search appearance and sitemap, share buttons, tables of contents, activity tracking, custom roles, menu and block visibility, and security/performance hardening — one panel, no outside dependency.
 * Version:           1.8.0
 * Requires at least: 6.1
 * Requires PHP:      8.0
 * Author:            Euneolink Solutions
 * Author URI:        https://edu.sepodesk.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       sepo-bridge
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Sepo Bridge needs PHP 8.0 or newer. The plugin is installed but not running.', 'sepo-bridge')
            . '</p></div>';
    });
    return;
}

define('SEPO_WB_VERSION', '1.8.0');
define('SEPO_WB_FILE', __FILE__);
define('SEPO_WB_DIR', plugin_dir_path(__FILE__));
define('SEPO_WB_URL', plugin_dir_url(__FILE__));
define('SEPO_WB_BASENAME', plugin_basename(__FILE__));

require_once __DIR__ . '/includes/admin-skin.php';

require_once SEPO_WB_DIR . 'includes/Autoloader.php';
\SepoWB\Autoloader::register();

register_activation_hook(__FILE__, ['SepoWB\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SepoWB\\Plugin', 'deactivate']);

add_action('plugins_loaded', ['SepoWB\\Plugin', 'boot']);

if (!function_exists('sepo_has_cookie_consent')) {
    /**
     * Has the visitor allowed a cookie category (necessary, preferences,
     * analytics or marketing)? Always true while the cookie banner is off.
     */
    function sepo_has_cookie_consent(string $category): bool
    {
        return \SepoWB\Consent\Consent::allows($category);
    }
}
