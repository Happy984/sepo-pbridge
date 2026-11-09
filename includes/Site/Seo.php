<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/**
 * Search appearance: titles, descriptions, canonical addresses, robots rules,
 * social previews, structured data and the XML sitemap. The sitemap is the one
 * WordPress builds at /wp-sitemap.xml, shaped by the choices on the SEO tab.
 */
final class Seo
{
    public const META_TITLE = '_sepo_wb_seo_title';
    public const META_DESC = '_sepo_wb_seo_desc';
    public const META_NOINDEX = '_sepo_wb_seo_noindex';
    public const META_CANONICAL = '_sepo_wb_seo_canonical';
    public const META_IMAGE = '_sepo_wb_seo_image';

    /** Another SEO plugin that already writes these tags, or ''. */
    public static function otherPlugin(): string
    {
        $known = [
            'WPSEO_VERSION'             => 'Yoast SEO',
            'RANK_MATH_VERSION'         => 'Rank Math',
            'AIOSEO_VERSION'            => 'All in One SEO',
            'SEOPRESS_VERSION'          => 'SEOPress',
            'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework',
            'SLIM_SEO_VER'              => 'Slim SEO',
        ];
        foreach ($known as $const => $name) {
            if (defined($const)) {
                return $name;
            }
        }
        return '';
    }

    public static function hook(): void
    {
        $s = SiteSettings::section('seo');
        self::sitemapHooks($s);
        if (empty($s['enabled']) || self::otherPlugin() !== '') {
            return;
        }
        add_filter('document_title_separator', static fn(string $sep): string => (string) $s['separator'] !== '' ? (string) $s['separator'] : $sep);
        add_filter('pre_get_document_title', [self::class, 'title'], 20);
        add_filter('wp_robots', [self::class, 'robots']);
        add_action('wp', static function (): void {
            if (is_singular() && self::canonicalFor((int) get_queried_object_id()) !== '') {
                remove_action('wp_head', 'rel_canonical');
            }
        });
        add_action('wp_head', [self::class, 'head'], 1);
    }

