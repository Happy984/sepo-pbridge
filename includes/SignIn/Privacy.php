<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/** Tools → Export / Erase Personal Data: connected providers and two-factor status. Secrets are never exported. */
final class Privacy
{
    public static function hook(): void
    {
        add_filter('wp_privacy_personal_data_exporters', static function (array $e): array {
            $e['sepo-wb-signin'] = ['exporter_friendly_name' => __('Sign-in security', 'sepo-bridge'), 'callback' => [self::class, 'export']];
            return $e;
        });
        add_filter('wp_privacy_personal_data_erasers', static function (array $e): array {
            $e['sepo-wb-signin'] = ['eraser_friendly_name' => __('Sign-in security', 'sepo-bridge'), 'callback' => [self::class, 'erase']];
            return $e;
        });
    }

    /** @return array{data:list<array<string,mixed>>,done:bool} */
    public static function export(string $email, int $page = 1): array
    {
        $u = get_user_by('email', $email);
        if (!$u) {
            return ['data' => [], 'done' => true];
        }
        $items = [];
        foreach (Store::links($u->ID) as $p => $l) {
            $items[] = [
                'group_id'    => 'sepo-wb-signin',
                'group_label' => __('Sign-in security', 'sepo-bridge'),
                'item_id'     => 'sepo-wb-link-' . $p,
                'data'        => [
                    ['name' => __('Provider', 'sepo-bridge'), 'value' => (string) (Providers::get((string) $p)['label'] ?? $p)],
                    ['name' => __('Provider account id', 'sepo-bridge'), 'value' => (string) ($l['subject'] ?? '')],
                    ['name' => __('Email at the provider', 'sepo-bridge'), 'value' => (string) ($l['email'] ?? '')],
                    ['name' => __('Connected', 'sepo-bridge'), 'value' => wp_date('Y-m-d H:i', (int) ($l['linked_at'] ?? 0))],
                ],
            ];
        }
        $items[] = [
            'group_id'    => 'sepo-wb-signin',
            'group_label' => __('Sign-in security', 'sepo-bridge'),
            'item_id'     => 'sepo-wb-2fa',
            'data'        => [['name' => __('Two-factor authentication', 'sepo-bridge'), 'value' => Store::enabled($u->ID) ? __('On', 'sepo-bridge') : __('Off', 'sepo-bridge')]],
        ];
        return ['data' => $items, 'done' => true];
    }

    /** @return array<string,mixed> */
    public static function erase(string $email, int $page = 1): array
    {
        $u = get_user_by('email', $email);
        if (!$u) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }
        $had = Store::links($u->ID) !== [] || Store::enabled($u->ID);
        Store::purge($u->ID);
        return ['items_removed' => $had, 'items_retained' => false, 'messages' => [], 'done' => true];
    }
}
