<?php
/**
 * Admin Settings Page - Feature Management
 * 
 * @package WSForm_ML
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Settings_Page {

	private static $instance = null;
	private $feature_manager;

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->feature_manager = WSForm_ML_Feature_Manager::instance();
		
		// Menü wird bereits in class-admin-menu.php registriert
		add_action('admin_post_wsform_ml_toggle_feature', [$this, 'handle_feature_toggle']);
		add_action('admin_post_wsform_ml_create_language_field', [$this, 'handle_create_language_field']);
		add_action('admin_post_wsform_ml_remove_language_field', [$this, 'handle_remove_language_field']);
	}

	/**
	 * Render Settings Page
	 */
	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Keine Berechtigung', 'wsform-ml'));
		}

		$features = $this->feature_manager->get_all_features();
		$language_field_manager = WSForm_ML_Language_Field_Manager::instance();
		$available_forms = $language_field_manager->get_available_forms();
		$configured_fields = $language_field_manager->get_configured_fields();
		?>
		<div class="wrap wsform-ml-settings">
			<h1><?php _e('WSForm Multilingual - Einstellungen', 'wsform-ml'); ?></h1>

			<?php if (isset($_GET['updated'])): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php _e('Einstellungen gespeichert.', 'wsform-ml'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['field_created'])): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php printf(__('Sprachfeld erfolgreich erstellt (Field ID: %d)', 'wsform-ml'), intval($_GET['field_id'])); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['field_removed'])): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php _e('Sprachfeld-Konfiguration entfernt.', 'wsform-ml'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['error'])): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
				</div>
			<?php endif; ?>

			<div class="wsform-ml-settings-container">
				<h2><?php _e('Feature Management', 'wsform-ml'); ?></h2>
				<p class="description">
					<?php _e('Aktiviere oder deaktiviere Features. Beide Systeme können parallel laufen.', 'wsform-ml'); ?>
				</p>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e('Feature', 'wsform-ml'); ?></th>
							<th><?php _e('Beschreibung', 'wsform-ml'); ?></th>
							<th><?php _e('Status', 'wsform-ml'); ?></th>
							<th><?php _e('Aktion', 'wsform-ml'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($features as $feature_key => $feature): ?>
							<?php 
								$status = $this->feature_manager->get_feature_status($feature_key);
								$available = $status['available'];
								$enabled = $status['enabled'];
							?>
							<tr>
								<td>
									<strong><?php echo esc_html($feature['name']); ?></strong>
									<?php if (!empty($feature['requires'])): ?>
										<br>
										<small class="description">
											<?php _e('Benötigt:', 'wsform-ml'); ?> 
											<?php echo esc_html(implode(', ', $feature['requires'])); ?>
										</small>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html($feature['description']); ?>
								</td>
								<td>
									<?php if (!$available): ?>
										<span class="wsform-ml-status-badge unavailable">
											<span class="dashicons dashicons-warning"></span>
											<?php _e('Nicht verfügbar', 'wsform-ml'); ?>
										</span>
									<?php elseif ($enabled): ?>
										<span class="wsform-ml-status-badge active">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php _e('Aktiv', 'wsform-ml'); ?>
										</span>
									<?php else: ?>
										<span class="wsform-ml-status-badge inactive">
											<span class="dashicons dashicons-marker"></span>
											<?php _e('Inaktiv', 'wsform-ml'); ?>
										</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($available): ?>
										<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
											<?php wp_nonce_field('wsform_ml_toggle_feature', 'wsform_ml_nonce'); ?>
											<input type="hidden" name="action" value="wsform_ml_toggle_feature">
											<input type="hidden" name="feature" value="<?php echo esc_attr($feature_key); ?>">
											<button type="submit" class="button">
												<?php echo $enabled ? __('Deaktivieren', 'wsform-ml') : __('Aktivieren', 'wsform-ml'); ?>
											</button>
										</form>
									<?php else: ?>
										<span class="description">
											<?php _e('Feature nicht verfügbar', 'wsform-ml'); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Language Field Integration -->
				<h2 style="margin-top: 40px;"><?php _e('Sprachfeld-Integration', 'wsform-ml'); ?></h2>
				
				<div class="wsform-ml-info-box warning">
					<h4><span class="dashicons dashicons-warning"></span> <?php _e('Wichtiger Hinweis zu Polylang AJAX', 'wsform-ml'); ?></h4>
					<p>
						<?php _e('Diese Funktion setzt den Sprachcode beim Rendern des Formulars. Sie funktioniert nur, wenn Polylang <strong>OHNE AJAX</strong> konfiguriert ist (Standard-Einstellung mit Page Reload).', 'wsform-ml'); ?>
					</p>
					<p>
						<?php _e('Bei aktiviertem AJAX-Sprachwechsel wird das Formular nicht neu geladen und der Wert kann nicht serverseitig aktualisiert werden.', 'wsform-ml'); ?>
					</p>
				</div>

				<div class="wsform-ml-info-box">
					<h4><?php _e('Sprachfeld zu Formular hinzufügen', 'wsform-ml'); ?></h4>
					<p><?php _e('Erstellt ein Hidden Field im ausgewählten Formular, das automatisch mit dem aktuellen Sprachcode befüllt wird (z.B. "de", "en", "fr").', 'wsform-ml'); ?></p>
					<p><?php _e('Das Field ist im WSForm Admin sichtbar und kann für Conditions und Actions verwendet werden.', 'wsform-ml'); ?></p>

					<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 20px;">
						<?php wp_nonce_field('wsform_ml_create_language_field', 'wsform_ml_nonce'); ?>
						<input type="hidden" name="action" value="wsform_ml_create_language_field">
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="form_id"><?php _e('Formular auswählen', 'wsform-ml'); ?></label>
								</th>
								<td>
									<select name="form_id" id="form_id" class="regular-text" required>
										<option value=""><?php _e('-- Formular wählen --', 'wsform-ml'); ?></option>
										<?php foreach ($available_forms as $form): ?>
											<?php 
												$has_field = $language_field_manager->form_has_language_field($form->id);
												$disabled = $has_field ? 'disabled' : '';
												$label_suffix = $has_field ? ' (' . __('bereits konfiguriert', 'wsform-ml') . ')' : '';
											?>
											<option value="<?php echo esc_attr($form->id); ?>" <?php echo $disabled; ?>>
												<?php echo esc_html($form->label . $label_suffix); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php _e('Wählen Sie das Formular, zu dem ein Sprachfeld hinzugefügt werden soll.', 'wsform-ml'); ?>
									</p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary">
								<span class="dashicons dashicons-plus-alt"></span>
								<?php _e('Sprachfeld erstellen', 'wsform-ml'); ?>
							</button>
						</p>
					</form>
				</div>

				<?php if (!empty($configured_fields)): ?>
					<div class="wsform-ml-info-box">
						<h4><?php _e('Konfigurierte Sprachfelder', 'wsform-ml'); ?></h4>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php _e('Formular', 'wsform-ml'); ?></th>
									<th><?php _e('Field ID', 'wsform-ml'); ?></th>
									<th><?php _e('Aktion', 'wsform-ml'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($configured_fields as $form_id => $field_id): ?>
									<?php 
										$form_name = '';
										foreach ($available_forms as $form) {
											if ($form->id == $form_id) {
												$form_name = $form->label;
												break;
											}
										}
									?>
									<tr>
										<td><strong><?php echo esc_html($form_name ?: "Form #{$form_id}"); ?></strong></td>
										<td><code><?php echo esc_html($field_id); ?></code></td>
										<td>
											<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
												<?php wp_nonce_field('wsform_ml_remove_language_field', 'wsform_ml_nonce'); ?>
												<input type="hidden" name="action" value="wsform_ml_remove_language_field">
												<input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
												<button type="submit" class="button button-small" onclick="return confirm('<?php _e('Konfiguration wirklich entfernen?', 'wsform-ml'); ?>');">
													<?php _e('Entfernen', 'wsform-ml'); ?>
												</button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description" style="margin-top: 10px;">
							<?php _e('Hinweis: Das Entfernen der Konfiguration löscht nicht das Field im Formular, sondern nur die Verknüpfung.', 'wsform-ml'); ?>
						</p>
					</div>

					<?php if (!empty($configured_fields)): ?>
						<div class="wsform-ml-info-box" style="margin-top: 20px;">
							<h4><?php _e('Automatisch gesetzte Länderkürzel', 'wsform-ml'); ?></h4>
							<p class="description">
								<?php _e('Diese Sprachcodes werden automatisch im Frontend basierend auf der aktuellen Polylang-Sprache gesetzt:', 'wsform-ml'); ?>
							</p>
							<?php 
								$polylang = WSForm_ML_Polylang_Integration::instance();
								$languages = $polylang->get_languages();
							?>
							<?php if (!empty($languages)): ?>
								<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
									<thead>
										<tr>
											<th style="width: 30%;"><?php _e('Sprache', 'wsform-ml'); ?></th>
											<th style="width: 20%;"><?php _e('Code', 'wsform-ml'); ?></th>
											<th><?php _e('Beschreibung', 'wsform-ml'); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($languages as $lang): ?>
											<?php
												// Polylang kann Arrays oder Objekte zurückgeben - normalisiere zu Array
												$lang_data = is_object($lang) ? (array) $lang : $lang;
												$name = $lang_data['name'] ?? '';
												$code = $lang_data['code'] ?? '';
												$flag_url = $lang_data['flag_url'] ?? '';
												$is_default = $lang_data['is_default'] ?? false;
											?>
											<tr>
												<td>
													<?php if (!empty($flag_url)): ?>
														<img src="<?php echo esc_url($flag_url); ?>" alt="<?php echo esc_attr($name); ?>" style="width: 16px; height: 12px; margin-right: 5px; vertical-align: middle;">
													<?php endif; ?>
													<strong><?php echo esc_html($name); ?></strong>
													<?php if ($is_default): ?>
														<span class="wsform-ml-status-badge active" style="margin-left: 5px; font-size: 11px;">Standard</span>
													<?php endif; ?>
												</td>
												<td><code style="font-size: 13px; font-weight: bold;"><?php echo esc_html($code); ?></code></td>
												<td class="description">
													<?php 
														printf(
															__('Wird gesetzt wenn Besucher die Seite in %s aufruft', 'wsform-ml'),
															'<strong>' . esc_html($name) . '</strong>'
														);
													?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<p class="description" style="margin-top: 10px;">
									<strong><?php _e('Hinweis:', 'wsform-ml'); ?></strong>
									<?php _e('Das Sprachfeld wird automatisch mit dem entsprechenden Code gefüllt, wenn ein Besucher das Formular in der jeweiligen Sprache öffnet. Dies ermöglicht sprachspezifische Formular-Auswertungen.', 'wsform-ml'); ?>
								</p>
							<?php else: ?>
								<p class="description">
									<?php _e('Keine Polylang-Sprachen gefunden. Bitte konfiguriere zuerst Sprachen in Polylang.', 'wsform-ml'); ?>
								</p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<div class="wsform-ml-settings-info">
					<h3><?php _e('Feature-Beschreibungen', 'wsform-ml'); ?></h3>
					
					<div class="wsform-ml-info-box">
						<h4><?php _e('Native WSForm API', 'wsform-ml'); ?></h4>
						<p>
							<?php _e('Nutzt WSForms offizielle Translation-API (WS_Form_Translate Klasse).', 'wsform-ml'); ?>
						</p>
						<ul>
							<li>✅ <?php _e('Tiefere Integration mit WSForm', 'wsform-ml'); ?></li>
							<li>✅ <?php _e('Automatisches Scanning via wsf_translate_register Hook', 'wsform-ml'); ?></li>
							<li>✅ <?php _e('Zukunftssicher bei WSForm Updates', 'wsform-ml'); ?></li>
							<li>⚠️ <?php _e('Benötigt WSForm mit WS_Form_Translate Klasse', 'wsform-ml'); ?></li>
						</ul>
					</div>

					<div class="wsform-ml-info-box">
						<h4><?php _e('Legacy Renderer', 'wsform-ml'); ?></h4>
						<p>
							<?php _e('Nutzt den wsf_pre_render Hook (bisherige Implementation).', 'wsform-ml'); ?>
						</p>
						<ul>
							<li>✅ <?php _e('Funktioniert mit allen WSForm Versionen', 'wsform-ml'); ?></li>
							<li>✅ <?php _e('Bewährt und stabil', 'wsform-ml'); ?></li>
							<li>✅ <?php _e('Kann parallel zur Native API laufen', 'wsform-ml'); ?></li>
						</ul>
					</div>

					<div class="wsform-ml-info-box warning">
						<h4><?php _e('Empfehlung', 'wsform-ml'); ?></h4>
						<p>
							<strong><?php _e('Für neue Installationen:', 'wsform-ml'); ?></strong><br>
							<?php _e('Aktiviere Native API wenn verfügbar, deaktiviere Legacy Renderer.', 'wsform-ml'); ?>
						</p>
						<p>
							<strong><?php _e('Für bestehende Installationen:', 'wsform-ml'); ?></strong><br>
							<?php _e('Lasse beide Features aktiviert und teste die Native API parallel.', 'wsform-ml'); ?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<style>
			.wsform-ml-settings-container {
				max-width: 1200px;
			}
			.wsform-ml-status-badge {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				padding: 4px 10px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 600;
			}
			.wsform-ml-status-badge.active {
				background: #d4edda;
				color: #155724;
			}
			.wsform-ml-status-badge.inactive {
				background: #f8f9fa;
				color: #6c757d;
			}
			.wsform-ml-status-badge.unavailable {
				background: #fff3cd;
				color: #856404;
			}
			.wsform-ml-settings-info {
				margin-top: 40px;
			}
			.wsform-ml-info-box {
				background: #fff;
				border: 1px solid #ddd;
				border-radius: 4px;
				padding: 20px;
				margin-bottom: 20px;
			}
			.wsform-ml-info-box.warning {
				background: #fff3cd;
				border-color: #ffc107;
			}
			.wsform-ml-info-box h4 {
				margin-top: 0;
			}
			.wsform-ml-info-box ul {
				margin: 10px 0;
				padding-left: 20px;
			}
		</style>
		<?php
	}

	/**
	 * Handle Feature Toggle
	 */
	public function handle_feature_toggle() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Keine Berechtigung', 'wsform-ml'));
		}

		check_admin_referer('wsform_ml_toggle_feature', 'wsform_ml_nonce');

		$feature = sanitize_text_field($_POST['feature']);
		
		$this->feature_manager->toggle($feature);

		wp_redirect(add_query_arg(
			['page' => 'wsform-ml-settings', 'updated' => '1'],
			admin_url('admin.php')
		));
		exit;
	}

	/**
	 * Handle Language Field Creation
	 */
	public function handle_create_language_field() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Keine Berechtigung', 'wsform-ml'));
		}

		check_admin_referer('wsform_ml_create_language_field', 'wsform_ml_nonce');

		$form_id = absint($_POST['form_id']);
		
		if (!$form_id) {
			wp_redirect(add_query_arg(
				['page' => 'wsform-ml-settings', 'error' => urlencode(__('Ungültige Form ID', 'wsform-ml'))],
				admin_url('admin.php')
			));
			exit;
		}

		$language_field_manager = WSForm_ML_Language_Field_Manager::instance();
		$result = $language_field_manager->create_language_field($form_id);

		if ($result['success']) {
			wp_redirect(add_query_arg(
				[
					'page' => 'wsform-ml-settings',
					'field_created' => '1',
					'field_id' => $result['field_id']
				],
				admin_url('admin.php')
			));
		} else {
			wp_redirect(add_query_arg(
				['page' => 'wsform-ml-settings', 'error' => urlencode($result['error'])],
				admin_url('admin.php')
			));
		}
		exit;
	}

	/**
	 * Handle Language Field Removal
	 */
	public function handle_remove_language_field() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Keine Berechtigung', 'wsform-ml'));
		}

		check_admin_referer('wsform_ml_remove_language_field', 'wsform_ml_nonce');

		$form_id = absint($_POST['form_id']);
		
		$language_field_manager = WSForm_ML_Language_Field_Manager::instance();
		$language_field_manager->remove_field_config($form_id);

		wp_redirect(add_query_arg(
			['page' => 'wsform-ml-settings', 'field_removed' => '1'],
			admin_url('admin.php')
		));
		exit;
	}
}
