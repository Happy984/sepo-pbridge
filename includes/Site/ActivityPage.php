<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/** WP Bridge → Activity: page views, account activity and the master switch. */
final class ActivityPage
{
    public const SLUG = 'sepo-wb-activity';
    private static string $hook = '';

    public static function hook(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_sepo_wb_activity_switch', [self::class, 'toggle']);
        add_action('admin_post_sepo_wb_activity_clear', [self::class, 'clearAll']);
    }

    public static function menu(): void
    {
        $title = __('Activity', 'sepo-bridge');
        if (current_user_can('manage_options')) {
            self::$hook = (string) add_submenu_page(\SepoWB\Admin\AdminController::SLUG, $title, $title, Activity::CAP, self::SLUG, [self::class, 'render']);
        } else {
            self::$hook = (string) add_dashboard_page($title, $title, Activity::CAP, self::SLUG, [self::class, 'render']);
        }
    }

    public static function assets(string $hook): void
    {
        if ($hook !== self::$hook) {
            return;
        }
        AdminAssets::enqueue();
    }

    public static function url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url(current_user_can('manage_options') ? 'admin.php' : 'index.php'));
    }

    public static function toggle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot change this.', 'sepo-bridge'), '', ['response' => 403]);
        }
        check_admin_referer('sepo_wb_activity_switch');
        $a = SiteSettings::section('activity');
        $a['enabled'] = !empty($_POST['enabled']) ? 1 : 0;
        SiteSettings::saveSection('activity', $a);
        Activity::install();
        Activity::schedule();
        wp_safe_redirect(self::url(['wb_switched' => $a['enabled'] ? 'on' : 'off']));
        exit;
    }

    public static function clearAll(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot change this.', 'sepo-bridge'), '', ['response' => 403]);
        }
        check_admin_referer('sepo_wb_activity_clear');
        Activity::clear();
        wp_safe_redirect(self::url(['wb_cleared' => 1]));
        exit;
    }

    /** @return array<string,string> */
    private static function roleNames(): array
    {
        return array_merge(['guest' => __('Visitors (not signed in)', 'sepo-bridge')], array_map('translate_user_role', wp_roles()->get_names()));
    }

    public static function render(): void
    {
        if (!current_user_can(Activity::CAP)) {
            wp_die(esc_html__('You cannot see the activity log.', 'sepo-bridge'), '', ['response' => 403]);
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters
        $view = isset($_GET['view']) && sanitize_key(wp_unslash((string) $_GET['view'])) === 'actions' ? 'actions' : 'overview';
        $days = isset($_GET['days']) ? absint($_GET['days']) : 30;
        $days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;
        $role = isset($_GET['role']) ? sanitize_key(wp_unslash((string) $_GET['role'])) : '';
        $action = isset($_GET['act']) ? sanitize_key(wp_unslash((string) $_GET['act'])) : '';
        $from = isset($_GET['from']) ? sanitize_text_field(wp_unslash((string) $_GET['from'])) : '';
        $to = isset($_GET['to']) ? sanitize_text_field(wp_unslash((string) $_GET['to'])) : '';
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $notice = isset($_GET['wb_switched']) ? sanitize_key(wp_unslash((string) $_GET['wb_switched'])) : (isset($_GET['wb_cleared']) ? 'cleared' : '');
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $from = preg_match('~^\d{4}-\d{2}-\d{2}$~', $from) ? $from : '';
        $to = preg_match('~^\d{4}-\d{2}-\d{2}$~', $to) ? $to : '';
        $roles = self::roleNames();
        $role = isset($roles[$role]) ? $role : '';
        $s = SiteSettings::section('activity');
        $on = !empty($s['enabled']);
        $admin = current_user_can('manage_options');
        Activity::install();

        echo '<div class="wrap wb-site-admin"><div class="wb-app">';
        echo '<header class="wb-head"><div class="wb-kicker">' . esc_html__('Sepo Bridge', 'sepo-bridge') . '</div><h1 class="wb-title">' . esc_html__('Activity', 'sepo-bridge') . '</h1>'
            . '<p class="wb-sub">' . esc_html__('Page views and what people did on the site, by role. Visitors are counted without cookies, and no addresses are stored.', 'sepo-bridge') . '</p></header>';

        if ($notice === 'on') {
            echo '<div class="wb-note wb-note--ok" role="status">' . esc_html__('Recording is on.', 'sepo-bridge') . '</div>';
        } elseif ($notice === 'off') {
            echo '<div class="wb-note" role="status">' . esc_html__('Recording is off. What was already recorded stays until it is cleared or ages out.', 'sepo-bridge') . '</div>';
        } elseif ($notice === 'cleared') {
            echo '<div class="wb-note wb-note--ok" role="status">' . esc_html__('The activity log and page views were cleared.', 'sepo-bridge') . '</div>';
        }

        // The master switch.
        echo '<div class="wb-site-master">';
        if ($admin) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wb-site-toggle" data-wb-autosubmit>'
                . '<input type="hidden" name="action" value="sepo_wb_activity_switch">' . wp_nonce_field('sepo_wb_activity_switch', '_wpnonce', true, false) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field markup from WordPress.
                . '<label class="wb-switch wb-switch--lg"><input type="checkbox" name="enabled" value="1"' . checked($on, true, false) . ' aria-describedby="wb-act-state"><span class="wb-slider"></span></label>'
                . '<div id="wb-act-state"><strong>' . esc_html($on ? __('Recording is on', 'sepo-bridge') : __('Recording is off', 'sepo-bridge')) . '</strong>'
                . '<p class="wb-help">' . esc_html__('Turns page views and the activity log on or off for the whole site. Which roles are recorded is set on the Activity and Roles tabs.', 'sepo-bridge') . '</p></div>'
                . '<noscript><button class="wb-btn">' . esc_html__('Save', 'sepo-bridge') . '</button></noscript></form>';
        } else {
            echo '<p><strong>' . esc_html($on ? __('Recording is on', 'sepo-bridge') : __('Recording is off', 'sepo-bridge')) . '</strong></p>';
        }
        echo '</div>';

        echo '<nav class="wb-tabs" aria-label="' . esc_attr__('Activity views', 'sepo-bridge') . '">'
            . '<a class="wb-tab' . ($view === 'overview' ? ' is-on' : '') . '" href="' . esc_url(self::url(['days' => $days, 'role' => $role])) . '"' . ($view === 'overview' ? ' aria-current="page"' : '') . '>' . esc_html__('Page views', 'sepo-bridge') . '</a>'
            . '<a class="wb-tab' . ($view === 'actions' ? ' is-on' : '') . '" href="' . esc_url(self::url(['view' => 'actions', 'role' => $role])) . '"' . ($view === 'actions' ? ' aria-current="page"' : '') . '>' . esc_html__('Activity log', 'sepo-bridge') . '</a></nav>';

        if ($view === 'overview') {
            self::overview($days, $role, $roles);
        } else {
            self::actions($role, $action, $from, $to, $paged, $roles);
        }

        if ($admin) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wb-site-danger">'
                . '<input type="hidden" name="action" value="sepo_wb_activity_clear">' . wp_nonce_field('sepo_wb_activity_clear', '_wpnonce', true, false) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field markup from WordPress.
                . '<button type="submit" class="wb-btn wb-btn--danger" data-wb-confirm="' . esc_attr__('Delete every recorded page view and activity line? This cannot be undone.', 'sepo-bridge') . '" data-wb-confirm-ok="' . esc_attr__('Clear everything', 'sepo-bridge') . '" data-busy-label="' . esc_attr__('Clearing…', 'sepo-bridge') . '">'
                . esc_html__('Clear all activity', 'sepo-bridge') . '</button> <span class="wb-help">'
                /* translators: %d: number of days */
                . esc_html(sprintf(__('Older lines are removed on their own after %d days.', 'sepo-bridge'), (int) $s['keep_days'])) . '</span></form>';
        }
        echo '</div></div>';
    }

    /** @param array<string,string> $roles */
    private static function overview(int $days, string $role, array $roles): void
    {
        $sum = Activity::summary($days, $role);
        echo '<form method="get" class="wb-toolbar"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">'
            . '<label>' . esc_html__('Period', 'sepo-bridge') . ' <select name="days">';
        foreach ([7 => __('Last 7 days', 'sepo-bridge'), 30 => __('Last 30 days', 'sepo-bridge'), 90 => __('Last 90 days', 'sepo-bridge'), 365 => __('Last year', 'sepo-bridge')] as $d => $l) {
            echo '<option value="' . esc_attr((string) $d) . '"' . selected($days, $d, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select></label> <label>' . esc_html__('Role', 'sepo-bridge') . ' <select name="role"><option value="">' . esc_html__('Everyone', 'sepo-bridge') . '</option>';
        foreach ($roles as $k => $l) {
            echo '<option value="' . esc_attr($k) . '"' . selected($role, $k, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select></label> <button class="wb-btn">' . esc_html__('Show', 'sepo-bridge') . '</button></form>';

        echo '<div class="wb-site-stats">';
        foreach ([[__('Page views', 'sepo-bridge'), $sum['views']], [__('Visitors', 'sepo-bridge'), $sum['visitors']], [__('Signed-in people', 'sepo-bridge'), $sum['people']]] as [$label, $n]) {
            echo '<div class="wb-site-stat"><span class="wb-site-stat__n">' . esc_html(number_format_i18n((int) $n)) . '</span><span class="wb-site-stat__l">' . esc_html($label) . '</span></div>';
        }
        echo '</div>';

        // Views per day, as bars.
        $max = max(1, max(array_column($sum['days'], 'views')));
        $w = 720;
        $h = 160;
        $n = count($sum['days']);
        $bw = $w / $n;
        $bars = '<rect class="wb-site-chart__base" x="0" y="' . esc_attr((string) ($h - 1)) . '" width="' . esc_attr((string) $w) . '" height="1"></rect>';
        foreach ($sum['days'] as $i => $d) {
            $bh = (int) round(($d['views'] / $max) * ($h - 20));
            $x = round($i * $bw + 1, 1);
            $label = wp_date(get_option('date_format'), (int) strtotime($d['day'] . ' 12:00 UTC')) . ': ' . number_format_i18n($d['views']);
            $bars .= '<rect x="' . esc_attr((string) $x) . '" y="' . esc_attr((string) ($h - $bh)) . '" width="' . esc_attr((string) max(1, round($bw - 2, 1))) . '" height="' . esc_attr((string) max(0, $bh)) . '" rx="2"><title>' . esc_html($label) . '</title></rect>';
        }
        /* translators: 1: number of page views, 2: number of days */
        $aria = sprintf(__('%1$s page views over %2$d days', 'sepo-bridge'), number_format_i18n($sum['views']), $days);
        echo '<figure class="wb-site-chart"><figcaption>' . esc_html__('Views per day', 'sepo-bridge') . '</figcaption>'
            . '<svg viewBox="0 0 ' . esc_attr((string) $w) . ' ' . esc_attr((string) $h) . '" preserveAspectRatio="none" role="img" aria-label="' . esc_attr($aria) . '">' . $bars . '</svg>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bars are built from escaped parts above.
            . '<div class="wb-site-chart__axis"><span>' . esc_html(wp_date('j M', (int) strtotime($sum['days'][0]['day'] . ' 12:00 UTC'))) . '</span><span>' . esc_html(wp_date('j M', (int) strtotime(end($sum['days'])['day'] . ' 12:00 UTC'))) . '</span></div></figure>';

        $byRole = Activity::actionsByRole($days);
        echo '<div class="wb-site-cols">';
        self::table(__('Most viewed', 'sepo-bridge'), [__('Page', 'sepo-bridge'), __('Views', 'sepo-bridge'), __('Visitors', 'sepo-bridge')], array_map(static function (array $r): array {
            $pid = (int) $r['post_id'];
            $name = $pid > 0 ? (string) get_the_title($pid) : '';
            $link = '<a href="' . esc_url(home_url((string) $r['path'])) . '" target="_blank" rel="noopener">' . esc_html($name !== '' ? $name : (string) $r['path']) . '</a>' . ($name !== '' ? '<br><code>' . esc_html((string) $r['path']) . '</code>' : '');
            return [$link, number_format_i18n((int) $r['views']), number_format_i18n((int) $r['visitors'])];
        }, $sum['pages']), true);
        self::table(__('By role', 'sepo-bridge'), [__('Role', 'sepo-bridge'), __('Views', 'sepo-bridge'), __('Visitors', 'sepo-bridge'), __('Activity lines', 'sepo-bridge')], array_map(static function (array $r) use ($roles, $byRole, $days): array {
            $k = (string) $r['role'];
            return ['<a href="' . esc_url(self::url(['days' => $days, 'role' => $k])) . '">' . esc_html($roles[$k] ?? $k) . '</a>', number_format_i18n((int) $r['views']), number_format_i18n((int) $r['visitors']), number_format_i18n($byRole[$k] ?? 0)];
        }, $sum['roles']), true);
        echo '</div><div class="wb-site-cols">';
        $devices = ['phone' => __('Phone', 'sepo-bridge'), 'tablet' => __('Tablet', 'sepo-bridge'), 'desktop' => __('Computer', 'sepo-bridge')];
        self::table(__('Devices', 'sepo-bridge'), [__('Device', 'sepo-bridge'), __('Views', 'sepo-bridge')], array_map(static fn(array $r): array => [esc_html($devices[(string) $r['device']] ?? (string) $r['device']), number_format_i18n((int) $r['views'])], $sum['devices']), true);
        self::table(__('Coming from', 'sepo-bridge'), [__('Site', 'sepo-bridge'), __('Views', 'sepo-bridge')], array_map(static fn(array $r): array => [esc_html((string) $r['referrer']), number_format_i18n((int) $r['views'])], $sum['referrers']), true);
        echo '</div>';
    }

    /** @param array<string,string> $roles */
    private static function actions(string $role, string $action, string $from, string $to, int $paged, array $roles): void
    {
        $labels = Activity::actionLabels();
        $action = isset($labels[$action]) ? $action : '';
        echo '<form method="get" class="wb-toolbar"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '"><input type="hidden" name="view" value="actions">'
            . '<label>' . esc_html__('Role', 'sepo-bridge') . ' <select name="role"><option value="">' . esc_html__('Everyone', 'sepo-bridge') . '</option>';
        foreach ($roles as $k => $l) {
            echo '<option value="' . esc_attr($k) . '"' . selected($role, $k, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select></label> <label>' . esc_html__('What', 'sepo-bridge') . ' <select name="act"><option value="">' . esc_html__('Everything', 'sepo-bridge') . '</option>';
        foreach ($labels as $k => $l) {
            echo '<option value="' . esc_attr($k) . '"' . selected($action, $k, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select></label> <label>' . esc_html__('From', 'sepo-bridge') . ' <input type="date" name="from" value="' . esc_attr($from) . '"></label>'
            . ' <label>' . esc_html__('To', 'sepo-bridge') . ' <input type="date" name="to" value="' . esc_attr($to) . '"></label>'
            . ' <button class="wb-btn">' . esc_html__('Filter', 'sepo-bridge') . '</button></form>';

        $per = 50;
        $r = Activity::entries(['role' => $role, 'action' => $action, 'from' => $from, 'to' => $to], $paged, $per);
        $rows = [];
        foreach ($r['rows'] as $e) {
            $uid = (int) $e['user_id'];
            $u = $uid > 0 ? get_userdata($uid) : false;
            $who = $u ? '<a href="' . esc_url((string) get_edit_user_link($uid)) . '">' . esc_html($u->display_name) . '</a>' : esc_html__('Visitor', 'sepo-bridge');
            $item = (string) $e['title'];
            $oid = (int) $e['object_id'];
            if ($oid > 0 && !in_array($e['object_type'], ['user', 'plugin', 'theme', 'comment'], true) && get_post($oid)) {
                $item = '<a href="' . esc_url((string) get_edit_post_link($oid)) . '">' . esc_html($item !== '' ? $item : '#' . $oid) . '</a>';
            } else {
                $item = esc_html($item);
            }
            $rows[] = [
                esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) strtotime($e['created'] . ' UTC'))),
                $who,
                esc_html($roles[(string) $e['role']] ?? (string) $e['role']),
                esc_html($labels[(string) $e['action']] ?? (string) $e['action']),
                $item,
                esc_html((string) $e['details']),
            ];
        }
        self::table('', [__('When', 'sepo-bridge'), __('Who', 'sepo-bridge'), __('Role', 'sepo-bridge'), __('What', 'sepo-bridge'), __('Item', 'sepo-bridge'), __('Details', 'sepo-bridge')], $rows, false);
        $pages = (int) ceil($r['total'] / $per);
        if ($pages > 1) {
            $links = paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $paged, 'total' => $pages, 'type' => 'plain']);
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post((string) $links) . '</div></div>';
        }
    }

    /**
     * @param list<string>       $head
     * @param list<list<string>> $rows cells already escaped
     */
    private static function table(string $title, array $head, array $rows, bool $card): void
    {
        echo $card ? '<section class="wb-site-card">' : '';
        if ($title !== '') {
            echo '<h2 class="wb-h2">' . esc_html($title) . '</h2>';
        }
        if ($rows === []) {
            echo '<p class="wb-empty">' . esc_html__('Nothing recorded yet.', 'sepo-bridge') . '</p>' . ($card ? '</section>' : '');
            return;
        }
        echo '<div class="wb-site-scroll"><table class="widefat striped wb-site-table"><thead><tr>';
        foreach ($head as $i => $h) {
            echo '<th scope="col"' . ($i > 0 && $card ? ' class="num"' : '') . '>' . esc_html($h) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $i => $cell) {
                echo '<td' . ($i > 0 && $card ? ' class="num"' : '') . '>' . wp_kses_post($cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>' . ($card ? '</section>' : '');
    }
}
