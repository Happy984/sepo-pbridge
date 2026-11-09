<?php
namespace SepoWB\Admin;

defined('ABSPATH') || exit;

final class View
{
    /** @return array<string,string> */
    public static function tabs(): array
    {
        $out = [];
        foreach (self::sections() as $slug => $s) {
            $out[$slug] = $s['label'];
        }
        return $out;
    }

    /**
     * The settings sections, in the order the side menu lists them.
     *
     * @return array<string,array{label:string,group:string,icon:string,desc:string}>
     */
    public static function sections(): array
    {
        $site = __('Site', 'sepo-bridge');
        $content = __('Content', 'sepo-bridge');
        $people = __('People', 'sepo-bridge');
        $reach = __('Visitors', 'sepo-bridge');
        return [
            'features'   => ['label' => __('Features', 'sepo-bridge'), 'group' => $site, 'icon' => 'toggle', 'desc' => __('Security and performance switches for the whole site. Each one takes effect as soon as it is turned on.', 'sepo-bridge')],
            'seo'        => ['label' => __('Search', 'sepo-bridge'), 'group' => $site, 'icon' => 'search', 'desc' => __('How the site appears in search results and when links are shared: titles, descriptions, sitemaps and social previews.', 'sepo-bridge')],
            'cpts'       => ['label' => __('Post types', 'sepo-bridge'), 'group' => $content, 'icon' => 'stack', 'desc' => __('Kinds of content beyond posts and pages, such as sermons, courses or staff profiles.', 'sepo-bridge')],
            'taxonomies' => ['label' => __('Taxonomies', 'sepo-bridge'), 'group' => $content, 'icon' => 'tag', 'desc' => __('Ways to group content, like categories and tags, for any post type.', 'sepo-bridge')],
            'metagroups' => ['label' => __('Meta fields', 'sepo-bridge'), 'group' => $content, 'icon' => 'fields', 'desc' => __('Extra fields shown in the editor, such as a speaker, a date or a download link.', 'sepo-bridge')],
            'contents'   => ['label' => __('Contents', 'sepo-bridge'), 'group' => $content, 'icon' => 'list', 'desc' => __('A box listing the headings of long posts and pages, so readers can jump to a section.', 'sepo-bridge')],
            'membership' => ['label' => __('Membership', 'sepo-bridge'), 'group' => $people, 'icon' => 'badge', 'desc' => __('Membership tiers and what each tier may see.', 'sepo-bridge')],
            'signin'     => ['label' => __('Sign-in', 'sepo-bridge'), 'group' => $people, 'icon' => 'key', 'desc' => __('The sign-in and registration pages, and the account page people see once signed in.', 'sepo-bridge')],
            'roles'      => ['label' => __('Roles', 'sepo-bridge'), 'group' => $people, 'icon' => 'users', 'desc' => __('Roles and what each one is allowed to do.', 'sepo-bridge')],
            'activity'   => ['label' => __('Activity', 'sepo-bridge'), 'group' => $people, 'icon' => 'pulse', 'desc' => __('A record of sign-ins and changes made in the dashboard.', 'sepo-bridge')],
            'cookies'    => ['label' => __('Cookies', 'sepo-bridge'), 'group' => $reach, 'icon' => 'cookie', 'desc' => __('The cookie banner, the choices visitors can make and the scripts that wait for consent.', 'sepo-bridge')],
            'sharing'    => ['label' => __('Sharing', 'sepo-bridge'), 'group' => $reach, 'icon' => 'share', 'desc' => __('Share buttons under posts and pages.', 'sepo-bridge')],
        ];
    }

    /** A small line icon for the side menu and section headers. */
    public static function icon(string $name, int $size = 18): string
    {
        $paths = [
            'toggle' => '<rect x="2.5" y="6.5" width="19" height="11" rx="5.5"/><circle cx="16" cy="12" r="3"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.3-4.3"/>',
            'stack'  => '<path d="M12 3.5l8.5 4.5-8.5 4.5L3.5 8z"/><path d="M3.5 12l8.5 4.5 8.5-4.5"/><path d="M3.5 16l8.5 4.5 8.5-4.5"/>',
            'tag'    => '<path d="M3.5 12.5V4h8.5l8.5 8.5-8.5 8.5z"/><circle cx="8" cy="8.5" r="1.4"/>',
            'fields' => '<rect x="3.5" y="4.5" width="17" height="5" rx="1.5"/><rect x="3.5" y="14.5" width="17" height="5" rx="1.5"/><path d="M6.5 7h5M6.5 17h3"/>',
            'list'   => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4.5 6h.1M4.5 12h.1M4.5 18h.1"/>',
            'badge'  => '<circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5L7 21l5-2.5 5 2.5-1.5-7.5"/>',
            'key'    => '<circle cx="8" cy="15" r="4"/><path d="M11 12.2L19.5 3.7M16 7.2l2.5 2.5M14 9.2l1.8 1.8"/>',
            'users'  => '<circle cx="9" cy="8.5" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M15.5 5.2a3.5 3.5 0 0 1 0 6.6"/><path d="M18 14.3a6.5 6.5 0 0 1 3.5 5.7"/>',
            'pulse'  => '<path d="M3 12h4l2.5-6 5 12 2.5-6h4"/>',
            'cookie' => '<path d="M20.5 12.5A8.5 8.5 0 1 1 11.5 3.5a3 3 0 0 0 4 3.5 3 3 0 0 0 5 5.5z"/><path d="M8.5 9.5h.1M8 15h.1M13 14.5h.1M15.5 17.5h.1"/>',
            'share'  => '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.2 10.8l7.6-3.6M8.2 13.2l7.6 3.6"/>',
            'bridge' => '<path d="M2.5 17.5h19"/><path d="M4 17.5V12M20 17.5V12"/><path d="M2.5 12c4-5.5 15-5.5 19 0"/><path d="M8 17.5v-5.2M12 17.5V10.5M16 17.5v-5.2"/>',
        ];
        $d = $paths[$name] ?? '';
        return '<svg class="wb-ico" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
    }

    public static function page(): void
    {
        $sections = self::sections();
        $tabs = self::tabs();
        $active = 'features';
        if (isset($_GET['tab']) && is_string($_GET['tab']) && isset($tabs[$_GET['tab']])) { // phpcs:ignore WordPress.Security.NonceVerification
            $active = sanitize_key($_GET['tab']); // phpcs:ignore WordPress.Security.NonceVerification
        }
        require __DIR__ . '/views/panel.php';
    }
}
