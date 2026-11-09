<?php
namespace SepoWB\Integration;

defined('ABSPATH') || exit;

/**
 * Custom fields in Elementor, when Elementor is active:
 *
 *  - a "Sepo fields" widget category with Post field and Field group widgets;
 *  - dynamic tags (Custom field, Custom field link), so any Elementor widget
 *    can take its text, number or link from a field.
 *
 * Nothing here loads unless Elementor fires its own hooks.
 */
final class Elementor
{
    public const CATEGORY = 'sepo-fields';

    public static function hook(): void
    {
        add_action('elementor/elements/categories_registered', [self::class, 'category']);
        add_action('elementor/widgets/register', [self::class, 'widgets']);
        add_action('elementor/dynamic_tags/register', [self::class, 'tags']);
        add_action('elementor/frontend/after_register_styles', [FieldDisplay::class, 'registerStyle']);
        add_action('elementor/editor/after_enqueue_styles', static function (): void {
            FieldDisplay::registerStyle();
            wp_enqueue_style('sepo-wb-fields');
        });
    }

    /** @param \Elementor\Elements_Manager $manager */
    public static function category($manager): void
    {
        $manager->add_category(self::CATEGORY, ['title' => __('Sepo fields', 'sepo-bridge'), 'icon' => 'eicon-database']);
    }

    /** @param \Elementor\Widgets_Manager $manager */
    public static function widgets($manager): void
    {
        $manager->register(new Elementor\FieldWidget());
        $manager->register(new Elementor\FieldGroupWidget());
    }

    /** @param \Elementor\Core\DynamicTags\Manager $manager */
    public static function tags($manager): void
    {
        $manager->register_group(self::CATEGORY, ['title' => __('Custom fields', 'sepo-bridge')]);
        $manager->register(new Elementor\FieldTag());
        $manager->register(new Elementor\FieldUrlTag());
    }

    /** The post a widget or tag shows. */
    public static function postId(): int
    {
        return (int) get_the_ID();
    }

    /** True while the Elementor editor or its preview draws the page. */
    public static function editing(): bool
    {
        if (!class_exists('\Elementor\Plugin')) {
            return false;
        }
        $p = \Elementor\Plugin::$instance;
        return ($p->editor && $p->editor->is_edit_mode()) || ($p->preview && $p->preview->is_preview_mode());
    }
}
