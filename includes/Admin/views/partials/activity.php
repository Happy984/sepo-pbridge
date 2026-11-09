<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the method that includes this file.
defined('ABSPATH') || exit;

use SepoWB\Site\ActivityPage;
use SepoWB\Site\SiteSettings;

$s = SiteSettings::section('activity');
$saved = isset($_GET['wb_saved'], $_GET['tab']) && $_GET['tab'] === 'activity'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$roles = array_merge(['guest' => __('Visitors (not signed in)', 'sepo-bridge')], array_map('translate_user_role', wp_roles()->get_names()));
echo $saved ? '<div class="wb-note wb-note--ok" role="status">' . esc_html__('Activity settings saved.', 'sepo-bridge') . '</div>' : '';
echo SiteSettings::formStart('activity'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
?>
<section class="wb-site-card">
  <?php echo SiteSettings::toggle('enabled', !empty($s['enabled']), __('Record activity (master switch)', 'sepo-bridge'), __('Nothing is recorded while this is off. Visitors are counted without cookies, and no IP addresses are stored.', 'sepo-bridge')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <div class="wb-checks">
    <label class="wb-field--check"><input type="checkbox" name="site[views]" value="1"<?php checked(!empty($s['views'])); ?>> <?php esc_html_e('Page views', 'sepo-bridge'); ?></label>
    <label class="wb-field--check"><input type="checkbox" name="site[actions]" value="1"<?php checked(!empty($s['actions'])); ?>> <?php esc_html_e('Sign-ins, publishing, uploads, comments, account and plugin changes', 'sepo-bridge'); ?></label>
    <label class="wb-field--check"><input type="checkbox" name="site[skip_admin_views]" value="1"<?php checked(!empty($s['skip_admin_views'])); ?>> <?php esc_html_e('Leave out page views by administrators', 'sepo-bridge'); ?></label>
  </div>
  <h3 class="wb-h3"><?php esc_html_e('Roles to record', 'sepo-bridge'); ?></h3>
  <?php echo SiteSettings::checks('roles', $roles, (array) $s['roles']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <label class="wb-field"><?php esc_html_e('Keep records for', 'sepo-bridge'); ?>
    <select name="site[keep_days]">
      <?php foreach ([30, 90, 180, 365, 730] as $d) : ?>
        <option value="<?php echo esc_attr((string) $d); ?>"<?php selected((int) $s['keep_days'], $d); ?>><?php /* translators: %d: number of days */ echo esc_html(sprintf(__('%d days', 'sepo-bridge'), $d)); ?></option>
      <?php endforeach; ?>
    </select></label>
  <p class="wb-help"><a href="<?php echo esc_url(ActivityPage::url()); ?>"><?php esc_html_e('Open the activity log and page views', 'sepo-bridge'); ?> →</a></p>
  <p class="wb-help"><?php esc_html_e('Mention the page-view count in your privacy policy. Other plugins can add their own lines with do_action( \'sepo_wb_activity\', $action, $type, $id, $title ).', 'sepo-bridge'); ?></p>
</section>
<?php
echo SiteSettings::saveButton(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
