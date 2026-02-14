<?php
/**
 * Feature Manager - Verwaltet Feature-Toggles
 * 
 * Ermöglicht das Ein- und Ausschalten von Features wie:
 * - Native WSForm API Integration
 * - Legacy Renderer
 * 
 * @package WSForm_ML
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Feature_Manager {

	private static $instance = null;

	const FEATURE_NATIVE_API = 'native_api';
	const FEATURE_LEGACY_RENDERER = 'legacy_renderer';

	private $features = [];

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_features();
	}

	/**
	 * Lade Feature-Status aus Datenbank
	 */
	private function load_features() {
		$this->features = [
			self::FEATURE_NATIVE_API => get_option('wsform_ml_feature_native_api', false),
			self::FEATURE_LEGACY_RENDERER => get_option('wsform_ml_feature_legacy_renderer', true)
		];
	}

	/**
	 * Prüfe ob Feature aktiviert ist
	 */
	public function is_enabled($feature) {
		return isset($this->features[$feature]) && $this->features[$feature];
	}

	/**
	 * Aktiviere Feature
	 */
	public function enable($feature) {
		if (!isset($this->features[$feature])) {
			return false;
		}

		$this->features[$feature] = true;
		update_option('wsform_ml_feature_' . $feature, true);

		// Trigger Feature-spezifische Aktionen
		$this->on_feature_change($feature, true);

		error_log("WSForm ML: Feature '{$feature}' enabled");
		return true;
	}

	/**
	 * Deaktiviere Feature
	 */
	public function disable($feature) {
		if (!isset($this->features[$feature])) {
			return false;
		}

		$this->features[$feature] = false;
		update_option('wsform_ml_feature_' . $feature, false);

		// Trigger Feature-spezifische Aktionen
		$this->on_feature_change($feature, false);

		error_log("WSForm ML: Feature '{$feature}' disabled");
		return true;
	}

	/**
	 * Toggle Feature
	 */
	public function toggle($feature) {
		if ($this->is_enabled($feature)) {
			return $this->disable($feature);
		} else {
			return $this->enable($feature);
		}
	}

	/**
	 * Hole alle Features mit Status
	 */
	public function get_all_features() {
		return [
			self::FEATURE_NATIVE_API => [
				'name' => __('Native WSForm API', 'wsform-ml'),
				'description' => __('Nutzt WSForms offizielle Translation API (wsf_translate Filter). Empfohlen für neue Installationen.', 'wsform-ml'),
				'enabled' => $this->is_enabled(self::FEATURE_NATIVE_API),
				'requires' => ['WS_Form_Translate'],
				'conflicts_with' => []
			],
			self::FEATURE_LEGACY_RENDERER => [
				'name' => __('Legacy Renderer', 'wsform-ml'),
				'description' => __('Nutzt den alten wsf_pre_render Hook. Kann parallel zur Native API laufen.', 'wsform-ml'),
				'enabled' => $this->is_enabled(self::FEATURE_LEGACY_RENDERER),
				'requires' => [],
				'conflicts_with' => []
			]
		];
	}

	/**
	 * Feature-Change Handler
	 */
	private function on_feature_change($feature, $enabled) {
		switch ($feature) {
			case self::FEATURE_NATIVE_API:
				$adapter = WSForm_ML_Native_Adapter::instance();
				if ($enabled) {
					$adapter->enable();
				} else {
					$adapter->disable();
				}
				break;

			case self::FEATURE_LEGACY_RENDERER:
				// Legacy Renderer wird über init() in wsform-ml.php gesteuert
				// Hier nur für Konsistenz
				break;
		}

		// Trigger Action für andere Plugins
		do_action('wsform_ml_feature_changed', $feature, $enabled);
	}

	/**
	 * Prüfe ob Feature verfügbar ist (Dependencies erfüllt)
	 */
	public function is_available($feature) {
		$features = $this->get_all_features();
		
		if (!isset($features[$feature])) {
			return false;
		}

		$feature_config = $features[$feature];

		// Prüfe Requirements
		if (!empty($feature_config['requires'])) {
			foreach ($feature_config['requires'] as $required_class) {
				if (!class_exists($required_class)) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Hole Feature-Status für Admin-Anzeige
	 */
	public function get_feature_status($feature) {
		$available = $this->is_available($feature);
		$enabled = $this->is_enabled($feature);

		return [
			'available' => $available,
			'enabled' => $enabled,
			'status' => $available ? ($enabled ? 'active' : 'inactive') : 'unavailable'
		];
	}
}
