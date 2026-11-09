<?php
namespace SepoWB\Integration;

use SepoWB\Schema\SchemaManager;

defined('ABSPATH') || exit;

/**
 * Shows meta-field values on the site and in the admin.
 *
 *  - [sepo_fields group="event_details" style="table"] prints a whole group.
 *  - Groups set to show automatically are added before or after the content
 *    of their post types' single pages.
 *  - Fields marked "Column in the posts list" get a column, sortable, on the
 *    admin list screen of each post type the group is assigned to.
 *
 * The Field group block and the Elementor widgets use the same output.
 */
final class FieldDisplay
{
    public const STYLES = ['table', 'grid', 'inline'];
    private const CORE = ['post_title', 'post_date', 'post_modified', 'post_author', 'permalink', 'post_excerpt'];

    /** @var array<int,bool> Posts that already had groups added to their content on this request. */
    private static array $added = [];
    /** @var array<int,array<string,bool>> Groups already printed for each post by a block, shortcode or widget. */
    private static array $printed = [];

    public static function hook(): void
    {
        add_action('init', static function (): void {
            add_shortcode('sepo_fields', [self::class, 'shortcode']);
        });
        add_action('wp_enqueue_scripts', [self::class, 'registerStyle']);
        add_action('enqueue_block_assets', [self::class, 'registerStyle']);
        add_filter('the_content', [self::class, 'autoDisplay'], 20);
        if (is_admin()) {
            add_action('admin_init', [self::class, 'adminColumns']);
            add_action('pre_get_posts', [self::class, 'sortByColumn']);
        }
    }

    public static function registerStyle(): void
    {
        $file = SEPO_WB_DIR . 'assets/css/fields.css';
        wp_register_style('sepo-wb-fields', SEPO_WB_URL . 'assets/css/fields.css', [], SEPO_WB_VERSION . (is_file($file) ? '.' . filemtime($file) : ''));
    }

    private static function useStyle(): void
    {
        if (!wp_style_is('sepo-wb-fields', 'registered')) {
            self::registerStyle();
        }
        wp_enqueue_style('sepo-wb-fields');
    }

    // ------------------------------------------------------------ values

    /** Every field definition by id, with the group it belongs to. @return array<string,array<string,mixed>> */
    public static function fieldDefs(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        foreach ((new SchemaManager())->allMetaGroups() as $gid => $g) {
            foreach ((array) ($g['fields'] ?? []) as $f) {
                $id = (string) ($f['id'] ?? '');
                if ($id !== '' && !isset($cache[$id])) {
                    $cache[$id] = $f + ['group' => (string) $gid];
                }
            }
        }
        return $cache;
    }

    /**
     * One value of a post, as safe HTML, or '' when empty or not allowed.
     * $field is a core value (post_title, post_date, post_modified,
     * post_author, permalink, post_excerpt) or a field id from a group.
     */
    public static function valueHtml(int $postId, string $field): string
    {
        switch ($field) {
            case 'post_title':
                return esc_html(get_the_title($postId));
            case 'post_date':
                return esc_html((string) get_the_date('', $postId));
            case 'post_modified':
                return esc_html((string) get_the_modified_date('', $postId));
            case 'post_author':
                return esc_html((string) get_the_author_meta('display_name', (int) get_post_field('post_author', $postId)));
            case 'permalink':
                $url = (string) get_permalink($postId);
                return '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>';
            case 'post_excerpt':
                return esc_html(get_the_excerpt($postId));
        }
        $def = self::fieldDefs()[$field] ?? null;
        // Only fields declared in a group, so a block or widget cannot print private data.
        $allowed = $def !== null && !is_protected_meta($field, 'post');
        if (!apply_filters('sepo_wb_field_allowed', $allowed, $field, $postId)) {
            return '';
        }
        $raw = (string) get_post_meta($postId, $field, true);
        if ($raw === '') {
            return '';
        }
        switch ((string) ($def['type'] ?? 'text')) {
            case 'checkbox':
                return $raw === '1' ? esc_html__('Yes', 'sepo-bridge') : esc_html__('No', 'sepo-bridge');
            case 'url':
                return '<a href="' . esc_url($raw) . '">' . esc_html(preg_replace('#^https?://(www\.)?#', '', rtrim($raw, '/')) ?? $raw) . '</a>';
            case 'email':
                return '<a href="mailto:' . esc_attr(antispambot($raw)) . '">' . esc_html(antispambot($raw)) . '</a>';
            case 'date':
                $ts = strtotime($raw);
                return esc_html($ts ? wp_date((string) get_option('date_format'), $ts) : $raw);
            case 'textarea':
                return nl2br(esc_html($raw));
            case 'select':
                $opts = (array) ($def['options'] ?? []);
                return esc_html((string) ($opts[$raw] ?? $raw));
            default:
                return esc_html($raw);
        }
    }

