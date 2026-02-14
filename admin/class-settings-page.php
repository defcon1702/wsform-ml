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
	}

	/**
	 * Render Settings Page
	 */
	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Keine Berechtigung', 'wsform-ml'));
		}

		$features = $this->feature_manager->get_all_features();
		?>
		<div class="wrap wsform-ml-settings">
			<h1><?php _e('WSForm Multilingual - Einstellungen', 'wsform-ml'); ?></h1>

			<?php if (isset($_GET['updated'])): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php _e('Einstellungen gespeichert.', 'wsform-ml'); ?></p>
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
}
