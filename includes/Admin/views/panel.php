<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the method that includes this file.
defined('ABSPATH') || exit;

use SepoWB\Admin\View;

$sepo_wb_groups = [];
foreach ($sections as $sepo_wb_slug => $sepo_wb_s) {
    $sepo_wb_groups[$sepo_wb_s['group']][$sepo_wb_slug] = $sepo_wb_s;
}
?>
<div class="wrap wb-wrap">
<div class="wb-app" data-wb-manage="<?php echo current_user_can('manage_options') ? '1' : '0'; ?>">
  <header class="wb-top">
    <span class="wb-top-logo" aria-hidden="true"><?php echo View::icon('bridge', 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG. ?></span>
    <div class="wb-top-text">
      <h1 class="wb-title"><?php esc_html_e('Sepo Bridge', 'sepo-bridge'); ?></h1>
      <p class="wb-top-sub"><?php esc_html_e('Site settings', 'sepo-bridge'); ?></p>
    </div>
    <span class="wb-top-ver"><?php
        /* translators: %s: plugin version number */
        echo esc_html(sprintf(__('Version %s', 'sepo-bridge'), SEPO_WB_VERSION));
    ?></span>
  </header>
  <hr class="wp-header-end">

  <div class="wb-layout">
    <nav class="wb-side" aria-label="<?php esc_attr_e('Settings sections', 'sepo-bridge'); ?>">
      <label class="wb-side-pick">
        <span class="screen-reader-text"><?php esc_html_e('Section', 'sepo-bridge'); ?></span>
        <select data-wb-pick>
          <?php foreach ($sepo_wb_groups as $sepo_wb_group => $sepo_wb_items) : ?>
            <optgroup label="<?php echo esc_attr($sepo_wb_group); ?>">
              <?php foreach ($sepo_wb_items as $sepo_wb_slug => $sepo_wb_s) : ?>
                <option value="<?php echo esc_attr($sepo_wb_slug); ?>" <?php selected($sepo_wb_slug, $active); ?>><?php echo esc_html($sepo_wb_s['label']); ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="wb-tabs" role="tablist" aria-orientation="vertical" aria-label="<?php esc_attr_e('Sections', 'sepo-bridge'); ?>">
        <?php foreach ($sepo_wb_groups as $sepo_wb_group => $sepo_wb_items) : ?>
          <div class="wb-side-group" role="presentation"><?php echo esc_html($sepo_wb_group); ?></div>
          <?php foreach ($sepo_wb_items as $sepo_wb_slug => $sepo_wb_s) : ?>
            <button type="button" role="tab" id="wb-tab-<?php echo esc_attr($sepo_wb_slug); ?>" aria-controls="wb-panel-<?php echo esc_attr($sepo_wb_slug); ?>"
                    class="wb-tab<?php echo $sepo_wb_slug === $active ? ' is-on' : ''; ?>" data-wb-tab="<?php echo esc_attr($sepo_wb_slug); ?>"
                    aria-selected="<?php echo $sepo_wb_slug === $active ? 'true' : 'false'; ?>" tabindex="<?php echo $sepo_wb_slug === $active ? '0' : '-1'; ?>">
              <?php echo View::icon($sepo_wb_s['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG. ?>
              <span><?php echo esc_html($sepo_wb_s['label']); ?></span>
            </button>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </nav>

    <main class="wb-main">
      <?php foreach ($sections as $sepo_wb_slug => $sepo_wb_s) : ?>
        <section class="wb-panel" role="tabpanel" id="wb-panel-<?php echo esc_attr($sepo_wb_slug); ?>" aria-labelledby="wb-tab-<?php echo esc_attr($sepo_wb_slug); ?>" data-wb-panel="<?php echo esc_attr($sepo_wb_slug); ?>" <?php echo $sepo_wb_slug !== $active ? 'hidden' : ''; ?>>
          <header class="wb-section-head">
            <span class="wb-section-icon" aria-hidden="true"><?php echo View::icon($sepo_wb_s['icon'], 22); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG. ?></span>
            <div>
              <h2 class="wb-section-title"><?php echo esc_html($sepo_wb_s['label']); ?></h2>
              <p class="wb-section-desc"><?php echo esc_html($sepo_wb_s['desc']); ?></p>
            </div>
          </header>
          <div class="wb-section-body">
            <?php require __DIR__ . '/partials/' . $sepo_wb_slug . '.php'; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </main>
  </div>

  <div class="wb-toast" data-wb-toast role="status" aria-live="polite" hidden></div>
</div>
</div>
