<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the method that includes this file.
defined('ABSPATH') || exit;

use SepoWB\Site\Seo;
use SepoWB\Site\SiteSettings;

$s = SiteSettings::section('seo');
$other = Seo::otherPlugin();
$saved = isset($_GET['wb_saved'], $_GET['tab']) && $_GET['tab'] === 'seo'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$img = (int) $s['default_image'];
$imgSrc = $img > 0 ? wp_get_attachment_image_url($img, 'medium') : '';
echo $saved ? '<div class="wb-note wb-note--ok" role="status">' . esc_html__('Search settings saved.', 'sepo-bridge') . '</div>' : '';
if ($other !== '') {
    /* translators: %s: name of another SEO plugin */
    echo '<div class="wb-note" role="status">' . esc_html(sprintf(__('%s is active, so it writes the titles, descriptions and social tags. The sitemap choices below still apply to the WordPress sitemap.', 'sepo-bridge'), $other)) . '</div>';
}
echo SiteSettings::formStart('seo'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
?>
<section class="wb-site-card">
  <?php echo SiteSettings::toggle('enabled', !empty($s['enabled']), __('Write search and social tags', 'sepo-bridge'), __('Titles, descriptions, canonical addresses, robots rules, social previews and structured data. Each post gets its own fields in the "Search, sharing and contents" box.', 'sepo-bridge')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <div class="wb-grid2">
    <label class="wb-field"><?php esc_html_e('Home page title', 'sepo-bridge'); ?>
      <input type="text" name="site[home_title]" maxlength="120" value="<?php echo esc_attr((string) $s['home_title']); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name') . ' – ' . get_bloginfo('description')); ?>"></label>
    <label class="wb-field"><?php esc_html_e('Title separator', 'sepo-bridge'); ?>
      <input type="text" name="site[separator]" maxlength="3" class="small-text" value="<?php echo esc_attr((string) $s['separator']); ?>"></label>
  </div>
  <label class="wb-field"><?php esc_html_e('Home page description', 'sepo-bridge'); ?>
    <textarea name="site[home_desc]" rows="2" maxlength="320"><?php echo esc_textarea((string) $s['home_desc']); ?></textarea>
    <small><?php esc_html_e('About 155 characters show in search results.', 'sepo-bridge'); ?></small></label>
  <div class="wb-field"><?php esc_html_e('Social image when a page has none', 'sepo-bridge'); ?>
    <div class="wb-site-media" data-wb-media>
      <input type="hidden" name="site[default_image]" value="<?php echo esc_attr((string) ($img ?: '')); ?>" data-wb-media-id>
      <img src="<?php echo esc_url((string) $imgSrc); ?>" alt="" data-wb-media-preview<?php echo $imgSrc ? '' : ' hidden'; ?>>
      <button type="button" class="wb-btn" data-wb-media-pick><?php esc_html_e('Choose image', 'sepo-bridge'); ?></button>
      <button type="button" class="wb-btn wb-btn--link" data-wb-media-clear<?php echo $img ? '' : ' hidden'; ?>><?php esc_html_e('Remove', 'sepo-bridge'); ?></button>
    </div>
    <small><?php esc_html_e('1200 × 630 pixels suits most networks.', 'sepo-bridge'); ?></small>
  </div>
  <div class="wb-checks">
    <label class="wb-field--check"><input type="checkbox" name="site[og]" value="1"<?php checked(!empty($s['og'])); ?>> <?php esc_html_e('Social previews (Open Graph and card tags)', 'sepo-bridge'); ?></label>
    <label class="wb-field--check"><input type="checkbox" name="site[schema]" value="1"<?php checked(!empty($s['schema'])); ?>> <?php esc_html_e('Structured data for the site and articles', 'sepo-bridge'); ?></label>
    <label class="wb-field--check"><input type="checkbox" name="site[noindex_search]" value="1"<?php checked(!empty($s['noindex_search'])); ?>> <?php esc_html_e('Keep search result pages out of search engines', 'sepo-bridge'); ?></label>
    <label class="wb-field--check"><input type="checkbox" name="site[noindex_paged]" value="1"<?php checked(!empty($s['noindex_paged'])); ?>> <?php esc_html_e('Keep page 2 and later of archives out of search engines', 'sepo-bridge'); ?></label>
  </div>
  <h3 class="wb-h3"><?php esc_html_e('Search engine verification', 'sepo-bridge'); ?></h3>
  <p class="wb-help"><?php esc_html_e('Paste only the code from the meta tag each service gives you.', 'sepo-bridge'); ?></p>
  <div class="wb-grid2">
    <label class="wb-field"><?php esc_html_e('Google Search Console', 'sepo-bridge'); ?><input type="text" name="site[google]" value="<?php echo esc_attr((string) $s['google']); ?>" autocomplete="off" spellcheck="false"></label>
    <label class="wb-field"><?php esc_html_e('Bing Webmaster Tools', 'sepo-bridge'); ?><input type="text" name="site[bing]" value="<?php echo esc_attr((string) $s['bing']); ?>" autocomplete="off" spellcheck="false"></label>
  </div>
</section>

<section class="wb-site-card">
  <?php echo SiteSettings::toggle('sitemap', !empty($s['sitemap']), __('XML sitemap', 'sepo-bridge'), __('Lists the pages search engines should find, with the date each last changed. Posts hidden from search engines are left out.', 'sepo-bridge')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <p class="wb-help"><a href="<?php echo esc_url(home_url('/wp-sitemap.xml')); ?>" target="_blank" rel="noopener"><?php echo esc_html(home_url('/wp-sitemap.xml')); ?></a>
  <?php if (!get_option('blog_public')) : ?>
    — <strong><?php esc_html_e('Settings → Reading asks search engines not to index this site, so WordPress serves no sitemap.', 'sepo-bridge'); ?></strong>
  <?php endif; ?></p>
  <h3 class="wb-h3"><?php esc_html_e('Content types in the sitemap', 'sepo-bridge'); ?></h3>
  <?php echo SiteSettings::checks('sitemap_types', SiteSettings::postTypes(), (array) $s['sitemap_types']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <h3 class="wb-h3"><?php esc_html_e('Groupings in the sitemap', 'sepo-bridge'); ?></h3>
  <?php echo SiteSettings::checks('sitemap_tax', SiteSettings::taxonomies(), (array) $s['sitemap_tax']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <label class="wb-field--check"><input type="checkbox" name="site[sitemap_users]" value="1"<?php checked(!empty($s['sitemap_users'])); ?>> <?php esc_html_e('Author pages', 'sepo-bridge'); ?></label>
</section>
<?php
echo SiteSettings::saveButton(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
