<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/**
 * Share buttons under (or above) posts, plus the [sepo_share] shortcode.
 * Every button is a plain link or a button: nothing is loaded from the
 * networks, so no cookies are set until a visitor clicks.
 */
final class Share
{
    public const META = '_sepo_wb_share';

    public static function hook(): void
    {
        add_shortcode('sepo_share', [self::class, 'shortcode']);
        $s = SiteSettings::section('share');
        if (!empty($s['enabled'])) {
            add_filter('the_content', [self::class, 'content'], 30);
        }
    }

    /** @return array<string,array{0:string,1:string}> network => [label, colour] */
    public static function networks(): array
    {
        return [
            'facebook' => [__('Facebook', 'sepo-bridge'), '#1559c4'],
            'x'        => [__('X', 'sepo-bridge'), '#000000'],
            'whatsapp' => [__('WhatsApp', 'sepo-bridge'), '#0f7a3f'],
            'linkedin' => [__('LinkedIn', 'sepo-bridge'), '#0a5bab'],
            'telegram' => [__('Telegram', 'sepo-bridge'), '#1a6f9e'],
            'email'    => [__('Email', 'sepo-bridge'), '#3c434a'],
            'copy'     => [__('Copy link', 'sepo-bridge'), '#3c434a'],
            'native'   => [__('More…', 'sepo-bridge'), '#3c434a'],
        ];
    }

    /** Whether the bar shows on this post, from the site setting and the post's own choice. */
    public static function showsOn(int $postId): bool
    {
        $s = SiteSettings::section('share');
        if (empty($s['enabled'])) {
            return false;
        }
        $choice = !empty($s['per_post']) ? (string) get_post_meta($postId, self::META, true) : '';
        if ($choice === 'hide') {
            return false;
        }
        if ($choice === 'show') {
            return true;
        }
        return in_array(get_post_type($postId), (array) $s['post_types'], true);
    }

    public static function content(string $html): string
    {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $html;
        }
        $id = (int) get_the_ID();
        if ($id !== (int) get_queried_object_id() || !self::showsOn($id)) {
            return $html;
        }
        $placement = (string) SiteSettings::section('share')['placement'];
        if ($placement === 'none') {
            return $html;
        }
        $bar = self::render($id);
        return ($placement === 'before' || $placement === 'both' ? $bar : '') . $html . ($placement === 'after' || $placement === 'both' ? $bar : '');
    }

    /** @param array<string,string>|string $atts */
    public static function shortcode($atts): string
    {
        $a = shortcode_atts(['networks' => '', 'label' => ''], is_array($atts) ? $atts : [], 'sepo_share');
        $nets = array_filter(array_map('sanitize_key', explode(',', (string) $a['networks'])));
        return self::render((int) get_the_ID(), $nets, (string) $a['label']);
    }

    /** @param list<string> $only */
    public static function render(int $postId, array $only = [], string $label = ''): string
    {
        $s = SiteSettings::section('share');
        $nets = $only !== [] ? array_values(array_intersect(SiteSettings::NETWORKS, $only)) : (array) $s['networks'];
        if ($nets === []) {
            return '';
        }
        Assets::front();
        $url = $postId > 0 ? (string) get_permalink($postId) : home_url(add_query_arg([]));
        $title = $postId > 0 ? html_entity_decode((string) get_the_title($postId), ENT_QUOTES, 'UTF-8') : (string) get_bloginfo('name');
        $u = rawurlencode($url);
        $t = rawurlencode($title);
        $links = [
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $u,
            'x'        => 'https://x.com/intent/post?url=' . $u . '&text=' . $t,
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode($title . ' ' . $url),
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u,
            'telegram' => 'https://t.me/share/url?url=' . $u . '&text=' . $t,
            'email'    => 'mailto:?subject=' . $t . '&body=' . rawurlencode($title . "\n\n" . $url),
        ];
        $label = $label !== '' ? $label : ((string) $s['label'] !== '' ? (string) $s['label'] : __('Share', 'sepo-bridge'));
        $names = self::networks();
        $out = '<nav class="sepo-share sepo-share--' . esc_attr((string) $s['style']) . '" aria-label="' . esc_attr($label) . '" data-sepo-share>'
            . '<span class="sepo-share__label">' . esc_html($label) . '</span><ul class="sepo-share__list">';
        foreach ($nets as $n) {
            if (!isset($names[$n])) {
                continue;
            }
            [$name, $colour] = $names[$n];
            $style = ' style="--sepo-share-c:' . esc_attr($colour) . '"';
            if ($n === 'copy') {
                $out .= '<li><button type="button" class="sepo-share__btn sepo-share__btn--copy"' . $style . ' data-sepo-share-copy="' . esc_url($url) . '" data-done="' . esc_attr__('Link copied', 'sepo-bridge') . '">' . self::icon('link') . '<span>' . esc_html($name) . '</span></button></li>';
            } elseif ($n === 'native') {
                $out .= '<li hidden data-sepo-share-native-wrap><button type="button" class="sepo-share__btn sepo-share__btn--native"' . $style . ' data-sepo-share-native data-url="' . esc_url($url) . '" data-title="' . esc_attr($title) . '">' . self::icon('share') . '<span>' . esc_html($name) . '</span></button></li>';
            } else {
                /* translators: %s: a network such as Facebook */
                $aria = sprintf(__('Share on %s (opens in a new tab)', 'sepo-bridge'), $name);
                $out .= '<li><a class="sepo-share__btn sepo-share__btn--' . esc_attr($n) . '"' . $style . ' href="' . esc_url($links[$n], ['https', 'mailto']) . '"'
                    . ($n === 'email' ? '' : ' target="_blank" rel="noopener noreferrer"') . ' aria-label="' . esc_attr($n === 'email' ? __('Share by email', 'sepo-bridge') : $aria) . '">'
                    . ($n === 'email' ? self::icon('mail') : '') . '<span>' . esc_html($name) . '</span></a></li>';
            }
        }
        return $out . '</ul><span class="sepo-share__status" role="status" aria-live="polite"></span></nav>';
    }

    /** Small generic icons, drawn here. */
    private static function icon(string $name): string
    {
        $paths = [
            'link'  => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
            'share' => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4"/>',
            'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5 12 13l8.5-6.5"/>',
        ];
        return '<svg class="sepo-share__icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
    }
}
