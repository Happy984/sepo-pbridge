<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/**
 * A table of contents built from a post's headings. It appears on its own on
 * the chosen post types once a post has enough headings, or wherever
 * [sepo_toc] is placed. The box sits on the left or right of the opening
 * text, or across the top, and is a full-width box on phones.
 */
final class Toc
{
    public const META = '_sepo_wb_toc';
    private const MARK = '<!--sepo-wb-toc-->';

    public static function hook(): void
    {
        add_shortcode('sepo_toc', static fn(): string => self::MARK);
        add_filter('the_content', [self::class, 'content'], 50);
    }

    public static function showsOn(int $postId): bool
    {
        $s = SiteSettings::section('toc');
        $choice = !empty($s['per_post']) ? (string) get_post_meta($postId, self::META, true) : '';
        if ($choice === 'hide') {
            return false;
        }
        if ($choice === 'show') {
            return true;
        }
        return !empty($s['enabled']) && in_array(get_post_type($postId), (array) $s['post_types'], true);
    }

    public static function content(string $html): string
    {
        $hasMark = str_contains($html, self::MARK);
        if (!$hasMark && (!is_singular() || !in_the_loop() || !is_main_query())) {
            return $html;
        }
        $id = (int) get_the_ID();
        $auto = !$hasMark && $id === (int) get_queried_object_id() && self::showsOn($id);
        if (!$hasMark && !$auto) {
            return $html;
        }
        $s = SiteSettings::section('toc');
        [$html, $items] = self::anchor($html, (int) $s['depth']);
        if ($auto && count($items) < (int) $s['min']) {
            return $html;
        }
        $box = $items !== [] ? self::box($items, $s) : '';
        if ($hasMark) {
            return str_replace(self::MARK, $box, $html);
        }
        return $box . $html;
    }

    /**
     * Give every heading an id and list them.
     *
     * @return array{0:string,1:list<array{level:int,id:string,text:string}>}
     */
    public static function anchor(string $html, int $depth): array
    {
        $items = [];
        $used = [];
        $max = max(2, min(4, $depth));
        $html = (string) preg_replace_callback('~<h([2-' . $max . '])(\s[^>]*)?>(.*?)</h\1>~is', static function (array $m) use (&$items, &$used): string {
            $level = (int) $m[1];
            $attrs = (string) ($m[2] ?? '');
            $text = trim((string) preg_replace('~\s+~u', ' ', wp_strip_all_tags($m[3])));
            if ($text === '' || str_contains($attrs, 'data-no-toc')) {
                return $m[0];
            }
            if (preg_match('~\sid=(["\'])(.*?)\1~i', $attrs, $idm)) {
                $id = $idm[2];
            } else {
                $base = sanitize_title($text) ?: 'section';
                $id = $base;
                $n = 2;
                while (isset($used[$id])) {
                    $id = $base . '-' . $n++;
                }
                $attrs .= ' id="' . esc_attr($id) . '"';
            }
            $used[$id] = true;
            $items[] = ['level' => $level, 'id' => $id, 'text' => $text];
            return '<h' . $level . $attrs . '>' . $m[3] . '</h' . $level . '>';
        }, $html);
        return [$html, $items];
    }

    /**
     * @param list<array{level:int,id:string,text:string}> $items
     * @param array<string,mixed>                          $s
     */
    public static function box(array $items, array $s): string
    {
        Assets::front();
        $title = (string) $s['title'] !== '' ? (string) $s['title'] : __('Contents', 'sepo-bridge');
        $top = min(array_column($items, 'level'));
        $list = '<ol class="sepo-toc__list">';
        $level = $top;
        $first = true;
        foreach ($items as $it) {
            $l = max($top, $it['level']);
            if ($first) {
                $first = false;
            } elseif ($l > $level) {
                $list .= str_repeat('<ol class="sepo-toc__list">', $l - $level);
            } else {
                $list .= '</li>' . str_repeat('</ol></li>', $level - $l);
            }
            $level = $l;
            $list .= '<li><a href="#' . esc_attr($it['id']) . '" data-sepo-toc-link>' . esc_html($it['text']) . '</a>';
        }
        $list .= '</li>' . str_repeat('</ol></li>', $level - $top) . '</ol>';
        $pos = in_array($s['position'], ['left', 'right', 'top'], true) ? (string) $s['position'] : 'right';
        return '<details class="sepo-toc sepo-toc--' . esc_attr($pos) . '" data-sepo-toc' . (!empty($s['open']) ? ' open' : '') . '>'
            . '<summary class="sepo-toc__title">' . esc_html($title) . '</summary>'
            . '<nav class="sepo-toc__nav" aria-label="' . esc_attr($title) . '">' . $list . '</nav></details>';
    }
}
