<?php
/**
 * Plugin Name: WSForm Multilingual
 * Plugin URI: https://github.com/yourusername/wsform-ml
 * Description: Automatische Übersetzungsverwaltung für WSForm mit Polylang-Integration. Scannt Formulare automatisch und verwaltet Übersetzungen zentral.
 * Version: 1.0.0
 * Author: Sebastian Berger
 * Author URI: https://yourwebsite.com
 * Text Domain: wsform-ml
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WSFORM_ML_VERSION', '1.0.0');
define('WSFORM_ML_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSFORM_ML_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WSFORM_ML_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class WSForm_ML {
	private static $instance = null;

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies() {
		require_once WSFORM_ML_PLUGIN_DIR . 'includes/class-database.php';
		require_once WSFORM_ML_PLUGIN_DIR . 'includes/class-field-scanner.php';
		require_once WSFORM_ML_PLUGIN_DIR . 'includes/class-translation-manager.php';
		require_once WSFORM_ML_PLUGIN_DIR . 'includes/class-renderer.php';
		require_once WSFORM_ML_PLUGIN_DIR . 'includes/class-polylang-integration.php';
		require_once WSFORM_ML_PLUGIN_DIR . 'admin/class-rest-api.php';
		
		if (is_admin()) {
			require_once WSFORM_ML_PLUGIN_DIR . 'admin/class-admin-menu.php';
		}
	}

	private function init_hooks() {
		register_activation_hook(__FILE__, [$this, 'activate']);
		register_deactivation_hook(__FILE__, [$this, 'deactivate']);
		
		add_action('plugins_loaded', [$this, 'init']);
		add_action('init', [$this, 'load_textdomain']);
	}

	public function activate() {
		WSForm_ML_Database::create_tables();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function init() {
		if (!$this->check_dependencies()) {
			add_action('admin_notices', [$this, 'dependency_notice']);
			return;
		}

		WSForm_ML_Renderer::instance();
		WSForm_ML_REST_API::instance();
		
		if (is_admin()) {
			WSForm_ML_Admin_Menu::instance();
		}
	}

	private function check_dependencies() {
		if (!class_exists('WS_Form')) {
			return false;
		}
		return true;
	}

	public function dependency_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php _e('WSForm Multilingual', 'wsform-ml'); ?>:</strong>
				<?php _e('Dieses Plugin benötigt WS Form. Bitte installieren und aktivieren Sie WS Form.', 'wsform-ml'); ?>
			</p>
		</div>
		<?php
	}

	public function load_textdomain() {
		load_plugin_textdomain('wsform-ml', false, dirname(WSFORM_ML_PLUGIN_BASENAME) . '/languages');
	}
}

function wsform_ml() {
	return WSForm_ML::instance();
}

wsform_ml();
