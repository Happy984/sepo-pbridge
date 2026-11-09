<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/**
 * Time-based one-time passwords (RFC 6238): what Google Authenticator, Authy,
 * 1Password and every other authenticator app use.
 *
 * Three details make it safe:
 *   - every comparison is constant-time (hash_equals), so a wrong code leaks
 *     nothing about the right one;
 *   - one 30-second step of clock drift is accepted either side of now, and
 *     no more;
 *   - the counter of the last accepted code is stored and anything at or
 *     below it is refused, so an overheard code cannot be used twice.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    public const WINDOW = 1;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A new shared secret: 20 random bytes, base32. */
    public static function secret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** The otpauth:// address an authenticator app reads from a QR code or a tap. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        // The issuer goes in the label for older apps and as a parameter for the rest.
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account) . '?' . http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function codeAt(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            return '';
        }
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Check a code. Returns the counter it matched, to be stored, or null.
     *
     * @param int $lastCounter the counter of the last code used; anything at or below it is a replay
     */
    public static function verify(string $secret, string $code, int $lastCounter = 0, ?int $now = null): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS || $secret === '') {
            return null;
        }
        $counter = intdiv($now ?? time(), self::PERIOD);
        for ($drift = -self::WINDOW; $drift <= self::WINDOW; $drift++) {
            $candidate = $counter + $drift;
            if ($candidate <= $lastCounter) {
                continue;
            }
            if (hash_equals(self::codeAt($secret, $candidate), $code)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Ten recovery codes, shown once in plain text and stored only as hashes.
     *
     * @return array{plain:list<string>,hashed:list<string>}
     */
    public static function recoveryCodes(int $count = 10): array
    {
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(5)));
            $code = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
            $plain[] = $code;
            $hashed[] = wp_hash_password($code);
        }
        return ['plain' => $plain, 'hashed' => $hashed];
    }

    /**
     * The index of the recovery code that matches, or null.
     *
     * @param list<string> $hashes
     */
    public static function matchRecoveryCode(string $input, array $hashes): ?int
    {
        $input = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $input) ?? '');
        if (strlen($input) !== 10) {
            return null;
        }
        $input = substr($input, 0, 5) . '-' . substr($input, 5);
        foreach ($hashes as $i => $hash) {
            if (is_string($hash) && $hash !== '' && wp_check_password($input, $hash)) {
                return (int) $i;
            }
        }
        return null;
    }

    /** Grouped in fours, as authenticator apps show it. */
    public static function readable(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if ($chunk !== '') {
                $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
            }
        }
        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret) ?? '');
        $bits = '';
        for ($i = 0, $n = strlen($secret); $i < $n; $i++) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $secret[$i])), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }
        return $out;
    }
}