    /** The value as plain text, for Elementor dynamic tags and attributes. */
    public static function valueText(int $postId, string $field): string
    {
        $def = self::fieldDefs()[$field] ?? null;
        if ($def !== null && in_array($def['type'] ?? '', ['url', 'email'], true)) {
            if (!apply_filters('sepo_wb_field_allowed', !is_protected_meta($field, 'post'), $field, $postId)) {
                return '';
            }
            return (string) get_post_meta($postId, $field, true);
        }
        if ($field === 'permalink') {
            return (string) get_permalink($postId);
        }
        return trim(html_entity_decode(wp_strip_all_tags(str_replace('<br />', "\n", self::valueHtml($postId, $field))), ENT_QUOTES));
    }

    /** One field with an optional label, as the Post field block and widget print it. */
    public static function renderField(int $postId, string $field, string $label = '', string $class = ''): string
    {
        if ($postId <= 0 || $field === '' || !MembershipGate::passes($postId)) {
            return '';
        }
        $value = self::valueHtml($postId, $field);
        if ($value === '') {
            return '';
        }
        self::useStyle();
        $cls = 'sepo-field sepo-field--' . sanitize_html_class($field) . ($class !== '' ? ' ' . $class : '');
        return '<div class="' . esc_attr($cls) . '">'
            . ($label !== '' ? '<span class="sepo-field-label">' . esc_html($label) . '</span> ' : '')
            . '<span class="sepo-field-value">' . $value . '</span></div>';
    }

    // ------------------------------------------------------------ groups

    /** Groups that apply to a post type. @return array<string,array<string,mixed>> */
    public static function groupsFor(string $postType): array
    {
        $out = [];
        foreach ((new SchemaManager())->allMetaGroups() as $gid => $g) {
            if (in_array($postType, (array) ($g['assign_to'] ?? []), true)) {
                $out[(string) $gid] = $g;
            }
        }
        return $out;
    }

    /**
     * A whole group for one post.
     *
     * @param array{style?:string,title?:bool|null,empty?:bool,class?:string} $args
     *   style: table, grid or inline (the group's own setting when not given);
     *   title: show the group title (the group's own setting when null);
     *   empty: also list fields with no value.
     */
    public static function renderGroup(string $gid, int $postId, array $args = []): string
    {
        $groups = (new SchemaManager())->allMetaGroups();
        $g = $groups[$gid] ?? null;
        if ($g === null || $postId <= 0 || !MembershipGate::passes($postId)) {
            return '';
        }
        $style = in_array($args['style'] ?? '', self::STYLES, true) ? $args['style'] : (in_array($g['style'] ?? '', self::STYLES, true) ? $g['style'] : 'table');
        $title = $args['title'] ?? !empty($g['show_title']);
        $rows = '';
        foreach ((array) ($g['fields'] ?? []) as $f) {
            if (isset($f['show']) && !$f['show']) {
                continue;
            }
            $value = self::valueHtml($postId, (string) $f['id']);
            if ($value === '' && empty($args['empty'])) {
                continue;
            }
            $rows .= '<div class="sepo-fields__row sepo-fields__row--' . esc_attr(sanitize_html_class((string) $f['id'])) . '">'
                . '<dt class="sepo-fields__label">' . esc_html((string) $f['label']) . '</dt>'
                . '<dd class="sepo-fields__value">' . ($value !== '' ? $value : '<span class="sepo-fields__empty">—</span>') . '</dd></div>';
        }
        if ($rows === '') {
            return '';
        }
        self::$printed[$postId][$gid] = true;
        self::useStyle();
        $cls = 'sepo-fields sepo-fields--' . $style . ' sepo-fields--group-' . sanitize_html_class($gid) . (!empty($args['class']) ? ' ' . $args['class'] : '');
        $html = '<div class="' . esc_attr($cls) . '">';
        if ($title) {
            $html .= '<h3 class="sepo-fields__title">' . esc_html((string) ($g['title'] ?? '')) . '</h3>';
        }
        $html .= '<dl class="sepo-fields__list">' . $rows . '</dl></div>';
        /** Change a field group's output. */
        return (string) apply_filters('sepo_wb_fields_html', $html, $gid, $postId, $style);
    }

