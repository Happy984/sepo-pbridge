<?php defined('ABSPATH') || exit; ?>
<div class="wb-loading" data-wb-loading><?php esc_html_e('Loading…', 'sepo-bridge'); ?></div>
<div class="wb-cc" data-wb-cc hidden>

  <div class="wb-cc-status">
    <label class="wb-switch wb-switch--lg"><input type="checkbox" data-cc-enabled aria-describedby="wb-cc-status-text"><span class="wb-slider"></span></label>
    <div class="wb-cc-status__text" id="wb-cc-status-text">
      <strong data-cc-status-title></strong>
      <span data-cc-status-sub></span>
    </div>
    <div class="wb-cc-status__actions">
      <a class="wb-btn" data-cc-preview target="_blank" rel="noopener"><?php esc_html_e('Preview on the site', 'sepo-bridge'); ?></a>
      <button type="button" class="wb-btn wb-btn--primary" data-cc-scan><?php esc_html_e('Scan site for cookies', 'sepo-bridge'); ?></button>
    </div>
  </div>

  <div class="wb-cc-scan" data-cc-scan-box hidden>
    <div class="wb-cc-scan__row">
      <strong data-cc-scan-label><?php esc_html_e('Scanning…', 'sepo-bridge'); ?></strong>
      <span data-cc-scan-count></span>
    </div>
    <div class="wb-cc-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-cc-progress><span></span></div>
    <p class="wb-help" data-cc-scan-page></p>
  </div>
  <div class="wb-cc-frames" data-cc-frames aria-hidden="true"></div>

  <nav class="wb-cc-sub" role="tablist" aria-label="<?php esc_attr_e('Cookie banner sections', 'sepo-bridge'); ?>">
    <button type="button" role="tab" class="is-on" data-cc-view="cookies" aria-selected="true"><?php esc_html_e('Cookies', 'sepo-bridge'); ?></button>
    <button type="button" role="tab" data-cc-view="banner" aria-selected="false"><?php esc_html_e('Banner', 'sepo-bridge'); ?></button>
    <button type="button" role="tab" data-cc-view="blocking" aria-selected="false"><?php esc_html_e('Blocking', 'sepo-bridge'); ?></button>
    <button type="button" role="tab" data-cc-view="records" aria-selected="false"><?php esc_html_e('Records', 'sepo-bridge'); ?></button>
    <button type="button" role="tab" data-cc-view="placement" aria-selected="false"><?php esc_html_e('Settings link', 'sepo-bridge'); ?></button>
  </nav>

  <!-- Cookies -->
  <section data-cc-pane="cookies">
    <div class="wb-cc-found" data-cc-found hidden></div>
    <div class="wb-toolbar">
      <input type="search" class="wb-search" data-cc-filter placeholder="<?php esc_attr_e('Find a cookie…', 'sepo-bridge'); ?>" aria-label="<?php esc_attr_e('Find a cookie', 'sepo-bridge'); ?>">
      <button type="button" class="wb-btn" data-cc-add><?php esc_html_e('Add cookie', 'sepo-bridge'); ?></button>
    </div>
    <div data-cc-groups></div>
  </section>

  <form data-cc-form>
  <!-- Banner -->
  <section data-cc-pane="banner" hidden>
    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('Layout', 'sepo-bridge'); ?></h2>
      <div class="wb-cc-layouts" role="radiogroup" aria-label="<?php esc_attr_e('Layout', 'sepo-bridge'); ?>">
        <label class="wb-cc-layout"><input type="radio" name="cc-layout" value="card"><span class="wb-cc-layout__pic wb-cc-layout__pic--card" aria-hidden="true"><i></i></span><span class="wb-cc-layout__name"><?php esc_html_e('Card in a corner', 'sepo-bridge'); ?></span></label>
        <label class="wb-cc-layout"><input type="radio" name="cc-layout" value="bar"><span class="wb-cc-layout__pic wb-cc-layout__pic--bar" aria-hidden="true"><i></i></span><span class="wb-cc-layout__name"><?php esc_html_e('Bar along the bottom', 'sepo-bridge'); ?></span></label>
        <label class="wb-cc-layout"><input type="radio" name="cc-layout" value="modal"><span class="wb-cc-layout__pic wb-cc-layout__pic--modal" aria-hidden="true"><i></i></span><span class="wb-cc-layout__name"><?php esc_html_e('Window in the centre', 'sepo-bridge'); ?></span></label>
      </div>
      <div class="wb-grid2">
        <label class="wb-field"><?php esc_html_e('Side of the screen', 'sepo-bridge'); ?>
          <select data-cc="position">
            <option value="left"><?php esc_html_e('Left', 'sepo-bridge'); ?></option>
            <option value="right"><?php esc_html_e('Right', 'sepo-bridge'); ?></option>
          </select>
          <small><?php esc_html_e('For the card and the cookie settings button.', 'sepo-bridge'); ?></small>
        </label>
        <label class="wb-field"><?php esc_html_e('Cookie settings button', 'sepo-bridge'); ?>
          <select data-cc="revisit">
            <option value="floating"><?php esc_html_e('Small round button in the corner', 'sepo-bridge'); ?></option>
            <option value="none"><?php esc_html_e('None (I add my own link)', 'sepo-bridge'); ?></option>
          </select>
          <small><?php esc_html_e('Shown after a visitor chooses, so they can change their mind.', 'sepo-bridge'); ?></small>
        </label>
        <div class="wb-field"><?php esc_html_e('Button colour', 'sepo-bridge'); ?>
          <div class="wb-cc-color">
            <input type="color" data-cc-accent-picker aria-label="<?php esc_attr_e('Button colour', 'sepo-bridge'); ?>">
            <label class="wb-field--check"><input type="checkbox" data-cc-accent-theme> <span><?php esc_html_e('Use the theme’s colour', 'sepo-bridge'); ?></span></label>
          </div>
          <small><?php esc_html_e('The theme’s colour follows its light and dark modes.', 'sepo-bridge'); ?></small>
        </div>
        <label class="wb-field"><?php esc_html_e('Remember a choice for', 'sepo-bridge'); ?>
          <select data-cc="lifetime">
            <?php foreach ([1, 3, 6, 12, 13] as $m) : ?>
              <option value="<?php echo (int) $m; ?>"><?php echo esc_html(sprintf(/* translators: %d: months */ _n('%d month', '%d months', $m, 'sepo-bridge'), $m)); ?></option>
            <?php endforeach; ?>
          </select>
          <small><?php esc_html_e('Visitors are asked again after this. Most regulators expect 6 to 12 months.', 'sepo-bridge'); ?></small>
        </label>
      </div>
    </div>

    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('Wording', 'sepo-bridge'); ?></h2>
      <p class="wb-help"><?php esc_html_e('Leave a box empty to use the default wording shown in it.', 'sepo-bridge'); ?></p>
      <label class="wb-field"><?php esc_html_e('Title', 'sepo-bridge'); ?><input type="text" data-cc-text="title"></label>
      <label class="wb-field"><?php esc_html_e('Message', 'sepo-bridge'); ?><textarea rows="3" data-cc-text="message"></textarea></label>
      <div class="wb-grid3">
        <label class="wb-field"><?php esc_html_e('Accept button', 'sepo-bridge'); ?><input type="text" data-cc-text="accept"></label>
        <label class="wb-field"><?php esc_html_e('Reject button', 'sepo-bridge'); ?><input type="text" data-cc-text="reject"></label>
        <label class="wb-field"><?php esc_html_e('Choose button', 'sepo-bridge'); ?><input type="text" data-cc-text="manage"></label>
        <label class="wb-field"><?php esc_html_e('Save choices button', 'sepo-bridge'); ?><input type="text" data-cc-text="save"></label>
        <label class="wb-field"><?php esc_html_e('Settings button and link', 'sepo-bridge'); ?><input type="text" data-cc-text="revisit"></label>
        <label class="wb-field"><?php esc_html_e('Policy link', 'sepo-bridge'); ?><input type="text" data-cc-text="policy"></label>
      </div>
      <label class="wb-field"><?php esc_html_e('Choices window title', 'sepo-bridge'); ?><input type="text" data-cc-text="prefsTitle"></label>
      <label class="wb-field"><?php esc_html_e('Choices window introduction', 'sepo-bridge'); ?><textarea rows="2" data-cc-text="prefsIntro"></textarea></label>
      <div class="wb-grid2">
        <label class="wb-field"><?php esc_html_e('Blocked video or map', 'sepo-bridge'); ?><input type="text" data-cc-text="blocked"><small><?php
        /* translators: Shown as help text. %1$s and %2$s are typed literally by the site owner. */
        esc_html_e('%1$s is the service, %2$s its kind of cookies.', 'sepo-bridge'); ?></small></label>
        <label class="wb-field"><?php esc_html_e('Blocked content button', 'sepo-bridge'); ?><input type="text" data-cc-text="allowBlocked"></label>
      </div>
    </div>

    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('Cookie policy', 'sepo-bridge'); ?></h2>
      <div class="wb-grid2">
        <label class="wb-field"><?php esc_html_e('Policy page', 'sepo-bridge'); ?><select data-cc="policy_page"></select></label>
        <label class="wb-field"><?php esc_html_e('Or an address', 'sepo-bridge'); ?><input type="url" data-cc="policy_url" placeholder="https://"></label>
      </div>
      <p class="wb-help"><?php esc_html_e('With neither set, the site’s privacy policy page is used (Settings → Privacy). Put [sepo_cookie_list] on the page to show the cookie table and each visitor’s current choice.', 'sepo-bridge'); ?></p>
    </div>

    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('Categories', 'sepo-bridge'); ?></h2>
      <p class="wb-help"><?php esc_html_e('What visitors read in the choices window. Leave a box empty for the default.', 'sepo-bridge'); ?></p>
      <div data-cc-cats></div>
    </div>
  </section>

  <!-- Blocking -->
  <section data-cc-pane="blocking" hidden>
    <div class="wb-cc-card">
      <div class="wb-cc-toggle">
        <label class="wb-switch"><input type="checkbox" data-cc="block"><span class="wb-slider"></span></label>
        <div><strong><?php esc_html_e('Hold back tracking scripts and embeds until visitors agree', 'sepo-bridge'); ?></strong>
          <p class="wb-help"><?php esc_html_e('Known analytics and advertising scripts, and videos, maps and posts from other sites, wait for the visitor’s permission. Embedded content shows a short notice with a button to allow it.', 'sepo-bridge'); ?></p></div>
      </div>
      <div class="wb-cc-toggle">
        <label class="wb-switch"><input type="checkbox" data-cc="consent_mode"><span class="wb-slider"></span></label>
        <div><strong><?php esc_html_e('Google Consent Mode v2', 'sepo-bridge'); ?></strong>
          <p class="wb-help"><?php esc_html_e('Tells Google tags what the visitor allowed. Required to keep Google Ads measurement in the EEA and UK.', 'sepo-bridge'); ?></p></div>
      </div>
      <div class="wb-cc-toggle">
        <label class="wb-switch"><input type="checkbox" data-cc="detect"><span class="wb-slider"></span></label>
        <div><strong><?php esc_html_e('Notice new cookies while I browse the site', 'sepo-bridge'); ?></strong>
          <p class="wb-help"><?php esc_html_e('When an administrator visits the site, cookies that are not on the list yet are added as unclassified.', 'sepo-bridge'); ?></p></div>
      </div>
    </div>

    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('My own rules', 'sepo-bridge'); ?></h2>
      <p class="wb-help"><?php esc_html_e('Hold back any script or embed whose address or code contains the text below. Use this for services the plugin does not know yet.', 'sepo-bridge'); ?></p>
      <table class="wb-table wb-cc-rules">
        <thead><tr>
          <th><?php esc_html_e('Address or code contains', 'sepo-bridge'); ?></th>
          <th><?php esc_html_e('Service name', 'sepo-bridge'); ?></th>
          <th><?php esc_html_e('Category', 'sepo-bridge'); ?></th>
          <th></th>
        </tr></thead>
        <tbody data-cc-rules></tbody>
      </table>
      <p><button type="button" class="wb-btn" data-cc-rule-add><?php esc_html_e('Add rule', 'sepo-bridge'); ?></button></p>
    </div>

    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('Services the plugin recognises', 'sepo-bridge'); ?></h2>
      <p class="wb-help"><?php esc_html_e('Services found by the last scan are marked.', 'sepo-bridge'); ?></p>
      <div class="wb-cc-services" data-cc-services></div>
    </div>
  </section>

  <!-- Records -->
  <section data-cc-pane="records" hidden>
    <div class="wb-cc-card">
      <div class="wb-cc-toggle">
        <label class="wb-switch"><input type="checkbox" data-cc="log"><span class="wb-slider"></span></label>
        <div><strong><?php esc_html_e('Keep a record of each choice', 'sepo-bridge'); ?></strong>
          <p class="wb-help"><?php esc_html_e('Proof of consent: the consent ID, what was allowed, the banner version and a shortened IP address. Nothing that names the visitor.', 'sepo-bridge'); ?></p></div>
      </div>
      <label class="wb-field wb-cc-days"><?php esc_html_e('Delete records after (days)', 'sepo-bridge'); ?><input type="number" min="30" max="3650" data-cc="log_days"></label>
    </div>

    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('Last 30 days', 'sepo-bridge'); ?></h2>
      <div class="wb-cc-stats" data-cc-stats></div>
      <div class="wb-cc-bars" data-cc-bars></div>
      <h2 class="wb-h2"><?php esc_html_e('Latest choices', 'sepo-bridge'); ?></h2>
      <div data-cc-recent></div>
      <p class="wb-cc-row-actions">
        <a class="wb-btn" data-cc-csv><?php esc_html_e('Download all records (CSV)', 'sepo-bridge'); ?></a>
      </p>
    </div>

    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('Ask everyone again', 'sepo-bridge'); ?></h2>
      <p class="wb-help"><?php esc_html_e('After you add a new kind of cookie or change what one is used for, visitors should choose again. This shows the banner to everyone on their next visit.', 'sepo-bridge'); ?></p>
      <p class="wb-cc-row-actions"><button type="button" class="wb-btn" data-cc-bump></button> <span class="wb-help" data-cc-version></span></p>
    </div>
  </section>
  </form>

  <!-- Settings link -->
  <section data-cc-pane="placement" hidden>
    <div class="wb-cc-card">
      <h2 class="wb-h2"><?php esc_html_e('Let visitors change their mind from anywhere', 'sepo-bridge'); ?></h2>
      <p class="wb-help"><?php esc_html_e('Any of these opens the cookie choices window. Use as many as you like.', 'sepo-bridge'); ?></p>
      <div class="wb-cc-ways">
        <div class="wb-cc-way">
          <h3><?php esc_html_e('Menu or footer link', 'sepo-bridge'); ?></h3>
          <p><?php esc_html_e('In Appearance → Menus (or the site editor’s navigation block), add a custom link with this address:', 'sepo-bridge'); ?></p>
          <div class="wb-cc-copy"><code>#cookie-settings</code><button type="button" class="wb-btn wb-btn--sm" data-cc-copy="#cookie-settings"><?php esc_html_e('Copy', 'sepo-bridge'); ?></button></div>
        </div>
        <div class="wb-cc-way">
          <h3><?php esc_html_e('Shortcode', 'sepo-bridge'); ?></h3>
          <p><?php esc_html_e('In any post, page or widget. Add style="button" for a button instead of a link.', 'sepo-bridge'); ?></p>
          <div class="wb-cc-copy"><code>[sepo_cookie_settings]</code><button type="button" class="wb-btn wb-btn--sm" data-cc-copy="[sepo_cookie_settings]"><?php esc_html_e('Copy', 'sepo-bridge'); ?></button></div>
          <div class="wb-cc-copy"><code>[sepo_cookie_list]</code><button type="button" class="wb-btn wb-btn--sm" data-cc-copy="[sepo_cookie_list]"><?php esc_html_e('Copy', 'sepo-bridge'); ?></button></div>
          <p class="wb-help"><?php esc_html_e('The second one lists every cookie, for your cookie policy page.', 'sepo-bridge'); ?></p>
        </div>
        <div class="wb-cc-way">
          <h3><?php esc_html_e('Any element in a theme or page builder', 'sepo-bridge'); ?></h3>
          <p><?php esc_html_e('Give a button or link this CSS class:', 'sepo-bridge'); ?></p>
          <div class="wb-cc-copy"><code>sepo-cookie-settings</code><button type="button" class="wb-btn wb-btn--sm" data-cc-copy="sepo-cookie-settings"><?php esc_html_e('Copy', 'sepo-bridge'); ?></button></div>
        </div>
        <div class="wb-cc-way">
          <h3><?php esc_html_e('From a script', 'sepo-bridge'); ?></h3>
          <p><?php esc_html_e('Open the window, or check a choice before running your own code:', 'sepo-bridge'); ?></p>
          <div class="wb-cc-copy"><code>SepoConsent.open()</code><button type="button" class="wb-btn wb-btn--sm" data-cc-copy="SepoConsent.open()"><?php esc_html_e('Copy', 'sepo-bridge'); ?></button></div>
          <div class="wb-cc-copy"><code>SepoConsent.has('analytics')</code><button type="button" class="wb-btn wb-btn--sm" data-cc-copy="SepoConsent.has('analytics')"><?php esc_html_e('Copy', 'sepo-bridge'); ?></button></div>
        </div>
      </div>
      <h2 class="wb-h2"><?php esc_html_e('Holding back your own scripts', 'sepo-bridge'); ?></h2>
      <p class="wb-help"><?php esc_html_e('A script you add by hand can wait for a category: give it type="text/plain" and data-sepo-consent with the category name.', 'sepo-bridge'); ?></p>
      <pre class="wb-cc-code">&lt;script type="text/plain" data-sepo-consent="analytics"&gt;
  // runs only after the visitor allows analytics
&lt;/script&gt;</pre>
    </div>
  </section>

  <p class="wb-actions wb-cc-save" data-cc-save-bar hidden><button type="button" class="wb-btn wb-btn--primary" data-cc-save><?php esc_html_e('Save settings', 'sepo-bridge'); ?></button></p>
</div>
