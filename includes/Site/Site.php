<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/** Search, sharing, contents boxes, activity and roles. */
final class Site
{
    public static function hook(): void
    {
        Seo::hook();
        Share::hook();
        Toc::hook();
        Activity::hook();
        if (is_admin()) {
            SiteSettings::hook();
            Roles::hook();
            PostBox::hook();
            ActivityPage::hook();
        }
    }
}
