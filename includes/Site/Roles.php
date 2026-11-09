<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/**
 * Roles made here, with a ticked list of what each may do. WordPress's own
 * roles and roles from other plugins are listed but left as they are; any of
 * them can be copied as the starting point for a new role.
 */
final class Roles
{
    public const OPTION = 'sepo_wb_roles';
    public const ACTION = 'sepo_wb_role';

    public static function hook(): void
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
    }

    /** Slugs of the roles this plugin made. @return list<string> */
    public static function ours(): array
    {
        $v = get_option(self::OPTION, []);
        return is_array($v) ? array_values(array_filter(array_map('strval', $v))) : [];
    }

    /** @return array<string,array{label:string,caps:array<string,string>}> */
    public static function groups(): array
    {
        $g = [
            'content' => [__('Posts', 'sepo-bridge'), [
                'read' => __('Open the dashboard and their profile', 'sepo-bridge'),
                'edit_posts' => __('Write posts', 'sepo-bridge'),
                'publish_posts' => __('Publish posts', 'sepo-bridge'),
                'edit_published_posts' => __('Edit their published posts', 'sepo-bridge'),
                'delete_posts' => __('Delete their drafts', 'sepo-bridge'),
                'delete_published_posts' => __('Delete their published posts', 'sepo-bridge'),
                'edit_others_posts' => __('Edit other people\'s posts', 'sepo-bridge'),
                'delete_others_posts' => __('Delete other people\'s posts', 'sepo-bridge'),
                'read_private_posts' => __('Read private posts', 'sepo-bridge'),
                'edit_private_posts' => __('Edit private posts', 'sepo-bridge'),
                'delete_private_posts' => __('Delete private posts', 'sepo-bridge'),
            ]],
            'pages' => [__('Pages', 'sepo-bridge'), [
                'edit_pages' => __('Write pages', 'sepo-bridge'),
                'publish_pages' => __('Publish pages', 'sepo-bridge'),
                'edit_published_pages' => __('Edit published pages', 'sepo-bridge'),
                'delete_pages' => __('Delete their draft pages', 'sepo-bridge'),
                'delete_published_pages' => __('Delete published pages', 'sepo-bridge'),
                'edit_others_pages' => __('Edit other people\'s pages', 'sepo-bridge'),
                'delete_others_pages' => __('Delete other people\'s pages', 'sepo-bridge'),
                'read_private_pages' => __('Read private pages', 'sepo-bridge'),
                'edit_private_pages' => __('Edit private pages', 'sepo-bridge'),
                'delete_private_pages' => __('Delete private pages', 'sepo-bridge'),
            ]],
            'media' => [__('Media, comments and terms', 'sepo-bridge'), [
                'upload_files' => __('Upload files', 'sepo-bridge'),
                'moderate_comments' => __('Approve and remove comments', 'sepo-bridge'),
                'manage_categories' => __('Manage categories and tags', 'sepo-bridge'),
                'unfiltered_html' => __('Use any HTML, including scripts', 'sepo-bridge'),
            ]],
            'users' => [__('People', 'sepo-bridge'), [
                'list_users' => __('See the list of users', 'sepo-bridge'),
                'create_users' => __('Add users', 'sepo-bridge'),
                'edit_users' => __('Edit users', 'sepo-bridge'),
                'promote_users' => __('Change users\' roles', 'sepo-bridge'),
                'remove_users' => __('Remove users from the site', 'sepo-bridge'),
                'delete_users' => __('Delete users', 'sepo-bridge'),
            ]],
            'site' => [__('Appearance and site', 'sepo-bridge'), [
                'edit_theme_options' => __('Menus, widgets and the customiser', 'sepo-bridge'),
                'switch_themes' => __('Change the theme', 'sepo-bridge'),
                'manage_options' => __('Change settings', 'sepo-bridge'),
                'import' => __('Import content', 'sepo-bridge'),
                'export' => __('Export content', 'sepo-bridge'),
                'activate_plugins' => __('Activate plugins', 'sepo-bridge'),
                'install_plugins' => __('Install plugins', 'sepo-bridge'),
                'update_plugins' => __('Update plugins', 'sepo-bridge'),
                'update_core' => __('Update WordPress', 'sepo-bridge'),
            ]],
            'tools' => [__('Reports', 'sepo-bridge'), [
                Activity::CAP => __('See the activity log and page views', 'sepo-bridge'),
            ]],
        ];
        $known = [];
        foreach ($g as [, $caps]) {
            $known += $caps;
        }
        // Capabilities added by other plugins, found on the site's roles.
        $other = [];
        foreach (wp_roles()->roles as $r) {
            foreach (array_keys((array) $r['capabilities']) as $cap) {
                if (!isset($known[$cap]) && !preg_match('~^level_\d+$~', (string) $cap) && !in_array($cap, ['edit_dashboard', 'edit_files', 'edit_plugins', 'edit_themes', 'install_themes', 'update_themes', 'delete_themes', 'delete_plugins', 'manage_links', 'create_sites', 'delete_sites', 'manage_network', 'manage_sites', 'manage_network_users', 'manage_network_plugins', 'manage_network_themes', 'manage_network_options', 'upgrade_network', 'setup_network', 'delete_site', 'resume_plugins', 'resume_themes', 'view_site_health_checks'], true)) {
                    $other[(string) $cap] = ucfirst(str_replace('_', ' ', (string) $cap));
                }
            }
        }
        ksort($other);
        if ($other !== []) {
            $g['other'] = [__('From other plugins', 'sepo-bridge'), $other];
        }
        $out = [];
        foreach ($g as $k => [$label, $caps]) {
            $out[$k] = ['label' => $label, 'caps' => $caps];
        }
        return $out;
    }

    /** Whether the signed-in person holds a permission themselves (checked directly, without meta-capability mapping). */
    public static function holds(string $cap): bool
    {
        return !empty(wp_get_current_user()->allcaps[$cap]);
    }

    /** People with each role. @return array<string,int> */
    public static function counts(): array
    {
        $c = count_users();
        return array_map('intval', (array) ($c['avail_roles'] ?? []));
    }

    public static function handle(): void
    {
        if (!current_user_can('promote_users') || !current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot change roles.', 'sepo-bridge'), '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);
        $do = isset($_POST['do']) ? sanitize_key(wp_unslash((string) $_POST['do'])) : '';
        $slug = isset($_POST['role']) ? sanitize_key(wp_unslash((string) $_POST['role'])) : '';
        $back = ['page' => \SepoWB\Admin\AdminController::SLUG, 'tab' => 'roles'];
        $ours = self::ours();
        $msg = 'saved';

        if ($do === 'create') {
            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
            $from = isset($_POST['copy']) ? sanitize_key(wp_unslash((string) $_POST['copy'])) : '';
            $new = sanitize_key(str_replace(' ', '_', strtolower($name)));
            if ($name === '' || $new === '') {
                $msg = 'name';
            } elseif (get_role($new) !== null) {
                $msg = 'exists';
            } else {
                $caps = ['read' => true];
                $src = $from !== '' ? get_role($from) : null;
                if ($src !== null) {
                    $caps = array_filter((array) $src->capabilities);
                }
                // Nobody hands out what they cannot do themselves.
                $caps = array_filter($caps, static fn($v, $cap): bool => self::holds((string) $cap), ARRAY_FILTER_USE_BOTH);
                add_role($new, $name, $caps);
                $ours[] = $new;
                update_option(self::OPTION, array_values(array_unique($ours)));
                self::setTracked($new, true);
                $back['role'] = $new;
                $msg = 'created';
            }
        } elseif ($do === 'save' && in_array($slug, $ours, true) && ($role = get_role($slug)) !== null) {
            $want = isset($_POST['caps']) && is_array($_POST['caps']) ? array_map('sanitize_key', wp_unslash($_POST['caps'])) : [];
            $all = [];
            foreach (self::groups() as $g) {
                $all += $g['caps'];
            }
            foreach (array_keys($all) as $cap) {
                if (!self::holds($cap)) {
                    continue;
                }
                if (in_array($cap, $want, true)) {
                    $role->add_cap($cap);
                } elseif ($role->has_cap($cap)) {
                    $role->remove_cap($cap);
                }
            }
            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
            if ($name !== '') {
                $roles = wp_roles();
                $roles->roles[$slug]['name'] = $name;
                $roles->role_names[$slug] = $name;
                update_option($roles->role_key, $roles->roles);
            }
            self::setTracked($slug, !empty($_POST['tracked']));
            $back['role'] = $slug;
        } elseif ($do === 'delete' && in_array($slug, $ours, true)) {
            $to = isset($_POST['move_to']) ? sanitize_key(wp_unslash((string) $_POST['move_to'])) : 'subscriber';
            $to = get_role($to) !== null && $to !== $slug ? $to : (string) get_option('default_role', 'subscriber');
            foreach (get_users(['role' => $slug, 'fields' => 'ID']) as $uid) {
                $u = new \WP_User((int) $uid);
                $u->remove_role($slug);
                if ($u->roles === []) {
                    $u->add_role($to);
                }
            }
            remove_role($slug);
            update_option(self::OPTION, array_values(array_diff($ours, [$slug])));
            self::setTracked($slug, false);
            $msg = 'deleted';
        } elseif ($do === 'track') {
            // Tracking can be set for any role, including WordPress's own.
            if (get_role($slug) !== null || $slug === 'guest') {
                self::setTracked($slug, !empty($_POST['tracked']));
            }
        }
        $back['wb_role'] = $msg;
        wp_safe_redirect(add_query_arg($back, admin_url('admin.php')));
        exit;
    }

    public static function setTracked(string $role, bool $on): void
    {
        $a = SiteSettings::section('activity');
        $list = array_values(array_diff((array) $a['roles'], [$role]));
        if ($on) {
            $list[] = $role;
        }
        $a['roles'] = $list;
        SiteSettings::saveSection('activity', $a);
    }
}
