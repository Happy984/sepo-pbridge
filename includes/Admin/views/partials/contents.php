<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the method that includes this file.
defined('ABSPATH') || exit;

use SepoWB\Site\SiteSettings;

$s = SiteSettings::section('toc');
$saved = isset($_GET['wb_saved'], $_GET['tab']) && $_GET['tab'] === 'contents'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
echo $saved ? '<div class="wb-note wb-note--ok" role="status">' . esc_html__('Contents settings saved.', 'sepo-bridge') . '</div>' : '';
echo SiteSettings::formStart('toc'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
?>
<section class="wb-site-card">
  <?php echo SiteSettings::toggle('enabled', !empty($s['enabled']), __('Add a table of contents on its own', 'sepo-bridge'), __('Built from the headings in the text. Each heading gets an address, so a section can be linked to directly.', 'sepo-bridge')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <h3 class="wb-h3"><?php esc_html_e('On', 'sepo-bridge'); ?></h3>
  <?php echo SiteSettings::checks('post_types', SiteSettings::postTypes(), (array) $s['post_types']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <div class="wb-site-positions" role="radiogroup" aria-label="<?php esc_attr_e('Position', 'sepo-bridge'); ?>">
    <?php foreach (['left' => __('Left of the text', 'sepo-bridge'), 'right' => __('Right of the text', 'sepo-bridge'), 'top' => __('Across the top', 'sepo-bridge')] as $k => $l) : ?>
      <label class="wb-site-pos wb-site-pos--<?php echo esc_attr($k); ?>"><input type="radio" name="site[position]" value="<?php echo esc_attr($k); ?>"<?php checked((string) $s['position'], $k); ?>>
        <span class="wb-site-pos__pic" aria-hidden="true"><i></i><b></b><b></b><b></b></span><span><?php echo esc_html($l); ?></span></label>
    <?php endforeach; ?>
  </div>
  <p class="wb-help"><?php esc_html_e('On phones the box always spans the full width, above the text.', 'sepo-bridge'); ?></p>
  <div class="wb-grid2">
    <label class="wb-field"><?php esc_html_e('Show it when a post has at least', 'sepo-bridge'); ?>
      <select name="site[min]"><?php for ($i = 2; $i <= 10; $i++) : ?><option value="<?php echo esc_attr((string) $i); ?>"<?php selected((int) $s['min'], $i); ?>><?php /* translators: %d: number of headings */ echo esc_html(sprintf(_n('%d heading', '%d headings', $i, 'sepo-bridge'), $i)); ?></option><?php endfor; ?></select></label>
    <label class="wb-field"><?php esc_html_e('Headings to list', 'sepo-bridge'); ?>
      <select name="site[depth]">
        <option value="2"<?php selected((int) $s['depth'], 2); ?>><?php esc_html_e('Main headings only (H2)', 'sepo-bridge'); ?></option>
        <option value="3"<?php selected((int) $s['depth'], 3); ?>><?php esc_html_e('Main and sub-headings (H2–H3)', 'sepo-bridge'); ?></option>
        <option value="4"<?php selected((int) $s['depth'], 4); ?>><?php esc_html_e('Three levels (H2–H4)', 'sepo-bridge'); ?></option>
      </select></label>
  </div>
  <label class="wb-field"><?php esc_html_e('Box heading', 'sepo-bridge'); ?>
    <input type="text" name="site[title]" maxlength="60" value="<?php echo esc_attr((string) $s['title']); ?>" placeholder="<?php esc_attr_e('Contents', 'sepo-bridge'); ?>"></label>
  <label class="wb-field--check"><input type="checkbox" name="site[open]" value="1"<?php checked(!empty($s['open'])); ?>> <?php esc_html_e('Open when the page loads (readers can fold it away)', 'sepo-bridge'); ?></label>
  <label class="wb-field--check"><input type="checkbox" name="site[per_post]" value="1"<?php checked(!empty($s['per_post'])); ?>> <?php esc_html_e('Let each post show or hide the box', 'sepo-bridge'); ?></label>
  <p class="wb-help"><?php esc_html_e('Place [sepo_toc] in a post to put the box at that spot. Add data-no-toc to a heading to leave it out.', 'sepo-bridge'); ?></p>
</section>
<?php
echo SiteSettings::saveButton(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
