<?php
if (!defined('ABSPATH')) {
	exit;
}

if (isset($_POST['wsform_ml_settings_submit'])) {
	check_admin_referer('wsform_ml_settings');
	
	update_option('wsform_ml_auto_scan', isset($_POST['auto_scan']) ? 1 : 0);
	update_option('wsform_ml_show_warnings', isset($_POST['show_warnings']) ? 1 : 0);
	update_option('wsform_ml_polylang_sync', isset($_POST['polylang_sync']) ? 1 : 0);
	
	echo '<div class="notice notice-success"><p>' . __('Einstellungen gespeichert.', 'wsform-ml') . '</p></div>';
}

$auto_scan = get_option('wsform_ml_auto_scan', 0);
$show_warnings = get_option('wsform_ml_show_warnings', 1);
$polylang_sync = get_option('wsform_ml_polylang_sync', 0);
?>

<div class="wrap">
	<h1><?php _e('WSForm Multilingual - Einstellungen', 'wsform-ml'); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field('wsform_ml_settings'); ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="auto_scan"><?php _e('Automatisches Scannen', 'wsform-ml'); ?></label>
				</th>
				<td>
					<input type="checkbox" id="auto_scan" name="auto_scan" value="1" <?php checked($auto_scan, 1); ?>>
					<p class="description">
						<?php _e('Formulare automatisch scannen, wenn sie gespeichert werden.', 'wsform-ml'); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="show_warnings"><?php _e('Warnungen anzeigen', 'wsform-ml'); ?></label>
				</th>
				<td>
					<input type="checkbox" id="show_warnings" name="show_warnings" value="1" <?php checked($show_warnings, 1); ?>>
					<p class="description">
						<?php _e('Warnungen bei fehlenden Übersetzungen im Backend anzeigen.', 'wsform-ml'); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="polylang_sync"><?php _e('Polylang String-Synchronisation', 'wsform-ml'); ?></label>
				</th>
				<td>
					<input type="checkbox" id="polylang_sync" name="polylang_sync" value="1" <?php checked($polylang_sync, 1); ?>>
					<p class="description">
						<?php _e('Übersetzungen auch in Polylang String Translation registrieren.', 'wsform-ml'); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php _e('System-Informationen', 'wsform-ml'); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php _e('Plugin-Version', 'wsform-ml'); ?></th>
				<td><?php echo WSFORM_ML_VERSION; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php _e('WS Form', 'wsform-ml'); ?></th>
				<td>
					<?php
					if (class_exists('WS_Form')) {
						echo '<span style="color: green;">✓ ' . __('Installiert', 'wsform-ml') . '</span>';
					} else {
						echo '<span style="color: red;">✗ ' . __('Nicht installiert', 'wsform-ml') . '</span>';
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Polylang', 'wsform-ml'); ?></th>
				<td>
					<?php
					if (WSForm_ML_Polylang_Integration::is_polylang_active()) {
						echo '<span style="color: green;">✓ ' . __('Aktiv', 'wsform-ml') . '</span>';
						$languages = WSForm_ML_Polylang_Integration::get_languages();
						echo ' (' . count($languages) . ' ' . __('Sprachen', 'wsform-ml') . ')';
					} else {
						echo '<span style="color: orange;">⚠ ' . __('Nicht aktiv', 'wsform-ml') . '</span>';
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Datenbank-Version', 'wsform-ml'); ?></th>
				<td><?php echo get_option('wsform_ml_db_version', 'N/A'); ?></td>
			</tr>
		</table>

		<?php submit_button(__('Einstellungen speichern', 'wsform-ml'), 'primary', 'wsform_ml_settings_submit'); ?>
	</form>

	<hr>

	<h2><?php _e('Datenbank-Wartung', 'wsform-ml'); ?></h2>
	<p><?php _e('Vorsicht: Diese Aktionen können nicht rückgängig gemacht werden!', 'wsform-ml'); ?></p>
	
	<button class="button button-secondary" onclick="if(confirm('<?php _e('Möchten Sie wirklich alle Caches löschen?', 'wsform-ml'); ?>')) { alert('<?php _e('Funktion noch nicht implementiert', 'wsform-ml'); ?>'); }">
		<?php _e('Cache leeren', 'wsform-ml'); ?>
	</button>
</div>
