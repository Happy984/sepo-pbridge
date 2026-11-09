<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/**
 * Settings for search, sharing, contents boxes and activity, kept in one option.
 * Each tab of the panel saves its own section through admin-post.php.
 */
final class SiteSettings
{
    public const OPTION = 'sepo_wb_site';
    public const ACTION = 'sepo_wb_site_save';

    /** Networks the share bar can show, in the order they appear. */
    public const NETWORKS = ['facebook', 'x', 'whatsapp', 'linkedin', 'telegram', 'email', 'copy', 'native'];

    /** @return array<string,array<string,mixed>> */
    public static function defaults(): array
    {
        return [
            'seo' => [
                'enabled'        => 0,
                'separator'      => '–',
                'home_title'     => '',
                'home_desc'      => '',
                'default_image'  => 0,
                'og'             => 1,
                'schema'         => 1,
                'noindex_search' => 1,
                'noindex_paged'  => 0,
                'google'         => '',
                'bing'           => '',
                'sitemap'        => 1,
                'sitemap_types'  => ['post', 'page'],
                'sitemap_tax'    => ['category'],
                'sitemap_users'  => 0,
            ],
            'share' => [
                'enabled'    => 0,
                'networks'   => ['facebook', 'x', 'whatsapp', 'linkedin', 'email', 'copy'],
                'post_types' => ['post'],
                'placement'  => 'after',
                'style'      => 'filled',
                'label'      => '',
                'per_post'   => 1,
            ],
            'toc' => [
                'enabled'    => 0,
                'post_types' => ['post', 'page'],
                'position'   => 'right',
                'min'        => 3,
                'depth'      => 3,
                'title'      => '',
                'open'       => 1,
                'per_post'   => 1,
            ],
            'activity' => [
                'enabled'    => 0,
                'views'      => 1,
                'actions'    => 1,
                'roles'      => ['guest', 'subscriber', 'contributor', 'author', 'editor', 'administrator'],
                'keep_days'  => 90,
                'skip_admin_views' => 1,
            ],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $out = [];
        foreach (self::defaults() as $section => $def) {
            $out[$section] = array_merge($def, is_array($saved[$section] ?? null) ? $saved[$section] : []);
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public static function section(string $name): array
    {
        return self::all()[$name] ?? [];
    }

    /** @param array<string,mixed> $values */
    public static function saveSection(string $name, array $values): void
    {
        $all = self::all();
        $all[$name] = $values;
        update_option(self::OPTION, $all);
    }

    public static function hook(): void
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
    }

    /** Public post types people can choose from, slug => label. @return array<string,string> */
    public static function postTypes(): array
    {
        $out = [];
        foreach (get_post_types(['public' => true], 'objects') as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }
            $out[$pt->name] = (string) $pt->labels->name;
        }
        return self::distinct($out);
    }

    /** Public taxonomies, slug => label. @return array<string,string> */
    public static function taxonomies(): array
    {
        $out = [];
        foreach (get_taxonomies(['public' => true], 'objects') as $tx) {
            if ($tx->name === 'post_format') {
                continue;
            }
            $out[$tx->name] = (string) $tx->labels->name;
        }
        return self::distinct($out);
    }

    /**
     * Add the slug to labels that appear more than once, so two "Events" can be told apart.
     *
     * @param array<string,string> $labels
     * @return array<string,string>
     */
    private static function distinct(array $labels): array
    {
        $seen = array_count_values($labels);
        foreach ($labels as $slug => $label) {
            if ($seen[$label] > 1) {
                $labels[$slug] = $label . ' (' . $slug . ')';
            }
        }
        return $labels;
    }

    /** Saves one tab of the panel. */
    public static function handle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot change these settings.', 'sepo-bridge'), '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash((string) $_POST['section'])) : '';
        $in = isset($_POST['site']) && is_array($_POST['site']) ? map_deep(wp_unslash($_POST['site']), 'sanitize_text_field') : [];
        $flag = static fn(string $k): int => !empty($in[$k]) ? 1 : 0;
        $list = static function (string $k, array $allowed) use ($in): array {
            $v = isset($in[$k]) && is_array($in[$k]) ? array_map('sanitize_key', $in[$k]) : [];
            return array_values(array_intersect(array_keys($allowed), $v));
        };
        $tab = $section;

