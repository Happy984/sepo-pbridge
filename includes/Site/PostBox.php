<?php
namespace SepoWB\Site;

defined('ABSPATH') || exit;

/**
 * The "Search, sharing and contents" box on the editing screen: a post's own
 * search title and description, and whether its share buttons and contents
 * box show. Only the parts switched on in the panel appear.
 */
final class PostBox
{
    private const NONCE = 'sepo_wb_postbox';

    public static function hook(): void
    {
        add_action('add_meta_boxes', [self::class, 'add']);
        add_action('save_post', [self::class, 'save'], 10, 2);
    }

    /** @return array{seo:bool,share:bool,toc:bool} */
    private static function parts(): array
    {
        $all = SiteSettings::all();
        return [
            'seo'   => !empty($all['seo']['enabled']) && Seo::otherPlugin() === '',
            'share' => !empty($all['share']['enabled']) && !empty($all['share']['per_post']),
            'toc'   => !empty($all['toc']['per_post']),
        ];
    }

    public static function add(string $postType): void
    {
        $p = self::parts();
        if (!in_array(true, $p, true) || !in_array($postType, array_keys(SiteSettings::postTypes()), true)) {
            return;
        }
        add_meta_box('sepo-wb-postbox', __('Search, sharing and contents', 'sepo-bridge'), [self::class, 'render'], $postType, 'normal', 'default');
    }

    public static function render(\WP_Post $post): void
    {
        $p = self::parts();
        wp_nonce_field(self::NONCE, self::NONCE);
        echo '<div class="sepo-wb-postbox">';
        if ($p['seo']) {
            $title = (string) get_post_meta($post->ID, Seo::META_TITLE, true);
            $desc = (string) get_post_meta($post->ID, Seo::META_DESC, true);
            echo '<h4>' . esc_html__('In search results', 'sepo-bridge') . '</h4>'
                . '<p><label for="sepo-wb-seo-title"><strong>' . esc_html__('Search title', 'sepo-bridge') . '</strong></label><br>'
                . '<input type="text" id="sepo-wb-seo-title" class="widefat" name="sepo_wb_seo_title" maxlength="120" value="' . esc_attr($title) . '" placeholder="' . esc_attr(get_the_title($post)) . '">'
                . '<span class="description">' . esc_html__('Empty uses the post title. About 60 characters show in results.', 'sepo-bridge') . '</span></p>'
                . '<p><label for="sepo-wb-seo-desc"><strong>' . esc_html__('Description', 'sepo-bridge') . '</strong></label><br>'
                . '<textarea id="sepo-wb-seo-desc" class="widefat" rows="2" maxlength="320" name="sepo_wb_seo_desc">' . esc_textarea($desc) . '</textarea>'
                . '<span class="description">' . esc_html__('Empty uses the excerpt or the opening text. About 155 characters show in results.', 'sepo-bridge') . '</span></p>'
                . '<p><label for="sepo-wb-seo-canonical"><strong>' . esc_html__('Canonical address', 'sepo-bridge') . '</strong></label><br>'
                . '<input type="url" id="sepo-wb-seo-canonical" class="widefat" name="sepo_wb_seo_canonical" value="' . esc_attr((string) get_post_meta($post->ID, Seo::META_CANONICAL, true)) . '" placeholder="' . esc_attr((string) get_permalink($post)) . '">'
                . '<span class="description">' . esc_html__('Only when this text first appeared at another address.', 'sepo-bridge') . '</span></p>'
                . '<p><label for="sepo-wb-seo-image"><strong>' . esc_html__('Social image (media ID)', 'sepo-bridge') . '</strong></label><br>'
                . '<input type="number" min="0" id="sepo-wb-seo-image" name="sepo_wb_seo_image" value="' . esc_attr((string) ((int) get_post_meta($post->ID, Seo::META_IMAGE, true) ?: '')) . '" class="small-text"> '
                . '<span class="description">' . esc_html__('Empty uses the featured image.', 'sepo-bridge') . '</span></p>'
                . '<p><label><input type="checkbox" name="sepo_wb_seo_noindex" value="1"' . checked(get_post_meta($post->ID, Seo::META_NOINDEX, true), '1', false) . '> '
                . esc_html__('Hide from search engines and the sitemap', 'sepo-bridge') . '</label></p>';
        }
        $choice = static function (string $name, string $value, string $label): string {
            $opts = ['' => __('As set for the site', 'sepo-bridge'), 'show' => __('Show', 'sepo-bridge'), 'hide' => __('Hide', 'sepo-bridge')];
            $out = '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label><br><select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';
            foreach ($opts as $k => $l) {
                $out .= '<option value="' . esc_attr($k) . '"' . selected($value, $k, false) . '>' . esc_html($l) . '</option>';
            }
            return $out . '</select></p>';
        };
        if ($p['share'] || $p['toc']) {
            echo '<h4>' . esc_html__('On this page', 'sepo-bridge') . '</h4><div class="sepo-wb-postbox__row">';
            if ($p['share']) {
                echo $choice('sepo_wb_share', (string) get_post_meta($post->ID, Share::META, true), __('Share buttons', 'sepo-bridge')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
            }
            if ($p['toc']) {
                echo $choice('sepo_wb_toc', (string) get_post_meta($post->ID, Toc::META, true), __('Contents box', 'sepo-bridge')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
            }
            echo '</div>';
        }
        echo '</div>';
    }

    public static function save(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST[self::NONCE])), self::NONCE)) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || !current_user_can('edit_post', $postId)) {
            return;
        }
        $set = static function (string $key, string $value) use ($postId): void {
            if ($value === '') {
                delete_post_meta($postId, $key);
            } else {
                update_post_meta($postId, $key, $value);
            }
        };
        $p = self::parts();
        if ($p['seo']) {
            $set(Seo::META_TITLE, mb_substr(sanitize_text_field(wp_unslash((string) ($_POST['sepo_wb_seo_title'] ?? ''))), 0, 120));
            $set(Seo::META_DESC, mb_substr(sanitize_textarea_field(wp_unslash((string) ($_POST['sepo_wb_seo_desc'] ?? ''))), 0, 320));
            $set(Seo::META_CANONICAL, esc_url_raw(trim(sanitize_text_field(wp_unslash((string) ($_POST['sepo_wb_seo_canonical'] ?? '')))), ['http', 'https']));
            $img = absint($_POST['sepo_wb_seo_image'] ?? 0);
            $set(Seo::META_IMAGE, $img > 0 && get_post_type($img) === 'attachment' ? (string) $img : '');
            $set(Seo::META_NOINDEX, !empty($_POST['sepo_wb_seo_noindex']) ? '1' : '');
        }
        foreach (['share' => Share::META, 'toc' => Toc::META] as $part => $key) {
            if ($p[$part]) {
                $v = sanitize_key(wp_unslash((string) ($_POST['sepo_wb_' . $part] ?? '')));
                $set($key, in_array($v, ['show', 'hide'], true) ? $v : '');
            }
        }
    }
}
