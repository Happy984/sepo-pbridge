<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to the method that includes this file.
defined('ABSPATH') || exit;

use SepoWB\Site\Roles;
use SepoWB\Site\SiteSettings;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen state
$editing = isset($_GET['role']) ? sanitize_key(wp_unslash((string) $_GET['role'])) : '';
$msg = isset($_GET['wb_role']) ? sanitize_key(wp_unslash((string) $_GET['wb_role'])) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$ours = Roles::ours();
$names = array_map('translate_user_role', wp_roles()->get_names());
$counts = Roles::counts();
$tracked = (array) SiteSettings::section('activity')['roles'];
$notes = [
    'created' => __('Role added. Tick what it may do, then save.', 'sepo-bridge'),
    'saved'   => __('Role saved.', 'sepo-bridge'),
    'deleted' => __('Role deleted. Its people were moved to the role you chose.', 'sepo-bridge'),
    'exists'  => __('A role with that name already exists.', 'sepo-bridge'),
    'name'    => __('Give the role a name.', 'sepo-bridge'),
];
if (isset($notes[$msg])) {
    echo '<div class="wb-note' . (in_array($msg, ['exists', 'name'], true) ? ' wb-note--warn' : ' wb-note--ok') . '" role="status">' . esc_html($notes[$msg]) . '</div>';
}
$action = esc_url(admin_url('admin-post.php'));
$nonce = wp_nonce_field(Roles::ACTION, '_wpnonce', true, false);
?>
<section class="wb-site-card">
  <h2 class="wb-h2"><?php esc_html_e('Add a role', 'sepo-bridge'); ?></h2>
  <form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>" class="wb-site-inline" data-wb-busy>
    <input type="hidden" name="action" value="<?php echo esc_attr(Roles::ACTION); ?>"><input type="hidden" name="do" value="create">
    <?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field markup from WordPress ?>
    <label class="wb-field"><?php esc_html_e('Name', 'sepo-bridge'); ?><input type="text" name="name" required maxlength="60" placeholder="<?php esc_attr_e('For example: Content reviewer', 'sepo-bridge'); ?>"></label>
    <label class="wb-field"><?php esc_html_e('Start from', 'sepo-bridge'); ?>
      <select name="copy"><option value=""><?php esc_html_e('Nothing but reading', 'sepo-bridge'); ?></option>
        <?php foreach ($names as $k => $l) : ?><option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($l); ?></option><?php endforeach; ?>
      </select></label>
    <button type="submit" class="wb-btn wb-btn--primary" data-busy-label="<?php esc_attr_e('Adding…', 'sepo-bridge'); ?>"><?php esc_html_e('Add role', 'sepo-bridge'); ?></button>
  </form>
</section>

<section class="wb-site-card">
  <h2 class="wb-h2"><?php esc_html_e('Roles on this site', 'sepo-bridge'); ?></h2>
  <div class="wb-site-scroll"><table class="widefat striped wb-site-table">
    <thead><tr><th scope="col"><?php esc_html_e('Role', 'sepo-bridge'); ?></th><th scope="col" class="num"><?php esc_html_e('People', 'sepo-bridge'); ?></th><th scope="col"><?php esc_html_e('Activity recorded', 'sepo-bridge'); ?></th><th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'sepo-bridge'); ?></span></th></tr></thead>
    <tbody>
    <?php foreach (array_merge(['guest' => __('Visitors (not signed in)', 'sepo-bridge')], $names) as $slug => $label) : $mine = in_array($slug, $ours, true); ?>
      <tr>
        <td><strong><?php echo esc_html($label); ?></strong> <code><?php echo esc_html($slug); ?></code><?php echo $mine ? ' <span class="wb-tag wb-tag--on">' . esc_html__('Made here', 'sepo-bridge') . '</span>' : ''; ?></td>
        <td class="num"><?php echo esc_html($slug === 'guest' ? '—' : number_format_i18n($counts[$slug] ?? 0)); ?></td>
        <td>
          <form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>" data-wb-autosubmit>
            <input type="hidden" name="action" value="<?php echo esc_attr(Roles::ACTION); ?>"><input type="hidden" name="do" value="track"><input type="hidden" name="role" value="<?php echo esc_attr($slug); ?>">
            <?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field markup from WordPress ?>
            <label class="wb-switch"><input type="checkbox" name="tracked" value="1"<?php checked(in_array($slug, $tracked, true)); ?> aria-label="<?php /* translators: %s: role name */ echo esc_attr(sprintf(__('Record activity for %s', 'sepo-bridge'), $label)); ?>"><span class="wb-slider"></span></label>
            <noscript><button class="wb-btn wb-btn--sm"><?php esc_html_e('Save', 'sepo-bridge'); ?></button></noscript>
          </form>
        </td>
        <td class="wb-actions-cell">
          <?php if ($mine) : ?>
            <a class="wb-btn wb-btn--sm" href="<?php echo esc_url(add_query_arg(['page' => \SepoWB\Admin\AdminController::SLUG, 'tab' => 'roles', 'role' => $slug], admin_url('admin.php'))); ?>#wb-role-edit"><?php esc_html_e('Edit', 'sepo-bridge'); ?></a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="wb-help"><?php esc_html_e('WordPress\'s own roles and roles from other plugins can be copied but are not changed here.', 'sepo-bridge'); ?></p>