    /** @param array<string,mixed> $s */
    private static function sitemapHooks(array $s): void
    {
        add_filter('wp_sitemaps_enabled', static fn(bool $on): bool => $on && !empty($s['sitemap']));
        if (empty($s['sitemap'])) {
            return;
        }
        add_filter('wp_sitemaps_post_types', static function (array $types) use ($s): array {
            return array_intersect_key($types, array_flip((array) $s['sitemap_types']));
        });
        add_filter('wp_sitemaps_taxonomies', static function (array $tax) use ($s): array {
            return array_intersect_key($tax, array_flip((array) $s['sitemap_tax']));
        });
        add_filter('wp_sitemaps_add_provider', static function ($provider, string $name) use ($s) {
            return $name === 'users' && empty($s['sitemap_users']) ? false : $provider;
        }, 10, 2);
        // Posts marked "Hide from search engines" stay out of the sitemap.
        add_filter('wp_sitemaps_posts_query_args', static function (array $args): array {
            $args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the sitemap query is paged and cached by WordPress.
                'relation' => 'OR',
                ['key' => self::META_NOINDEX, 'compare' => 'NOT EXISTS'],
                ['key' => self::META_NOINDEX, 'value' => '1', 'compare' => '!='],
            ];
            return $args;
        });
        add_filter('wp_sitemaps_posts_entry', static function (array $entry, \WP_Post $post): array {
            $entry['lastmod'] = get_post_modified_time('c', true, $post);
            return $entry;
        }, 10, 2);
    }

    /** @return array<string,mixed> */
    private static function settings(): array
    {
        return SiteSettings::section('seo');
    }

    public static function title(string $title): string
    {
        if (is_front_page() && (string) self::settings()['home_title'] !== '') {
            return (string) self::settings()['home_title'];
        }
        if (is_singular()) {
            $custom = trim((string) get_post_meta((int) get_queried_object_id(), self::META_TITLE, true));
            if ($custom !== '') {
                return $custom;
            }
        }
        return $title;
    }

    /** @param array<string,bool|string> $robots @return array<string,bool|string> */
    public static function robots(array $robots): array
    {
        $s = self::settings();
        $hide = (is_search() && !empty($s['noindex_search']))
            || (is_paged() && !empty($s['noindex_paged']))
            || (is_singular() && get_post_meta((int) get_queried_object_id(), self::META_NOINDEX, true) === '1');
        if ($hide) {
            $robots['noindex'] = true;
            $robots['follow'] = true;
            unset($robots['max-image-preview']);
        }
        return $robots;
    }

    public static function canonicalFor(int $postId): string
    {
        return $postId > 0 ? esc_url_raw((string) get_post_meta($postId, self::META_CANONICAL, true)) : '';
    }

    /** The description for the current page, plain text. */
    public static function description(): string
    {
        $s = self::settings();
        if (is_front_page() && (string) $s['home_desc'] !== '') {
            return (string) $s['home_desc'];
        }
        if (is_singular()) {
            $id = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($id, self::META_DESC, true));
            if ($custom !== '') {
                return $custom;
            }
            $post = get_post($id);
            if ($post) {
                $text = $post->post_excerpt !== '' ? $post->post_excerpt : strip_shortcodes($post->post_content);
                return self::clip(wp_strip_all_tags($text));
            }
        }
        if (is_category() || is_tag() || is_tax()) {
            return self::clip(wp_strip_all_tags(term_description()));
        }
        if (is_front_page() || is_home()) {
            return self::clip((string) get_bloginfo('description'));
        }
        return '';
    }

    private static function clip(string $text, int $max = 160): string
    {
        $text = trim((string) preg_replace('~\s+~u', ' ', html_entity_decode($text, ENT_QUOTES, 'UTF-8')));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
    }

    /** The image for social previews, as [url, width, height] or []. @return array{0?:string,1?:int,2?:int} */
    private static function image(): array
    {
        $id = 0;
        if (is_singular()) {
            $post = (int) get_queried_object_id();
            $id = (int) get_post_meta($post, self::META_IMAGE, true) ?: (int) get_post_thumbnail_id($post);
        }
        $id = $id ?: (int) self::settings()['default_image'];
        $src = $id > 0 ? wp_get_attachment_image_src($id, 'large') : false;
        return is_array($src) ? [(string) $src[0], (int) $src[1], (int) $src[2]] : [];
    }

    private static function currentUrl(): string
    {
        if (is_singular()) {
            $id = (int) get_queried_object_id();
            return self::canonicalFor($id) ?: (string) get_permalink($id);
        }
        if (is_front_page()) {
            return home_url('/');
        }
        if (is_category() || is_tag() || is_tax()) {
            $link = get_term_link(get_queried_object());
            return is_string($link) ? $link : '';
        }
        if (is_post_type_archive()) {
            return (string) get_post_type_archive_link((string) get_query_var('post_type'));
        }
        return '';
    }

    public static function head(): void
    {
        $s = self::settings();
        $tags = [];
        if ((string) $s['google'] !== '') {
            $tags[] = ['name', 'google-site-verification', (string) $s['google']];
        }
        if ((string) $s['bing'] !== '') {
            $tags[] = ['name', 'msvalidate.01', (string) $s['bing']];
        }
        $desc = self::description();
        if ($desc !== '') {
            $tags[] = ['name', 'description', $desc];
        }
        $url = self::currentUrl();
        $title = wp_get_document_title();
        if (!empty($s['og'])) {
            $tags[] = ['property', 'og:site_name', (string) get_bloginfo('name')];
            $tags[] = ['property', 'og:locale', str_replace('-', '_', (string) get_bloginfo('language'))];
            $tags[] = ['property', 'og:type', is_singular('post') ? 'article' : 'website'];
            $tags[] = ['property', 'og:title', is_singular() ? (trim((string) get_post_meta((int) get_queried_object_id(), self::META_TITLE, true)) ?: single_post_title('', false)) : $title];
            if ($desc !== '') {
                $tags[] = ['property', 'og:description', $desc];
            }
            if ($url !== '') {
                $tags[] = ['property', 'og:url', $url];
            }
            $img = self::image();
            if ($img !== []) {
                $tags[] = ['property', 'og:image', $img[0]];
                $tags[] = ['property', 'og:image:width', (string) $img[1]];
                $tags[] = ['property', 'og:image:height', (string) $img[2]];
            }
            if (is_singular('post')) {
                $tags[] = ['property', 'article:published_time', (string) get_post_time('c', true)];
                $tags[] = ['property', 'article:modified_time', (string) get_post_modified_time('c', true)];
            }
            $tags[] = ['name', 'twitter:card', $img !== [] ? 'summary_large_image' : 'summary'];
        }
        echo "\n<!-- Sepo Bridge: search and social tags -->\n";
        foreach ($tags as [$attr, $key, $value]) {
            echo '<meta ' . esc_attr($attr) . '="' . esc_attr($key) . '" content="' . esc_attr($value) . '">' . "\n";
        }
        if (is_singular() && self::canonicalFor((int) get_queried_object_id()) !== '') {
            echo '<link rel="canonical" href="' . esc_url(self::canonicalFor((int) get_queried_object_id())) . '">' . "\n";
        }
        if (!empty($s['schema'])) {
            $data = self::schema($url, $desc);
            if ($data !== []) {
                wp_print_inline_script_tag((string) wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ['type' => 'application/ld+json']);
            }
        }
    }

    /** @return array<string,mixed> */
    private static function schema(string $url, string $desc): array
    {
        $site = [
            '@type' => 'WebSite',
            '@id'   => home_url('/#website'),
            'url'   => home_url('/'),
            'name'  => (string) get_bloginfo('name'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ];
        $graph = [$site];
        if (is_singular('post')) {
            $id = (int) get_queried_object_id();
            $img = self::image();
            $article = [
                '@type'         => 'Article',
                'headline'      => mb_substr(single_post_title('', false), 0, 110),
                'datePublished' => (string) get_post_time('c', true, $id),
                'dateModified'  => (string) get_post_modified_time('c', true, $id),
                'author'        => ['@type' => 'Person', 'name' => (string) get_the_author_meta('display_name', (int) get_post_field('post_author', $id))],
                'mainEntityOfPage' => $url,
                'isPartOf'      => ['@id' => home_url('/#website')],
            ];
            if ($desc !== '') {
                $article['description'] = $desc;
            }
            if ($img !== []) {
                $article['image'] = $img[0];
            }
            $graph[] = $article;
        }
        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