    /** [sepo_fields group="id" style="table|grid|inline" title="yes|no" empty="yes|no" post="123"] */
    public static function shortcode($atts): string
    {
        $a = shortcode_atts(['group' => '', 'style' => '', 'title' => '', 'empty' => 'no', 'post' => ''], (array) $atts, 'sepo_fields');
        $postId = $a['post'] !== '' ? absint($a['post']) : (int) get_the_ID();
        if ($postId <= 0) {
            return '';
        }
        $args = ['style' => (string) $a['style'], 'empty' => $a['empty'] === 'yes'];
        if ($a['title'] !== '') {
            $args['title'] = in_array(strtolower((string) $a['title']), ['yes', '1', 'true'], true);
        }
        $ids = $a['group'] !== '' ? array_map('sanitize_key', explode(',', (string) $a['group'])) : array_keys(self::groupsFor((string) get_post_type($postId)));
        $out = '';
        foreach ($ids as $gid) {
            $out .= self::renderGroup($gid, $postId, $args);
        }
        return $out;
    }

    /** Groups set to show automatically, added to the single page's content. */
    public static function autoDisplay(string $content): string
    {
        if (is_admin() || !is_singular() || !in_the_loop() || !is_main_query() || post_password_required()) {
            return $content;
        }
        $postId = (int) get_the_ID();
        if ($postId <= 0 || isset(self::$added[$postId])) {
            return $content;
        }
        $before = $after = '';
        foreach (self::groupsFor((string) get_post_type($postId)) as $gid => $g) {
            // A group already placed in the content (block, shortcode, Elementor widget) is not repeated.
            if (isset(self::$printed[$postId][$gid])) {
                continue;
            }
            $where = (string) ($g['display'] ?? 'none');
            if ($where === 'before') {
                $before .= self::renderGroup($gid, $postId);
            } elseif ($where === 'after') {
                $after .= self::renderGroup($gid, $postId);
            }
        }
        if ($before === '' && $after === '') {
            return $content;
        }
        self::$added[$postId] = true;
        return $before . $content . $after;
    }

    // ------------------------------------------------------------ admin list columns

    /** Columns for each post type, from fields marked "Column in the posts list". @return array<string,array<string,array<string,mixed>>> */
    public static function columns(): array
    {
        $out = [];
        foreach ((new SchemaManager())->allMetaGroups() as $g) {
            foreach ((array) ($g['fields'] ?? []) as $f) {
                if (empty($f['column'])) {
                    continue;
                }
                foreach ((array) ($g['assign_to'] ?? []) as $pt) {
                    $out[(string) $pt][(string) $f['id']] = $f;
                }
            }
        }
        return $out;
    }

