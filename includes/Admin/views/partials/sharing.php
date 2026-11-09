<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the method that includes this file.
defined('ABSPATH') || exit;

use SepoWB\Site\Share;
use SepoWB\Site\SiteSettings;

$s = SiteSettings::section('share');
$saved = isset($_GET['wb_saved'], $_GET['tab']) && $_GET['tab'] === 'sharing'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$nets = [];
foreach (Share::networks() as $k => [$label]) {
    $nets[$k] = $label;
}
$nets['native'] = __('The phone\'s own share menu (where the browser offers it)', 'sepo-bridge');
echo $saved ? '<div class="wb-note wb-note--ok" role="status">' . esc_html__('Sharing settings saved.', 'sepo-bridge') . '</div>' : '';
echo SiteSettings::formStart('share'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
?>
<section class="wb-site-card">
  <?php echo SiteSettings::toggle('enabled', !empty($s['enabled']), __('Show share buttons', 'sepo-bridge'), __('Plain links to each network. Nothing loads from the networks, so no cookies are set until someone clicks.', 'sepo-bridge')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <h3 class="wb-h3"><?php esc_html_e('Buttons', 'sepo-bridge'); ?></h3>
  <?php echo SiteSettings::checks('networks', $nets, (array) $s['networks']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <h3 class="wb-h3"><?php esc_html_e('Where', 'sepo-bridge'); ?></h3>
  <?php echo SiteSettings::checks('post_types', SiteSettings::postTypes(), (array) $s['post_types']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <div class="wb-grid2">
    <label class="wb-field"><?php esc_html_e('Position', 'sepo-bridge'); ?>
      <select name="site[placement]">
        <?php foreach (['after' => __('After the content', 'sepo-bridge'), 'before' => __('Before the content', 'sepo-bridge'), 'both' => __('Before and after', 'sepo-bridge'), 'none' => __('Only where [sepo_share] is placed', 'sepo-bridge')] as $k => $l) : ?>
          <option value="<?php echo esc_attr($k); ?>"<?php selected((string) $s['placement'], $k); ?>><?php echo esc_html($l); ?></option>
        <?php endforeach; ?>
      </select></label>
    <label class="wb-field"><?php esc_html_e('Look', 'sepo-bridge'); ?>
      <select name="site[style]">
        <option value="filled"<?php selected((string) $s['style'], 'filled'); ?>><?php esc_html_e('Filled with each network\'s colour', 'sepo-bridge'); ?></option>
        <option value="outline"<?php selected((string) $s['style'], 'outline'); ?>><?php esc_html_e('Outlined, in the text colour', 'sepo-bridge'); ?></option>
      </select></label>
  </div>
  <label class="wb-field"><?php esc_html_e('Heading before the buttons', 'sepo-bridge'); ?>
    <input type="text" name="site[label]" maxlength="60" value="<?php echo esc_attr((string) $s['label']); ?>" placeholder="<?php esc_attr_e('Share', 'sepo-bridge'); ?>"></label>
  <h3 class="wb-h3"><?php esc_html_e('Site-wide or per post', 'sepo-bridge'); ?></h3>
  <label class="wb-field--check"><input type="checkbox" name="site[per_post]" value="1"<?php checked(!empty($s['per_post'])); ?>> <?php esc_html_e('Let each post show or hide the buttons, whatever is set above', 'sepo-bridge'); ?></label>
  <p class="wb-help"><?php esc_html_e('When this is off, the choices above apply to every post. When it is on, the editing screen offers "As set for the site", "Show" and "Hide".', 'sepo-bridge'); ?></p>
  <p class="wb-help"><?php esc_html_e('Shortcode for anywhere: [sepo_share] or [sepo_share networks="whatsapp,facebook,copy"].', 'sepo-bridge'); ?></p>
</section>
<?php
echo SiteSettings::saveButton(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
