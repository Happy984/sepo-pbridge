<?php
namespace SepoWB\Integration\Elementor;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use SepoWB\Integration\Elementor;
use SepoWB\Integration\FieldDisplay;

defined('ABSPATH') || exit;

/** Elementor dynamic tag: a link or email field as the link of a button, image or heading. */
final class FieldUrlTag extends Data_Tag
{
    public function get_name(): string
    {
        return 'sepo-field-url';
    }

    public function get_title(): string
    {
        return __('Custom field link', 'sepo-bridge');
    }

    public function get_group(): string
    {
        return Elementor::CATEGORY;
    }

    /** @return list<string> */
    public function get_categories(): array
    {
        return [Module::URL_CATEGORY];
    }

    public function get_panel_template_setting_key(): string
    {
        return 'field';
    }

    protected function register_controls(): void
    {
        $choices = ['permalink' => __('Link to the post', 'sepo-bridge')];
        $all = FieldDisplay::fieldChoices();
        foreach (FieldDisplay::fieldDefs() as $id => $f) {
            if (in_array($f['type'] ?? '', ['url', 'email'], true)) {
                $choices[$id] = $all[$id] ?? $id;
            }
        }
        $this->add_control('field', [
            'label'   => __('Field', 'sepo-bridge'),
            'type'    => Controls_Manager::SELECT,
            'options' => $choices,
            'default' => 'permalink',
        ]);
    }

    /** @param array<string,mixed> $options */
    public function get_value(array $options = []): string
    {
        $field = sanitize_key((string) $this->get_settings('field'));
        $postId = Elementor::postId();
        if ($field === '' || $postId <= 0) {
            return '';
        }
        $value = FieldDisplay::valueText($postId, $field);
        if ($value !== '' && (FieldDisplay::fieldDefs()[$field]['type'] ?? '') === 'email') {
            return 'mailto:' . sanitize_email($value);
        }
        return esc_url($value);
    }
}
