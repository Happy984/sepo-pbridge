<?php
namespace SepoWB\Consent;

defined('ABSPATH') || exit;

/**
 * What the plugin knows about common cookies and the services that set them.
 *
 * A service has a category, the cookies it sets (a trailing * matches any
 * ending), and the script and embed addresses that identify it on a page.
 * Those addresses are how tracking scripts are held back until consent, and
 * how the scanner lists cookies a service sets on its own domain, which no
 * script on this site can read.
 *
 * Categories: necessary, preferences, analytics, marketing.
 */
final class CookieCatalog
{
    /**
     * @return array<string,array{name:string,category:string,provider:string,scripts:list<string>,embeds:list<string>,cookies:list<array{name:string,purpose:string,duration:string,domain?:string}>}>
     */
    public static function services(): array
    {
        $y = static fn(int $n): string => sprintf(/* translators: %d: years */ _n('%d year', '%d years', $n, 'sepo-bridge'), $n);
        $m = static fn(int $n): string => sprintf(/* translators: %d: months */ _n('%d month', '%d months', $n, 'sepo-bridge'), $n);
        $d = static fn(int $n): string => sprintf(/* translators: %d: days */ _n('%d day', '%d days', $n, 'sepo-bridge'), $n);
        $min = static fn(int $n): string => sprintf(/* translators: %d: minutes */ _n('%d minute', '%d minutes', $n, 'sepo-bridge'), $n);
        $session = __('Session', 'sepo-bridge');

        $services = [
            'consent' => [
                'name' => __('Cookie consent', 'sepo-bridge'), 'category' => 'necessary', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => [], 'embeds' => [],
                'cookies' => [['name' => Consent::COOKIE, 'purpose' => __('Remembers your cookie choices.', 'sepo-bridge'), 'duration' => $m(6)]],
            ],
            'wordpress' => [
                'name' => 'WordPress', 'category' => 'necessary', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => [], 'embeds' => [],
                'cookies' => [
                    ['name' => 'wordpress_logged_in_*', 'purpose' => __('Keeps you signed in.', 'sepo-bridge'), 'duration' => $session],
                    ['name' => 'wordpress_sec_*', 'purpose' => __('Secures your sign-in.', 'sepo-bridge'), 'duration' => $session],
                    ['name' => 'wordpress_*', 'purpose' => __('Secures your sign-in to the site’s admin area.', 'sepo-bridge'), 'duration' => $session],
                    ['name' => 'wordpress_test_cookie', 'purpose' => __('Checks that your browser accepts cookies when you sign in.', 'sepo-bridge'), 'duration' => $session],
                    ['name' => 'wp-settings-*', 'purpose' => __('Remembers how you set up the admin screens (signed-in users).', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => 'wp-settings-time-*', 'purpose' => __('Records when the admin screen settings were saved (signed-in users).', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => 'wp-postpass_*', 'purpose' => __('Lets you read a password-protected page after entering its password.', 'sepo-bridge'), 'duration' => $d(10)],
                    ['name' => 'wp_lang', 'purpose' => __('Remembers the language chosen on the sign-in page.', 'sepo-bridge'), 'duration' => $session],
                ],
            ],
            'comments' => [
                'name' => __('WordPress comments', 'sepo-bridge'), 'category' => 'preferences', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => [], 'embeds' => [],
                'cookies' => [
                    ['name' => 'comment_author_*', 'purpose' => __('Remembers your name for the next comment, when you ask it to.', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => 'comment_author_email_*', 'purpose' => __('Remembers your email for the next comment, when you ask it to.', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => 'comment_author_url_*', 'purpose' => __('Remembers your website for the next comment, when you ask it to.', 'sepo-bridge'), 'duration' => $y(1)],
                ],
            ],
            'woocommerce' => [
                'name' => 'WooCommerce', 'category' => 'necessary', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => [], 'embeds' => [],
                'cookies' => [
                    ['name' => 'woocommerce_cart_hash', 'purpose' => __('Tracks changes to your basket.', 'sepo-bridge'), 'duration' => $session],
                    ['name' => 'woocommerce_items_in_cart', 'purpose' => __('Notes that your basket has items in it.', 'sepo-bridge'), 'duration' => $session],
                    ['name' => 'wp_woocommerce_session_*', 'purpose' => __('Keeps your basket and checkout details between pages.', 'sepo-bridge'), 'duration' => $d(2)],
                    ['name' => 'woocommerce_recently_viewed', 'purpose' => __('Shows products you looked at recently.', 'sepo-bridge'), 'duration' => $session],
                    ['name' => 'store_notice*', 'purpose' => __('Remembers that you closed the store notice.', 'sepo-bridge'), 'duration' => $session],
                ],
            ],
            'woo_attribution' => [
                'name' => __('WooCommerce order attribution', 'sepo-bridge'), 'category' => 'analytics', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => ['sourcebuster', 'order-attribution'], 'embeds' => [],
                'cookies' => [['name' => 'sbjs_*', 'purpose' => __('Records how you arrived at the shop (search, link or advert) to credit orders to their source.', 'sepo-bridge'), 'duration' => $session]],
            ],
            'php' => [
                'name' => 'PHP', 'category' => 'necessary', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => [], 'embeds' => [],
                'cookies' => [['name' => 'PHPSESSID', 'purpose' => __('Keeps your session between pages.', 'sepo-bridge'), 'duration' => $session]],
            ],
            'cloudflare' => [
                'name' => 'Cloudflare', 'category' => 'necessary', 'provider' => 'Cloudflare',
                'scripts' => [], 'embeds' => [],
                'cookies' => [
                    ['name' => '__cf_bm', 'purpose' => __('Tells people from automated traffic to protect the site.', 'sepo-bridge'), 'duration' => $min(30)],
                    ['name' => 'cf_clearance', 'purpose' => __('Remembers that you passed a security check.', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => '_cfuvid', 'purpose' => __('Applies fair rate limits between visitors.', 'sepo-bridge'), 'duration' => $session],
                ],
            ],
            'stripe' => [
                'name' => 'Stripe', 'category' => 'necessary', 'provider' => 'Stripe',
                'scripts' => [], 'embeds' => [],
                'cookies' => [
                    ['name' => '__stripe_mid', 'purpose' => __('Prevents fraud when you pay by card.', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => '__stripe_sid', 'purpose' => __('Prevents fraud when you pay by card.', 'sepo-bridge'), 'duration' => $min(30)],
                ],
            ],
            'recaptcha' => [
                'name' => 'Google reCAPTCHA', 'category' => 'necessary', 'provider' => 'Google',
                'scripts' => [], 'embeds' => [],
                'cookies' => [['name' => '_GRECAPTCHA', 'purpose' => __('Protects forms from spam and abuse.', 'sepo-bridge'), 'duration' => $m(6), 'domain' => 'google.com']],
            ],
            'polylang' => [
                'name' => 'Polylang', 'category' => 'preferences', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => [], 'embeds' => [],
                'cookies' => [['name' => 'pll_language', 'purpose' => __('Remembers your language.', 'sepo-bridge'), 'duration' => $y(1)]],
            ],
            'wpml' => [
                'name' => 'WPML', 'category' => 'preferences', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => [], 'embeds' => [],
                'cookies' => [
                    ['name' => 'wp-wpml_current_language', 'purpose' => __('Remembers your language.', 'sepo-bridge'), 'duration' => $d(1)],
                    ['name' => '_icl_current_language', 'purpose' => __('Remembers your language.', 'sepo-bridge'), 'duration' => $d(1)],
                ],
            ],
            'google_analytics' => [
                'name' => 'Google Analytics', 'category' => 'analytics', 'provider' => 'Google',
                'scripts' => ['googletagmanager.com/gtag/js', 'google-analytics.com/analytics.js', "gtag('config'", 'gtag("config"', "ga('create'", 'ga("create"'],
                'embeds' => [],
                'cookies' => [
                    ['name' => '_ga', 'purpose' => __('Tells visitors apart to count visits and page views.', 'sepo-bridge'), 'duration' => $y(2)],
                    ['name' => '_ga_*', 'purpose' => __('Keeps the state of your visit for Google Analytics.', 'sepo-bridge'), 'duration' => $y(2)],
                    ['name' => '_gid', 'purpose' => __('Tells visitors apart for the day.', 'sepo-bridge'), 'duration' => $d(1)],
                    ['name' => '_gat*', 'purpose' => __('Limits how often data is sent to Google Analytics.', 'sepo-bridge'), 'duration' => $min(1)],
                ],
            ],
            'google_analytics_classic' => [
                'name' => __('Google Analytics (classic)', 'sepo-bridge'), 'category' => 'analytics', 'provider' => 'Google',
                'scripts' => ['google-analytics.com/ga.js', '_gaq.push'], 'embeds' => [],
                'cookies' => [['name' => '__utm*', 'purpose' => __('Count visits and where visitors came from.', 'sepo-bridge'), 'duration' => $y(2)]],
            ],
            'google_tag_manager' => [
                'name' => 'Google Tag Manager', 'category' => 'analytics', 'provider' => 'Google',
                'scripts' => ['googletagmanager.com/gtm.js'], 'embeds' => ['googletagmanager.com/ns.html'],
                'cookies' => [],
            ],
            'google_ads' => [
                'name' => 'Google Ads', 'category' => 'marketing', 'provider' => 'Google',
                'scripts' => ['googleadservices.com', 'googlesyndication.com', 'doubleclick.net', "gtag('config', 'AW-", 'adsbygoogle'],
                'embeds' => ['doubleclick.net'],
                'cookies' => [
                    ['name' => '_gcl_*', 'purpose' => __('Measures which adverts lead to visits and purchases.', 'sepo-bridge'), 'duration' => $m(3)],
                    ['name' => 'IDE', 'purpose' => __('Shows and measures Google adverts across sites.', 'sepo-bridge'), 'duration' => $y(1), 'domain' => 'doubleclick.net'],
                    ['name' => 'test_cookie', 'purpose' => __('Checks whether your browser accepts cookies for advertising.', 'sepo-bridge'), 'duration' => $min(15), 'domain' => 'doubleclick.net'],
                ],
            ],
            'meta_pixel' => [
                'name' => 'Meta Pixel (Facebook)', 'category' => 'marketing', 'provider' => 'Meta',
                'scripts' => ['connect.facebook.net', 'fbq('], 'embeds' => ['facebook.com/plugins'],
                'cookies' => [
                    ['name' => '_fbp', 'purpose' => __('Measures and targets Facebook and Instagram adverts.', 'sepo-bridge'), 'duration' => $m(3)],
                    ['name' => '_fbc', 'purpose' => __('Records which Facebook advert you clicked to get here.', 'sepo-bridge'), 'duration' => $m(3)],
                    ['name' => 'fr', 'purpose' => __('Shows and measures Facebook adverts.', 'sepo-bridge'), 'duration' => $m(3), 'domain' => 'facebook.com'],
                ],
            ],
            'hotjar' => [
                'name' => 'Hotjar', 'category' => 'analytics', 'provider' => 'Hotjar',
                'scripts' => ['static.hotjar.com', '_hjSettings'], 'embeds' => [],
                'cookies' => [
                    ['name' => '_hjSessionUser_*', 'purpose' => __('Recognises returning visitors for usage heatmaps and recordings.', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => '_hjSession_*', 'purpose' => __('Keeps the current visit together.', 'sepo-bridge'), 'duration' => $min(30)],
                    ['name' => '_hj*', 'purpose' => __('Hotjar visit and sampling information.', 'sepo-bridge'), 'duration' => $session],
                ],
            ],
            'clarity' => [
                'name' => 'Microsoft Clarity', 'category' => 'analytics', 'provider' => 'Microsoft',
                'scripts' => ['clarity.ms'], 'embeds' => [],
                'cookies' => [
                    ['name' => '_clck', 'purpose' => __('Recognises returning visitors for usage heatmaps and recordings.', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => '_clsk', 'purpose' => __('Joins page views into one visit.', 'sepo-bridge'), 'duration' => $d(1)],
                    ['name' => 'CLID', 'purpose' => __('Recognises returning visitors across Clarity sites.', 'sepo-bridge'), 'duration' => $y(1), 'domain' => 'clarity.ms'],
                ],
            ],
            'bing_ads' => [
                'name' => 'Microsoft Advertising', 'category' => 'marketing', 'provider' => 'Microsoft',
                'scripts' => ['bat.bing.com'], 'embeds' => [],
                'cookies' => [
                    ['name' => '_uetsid', 'purpose' => __('Measures which Microsoft adverts lead to visits.', 'sepo-bridge'), 'duration' => $d(1)],
                    ['name' => '_uetvid', 'purpose' => __('Recognises returning visitors from Microsoft adverts.', 'sepo-bridge'), 'duration' => $m(13)],
                    ['name' => 'MUID', 'purpose' => __('Recognises your browser across Microsoft sites.', 'sepo-bridge'), 'duration' => $y(1), 'domain' => 'bing.com'],
                ],
            ],
            'linkedin' => [
                'name' => 'LinkedIn Insight', 'category' => 'marketing', 'provider' => 'LinkedIn',
                'scripts' => ['snap.licdn.com', '_linkedin_partner_id'], 'embeds' => [],
                'cookies' => [
                    ['name' => 'li_fat_id', 'purpose' => __('Measures which LinkedIn adverts lead to visits.', 'sepo-bridge'), 'duration' => $d(30)],
                    ['name' => 'bcookie', 'purpose' => __('Recognises your browser for LinkedIn.', 'sepo-bridge'), 'duration' => $y(1), 'domain' => 'linkedin.com'],
                    ['name' => 'lidc', 'purpose' => __('Routes requests to LinkedIn.', 'sepo-bridge'), 'duration' => $d(1), 'domain' => 'linkedin.com'],
                ],
            ],
            'tiktok' => [
                'name' => 'TikTok Pixel', 'category' => 'marketing', 'provider' => 'TikTok',
                'scripts' => ['analytics.tiktok.com', 'ttq.load'], 'embeds' => [],
                'cookies' => [['name' => '_ttp', 'purpose' => __('Measures and targets TikTok adverts.', 'sepo-bridge'), 'duration' => $m(13)]],
            ],
            'pinterest' => [
                'name' => 'Pinterest Tag', 'category' => 'marketing', 'provider' => 'Pinterest',
                'scripts' => ['s.pinimg.com/ct', 'pintrk('], 'embeds' => [],
                'cookies' => [
                    ['name' => '_pin_unauth', 'purpose' => __('Measures which Pinterest adverts lead to visits.', 'sepo-bridge'), 'duration' => $y(1)],
                    ['name' => '_epik', 'purpose' => __('Records which Pinterest advert you clicked to get here.', 'sepo-bridge'), 'duration' => $y(1)],
                ],
            ],
            'x_ads' => [
                'name' => 'X (Twitter) adverts', 'category' => 'marketing', 'provider' => 'X',
                'scripts' => ['static.ads-twitter.com'], 'embeds' => [],
                'cookies' => [['name' => 'personalization_id', 'purpose' => __('Measures and targets X adverts.', 'sepo-bridge'), 'duration' => $y(2), 'domain' => 'x.com']],
            ],
            'x_embeds' => [
                'name' => 'X (Twitter) posts', 'category' => 'marketing', 'provider' => 'X',
                'scripts' => ['platform.twitter.com/widgets.js'], 'embeds' => ['platform.twitter.com/embed'],
                'cookies' => [['name' => 'guest_id', 'purpose' => __('Recognises your browser when showing embedded posts.', 'sepo-bridge'), 'duration' => $y(2), 'domain' => 'x.com']],
            ],
            'hubspot' => [
                'name' => 'HubSpot', 'category' => 'marketing', 'provider' => 'HubSpot',
                'scripts' => ['js.hs-scripts.com', 'js.hs-analytics.net', 'js.hsforms.net'], 'embeds' => [],
                'cookies' => [
                    ['name' => '__hstc', 'purpose' => __('Tracks visits for HubSpot marketing.', 'sepo-bridge'), 'duration' => $m(6)],
                    ['name' => 'hubspotutk', 'purpose' => __('Recognises you when you fill in HubSpot forms.', 'sepo-bridge'), 'duration' => $m(6)],
                    ['name' => '__hssc', 'purpose' => __('Counts pages in your current visit.', 'sepo-bridge'), 'duration' => $min(30)],
                    ['name' => '__hssrc', 'purpose' => __('Notes when you restart your browser.', 'sepo-bridge'), 'duration' => $session],
                ],
            ],
            'matomo' => [
                'name' => 'Matomo', 'category' => 'analytics', 'provider' => __('This site', 'sepo-bridge'),
                'scripts' => ['matomo.js', 'piwik.js', '_paq.push'], 'embeds' => [],
                'cookies' => [
                    ['name' => '_pk_id.*', 'purpose' => __('Tells visitors apart to count visits.', 'sepo-bridge'), 'duration' => $m(13)],
                    ['name' => '_pk_ses.*', 'purpose' => __('Keeps the current visit together.', 'sepo-bridge'), 'duration' => $min(30)],
                ],
            ],
            'jetpack_stats' => [
                'name' => 'Jetpack Stats', 'category' => 'analytics', 'provider' => 'Automattic',
                'scripts' => ['stats.wp.com'], 'embeds' => [],
                'cookies' => [['name' => 'tk_*', 'purpose' => __('Counts visits for site statistics.', 'sepo-bridge'), 'duration' => $y(1)]],
            ],
            'youtube' => [
                'name' => 'YouTube', 'category' => 'marketing', 'provider' => 'Google',
                'scripts' => ['youtube.com/iframe_api'], 'embeds' => ['youtube.com/embed', 'youtube-nocookie.com/embed'],
                'cookies' => [
                    ['name' => 'YSC', 'purpose' => __('Counts views of embedded videos.', 'sepo-bridge'), 'duration' => $session, 'domain' => 'youtube.com'],
                    ['name' => 'VISITOR_INFO1_LIVE', 'purpose' => __('Estimates your connection speed and recommends videos.', 'sepo-bridge'), 'duration' => $m(6), 'domain' => 'youtube.com'],
                    ['name' => 'VISITOR_PRIVACY_METADATA', 'purpose' => __('Stores your privacy choices for YouTube.', 'sepo-bridge'), 'duration' => $m(6), 'domain' => 'youtube.com'],
                ],
            ],
            'vimeo' => [
                'name' => 'Vimeo', 'category' => 'analytics', 'provider' => 'Vimeo',
                'scripts' => ['player.vimeo.com/api'], 'embeds' => ['player.vimeo.com/video'],
                'cookies' => [['name' => 'vuid', 'purpose' => __('Counts views of embedded videos.', 'sepo-bridge'), 'duration' => $y(2), 'domain' => 'vimeo.com']],
            ],
            'google_maps' => [
                'name' => 'Google Maps', 'category' => 'marketing', 'provider' => 'Google',
                'scripts' => ['maps.googleapis.com/maps/api'], 'embeds' => ['google.com/maps/embed', 'maps.google.com'],
                'cookies' => [['name' => 'NID', 'purpose' => __('Remembers your Google preferences and shows relevant adverts.', 'sepo-bridge'), 'duration' => $m(6), 'domain' => 'google.com']],
            ],
            'tawk' => [
                'name' => 'Tawk.to chat', 'category' => 'preferences', 'provider' => 'Tawk.to',
                'scripts' => ['embed.tawk.to'], 'embeds' => [],
                'cookies' => [['name' => 'twk_*', 'purpose' => __('Keeps your live chat conversation.', 'sepo-bridge'), 'duration' => $m(6)]],
            ],
            'crisp' => [
                'name' => 'Crisp chat', 'category' => 'preferences', 'provider' => 'Crisp',
                'scripts' => ['client.crisp.chat'], 'embeds' => [],
                'cookies' => [['name' => 'crisp-client*', 'purpose' => __('Keeps your live chat conversation.', 'sepo-bridge'), 'duration' => $m(6)]],
            ],
        ];
        /** Add or change known services. */
        return (array) apply_filters('sepo_wb_cookie_services', $services);
    }

    /** Does a cookie name match a catalogue pattern ("_ga_*", "_pk_id.*")? */
    public static function matches(string $pattern, string $name): bool
    {
        if (!str_contains($pattern, '*')) {
            return $pattern === $name;
        }
        $re = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($re, $name);
    }

    /**
     * What is known about a cookie name: the most specific matching pattern
     * wins (an exact name before "wordpress_logged_in_*" before "wordpress_*").
     *
     * @return array{service:string,pattern:string,category:string,provider:string,purpose:string,duration:string,domain:string}|null
     */
    public static function lookup(string $name): ?array
    {
        $best = null;
        $bestLen = -1;
        foreach (self::services() as $id => $s) {
            foreach ($s['cookies'] as $c) {
                if (!self::matches($c['name'], $name)) {
                    continue;
                }
                $len = strlen(str_replace('*', '', $c['name'])) + (str_contains($c['name'], '*') ? 0 : 1000);
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $best = [
                        'service'  => (string) $id,
                        'pattern'  => $c['name'],
                        'category' => $s['category'],
                        'provider' => $s['provider'],
                        'purpose'  => $c['purpose'],
                        'duration' => $c['duration'],
                        'domain'   => $c['domain'] ?? '',
                    ];
                }
            }
        }
        return $best;
    }
}
