<?php
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are returned as JSON data or escaped at the point they are displayed.
namespace SepoWB\SignIn;

use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

defined('ABSPATH') || exit;

/**
 * Sign-in routes under /wp-json/sepo-wb/v1.
 *
 *   GET  auth/callback/{provider}   public: where providers send people back
 *   GET  signin, POST signin        administrators: the Sign-in tab
 *   GET  account/security           the signed-in person's own status
 *   POST account/2fa/begin|confirm|disable|recovery, account/unlink
 *   POST users/{id}/2fa/reset       someone who may edit that user
 *
 * Every account route acts on the signed-in person's own account, taken from
 * the session and never from the request: a route that accepted a user id
 * would let anyone switch off anyone's second factor.
 */
final class Rest
{
    private const NS = 'sepo-wb/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/auth/callback/(?P<provider>[a-z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => static function (WP_REST_Request $r) {
                OAuth::callback((string) $r['provider']);
                return new WP_REST_Response(null, 204);
            },
            'permission_callback' => '__return_true',
        ]);

        $admin = static fn() => current_user_can('manage_options');
        $self = static fn() => is_user_logged_in();
        $routes = [
            ['GET',  '/signin',              'settings', $admin],
            ['POST', '/signin',              'saveSettings', $admin],
            ['GET',  '/account/security',    'status', $self],
            ['POST', '/account/2fa/begin',   'begin', $self],
            ['POST', '/account/2fa/confirm', 'confirm', $self],
            ['POST', '/account/2fa/disable', 'disable', $self],
            ['POST', '/account/2fa/recovery', 'recovery', $self],
            ['POST', '/account/unlink',      'unlink', $self],
        ];
        foreach ($routes as [$method, $path, $cb, $perm]) {
            register_rest_route(self::NS, $path, ['methods' => $method, 'callback' => self::wrap($cb), 'permission_callback' => $perm]);
        }
        register_rest_route(self::NS, '/users/(?P<id>\d+)/2fa/reset', [
            'methods'             => 'POST',
            'callback'            => self::wrap('reset'),
            'permission_callback' => static fn(WP_REST_Request $r) => current_user_can('edit_user', (int) $r['id']),
        ]);
    }

    private static function wrap(string $method): callable
    {
        return static function (WP_REST_Request $req) use ($method) {
            try {
                return new WP_REST_Response(['status' => 'success', 'data' => self::{$method}($req)], 200);
            } catch (InvalidArgumentException $e) {
                return new WP_Error('sepo_wb_signin', $e->getMessage(), ['status' => $e->getCode() ?: 422]);
            } catch (\Throwable $e) {
                Log::write('error', $method . ': ' . $e->getMessage());
                return new WP_Error('sepo_wb_error', __('Something went wrong. Nothing was changed.', 'sepo-bridge'), ['status' => 500]);
            }
        };
    }

    /** @return array<string,mixed> */
    private static function body(WP_REST_Request $r): array
    {
        $j = $r->get_json_params();
        return is_array($j) ? $j : (array) $r->get_body_params();
    }

    // ---------------------------------------------------------------- settings

    /** Where connect buttons should bring the person back to: the page they are on. */
    private static function returnTo(WP_REST_Request $r): string
    {
        $v = self::body($r)['return'] ?? $r->get_param('return');
        return is_string($v) ? wp_validate_redirect($v, '') : '';
    }

    /** @return array<string,mixed> */
    private static function settings(): array
    {
        return Config::forScreen();
    }

    /** @return array<string,mixed> */
    private static function saveSettings(WP_REST_Request $r): array
    {
        Config::save(self::body($r));
        return Config::forScreen();
    }

    // ---------------------------------------------------------------- the person's own account

    private static function me(): WP_User
    {
        return wp_get_current_user();
    }

    /** @return array<string,mixed> */
    public static function statusFor(WP_User $u, string $return = ''): array
    {
        $links = Store::links($u->ID);
        $ready = Config::readyProviders();
        $return = $return !== '' ? $return : admin_url('profile.php#sepo-wb-security');
        $providers = [];
        foreach (Providers::all() as $key => $spec) {
            $linked = isset($links[$key]);
            if (!$linked && !isset($ready[$key])) {
                continue;
            }
            $providers[] = [
                'key'        => $key,
                'label'      => (string) $spec['label'],
                'linked'     => $linked,
                'email'      => $linked ? (string) ($links[$key]['email'] ?? '') : '',
                'linked_at'  => $linked ? (int) ($links[$key]['linked_at'] ?? 0) : 0,
                'offered'    => isset($ready[$key]),
                'connect'    => isset($ready[$key]) && !$linked ? OAuth::startUrl($key, $return, true, $return) : '',
                'icon'       => Buttons::icon($key),
            ];
        }
        $history = [];
        foreach (array_slice(Store::history($u->ID), 0, 6) as $h) {
            $history[] = ['at' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $h['at']), 'label' => Store::eventLabel((string) $h['event'])];
        }
        return [
            'offered'       => Config::twoFactorOn(),
            'canStore'      => Crypto::available(),
            'enabled'       => Store::enabled($u->ID),
            'required'      => TwoFactor::required($u),
            'recoveryLeft'  => count(Store::recoveryHashes($u->ID)),
            'providers'     => $providers,
            'history'       => $history,
            'email'         => $u->user_email,
            'lostPassword'  => wp_lostpassword_url(),
        ];
    }

    /** @return array<string,mixed> */
    private static function status(WP_REST_Request $r): array
    {
        $return = wp_validate_redirect((string) $r->get_param('return'), '');
        $out = self::statusFor(self::me(), $return);
        // A message carried back in the address from a connect attempt.
        $error = sanitize_key((string) $r->get_param('problem'));
        $linked = sanitize_key((string) $r->get_param('linked'));
        if ($error !== '') {
            $out['notice'] = ['kind' => 'bad', 'text' => Auth::message($error)];
        } elseif ($linked !== '' && Providers::exists($linked)) {
            /* translators: %s: provider name */
            $out['notice'] = ['kind' => 'good', 'text' => sprintf(__('%s is now connected. You can use it to sign in.', 'sepo-bridge'), (string) Providers::get($linked)['label'])];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private static function begin(): array
    {
        $u = self::me();
        if (!Config::twoFactorOn()) {
            throw new InvalidArgumentException(__('Two-factor authentication is switched off for this site.', 'sepo-bridge'), 409);
        }
        if (!Crypto::available()) {
            throw new InvalidArgumentException(__('This server cannot store the secret safely (OpenSSL with AES-256-GCM is needed), so setup is unavailable.', 'sepo-bridge'), 503);
        }
        if (Store::enabled($u->ID)) {
            throw new InvalidArgumentException(__('Two-factor is already on for this account.', 'sepo-bridge'), 409);
        }
        $secret = Totp::secret();
        if (!Store::begin($u->ID, $secret)) {
            throw new InvalidArgumentException(__('The setup could not be saved. Please try again.', 'sepo-bridge'), 500);
        }
        $uri = Totp::uri($secret, $u->user_email !== '' ? $u->user_email : $u->user_login, Config::issuer());
        return [
            'key'    => Totp::readable($secret),
            'uri'    => $uri,
            'qr'     => Qr::svg($uri, __('QR code to scan with your authenticator app', 'sepo-bridge')),
            'issuer' => Config::issuer(),
        ];
    }

    /** @return array<string,mixed> */
    private static function confirm(WP_REST_Request $r): array
    {
        $u = self::me();
        $secret = Store::pendingSecret($u->ID);
        if ($secret === '') {
            throw new InvalidArgumentException(__('The setup has expired. Start again.', 'sepo-bridge'), 409);
        }
        $counter = Totp::verify($secret, (string) (self::body($r)['code'] ?? ''));
        if ($counter === null) {
            throw new InvalidArgumentException(__('That code did not match. Check the app shows this site, then try the current code.', 'sepo-bridge'));
        }
        $codes = Totp::recoveryCodes();
        if (!Store::confirm($u->ID, $counter, $codes['hashed'])) {
            throw new InvalidArgumentException(__('Two-factor could not be switched on. Please try again.', 'sepo-bridge'), 500);
        }
        // The only time these are readable: they are kept as hashes.
        return ['recoveryCodes' => $codes['plain'], 'status' => self::statusFor($u, self::returnTo($r))];
    }

    /** @return array<string,mixed> */
    private static function disable(WP_REST_Request $r): array
    {
        $u = self::me();
        if (!Store::enabled($u->ID)) {
            return ['status' => self::statusFor($u, self::returnTo($r))];
        }
        if (TwoFactor::required($u)) {
            throw new InvalidArgumentException(__('Two-factor is required for your role, so it cannot be switched off.', 'sepo-bridge'), 409);
        }
        // A current code is needed, or anyone at an unlocked computer could remove it.
        if (!TwoFactor::check($u->ID, (string) (self::body($r)['code'] ?? ''))) {
            throw new InvalidArgumentException(__('Enter a current code from your app, or one of your recovery codes.', 'sepo-bridge'));
        }
        Store::disable($u->ID, $u->ID);
        return ['status' => self::statusFor($u, self::returnTo($r))];
    }

    /** @return array<string,mixed> */
    private static function recovery(WP_REST_Request $r): array
    {
        $u = self::me();
        if (!Store::enabled($u->ID)) {
            throw new InvalidArgumentException(__('Two-factor is not on for this account.', 'sepo-bridge'), 409);
        }
        $secret = Store::secret($u->ID);
        $counter = Totp::verify($secret, (string) (self::body($r)['code'] ?? ''), Store::lastCounter($u->ID));
        if ($counter === null || !Store::spendCounter($u->ID, $counter)) {
            throw new InvalidArgumentException(__('Enter a current code from your app to make new recovery codes.', 'sepo-bridge'));
        }
        $codes = Totp::recoveryCodes();
        Store::setRecovery($u->ID, $codes['hashed']);
        return ['recoveryCodes' => $codes['plain'], 'status' => self::statusFor($u, self::returnTo($r))];
    }

    /** @return array<string,mixed> */
    private static function unlink(WP_REST_Request $r): array
    {
        $u = self::me();
        $p = sanitize_key((string) (self::body($r)['provider'] ?? ''));
        if (!Store::unlink($u->ID, $p)) {
            throw new InvalidArgumentException(__('That provider is not connected.', 'sepo-bridge'));
        }
        return ['status' => self::statusFor($u, self::returnTo($r))];
    }

    /** @return array<string,mixed> */
    private static function reset(WP_REST_Request $r): array
    {
        $id = (int) $r['id'];
        $u = get_user_by('id', $id);
        if (!$u instanceof WP_User) {
            throw new InvalidArgumentException(__('That user was not found.', 'sepo-bridge'), 404);
        }
        if ($id === get_current_user_id()) {
            throw new InvalidArgumentException(__('Turn your own two-factor off from your profile, with a code.', 'sepo-bridge'), 409);
        }
        Store::disable($id, get_current_user_id(), 'reset');
        Log::write('warning', 'two-factor reset by an administrator', ['user' => $id, 'by' => get_current_user_id()]);
        return ['enabled' => false];
    }
}
