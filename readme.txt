=== Sepo Bridge ===
Contributors: euneolink
Tags: custom post types, custom fields, membership, cookie consent, security
Requires at least: 6.1
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One panel for feature toggles, post types, taxonomies, custom fields, membership tiers, cookie consent and menu or block visibility.

== Description ==

Everything lives under **WP Bridge** in the admin menu, in six tabs.

* **Features** — security, performance, admin and media switches, grouped by category, each with a plain description. Some have settings (login attempts and lock-out minutes, password length, rate limit, roles, JPEG quality, REST namespaces left open). Add your own by extending the `SepoWB\Features\Handlers` class.
* **Post types** — singular/plural names, slug, address, menu icon, what the editing screen shows, public/archive/hierarchical.
* **Taxonomies** — categories (nested) or tags (flat), attached to any post types.
* **Meta fields** — groups of fields shown as a box on the editing screen: text, paragraph, number, yes/no, choice list, email, link, date. Values are saved as post meta and registered for the REST API and block editor.

Showing custom fields:

* **Automatically** — set a group to appear before or after the content of its post types, as a table, a grid of tiles or one inline line, with or without its title. Untick "Show on the site" for fields that are for editors only.
* **Posts list columns** — tick "Column in the posts list" on a field to see its value on Posts → All Posts (or any post type's list), and sort by it. Empty values stay at the end.
* **Block editor** — the **Post field** block shows one value; the **Field group** block shows a whole group. Both work inside Query Loop templates.
* **Shortcodes** — `[sepo_field field="price" label="Price:"]` for one value; `[sepo_fields group="event_details" style="grid"]` for a group (`style` table, grid or inline; `title` yes or no; `empty` yes to list empty fields; `post` for another post's values).
* **Elementor** — a "Sepo fields" widget category with **Post field** and **Field group** widgets, each with colour, typography and spacing controls; and dynamic tags (**Custom field** for any text or number, **Custom field link** for any button or link), so Elementor's own widgets can show field values.
* A group placed by hand on a page is not repeated by the automatic display.
* **Styled login page** — a clean single-card login in your colours and logo (Features → Branding).
* **Membership** — ranked tiers (Gold includes Silver), a site-wide default tier for chosen post types, join and account links.
* **Cookies** — a cookie consent banner, a site scanner, the cookie list, blocking rules and proof-of-consent records.

Cookie banner:

* Visitors choose **Accept all**, **Reject all** (necessary cookies only) or **Choose cookies**, which opens a window with a switch per category (necessary, preferences, analytics, marketing) and every cookie in each one: name, provider, purpose and duration.
* **Scan site for cookies** loads a sample of your pages in the background and lists what they set. Cookies from known services (Google Analytics, Tag Manager, Ads, Meta Pixel, Hotjar, Clarity, LinkedIn, TikTok, Pinterest, HubSpot, YouTube, Vimeo, Google Maps and more) are described and sorted for you, including the ones those services set on their own domains. Anything unknown is marked so you can give it a category.
* Tracking scripts and embedded videos, maps and posts wait until the visitor allows their category. A held-back embed shows a short notice with a button to allow it.
* Google Consent Mode v2 and the WP Consent API are supported.
* Visitors can change their mind at any time: the small cookie button in the corner, any menu link to `#cookie-settings`, any element with the class `sepo-cookie-settings`, or the `[sepo_cookie_settings]` shortcode. Turning a category off removes its cookies.
* `[sepo_cookie_list]` prints the full cookie table and the visitor's current choice, for your cookie policy page.
* Card, bar or centred window; left or right; your wording; the theme's colours in light and dark mode.
* Each choice is recorded with a consent ID, the banner version and a shortened IP address. Download the records as CSV; old ones are deleted after the period you set. **Ask everyone again** shows the banner to all visitors once more.

Membership in use:

* Each post has a **Members only** box: who can read it, the message locked-out readers see, or a page to send them to.
* Each user has a **Tier** on their profile; the Users list shows it.
* Locked posts show a lock card instead of the content. The content is also removed from excerpts, feeds, the REST API and comments. Anyone who can edit the post still sees it.

Menu and block visibility:

* Each menu item (Appearance → Menus) has a **Visibility** box: everyone, signed-in or signed-out visitors, roles, and a tier — hidden, or shown with a lock. Rules apply to the item's sub-menu too.
* Each block has a **Visibility** panel in the block editor with the same choices.
* CSS classes work too: `sp-logged-in`, `sp-guests`, `sp-role-editor`, `sp-tier-gold`, `sp-locked`.
* Smart menu links: `#sp-login`, `#sp-register`, `#sp-logout`, `#sp-account`, `#sp-join` (login/register show only to visitors; logout/account only to signed-in users).

Sign-in (WP Bridge → Sign-in):

* **Social sign-in** with Google, Facebook, GitHub, Microsoft and LinkedIn, on the login page and anywhere with `[sepo_social_login]`. Off until you switch it on. Each provider's redirect address is shown ready to copy.
* An existing account is matched only on an email address the provider says it has verified. Creating accounts on first sign-in is a separate setting, off by default, and never gives the administrator role.
* People connect and disconnect providers from their own Profile, or from a page with `[sepo_account_security]`.
* **Two-factor authentication** with any authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password…). Setup shows a QR code drawn on your own server, so the secret never goes to an outside service. Ten one-time recovery codes are shown once.
* Once someone turns it on, every sign-in asks for a code, with a password or a provider. Application passwords keep working. Optionally remember a device for a number of days, and require two-factor for chosen roles.
* Administrators can reset two-factor for someone who has lost their phone and codes, from that user's edit screen; the reset is recorded with their name.
* Paste each provider's client ID and secret on the Sign-in tab. Client secrets and authenticator secrets are stored encrypted with keys derived from your site's salts. (Hosts that manage secrets in wp-config.php can define `SEPO_WB_GOOGLE_CLIENT_SECRET` and so on instead; nothing requires it.)

== Installation ==

1. Upload the `sepo-bridge` folder to `wp-content/plugins/`, or upload the zip under Plugins → Add New.
2. Activate it.
3. Open **WP Bridge** in the admin menu.

If a feature ever locks you out, add `define('SEPO_WB_SAFE_MODE', true);` to wp-config.php. No features run until you remove it, and that includes the two-factor prompt.

== For developers ==

* REST API (manage_options): `/wp-json/sepo-wb/v1/features`, `/cpts`, `/taxonomies`, `/metagroups`, `/membership`, with `/save` and `/delete` sub-routes.
* REST API (manage_options): `/wp-json/sepo-wb/v1/cookies`, with `/save`, `/list`, `/scan-urls`, `/scan-results`, `/version` and `/log`. Visitors' choices are recorded through `POST /wp-json/sepo-wb/v1/consent`.
* REST API: `/wp-json/sepo-wb/v1/signin` (manage_options); `/account/security` and `/account/2fa/begin|confirm|disable|recovery`, `/account/unlink` (the signed-in user's own account); `/users/{id}/2fa/reset` (edit_user); `/auth/callback/{provider}` (public, the OAuth redirect address).
* Field output: the `.sepo-fields` classes, restyled through `--sepo-fields-line`, `--sepo-fields-soft`, `--sepo-fields-label`, `--sepo-fields-value`, `--sepo-fields-radius` and `--sepo-fields-gap`.
* PHP: `SepoWB\Integration\FieldDisplay::renderGroup('event_details', $postId)`, `(new SepoWB\TierStore())->hasLevel($userId, 'gold')`, `SepoWB\Integration\MembershipGate::passes($postId)`, `sepo_has_cookie_consent('analytics')`.
* JavaScript: `SepoConsent.open()`, `SepoConsent.has('marketing')`, `SepoConsent.get()`, `SepoConsent.on(fn)`, and the `sepo:consent` event on `document`.
* Hold back a script of your own: `<script type="text/plain" data-sepo-consent="analytics">…</script>`. Add `data-sepo-allow` to a script or iframe the banner should never hold back.
* Filters:
  * `sepo_wb_handlers_class` — your own Handlers subclass.
  * `sepo_wb_tiers` — tiers in code.
  * `sepo_wb_user_has_tier` — grant tiers from a shop or subscription plugin.
  * `sepo_wb_can_view` — final say on a gated post.
  * `sepo_wb_lock_html` — the lock card.
  * `sepo_wb_join_url` and `sepo_wb_account_url` — the join and account links.
  * `sepo_wb_post_type_args` and `sepo_wb_taxonomy_args` — registration arguments.
  * `sepo_wb_field_allowed` — let the Post field block print other meta.
  * `sepo_wb_fields_html` — change a field group's output.
  * `sepo_wb_cookie_services` — add or change the services the cookie scanner and blocker know.
  * `sepo_wb_cookie_scan_urls` — the pages a cookie scan loads.
  * `sepo_wb_consent_block` — return false to leave a page's scripts alone.
  * `sepo_wb_signin_providers` — add or change a sign-in provider.
  * `sepo_wb_social_login_allowed` — return false to refuse a provider sign-in for a user.
* Actions: `sepo_wb_social_user_created` (a provider sign-in made an account), `sepo_wb_consent_saved` (a visitor's choice was recorded), `sepo_wb_consent_settings_saved`, `sepo_wb_activity` (add a line to the activity log), `sepo_wb_error`, `sepo_wb_sign_in_log`.
* Shortcodes: `[sepo_share]`, `[sepo_toc]`.
* Meta keys:
  * Posts: `_sp_require_membership`, `_sp_membership_message`, `_sp_membership_redirect`.
  * Menu items: `_sp_nav_visibility`.
  * Users: `_sepo_wb_tier`, `_sepo_wb_social`, `_sepo_wb_social_<provider>`, `_sepo_wb_2fa`, `_sepo_wb_2fa_counter`, `_sepo_wb_2fa_log`.

== Uninstall ==

Deleting the plugin removes its settings only if "Delete this plugin's settings…" is ticked under WP Bridge → Membership. Posts, terms and saved field values are never deleted. With that box ticked, the cookie banner settings and the consent records are deleted too, as are the search, sharing, contents and activity settings, the page views and activity log, and the roles made on the Roles tab (their people move to the default role), and so are the sign-in settings, provider links and two-factor enrolments. Accounts made by a provider sign-in are kept.

== Screenshots ==

1. The panel: turn features on and off.
2. Roles and what each can do.

== Upgrade Notice ==

= 1.8.0 =
The plugin now installs in a folder named sepo-bridge. Deactivate and delete the old copy (folder sepo-wp-bridge), then install this one. Your settings are kept.

== Changelog ==

= 1.8.0 =
* The plugin's folder, main file and text domain are now sepo-bridge, to match its name. Settings, tiers, post types, fields and cookie choices are kept: deactivate and delete the old Sepo Bridge (folder sepo-wp-bridge), then install and activate this one. The settings page address is now admin.php?page=sepo-bridge; the Sepo Bridge menu leads there as before.

= 1.7.1 =
* The plugin is by Euneolink Solutions. Its plugin page and author page are edu.sepodesk.com.

= 1.7.0 =
* The settings page has a side menu grouped into Site, Content, People and Visitors, with an icon for each section and a header that says what the section does. On narrow screens the menu becomes a list to choose from.

= 1.6.3 =
* Readme: screenshots and their captions for the plugin directory.

= 1.6.2 =
* Admin screens: settings shown as cards, lists in a rounded frame, rounded fields and buttons, pill-shaped tabs and filters, and notices on tinted backgrounds.

= 1.6.1 =
* Roles: the Add a role form lines up its name, starting point and button on one row, with even spacing, and wraps neatly on narrow screens.

= 1.6.0 =
* Search tab: search titles and descriptions, canonical addresses, robots rules, social previews, structured data, verification codes and sitemap choices.
* Sharing tab: share buttons, site-wide or chosen per post.
* Contents tab: a table of contents on the left, the right or across the top.
* Activity tab and WP Bridge → Activity: page views and an activity log, with a master switch and roles to record.
* Roles tab: add roles, tick their permissions, and set activity recording per role.
* Each post gets a "Search, sharing and contents" box.

= 1.5.2 =
* The plugin is now called Sepo Bridge.
* Requires WordPress 6.1 or newer.
* "Turn off automatic updates" uses WordPress's single switch for every background update.
* Errors are passed to the sepo_wb_error action and sign-in messages to sepo_wb_sign_in_log, instead of the PHP error log.
* The Google consent defaults print through wp_print_inline_script_tag().
* Passes the WordPress Plugin Check: translator comments on every string with placeholders, escaped output, sanitised request data and documented database access to the plugin's own tables.
* Tested up to WordPress 7.1.

= 1.5.1 =
* Changed: provider client secrets pasted on the Sign-in tab are stored encrypted. The tab's instructions no longer mention wp-config.php.

= 1.5.0 =
* New: Sign-in tab. Social sign-in with Google, Facebook, GitHub, Microsoft and LinkedIn, with state and PKCE checks, matched only on verified addresses.
* New: two-factor authentication with an authenticator app, a QR code drawn on the server, recovery codes, remembered devices and required roles. It covers password and provider sign-ins.
* New: `[sepo_social_login]` and `[sepo_account_security]` shortcodes, a Sign-in section on the Profile screen, a Sign-in column on the Users list, and an administrator reset.
* New: personal data export and erase include linked accounts.
* Changed: "Restrict REST API" always lets the sign-in callback through. "Hide login error details" keeps sign-in step messages, which do not reveal whether an account exists.

= 1.4.0 =
* New: show field groups on the site automatically, before or after the content, as a table, grid or inline.
* New: field values as sortable columns on the admin posts lists.
* New: Field group block and `[sepo_fields]` shortcode for a whole group.
* New: Elementor widgets (Post field, Field group) with style controls, and Elementor dynamic tags for field values and links.
* New: hide a field from the site while keeping it in the editor.

= 1.3.0 =
* New: Cookies tab with a cookie consent banner. Accept all, reject all or choose by category, with every cookie listed for visitors to see. Site scanner, held-back tracking scripts and embeds, Google Consent Mode v2, the WP Consent API, a settings link that can go anywhere, a cookie list shortcode, and consent records with CSV export.
* Admin styles and scripts now refresh as soon as they change.

= 1.2.0 =
* New: "Styled login page" feature (Branding). Log in, lost password, reset password and register become one flat card in your colours, with your logo, a title and a footer line. Settings: title, footer ({year}, {site}), logo address, main and hover colours. Switch it off to get the standard WordPress login back.

= 1.1.0 =
* New: the admin panel's REST API (it was missing, so the panel could not load or save).
* New: Membership tab (tiers, default tier, post types it applies to, links); "Members only" box on posts; tier on user profiles and the Users list.
* New: Visibility panel for every block and the Post field block in the block editor; [sepo_field] shortcode.
* New: feature descriptions and settings; more shipped features; Restore defaults.
* New: field types email, link and date; choice lists check their values.
* Fixed: features that hook into `after_setup_theme` never ran (features now start on `plugins_loaded`).
* Fixed: menu visibility settings were never saved (the save hook read the wrong argument).
* Fixed: comments on gated posts were still shown.
* Fixed: a site-wide default tier locked every page, including the join and login pages. It now applies only to the post types you choose.
* Fixed: new post types and taxonomies returned 404 until permalinks were re-saved.
* Fixed: a field id could overwrite WordPress's own data (`_thumbnail_id`, `post_title`…). Ids starting with `_` are refused, and form names are namespaced.
* Fixed: deleting a field group deleted that meta key on every post of every type. Saved values are now kept unless you ask, and then only for that group's post types.
* Fixed: the Post field block could print any private meta. It now prints only declared fields.
* Security: SVG uploads are limited to administrators and checked for scripts; more dangerous file types are blocked, including double extensions.
* Security: limit-login, rate-limit and user-enumeration rules are tightened.
* Changed: the join/account link filters and the default-tier option now use sepo_wb_ names.

= 1.0.0 =
* First release.
