<?php
namespace SepoWB\Integration\Elementor;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use SepoWB\Integration\Elementor;
use SepoWB\Integration\FieldDisplay;

defined('ABSPATH') || exit;

/** Elementor dynamic tag: a field's value as the text or number of any widget. */
final class FieldTag extends Tag
{
    public function get_name(): string
    {
        return 'sepo-field';
    }

    public function get_title(): string
    {
        return __('Custom field', 'sepo-bridge');
    }

    public function get_group(): string
    {
        return Elementor::CATEGORY;
    }

    /** @return list<string> */
    public function get_categories(): array
    {
        return [Module::TEXT_CATEGORY, Module::NUMBER_CATEGORY, Module::POST_META_CATEGORY];
    }

    public function get_panel_template_setting_key(): string
    {
        return 'field';
    }

    protected function register_controls(): void
    {
        $this->add_control('field', [
            'label'   => __('Field', 'sepo-bridge'),
            'type'    => Controls_Manager::SELECT,
            'options' => FieldDisplay::fieldChoices(),
            'default' => '',
        ]);
        $this->add_control('format', [
            'label'   => __('Show as', 'sepo-bridge'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['html' => __('Formatted (links, line breaks)', 'sepo-bridge'), 'text' => __('Plain text', 'sepo-bridge')],
            'default' => 'html',
        ]);
    }

    public function render(): void
    {
        $field = sanitize_key((string) $this->get_settings('field'));
        $postId = Elementor::postId();
        if ($field === '' || $postId <= 0) {
            return;
        }
        if ($this->get_settings('format') === 'text') {
            echo esc_html(FieldDisplay::valueText($postId, $field));
            return;
        }
        echo FieldDisplay::valueHtml($postId, $field); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by valueHtml
    }
}
