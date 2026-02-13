<?php
if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Admin_Menu {
	private static $instance = null;

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	public function add_menu() {
		add_menu_page(
			__('WSForm Multilingual', 'wsform-ml'),
			__('WSForm ML', 'wsform-ml'),
			'manage_options',
			'wsform-ml',
			[$this, 'render_admin_page'],
			'dashicons-translation',
			30
		);

		add_submenu_page(
			'wsform-ml',
			__('Formulare', 'wsform-ml'),
			__('Formulare', 'wsform-ml'),
			'manage_options',
			'wsform-ml',
			[$this, 'render_admin_page']
		);

		add_submenu_page(
			'wsform-ml',
			__('Einstellungen', 'wsform-ml'),
			__('Einstellungen', 'wsform-ml'),
			'manage_options',
			'wsform-ml-settings',
			[$this, 'render_settings_page']
		);
	}

	public function enqueue_assets($hook) {
		if (strpos($hook, 'wsform-ml') === false) {
			return;
		}

		wp_enqueue_style(
			'wsform-ml-admin',
			WSFORM_ML_PLUGIN_URL . 'admin/assets/css/admin.css',
			[],
			WSFORM_ML_VERSION
		);

		wp_enqueue_script(
			'wsform-ml-admin',
			WSFORM_ML_PLUGIN_URL . 'admin/assets/js/admin.js',
			[],
			WSFORM_ML_VERSION,
			true
		);

		wp_localize_script('wsform-ml-admin', 'wsformML', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'restUrl' => rest_url('wsform-ml/v1'),
			'nonce' => wp_create_nonce('wp_rest'),
			'languages' => WSForm_ML_Polylang_Integration::get_languages(),
			'currentLanguage' => WSForm_ML_Polylang_Integration::get_current_language(),
			'defaultLanguage' => WSForm_ML_Polylang_Integration::get_default_language(),
			'i18n' => [
				'scanForm' => __('Formular scannen', 'wsform-ml'),
				'scanning' => __('Scanne...', 'wsform-ml'),
				'scanComplete' => __('Scan abgeschlossen', 'wsform-ml'),
				'scanError' => __('Fehler beim Scannen', 'wsform-ml'),
				'saveTranslation' => __('Speichern', 'wsform-ml'),
				'saving' => __('Speichere...', 'wsform-ml'),
				'saved' => __('Gespeichert', 'wsform-ml'),
				'missingTranslations' => __('Fehlende Übersetzungen', 'wsform-ml'),
				'confirmDelete' => __('Möchten Sie diese Übersetzung wirklich löschen?', 'wsform-ml')
			]
		]);
	}

	public function render_admin_page() {
		include WSFORM_ML_PLUGIN_DIR . 'admin/views/admin-page.php';
	}

	public function render_settings_page() {
		include WSFORM_ML_PLUGIN_DIR . 'admin/views/settings-page.php';
	}
}
