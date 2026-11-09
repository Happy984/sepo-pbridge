<?php
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom plugin tables: names are built from $wpdb->prefix plus a fixed suffix, values go through $wpdb->prepare(), and dynamic fragments come from fixed allow-lists.
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Queries are bounded by indexes and limits.
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/**
 * Provider links and two-factor state, in user meta.
 *
 *   _sepo_wb_social              provider => link details, for the account screens
 *   _sepo_wb_social_<provider>   the provider's account id, for finding the user
 *                                on a callback with one indexed lookup
 *   _sepo_wb_2fa                 enabled flag, encrypted secret, hashed recovery codes
 *   _sepo_wb_2fa_counter         the last accepted code's counter, in its own row so
 *                                it can be advanced with a single conditional UPDATE
 *   _sepo_wb_2fa_log             the last few security events on the account
 *
 * Links are keyed on the provider's account id, never the email address: an id
 * is permanent, while an address can be changed or recycled to someone else.
 */
final class Store
{
    public const META_LINKS   = '_sepo_wb_social';
    public const META_SUBJECT = '_sepo_wb_social_';
    public const META_2FA     = '_sepo_wb_2fa';
    public const META_COUNTER = '_sepo_wb_2fa_counter';
    public const META_LOG     = '_sepo_wb_2fa_log';

    // ---------------------------------------------------------------- links

    /** The user a provider account is linked to, or 0. */
    public static function userForSubject(string $provider, string $subject): int
    {
        if ($provider === '' || $subject === '') {
            return 0;
        }
        $ids = get_users([
            'meta_key'    => self::META_SUBJECT . $provider,
            'meta_value'  => $subject,
            'fields'      => 'ID',
            'number'      => 2,
            'count_total' => false,
        ]);
        if (count($ids) > 1) {
            // Two accounts claiming one provider account is corruption, not a
            // choice to make silently. Refuse both.
            Log::write('warning', 'provider account linked to more than one user', ['provider' => $provider]);
            return 0;
        }
        return $ids === [] ? 0 : (int) $ids[0];
    }

    /** @return array<string,array<string,mixed>> */
    public static function links(int $uid): array
    {
        $l = get_user_meta($uid, self::META_LINKS, true);
        return is_array($l) ? $l : [];
    }

    /**
     * Link a provider account to a user. Refused when that provider account is
     * already linked to someone else: moving a way in between accounts is not
     * something a sign-in does on its own.
     *
     * @param array{email?:string,name?:string} $identity
     */
    public static function link(int $uid, string $provider, string $subject, array $identity = []): bool
    {
        $owner = self::userForSubject($provider, $subject);
        if ($owner !== 0 && $owner !== $uid) {
            Log::write('warning', 'refused to move a provider link to another user', ['provider' => $provider]);
            return false;
        }
        $links = self::links($uid);
        $existing = $links[$provider] ?? [];
        if (!empty($existing['subject']) && (string) $existing['subject'] !== $subject) {
            // A different account at the same provider replaces the old link.
            delete_user_meta($uid, self::META_SUBJECT . $provider);
        }
        $links[$provider] = [
            'subject'    => $subject,
            'email'      => (string) ($identity['email'] ?? ''),
            'name'       => (string) ($identity['name'] ?? ''),
            'linked_at'  => (int) ($existing['linked_at'] ?? time()),
            'last_login' => time(),
        ];
        update_user_meta($uid, self::META_SUBJECT . $provider, $subject);
        update_user_meta($uid, self::META_LINKS, $links);
        return true;
    }

    public static function touch(int $uid, string $provider): void
    {
        $links = self::links($uid);
        if (isset($links[$provider])) {
            $links[$provider]['last_login'] = time();
            update_user_meta($uid, self::META_LINKS, $links);
        }
    }

    public static function unlink(int $uid, string $provider): bool
    {
        $links = self::links($uid);
        if (!isset($links[$provider])) {
            return false;
        }
        unset($links[$provider]);
        delete_user_meta($uid, self::META_SUBJECT . $provider);
        if ($links === []) {
            delete_user_meta($uid, self::META_LINKS);
        } else {
            update_user_meta($uid, self::META_LINKS, $links);
        }
        return true;
    }

    // ---------------------------------------------------------------- two-factor

    /** @return array<string,mixed> */
    private static function state(int $uid): array
    {
        $s = get_user_meta($uid, self::META_2FA, true);
        return is_array($s) ? $s : [];
    }

    private static function fresh(int $uid): void
    {
        wp_cache_delete($uid, 'user_meta');
    }

    public static function enabled(int $uid): bool
    {
        return $uid > 0 && !empty(self::state($uid)['enabled']);
    }

    /** Changes whenever two-factor is turned off or reset, so remembered devices stop counting. */
    public static function generation(int $uid): int
    {
        return (int) (self::state($uid)['generation'] ?? 0);
    }

    /**
     * Keep a new secret, not yet switched on. Two-factor only starts once a
     * code from the app proves it was set up: switching it on at this point
     * is how people lock themselves out with a QR code they never scanned.
     */
    public static function begin(int $uid, string $secret): bool
    {
        $enc = Crypto::encrypt($secret);
        if ($enc === '') {
            return false;
        }
        $s = self::state($uid);
        if (!empty($s['enabled'])) {
            return false;
        }
        $s['pending'] = $enc;
        $s['pending_at'] = time();
        return (bool) update_user_meta($uid, self::META_2FA, $s);
    }

