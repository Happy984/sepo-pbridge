<?php defined('ABSPATH') || exit; ?>
<div class="wb-loading" data-wb-loading><?php esc_html_e('Loading…', 'sepo-bridge'); ?></div>
<form class="wb-si" data-wb-si hidden novalidate>

  <div class="wb-si-status">
    <label class="wb-switch wb-switch--lg"><input type="checkbox" data-si="enabled" aria-describedby="wb-si-status-text"><span class="wb-slider"></span></label>
    <div class="wb-si-status__text" id="wb-si-status-text">
      <strong data-si-status-title></strong>
      <span data-si-status-sub></span>
    </div>
    <div class="wb-si-status__actions">
      <a class="wb-btn" data-si-login target="_blank" rel="noopener"><?php esc_html_e('Open the login page', 'sepo-bridge'); ?></a>
    </div>
  </div>

  <div data-si-notes></div>

  <h2 class="wb-h2"><?php esc_html_e('Providers', 'sepo-bridge'); ?></h2>
  <p class="wb-help"><?php esc_html_e('For each provider you want: create an app in its developer console, add the redirect address shown below, then paste the client ID and client secret here and save. Secrets are stored encrypted and never shown again.', 'sepo-bridge'); ?></p>
  <div class="wb-si-providers" data-si-providers></div>

  <h2 class="wb-h2"><?php esc_html_e('Accounts', 'sepo-bridge'); ?></h2>
  <div class="wb-si-card">
    <label class="wb-field wb-field--check"><input type="checkbox" data-si="show_on_login"> <span><?php esc_html_e('Show the buttons on the WordPress login page', 'sepo-bridge'); ?></span></label>
    <p class="wb-help"><?php esc_html_e('For other pages, add [sepo_social_login] where the buttons should appear.', 'sepo-bridge'); ?></p>
    <label class="wb-field wb-field--check"><input type="checkbox" data-si="link_existing"> <span><?php esc_html_e('Sign in to an existing account with the same email address', 'sepo-bridge'); ?></span></label>
    <p class="wb-help"><?php esc_html_e('Only ever on an address the provider says it has verified; that check cannot be switched off. When this is off, people connect a provider from their own account security settings instead.', 'sepo-bridge'); ?></p>
    <label class="wb-field wb-field--check"><input type="checkbox" data-si="allow_signup"> <span><?php esc_html_e('Create an account when there is none for the address', 'sepo-bridge'); ?></span></label>
    <div class="wb-grid2">
      <label class="wb-field"><?php esc_html_e('Role for accounts created this way', 'sepo-bridge'); ?>
        <select data-si="signup_role"></select>
        <small><?php esc_html_e('Administrator is not offered: accounts made with nobody watching should not be able to change the site. Promote people from Users instead.', 'sepo-bridge'); ?></small>
      </label>
    </div>
    <p class="wb-help" data-si-registration></p>
  </div>

  <h2 class="wb-h2"><?php esc_html_e('Two-factor authentication', 'sepo-bridge'); ?></h2>
  <div class="wb-si-card">
    <label class="wb-field wb-field--check"><input type="checkbox" data-si="twofactor_enabled"> <span><?php esc_html_e('Let people add an authenticator app to their sign-in', 'sepo-bridge'); ?></span></label>
    <p class="wb-help"><?php esc_html_e('Once someone turns it on, every sign-in to their account asks for a code, with a password or a provider. Application passwords for apps and scripts keep working.', 'sepo-bridge'); ?></p>
    <div class="wb-grid2">
      <label class="wb-field"><?php esc_html_e('Name shown in the authenticator app', 'sepo-bridge'); ?>
        <input type="text" data-si="twofactor_issuer" maxlength="60">
        <small><?php esc_html_e('Left empty, the site title is used.', 'sepo-bridge'); ?></small>
      </label>
      <label class="wb-field"><?php esc_html_e('Remember a device for (days)', 'sepo-bridge'); ?>
        <input type="number" data-si="remember_days" min="0" max="90" step="1">
        <small><?php esc_html_e('0 asks for a code every time. Anything above 0 means a stolen laptop with the cookie skips the code until it expires.', 'sepo-bridge'); ?></small>
      </label>
    </div>
    <fieldset class="wb-field"><legend><?php esc_html_e('Require it for these roles', 'sepo-bridge'); ?></legend><div class="wb-checks" data-si-roles></div>
      <small><?php esc_html_e('People in these roles are kept on their security settings until they have set it up. Start with Administrator.', 'sepo-bridge'); ?></small>
    </fieldset>
    <label class="wb-field"><?php esc_html_e('Front-end security page (optional)', 'sepo-bridge'); ?>
      <input type="url" data-si="security_page" placeholder="<?php echo esc_attr(home_url('/account/security/')); ?>">
      <small><?php esc_html_e('A page with the [sepo_account_security] shortcode. Members in a required role are sent there instead of to their dashboard profile.', 'sepo-bridge'); ?></small>
    </label>
    <p class="wb-help" data-si-enrolled></p>
  </div>

  <p class="wb-actions"><button type="submit" class="wb-btn wb-btn--primary"><?php esc_html_e('Save sign-in settings', 'sepo-bridge'); ?></button></p>
</form>