</section>

<?php if ($editing !== '' && in_array($editing, $ours, true) && ($role = get_role($editing)) !== null) : ?>
<section class="wb-site-card" id="wb-role-edit">
  <form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>" data-wb-busy>
    <input type="hidden" name="action" value="<?php echo esc_attr(Roles::ACTION); ?>"><input type="hidden" name="do" value="save"><input type="hidden" name="role" value="<?php echo esc_attr($editing); ?>">
    <?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field markup from WordPress ?>
    <?php /* translators: %s: role name */ ?>
    <h2 class="wb-h2"><?php echo esc_html(sprintf(__('What %s may do', 'sepo-bridge'), $names[$editing] ?? $editing)); ?></h2>
    <div class="wb-grid2">
      <label class="wb-field"><?php esc_html_e('Name', 'sepo-bridge'); ?><input type="text" name="name" maxlength="60" value="<?php echo esc_attr($names[$editing] ?? ''); ?>"></label>
      <label class="wb-field"><?php esc_html_e('Find a permission', 'sepo-bridge'); ?><input type="search" data-wb-cap-filter placeholder="<?php esc_attr_e('Type to filter…', 'sepo-bridge'); ?>"></label>
    </div>
    <label class="wb-field--check"><input type="checkbox" name="tracked" value="1"<?php checked(in_array($editing, $tracked, true)); ?>> <?php esc_html_e('Record this role\'s page views and actions', 'sepo-bridge'); ?></label>
    <div class="wb-site-caps">
    <?php foreach (Roles::groups() as $gk => $g) : ?>
      <fieldset class="wb-site-capgroup" data-wb-capgroup>
        <legend><?php echo esc_html($g['label']); ?> <button type="button" class="wb-btn wb-btn--link wb-btn--sm" data-wb-cap-all><?php esc_html_e('All', 'sepo-bridge'); ?></button> <button type="button" class="wb-btn wb-btn--link wb-btn--sm" data-wb-cap-none><?php esc_html_e('None', 'sepo-bridge'); ?></button></legend>
        <?php foreach ($g['caps'] as $cap => $label) : $can = Roles::holds($cap); ?>
          <label class="wb-site-cap<?php echo $can ? '' : ' is-locked'; ?>" data-search="<?php echo esc_attr(strtolower($label . ' ' . $cap)); ?>">
            <input type="checkbox" name="caps[]" value="<?php echo esc_attr($cap); ?>"<?php checked($role->has_cap($cap)); ?><?php disabled(!$can); ?>>
            <span><?php echo esc_html($label); ?> <code><?php echo esc_html($cap); ?></code></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>
    </div>
    <p class="wb-help"><?php esc_html_e('Permissions you do not have yourself are shown but cannot be given.', 'sepo-bridge'); ?></p>
    <p class="wb-site-actions"><button type="submit" class="wb-btn wb-btn--primary" data-busy-label="<?php esc_attr_e('Saving…', 'sepo-bridge'); ?>"><?php esc_html_e('Save role', 'sepo-bridge'); ?></button></p>
  </form>
  <form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>" class="wb-site-danger">
    <input type="hidden" name="action" value="<?php echo esc_attr(Roles::ACTION); ?>"><input type="hidden" name="do" value="delete"><input type="hidden" name="role" value="<?php echo esc_attr($editing); ?>">
    <?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field markup from WordPress ?>
    <label class="wb-field"><?php esc_html_e('Move its people to', 'sepo-bridge'); ?>
      <select name="move_to"><?php foreach ($names as $k => $l) : if ($k === $editing) { continue; } ?><option value="<?php echo esc_attr($k); ?>"<?php selected($k, (string) get_option('default_role', 'subscriber')); ?>><?php echo esc_html($l); ?></option><?php endforeach; ?></select></label>
    <?php /* translators: 1: role name, 2: number of people */ ?>
    <button type="submit" class="wb-btn wb-btn--danger" data-wb-confirm="<?php echo esc_attr(sprintf(__('Delete the role "%1$s"? Its %2$d people move to the role chosen beside the button.', 'sepo-bridge'), $names[$editing] ?? $editing, $counts[$editing] ?? 0)); ?>" data-wb-confirm-ok="<?php esc_attr_e('Delete role', 'sepo-bridge'); ?>" data-busy-label="<?php esc_attr_e('Deleting…', 'sepo-bridge'); ?>"><?php esc_html_e('Delete this role', 'sepo-bridge'); ?></button>
  </form>
</section>
<?php endif; ?>