    public static function adminColumns(): void
    {
        $all = self::columns();
        if ($all) {
            add_action('admin_head-edit.php', static function () use ($all): void {
                $sel = [];
                foreach ($all as $fields) {
                    foreach (array_keys($fields) as $id) {
                        $sel[] = '.wp-list-table .column-sepo_' . sanitize_html_class((string) $id);
                    }
                }
                echo '<style>' . implode(',', array_unique($sel)) . '{width:11%;overflow-wrap:normal;word-break:normal}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput -- class names only
            });
        }
        foreach ($all as $pt => $fields) {
            $hook = $pt . '_posts';
            add_filter('manage_' . $hook . '_columns', static function (array $cols) use ($fields): array {
                $new = [];
                $placed = false;
                foreach ($cols as $k => $v) {
                    // The field columns go before the date and comments columns.
                    if (!$placed && in_array($k, ['date', 'comments'], true)) {
                        foreach ($fields as $id => $f) {
                            $new['sepo_' . $id] = esc_html((string) $f['label']);
                        }
                        $placed = true;
                    }
                    $new[$k] = $v;
                }
                if (!$placed) {
                    foreach ($fields as $id => $f) {
                        $new['sepo_' . $id] = esc_html((string) $f['label']);
                    }
                }
                return $new;
            });
            add_action('manage_' . $hook . '_custom_column', static function (string $col, int $postId) use ($fields): void {
                if (strncmp($col, 'sepo_', 5) !== 0 || !isset($fields[substr($col, 5)])) {
                    return;
                }
                $id = substr($col, 5);
                $v = self::valueHtml($postId, $id);
                if (($fields[$id]['type'] ?? '') === 'date' && $v !== '') {
                    $ts = strtotime((string) get_post_meta($postId, $id, true));
                    $v = $ts ? esc_html(wp_date('M j, Y', $ts)) : $v;
                }
                if (($fields[$id]['type'] ?? '') === 'textarea') {
                    $v = esc_html(wp_trim_words(wp_strip_all_tags($v), 12));
                }
                echo $v !== '' ? $v : '<span aria-hidden="true">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by valueHtml
            }, 10, 2);
            add_filter('manage_edit-' . $pt . '_sortable_columns', static function (array $cols) use ($fields): array {
                foreach ($fields as $id => $f) {
                    if (($f['type'] ?? '') !== 'textarea') {
                        $cols['sepo_' . $id] = 'sepo_' . $id;
                    }
                }
                return $cols;
            });
        }
    }

    /** Sorting by a field column. Posts with no value stay in the list, at the end in either direction. */
    public static function sortByColumn(\WP_Query $q): void
    {
        if (!$q->is_main_query()) {
            return;
        }
        $orderby = $q->get('orderby');
        if (!is_string($orderby) || strncmp($orderby, 'sepo_', 5) !== 0) {
            return;
        }
        $id = substr($orderby, 5);
        $cols = self::columns()[(string) ($q->get('post_type') ?: 'post')] ?? [];
        if (!isset($cols[$id])) {
            return;
        }
        $type = (string) ($cols[$id]['type'] ?? 'text');
        $q->set('orderby', '');
        add_filter('posts_clauses', static function (array $c, \WP_Query $query) use ($q, $id, $type): array {
            if ($query !== $q) {
                return $c;
            }
            global $wpdb;
            $dir = strtoupper((string) $q->get('order')) === 'DESC' ? 'DESC' : 'ASC';
            $c['join'] .= $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} sepo_sort ON (sepo_sort.post_id = {$wpdb->posts}.ID AND sepo_sort.meta_key = %s)", $id);
            $value = $type === 'number' ? 'CAST(sepo_sort.meta_value AS DECIMAL(20,6))' : 'sepo_sort.meta_value';
            $c['orderby'] = "(sepo_sort.meta_value IS NULL OR sepo_sort.meta_value = '') ASC, {$value} {$dir}, {$wpdb->posts}.post_date DESC";
            $c['groupby'] = "{$wpdb->posts}.ID";
            return $c;
        }, 10, 2);
    }

    // ------------------------------------------------------------ choices for editors

    /** Every value a Post field block or widget can show. @return array<string,string> */
    public static function fieldChoices(): array
    {
        $out = [
            'post_title'    => __('Title', 'sepo-bridge'),
            'post_date'     => __('Published date', 'sepo-bridge'),
            'post_modified' => __('Updated date', 'sepo-bridge'),
            'post_author'   => __('Author', 'sepo-bridge'),
            'post_excerpt'  => __('Excerpt', 'sepo-bridge'),
            'permalink'     => __('Link', 'sepo-bridge'),
        ];
        $groups = (new SchemaManager())->allMetaGroups();
        foreach (self::fieldDefs() as $id => $f) {
            $out[$id] = sprintf('%s — %s', (string) ($groups[$f['group']]['title'] ?? $f['group']), (string) ($f['label'] ?? $id));
        }
        return $out;
    }

    /** @return array<string,string> */
    public static function groupChoices(): array
    {
        $out = [];
        foreach ((new SchemaManager())->allMetaGroups() as $gid => $g) {
            $out[(string) $gid] = (string) ($g['title'] ?? $gid);
        }
        return $out;
    }

    /** @return list<string> */
    public static function coreFields(): array
    {
        return self::CORE;
    }
}