        switch ($section) {
            case 'seo':
                $types = self::postTypes();
                $tax = self::taxonomies();
                self::saveSection('seo', [
                    'enabled'        => $flag('enabled'),
                    'separator'      => mb_substr((string) ($in['separator'] ?? '–'), 0, 3) ?: '–',
                    'home_title'     => mb_substr((string) ($in['home_title'] ?? ''), 0, 120),
                    'home_desc'      => mb_substr((string) ($in['home_desc'] ?? ''), 0, 320),
                    'default_image'  => absint($in['default_image'] ?? 0),
                    'og'             => $flag('og'),
                    'schema'         => $flag('schema'),
                    'noindex_search' => $flag('noindex_search'),
                    'noindex_paged'  => $flag('noindex_paged'),
                    'google'         => preg_replace('~[^A-Za-z0-9_-]~', '', (string) ($in['google'] ?? '')),
                    'bing'           => preg_replace('~[^A-Za-z0-9_-]~', '', (string) ($in['bing'] ?? '')),
                    'sitemap'        => $flag('sitemap'),
                    'sitemap_types'  => $list('sitemap_types', $types),
                    'sitemap_tax'    => $list('sitemap_tax', $tax),
                    'sitemap_users'  => $flag('sitemap_users'),
                ]);
                break;
            case 'share':
                $placement = (string) ($in['placement'] ?? 'after');
                $style = (string) ($in['style'] ?? 'filled');
                self::saveSection('share', [
                    'enabled'    => $flag('enabled'),
                    'networks'   => $list('networks', array_flip(self::NETWORKS)),
                    'post_types' => $list('post_types', self::postTypes()),
                    'placement'  => in_array($placement, ['before', 'after', 'both', 'none'], true) ? $placement : 'after',
                    'style'      => in_array($style, ['filled', 'outline'], true) ? $style : 'filled',
                    'label'      => mb_substr((string) ($in['label'] ?? ''), 0, 60),
                    'per_post'   => $flag('per_post'),
                ]);
                break;
            case 'toc':
                $position = (string) ($in['position'] ?? 'right');
                self::saveSection('toc', [
                    'enabled'    => $flag('enabled'),
                    'post_types' => $list('post_types', self::postTypes()),
                    'position'   => in_array($position, ['left', 'right', 'top'], true) ? $position : 'right',
                    'min'        => max(2, min(10, absint($in['min'] ?? 3))),
                    'depth'      => max(2, min(4, absint($in['depth'] ?? 3))),
                    'title'      => mb_substr((string) ($in['title'] ?? ''), 0, 60),
                    'open'       => $flag('open'),
                    'per_post'   => $flag('per_post'),
                ]);
                break;
            case 'activity':
                $roles = array_merge(['guest' => 'guest'], wp_roles()->get_names());
                self::saveSection('activity', [
                    'enabled'   => $flag('enabled'),
                    'views'     => $flag('views'),
                    'actions'   => $flag('actions'),
                    'roles'     => $list('roles', $roles),
                    'keep_days' => max(1, min(730, absint($in['keep_days'] ?? 90))),
                    'skip_admin_views' => $flag('skip_admin_views'),
                ]);
                Activity::install();
                Activity::schedule();
                break;
            default:
                $tab = 'seo';
        }
        $tab = ['share' => 'sharing', 'toc' => 'contents'][$tab] ?? $tab;
        wp_safe_redirect(add_query_arg(['page' => \SepoWB\Admin\AdminController::SLUG, 'tab' => $tab, 'wb_saved' => 1], admin_url('admin.php')));
        exit;
    }

    /** The hidden fields and nonce every tab form needs. */
    public static function formStart(string $section): string
    {
        return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wb-site-form" data-wb-busy>'
            . '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">'
            . '<input type="hidden" name="section" value="' . esc_attr($section) . '">'
            . wp_nonce_field(self::ACTION, '_wpnonce', true, false);
    }

    /**
     * A row of checkboxes.
     *
     * @param array<string,string> $options value => label
     * @param list<string>         $selected
     */
    public static function checks(string $name, array $options, array $selected): string
    {
        $out = '<div class="wb-checks">';
        foreach ($options as $value => $label) {
            $out .= '<label class="wb-field--check"><input type="checkbox" name="site[' . esc_attr($name) . '][]" value="' . esc_attr($value) . '"' . checked(in_array($value, $selected, true), true, false) . '> ' . esc_html($label) . '</label>';
        }
        return $out . '</div>';
    }

    /** A switch bound to a site setting. */
    public static function toggle(string $name, bool $on, string $label, string $help = ''): string
    {
        return '<div class="wb-site-toggle"><label class="wb-switch"><input type="checkbox" name="site[' . esc_attr($name) . ']" value="1"' . checked($on, true, false) . '><span class="wb-slider"></span></label>'
            . '<div><strong>' . esc_html($label) . '</strong>' . ($help !== '' ? '<p class="wb-help">' . esc_html($help) . '</p>' : '') . '</div></div>';
    }

    public static function saveButton(): string
    {
        return '<p class="wb-site-actions"><button type="submit" class="wb-btn wb-btn--primary" data-busy-label="' . esc_attr__('Saving…', 'sepo-bridge') . '">' . esc_html__('Save changes', 'sepo-bridge') . '</button></p></form>';
    }
}
