<?php
namespace SepoWB\Integration\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use SepoWB\Integration\Elementor;
use SepoWB\Integration\FieldDisplay;

defined('ABSPATH') || exit;

/** Elementor widget: every field of a group for the current post. */
final class FieldGroupWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'sepo-field-group';
    }

    public function get_title(): string
    {
        return __('Field group', 'sepo-bridge');
    }

    public function get_icon(): string
    {
        return 'eicon-bullet-list';
    }

    /** @return list<string> */
    public function get_categories(): array
    {
        return [Elementor::CATEGORY];
    }

    /** @return list<string> */
    public function get_keywords(): array
    {
        return ['fields', 'custom fields', 'meta', 'details', 'specs', 'sepo'];
    }

    /** @return list<string> */
    public function get_style_depends(): array
    {
        return ['sepo-wb-fields'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Fields', 'sepo-bridge')]);
        $this->add_control('group', [
            'label'   => __('Group', 'sepo-bridge'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['' => __('Every group for this post type', 'sepo-bridge')] + FieldDisplay::groupChoices(),
            'default' => '',
        ]);
        $this->add_control('style', [
            'label'   => __('Style', 'sepo-bridge'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['table' => __('Table', 'sepo-bridge'), 'grid' => __('Grid', 'sepo-bridge'), 'inline' => __('Inline', 'sepo-bridge')],
            'default' => 'table',
        ]);
        $this->add_control('show_title', [
            'label'        => __('Show the group title', 'sepo-bridge'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ]);
        $this->add_control('show_empty', [
            'label'        => __('List empty fields too', 'sepo-bridge'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ]);
        $this->add_responsive_control('columns_min', [
            'label'      => __('Smallest tile width', 'sepo-bridge'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 100, 'max' => 400]],
            'selectors'  => ['{{WRAPPER}} .sepo-fields--grid .sepo-fields__list' => 'grid-template-columns: repeat(auto-fill, minmax({{SIZE}}{{UNIT}}, 1fr));'],
            'condition'  => ['style' => 'grid'],
        ]);
        $this->add_responsive_control('label_width', [
            'label'      => __('Label column width', 'sepo-bridge'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['%', 'px'],
            'range'      => ['%' => ['min' => 15, 'max' => 70], 'px' => ['min' => 80, 'max' => 400]],
            'selectors'  => ['{{WRAPPER}} .sepo-fields--table .sepo-fields__row' => 'grid-template-columns: {{SIZE}}{{UNIT}} 1fr;'],
            'condition'  => ['style' => 'table'],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_box', ['label' => __('Rows and tiles', 'sepo-bridge'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('soft', ['label' => __('Label / tile background', 'sepo-bridge'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sepo-fields' => '--sepo-fields-soft: {{VALUE}};']]);
        $this->add_control('line', ['label' => __('Lines', 'sepo-bridge'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sepo-fields' => '--sepo-fields-line: {{VALUE}};']]);
        $this->add_responsive_control('radius', [
            'label'      => __('Corner radius', 'sepo-bridge'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .sepo-fields' => '--sepo-fields-radius: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('gap', [
            'label'      => __('Space between tiles', 'sepo-bridge'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'selectors'  => ['{{WRAPPER}} .sepo-fields' => '--sepo-fields-gap: {{SIZE}}{{UNIT}};'],
            'condition'  => ['style' => 'grid'],
        ]);
        $this->add_responsive_control('padding', [
            'label'      => __('Padding', 'sepo-bridge'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .sepo-fields--table :is(.sepo-fields__label, .sepo-fields__value), {{WRAPPER}} .sepo-fields--grid .sepo-fields__row' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            'condition'  => ['style!' => 'inline'],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style_title', ['label' => __('Group title', 'sepo-bridge'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['show_title' => 'yes']]);
        $this->add_control('title_color', ['label' => __('Colour', 'sepo-bridge'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sepo-fields__title' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'title_type', 'selector' => '{{WRAPPER}} .sepo-fields__title']);
        $this->end_controls_section();

        $this->start_controls_section('style_label', ['label' => __('Labels', 'sepo-bridge'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('label_color', ['label' => __('Colour', 'sepo-bridge'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sepo-fields' => '--sepo-fields-label: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'label_type', 'selector' => '{{WRAPPER}} .sepo-fields__label']);
        $this->end_controls_section();

        $this->start_controls_section('style_value', ['label' => __('Values', 'sepo-bridge'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('value_color', ['label' => __('Colour', 'sepo-bridge'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sepo-fields' => '--sepo-fields-value: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'value_type', 'selector' => '{{WRAPPER}} .sepo-fields__value']);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $postId = Elementor::postId();
        $args = ['style' => (string) ($s['style'] ?? 'table'), 'title' => ($s['show_title'] ?? '') === 'yes', 'empty' => ($s['show_empty'] ?? '') === 'yes'];
        $gid = sanitize_key((string) ($s['group'] ?? ''));
        $html = '';
        $ids = $gid !== '' ? [$gid] : array_keys(FieldDisplay::groupsFor((string) get_post_type($postId)));
        foreach ($ids as $g) {
            $html .= FieldDisplay::renderGroup($g, $postId, $args);
        }
        if ($html === '' && Elementor::editing()) {
            $html = '<div class="sepo-fields sepo-fields--placeholder">' . esc_html__('Field group — no values for this post yet, or the group is not assigned to this post type.', 'sepo-bridge') . '</div>';
        }
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
    }
}
