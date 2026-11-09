<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/**
 * Social sign-in and two-factor authentication.
 *
 * Two-factor runs whenever it is offered, so accounts that turned it on stay
 * protected even while social sign-in is switched off. The provider buttons
 * and flows only run when the Sign-in tab's master switch is on.
 */
final class SignIn
{
    public static function hook(): void
    {
        if (defined('SEPO_WB_SAFE_MODE') && SEPO_WB_SAFE_MODE) {
            return;
        }
        TwoFactor::hook();
        OAuth::hook();
        LoginPage::hook();
        Account::hook();
        Privacy::hook();
        add_action('rest_api_init', [Rest::class, 'register']);
    }
}
