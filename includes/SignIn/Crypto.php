<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/**
 * Encryption for authenticator secrets at rest: AES-256-GCM, which also
 * detects a tampered value instead of decrypting it to rubbish.
 *
 * The key is derived from the site's own salts (AUTH_KEY and SECURE_AUTH_KEY
 * in wp-config.php), so there is no key file to lose or back up. Define
 * SEPO_WB_SIGNIN_KEY to use a key of your own instead. Changing the salts
 * makes the stored secrets unreadable: everyone then sets two-factor up again,
 * which is recoverable, unlike secrets kept in the clear.
 */
final class Crypto
{
    private static function key(): ?string
    {
        $material = '';
        if (defined('SEPO_WB_SIGNIN_KEY') && is_string(SEPO_WB_SIGNIN_KEY) && SEPO_WB_SIGNIN_KEY !== '') {
            $material = SEPO_WB_SIGNIN_KEY;
        } else {
            $material = (defined('AUTH_KEY') ? (string) AUTH_KEY : '') . (defined('SECURE_AUTH_KEY') ? (string) SECURE_AUTH_KEY : '');
            if ($material === '' || str_contains($material, 'put your unique phrase here')) {
                $material = wp_salt('auth') . wp_salt('secure_auth');
            }
        }
        return $material === '' ? null : hash_hkdf('sha256', $material, 32, 'sepo-wb-signin-totp');
    }

    public static function available(): bool
    {
        return function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true) && self::key() !== null;
    }

    /** '' when encryption is unavailable: the caller refuses rather than storing in the clear. */
    public static function encrypt(string $plain): string
    {
        $key = self::key();
        if ($key === null || !self::available()) {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $cipher === false ? '' : 'v1.' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        $key = self::key();
        if ($key === null || !str_starts_with($stored, 'v1.') || !function_exists('openssl_decrypt')) {
            return '';
        }
        $raw = base64_decode(substr($stored, 3), true);
        if (!is_string($raw) || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return is_string($plain) ? $plain : '';
    }
}
