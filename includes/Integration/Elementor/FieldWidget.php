<?php
namespace SepoWB\Integration\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use SepoWB\Integration\Elementor;
use SepoWB\Integration\FieldDisplay;

defined('ABSPATH') || exit;

/** Elementor widget: one value of the current post, with an optional label. */
final class FieldWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'sepo-post-field';
    }

    public function get_title(): string
    {
        return __('Post field', 'sepo-bridge');
    }

    public function get_icon(): string
    {
        return 'eicon-database';
    }

    /** @return list<string> */
    public function get_categories(): array
    {
        return [Elementor::CATEGORY];
    }

    /** @return list<string> */
    public function get_keywords(): array
    {
        return ['field', 'custom field', 'meta', 'acf', 'sepo'];
    }

    /** @return list<string> */
    public function get_style_depends(): array
    {
        return ['sepo-wb-fields'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Field', 'sepo-bridge')]);
        $this->add_control('field', [
            'label'   => __('Show', 'sepo-bridge'),
            'type'    => Controls_Manager::SELECT,
            'options' => FieldDisplay::fieldChoices(),
            'default' => 'post_title',
        ]);
        $this->add_control('label', [
            'label'       => __('Label before the value', 'sepo-bridge'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('Price:', 'sepo-bridge'),
        ]);
        $this->add_control('layout', [
            'label'        => __('Label position', 'sepo-bridge'),
            'type'         => Controls_Manager::CHOOSE,
            'options'      => [
                'inline' => ['title' => __('Beside the value', 'sepo-bridge'), 'icon' => 'eicon-h-align-left'],
                'block'  => ['title' => __('Above the value', 'sepo-bridge'), 'icon' => 'eicon-v-align-top'],
            ],
            'default'      => 'inline',
            'prefix_class' => 'sepo-field-layout-',
            'condition'    => ['label!' => ''],
        ]);
        $this->add_responsive_control('align', [
            'label'     => __('Alignment', 'sepo-bridge'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => ['title' => __('Left', 'sepo-bridge'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Centre', 'sepo-bridge'), 'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Right', 'sepo-bridge'), 'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => ['{{WRAPPER}} .sepo-field' => 'text-align: {{VALUE}};'],
        ]);
        $this->add_control('fallback', [
            'label'       => __('When empty, show', 'sepo-bridge'),
            'type'        => Controls_Manager::TEXT,
            'description' => __('Leave empty to hide the widget on posts without a value.', 'sepo-bridge'),
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_label', ['label' => __('Label', 'sepo-bridge'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['label!' => '']]);
        $this->add_control('label_color', ['label' => __('Colour', 'sepo-bridge'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sepo-field-label' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'label_type', 'selector' => '{{WRAPPER}} .sepo-field-label']);
        $this->add_responsive_control('label_gap', [
            'label'      => __('Space after', 'sepo-bridge'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .sepo-field' => '--sepo-field-gap: {{SIZE}}{{UNIT}};'],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_value', ['label' => __('Value', 'sepo-bridge'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('value_color', ['label' => __('Colour', 'sepo-bridge'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sepo-field-value, {{WRAPPER}} .sepo-field-value a' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'value_type', 'selector' => '{{WRAPPER}} .sepo-field-value']);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $html = FieldDisplay::renderField(Elementor::postId(), sanitize_key((string) ($s['field'] ?? '')), (string) ($s['label'] ?? ''));
        if ($html === '' && (string) ($s['fallback'] ?? '') !== '') {
            $html = '<div class="sepo-field">' . ((string) ($s['label'] ?? '') !== '' ? '<span class="sepo-field-label">' . esc_html((string) $s['label']) . '</span> ' : '')
                . '<span class="sepo-field-value">' . esc_html((string) $s['fallback']) . '</span></div>';
        }
        if ($html === '' && Elementor::editing()) {
            $html = '<div class="sepo-field sepo-field--placeholder">' . esc_html(sprintf(/* translators: %s: field name */ __('%s — empty for this post', 'sepo-bridge'), FieldDisplay::fieldChoices()[$s['field'] ?? ''] ?? '')) . '</div>';
        }
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
    }
}
