<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/**
 * The sign-in providers, and what each one calls things.
 *
 * OAuth 2 providers differ in their three endpoint addresses, their scope
 * string and the names of the fields in the profile they return. Everything
 * else is the same, so they are a table rather than a class each. The
 * `sepo_wb_signin_providers` filter can add one.
 *
 * The field that matters for security is whether the email address is
 * verified. A social sign-in proves the person controls an account at the
 * provider; it proves they control the address only when the provider says it
 * has verified it. identity() turns each provider's answer to that question
 * into one boolean, and nothing is linked on an address where it is false.
 */
final class Providers
{
    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        static $all = null;
        if ($all !== null) {
            return $all;
        }
        $list = [
            'google' => [
                'label'     => 'Google',
                'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token'     => 'https://oauth2.googleapis.com/token',
                'userinfo'  => 'https://openidconnect.googleapis.com/v1/userinfo',
                'scope'     => 'openid email profile',
                'pkce'      => true,
                // An account chooser, rather than silently signing in as
                // whoever is already signed in to Google on this device.
                'extra'     => ['prompt' => 'select_account'],
                'shape'     => 'oidc',
                'console'   => 'https://console.cloud.google.com/apis/credentials',
                'colour'    => '#ffffff', 'text' => '#3c4043', 'border' => '#dadce0',
            ],
            'facebook' => [
                'label'     => 'Facebook',
                'authorize' => 'https://www.facebook.com/v19.0/dialog/oauth',
                'token'     => 'https://graph.facebook.com/v19.0/oauth/access_token',
                'userinfo'  => 'https://graph.facebook.com/v19.0/me?fields=id,name,email,picture',
                'scope'     => 'email',
                'pkce'      => true,
                'extra'     => [],
                'shape'     => 'facebook',
                'console'   => 'https://developers.facebook.com/apps/',
                'colour'    => '#1877f2', 'text' => '#ffffff', 'border' => '#1877f2',
            ],
            'github' => [
                'label'     => 'GitHub',
                'authorize' => 'https://github.com/login/oauth/authorize',
                'token'     => 'https://github.com/login/oauth/access_token',
                'userinfo'  => 'https://api.github.com/user',
                // user:email as well: GitHub leaves a private address off /user,
                // and only /user/emails says which address is verified.
                'scope'     => 'read:user user:email',
                'pkce'      => false,
                'extra'     => [],
                'emails'    => 'https://api.github.com/user/emails',
                'shape'     => 'github',
                'console'   => 'https://github.com/settings/developers',
                'colour'    => '#24292f', 'text' => '#ffffff', 'border' => '#24292f',
            ],
            'microsoft' => [
                'label'     => 'Microsoft',
                'authorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'userinfo'  => 'https://graph.microsoft.com/oidc/userinfo',
                'scope'     => 'openid email profile',
                'pkce'      => true,
                'extra'     => [],
                'shape'     => 'oidc',
                'console'   => 'https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
                'colour'    => '#2f2f2f', 'text' => '#ffffff', 'border' => '#2f2f2f',
            ],
            'linkedin' => [
                'label'     => 'LinkedIn',
                'authorize' => 'https://www.linkedin.com/oauth/v2/authorization',
                'token'     => 'https://www.linkedin.com/oauth/v2/accessToken',
                'userinfo'  => 'https://api.linkedin.com/v2/userinfo',
                'scope'     => 'openid email profile',
                'pkce'      => false,
                'extra'     => [],
                'shape'     => 'linkedin',
                'console'   => 'https://www.linkedin.com/developers/apps',
                'colour'    => '#0a66c2', 'text' => '#ffffff', 'border' => '#0a66c2',
            ],
        ];
        $filtered = apply_filters('sepo_wb_signin_providers', $list);
        $all = [];
        foreach (is_array($filtered) ? $filtered : $list as $key => $spec) {
            $key = sanitize_key((string) $key);
            if ($key === '' || !is_array($spec) || empty($spec['authorize']) || empty($spec['token']) || empty($spec['userinfo'])) {
                continue;
            }
            $all[$key] = $spec + ['label' => ucfirst($key), 'scope' => '', 'pkce' => false, 'extra' => [], 'shape' => 'oidc', 'console' => '', 'colour' => '#ffffff', 'text' => '#1d2327', 'border' => '#c3c4c7'];
        }
        return $all;
    }

    public static function exists(string $provider): bool
    {
        return isset(self::all()[$provider]);
    }

    /** @return array<string,mixed>|null */
    public static function get(string $provider): ?array
    {
        return self::all()[$provider] ?? null;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * A provider's profile in the one shape the rest of the code uses.
     *
     * @param array<string,mixed>       $profile
     * @param list<array<string,mixed>> $emails GitHub's /user/emails
     * @return array{subject:string,email:string,email_verified:bool,name:string}|null
     */
    public static function identity(string $provider, array $profile, array $emails = []): ?array
    {
        $spec = self::get($provider);
        if ($spec === null) {
            return null;
        }
        $subject = '';
        $email = '';
        $verified = false;
        $name = '';

        switch ((string) $spec['shape']) {
            case 'facebook':
                $subject = (string) ($profile['id'] ?? '');
                $email = (string) ($profile['email'] ?? '');
                $name = (string) ($profile['name'] ?? '');
                // Facebook returns an address only once it is confirmed.
                $verified = $email !== '';
                break;

            case 'github':
                $subject = (string) ($profile['id'] ?? '');
                $name = (string) (($profile['name'] ?? '') ?: ($profile['login'] ?? ''));
                // The verified primary address, else any verified one; never the
                // public address on /user, which may be unverified.
                foreach ([true, false] as $wantPrimary) {
                    foreach ($emails as $row) {
                        if (is_array($row) && !empty($row['verified']) && (!$wantPrimary || !empty($row['primary']))) {
                            $email = (string) ($row['email'] ?? '');
                            $verified = $email !== '';
                            break 2;
                        }
                    }
                }
                break;

            case 'linkedin':
                $subject = (string) ($profile['sub'] ?? '');
                $email = (string) ($profile['email'] ?? '');
                $name = (string) ($profile['name'] ?? '');
                // LinkedIn's userinfo leaves email_verified out and only ever
                // returns a confirmed address; a false it does send is honoured.
                $flag = $profile['email_verified'] ?? true;
                $verified = $email !== '' && ($flag === true || $flag === 1 || $flag === 'true' || $flag === '1');
                break;

            default: // OpenID Connect userinfo: Google, Microsoft
                $subject = (string) ($profile['sub'] ?? '');
                $email = (string) ($profile['email'] ?? '');
                $name = (string) ($profile['name'] ?? '');
                $flag = $profile['email_verified'] ?? null;
                $verified = $flag === true || $flag === 1 || $flag === 'true' || $flag === '1';
        }

        $subject = trim($subject);
        if ($subject === '') {
            return null;
        }
        $email = strtolower(trim($email));
        if ($email !== '' && !is_email($email)) {
            $email = '';
        }
        return [
            'subject'        => $subject,
            'email'          => $email,
            'email_verified' => $email !== '' && $verified,
            'name'           => trim(str_replace(["\r", "\n"], ' ', sanitize_text_field($name))),
        ];
    }

    /**
     * A JSON request through WordPress's HTTP API, with certificates checked.
     *
     * @param array<string,string>     $headers
     * @param array<string,mixed>|null $post
     * @return array<string,mixed>|null
     */
    public static function request(string $url, array $headers = [], ?array $post = null)
    {
        $args = [
            'timeout'     => 12,
            'redirection' => 0,
            'sslverify'   => true,
            // GitHub answers 403 to a request with no User-Agent.
            'headers'     => $headers + ['Accept' => 'application/json', 'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; Sepo WP Bridge'],
        ];
        if ($post !== null) {
            $args['body'] = $post;
            $res = wp_remote_post($url, $args);
        } else {
            $res = wp_remote_get($url, $args);
        }
        if (is_wp_error($res)) {
            Log::write('warning', 'request failed', ['host' => (string) wp_parse_url($url, PHP_URL_HOST), 'detail' => $res->get_error_message()]);
            return null;
        }
        $raw = (string) wp_remote_retrieve_body($res);
        if (trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
        // GitHub's token endpoint answers form-encoded when Accept is stripped on the way.
        parse_str($raw, $parsed);
        return $parsed === [] ? null : $parsed;
    }
}