    /** The secret waiting for its first code, or ''. Expires after an hour. */
    public static function pendingSecret(int $uid): string
    {
        $s = self::state($uid);
        if (empty($s['pending']) || time() - (int) ($s['pending_at'] ?? 0) > HOUR_IN_SECONDS) {
            return '';
        }
        return Crypto::decrypt((string) $s['pending']);
    }

    /** @param list<string> $recoveryHashes */
    public static function confirm(int $uid, int $counter, array $recoveryHashes): bool
    {
        $s = self::state($uid);
        if (empty($s['pending'])) {
            return false;
        }
        $s['enabled'] = 1;
        $s['secret'] = $s['pending'];
        $s['confirmed_at'] = time();
        $s['recovery'] = $recoveryHashes;
        unset($s['pending'], $s['pending_at']);
        update_user_meta($uid, self::META_COUNTER, (string) $counter);
        $ok = (bool) update_user_meta($uid, self::META_2FA, $s);
        if ($ok) {
            self::event($uid, 'enabled');
        }
        return $ok;
    }

    public static function secret(int $uid): string
    {
        $s = self::state($uid);
        return empty($s['enabled']) || empty($s['secret']) ? '' : Crypto::decrypt((string) $s['secret']);
    }

    public static function lastCounter(int $uid): int
    {
        self::fresh($uid);
        return (int) get_user_meta($uid, self::META_COUNTER, true);
    }

    /**
     * Record an accepted code. A single conditional UPDATE, so two requests
     * racing with the same code cannot both succeed: the second finds the
     * counter already advanced and changes nothing.
     */
    public static function spendCounter(int $uid, int $counter): bool
    {
        global $wpdb;
        $n = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE user_id = %d AND meta_key = %s AND CAST(meta_value AS UNSIGNED) < %d",
            (string) $counter, $uid, self::META_COUNTER, $counter
        ));
        self::fresh($uid);
        return $n === 1;
    }

    /** @return list<string> */
    public static function recoveryHashes(int $uid): array
    {
        $r = self::state($uid)['recovery'] ?? [];
        return is_array($r) ? array_values(array_filter($r, 'is_string')) : [];
    }

    /** Replace the recovery codes. @param list<string> $hashes */
    public static function setRecovery(int $uid, array $hashes): bool
    {
        $s = self::state($uid);
        if (empty($s['enabled'])) {
            return false;
        }
        $s['recovery'] = $hashes;
        self::event($uid, 'recovery_renewed');
        return (bool) update_user_meta($uid, self::META_2FA, $s);
    }

    /** Use up one recovery code, under a database lock so it works exactly once. */
    public static function spendRecovery(int $uid, string $input): bool
    {
        global $wpdb;
        $lock = 'sepo_wb_2fa_' . $uid;
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
            return false;
        }
        try {
            self::fresh($uid);
            $s = self::state($uid);
            $codes = is_array($s['recovery'] ?? null) ? array_values($s['recovery']) : [];
            $i = Totp::matchRecoveryCode($input, $codes);
            if ($i === null) {
                return false;
            }
            unset($codes[$i]);
            $s['recovery'] = array_values($codes);
            update_user_meta($uid, self::META_2FA, $s);
            self::event($uid, 'recovery_used');
            return true;
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    /**
     * Turn two-factor off. $by is the user who did it: the account holder, or
     * an administrator resetting it for someone who lost their phone.
     */
    public static function disable(int $uid, int $by, string $event = 'disabled'): void
    {
        $gen = self::generation($uid) + 1;
        update_user_meta($uid, self::META_2FA, ['enabled' => 0, 'generation' => $gen]);
        delete_user_meta($uid, self::META_COUNTER);
        self::event($uid, $event, $by);
    }

    // ---------------------------------------------------------------- history

    public static function event(int $uid, string $event, int $by = 0): void
    {
        $log = get_user_meta($uid, self::META_LOG, true);
        $log = is_array($log) ? $log : [];
        $log[] = ['at' => time(), 'event' => $event, 'by' => $by > 0 ? $by : get_current_user_id()];
        update_user_meta($uid, self::META_LOG, array_slice($log, -20));
    }

    /** @return list<array{at:int,event:string,by:int}> newest first */
    public static function history(int $uid): array
    {
        $log = get_user_meta($uid, self::META_LOG, true);
        return is_array($log) ? array_reverse(array_values($log)) : [];
    }

    public static function eventLabel(string $event): string
    {
        return [
            'enabled'          => __('Two-factor turned on', 'sepo-bridge'),
            'disabled'         => __('Two-factor turned off', 'sepo-bridge'),
            'reset'            => __('Two-factor reset by an administrator', 'sepo-bridge'),
            'recovery_used'    => __('Signed in with a recovery code', 'sepo-bridge'),
            'recovery_renewed' => __('New recovery codes made', 'sepo-bridge'),
            'locked'           => __('Too many wrong codes at sign-in', 'sepo-bridge'),
        ][$event] ?? $event;
    }

    public static function countEnrolled(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", self::META_COUNTER));
    }

    /** Everything this feature keeps about a user, for uninstall and erasure. */
    public static function purge(int $uid): void
    {
        foreach (array_keys(self::links($uid)) as $p) {
            delete_user_meta($uid, self::META_SUBJECT . $p);
        }
        foreach ([self::META_LINKS, self::META_2FA, self::META_COUNTER, self::META_LOG] as $k) {
            delete_user_meta($uid, $k);
        }
    }
}
