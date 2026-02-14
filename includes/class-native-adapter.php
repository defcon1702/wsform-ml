<?php
/**
 * Native WSForm Translation API Adapter
 * 
 * Integriert mit WSForms offizieller Translation-API (WS_Form_Translate)
 * Kann parallel zur Legacy-Implementation laufen oder diese ersetzen.
 * 
 * @package WSForm_ML
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Native_Adapter {

	private static $instance = null;
	private $translation_manager;
	private $enabled = false;
	private $string_id_map = [];

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->translation_manager = WSForm_ML_Translation_Manager::instance();
		
		// Prüfe ob native Integration aktiviert ist
		$this->enabled = get_option('wsform_ml_use_native_api', false);
		
		if ($this->enabled) {
			$this->init_hooks();
		}
	}

	/**
	 * Aktiviere native Integration
	 */
	public function enable() {
		$this->enabled = true;
		update_option('wsform_ml_use_native_api', true);
		$this->init_hooks();
		
	}

	/**
	 * Deaktiviere native Integration
	 */
	public function disable() {
		$this->enabled = false;
		update_option('wsform_ml_use_native_api', false);
		$this->remove_hooks();
		
	}

	/**
	 * Prüfe ob native Integration aktiv ist
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Registriere Hooks für native WSForm Translation API
	 */
	private function init_hooks() {
		// Hook in WSForm's Translation Filter
		add_filter('wsf_translate', [$this, 'translate_string'], 10, 4);
		
		// Hook in String Registration (für automatisches Scanning)
		add_action('wsf_translate_register', [$this, 'register_string'], 10, 6);
		
		// Hook in Form Lifecycle
		add_action('wsf_translate_start', [$this, 'translation_start'], 10, 2);
		add_action('wsf_translate_finish', [$this, 'translation_finish'], 10, 2);
		
		// Hook in Form Deletion
		add_action('wsf_translate_unregister_all', [$this, 'unregister_all'], 10, 1);
		
	}

	/**
	 * Entferne Hooks
	 */
	private function remove_hooks() {
		remove_filter('wsf_translate', [$this, 'translate_string'], 10);
		remove_action('wsf_translate_register', [$this, 'register_string'], 10);
		remove_action('wsf_translate_start', [$this, 'translation_start'], 10);
		remove_action('wsf_translate_finish', [$this, 'translation_finish'], 10);
		remove_action('wsf_translate_unregister_all', [$this, 'unregister_all'], 10);
	}

	/**
	 * Übersetze einen String via WSForm's Translation Filter
	 * 
	 * @param string $string_value Original-Wert
	 * @param string $string_id WSForm String-ID (z.B. 'wsf-field-123-label')
	 * @param int $form_id Form ID
	 * @param string $form_label Form Label
	 * @return string Übersetzter String oder Original
	 */
	public function translate_string($string_value, $string_id, $form_id, $form_label) {
		if (!$this->enabled) {
			return $string_value;
		}

		$current_lang = WSForm_ML_Polylang_Integration::get_current_language();
		
		if (!$current_lang) {
			return $string_value;
		}


		// Konvertiere WSForm String-ID zu unserem field_path Format
		$field_data = $this->parse_string_id($string_id);
		
		if (!$field_data) {
			return $string_value;
		}

		// Hole Übersetzung aus Datenbank
		$translation = $this->get_translation(
			$form_id,
			$field_data['field_id'],
			$field_data['field_path'],
			$field_data['property_type'],
			$current_lang
		);

		if ($translation && !empty($translation->translated_value)) {
			return $translation->translated_value;
		}

		return $string_value;
	}

	/**
	 * Registriere String für Übersetzung (automatisches Scanning)
	 * 
	 * @param string $string_value String-Wert
	 * @param string $string_id String-ID
	 * @param string $string_label String-Label
	 * @param string $type Typ
	 * @param int $form_id Form ID
	 * @param string $form_label Form Label
	 */
	public function register_string($string_value, $string_id, $string_label, $type, $form_id, $form_label) {
		if (!$this->enabled) {
			return;
		}


		// Parse String-ID
		$field_data = $this->parse_string_id($string_id);
		
		if (!$field_data) {
			return;
		}

		// Speichere Mapping für spätere Verwendung
		$this->string_id_map[$string_id] = $field_data;

		// Synchronisiere mit unserem Cache
		$this->sync_to_cache($form_id, $field_data, $string_value, $type);
	}

	/**
	 * Translation Start Event
	 */
	public function translation_start($form_id, $form_label) {
		if (!$this->enabled) {
			return;
		}

		$this->string_id_map = [];
	}

	/**
	 * Translation Finish Event
	 */
	public function translation_finish($form_id, $form_label) {
		if (!$this->enabled) {
			return;
		}

	}

	/**
	 * Unregister alle Strings eines Formulars
	 */
	public function unregister_all($form_id) {
		if (!$this->enabled) {
			return;
		}

		
		// Lösche aus unserem Cache
		global $wpdb;
		$cache_table = $wpdb->prefix . 'wsform_ml_field_cache';
		
		$wpdb->delete(
			$cache_table,
			['form_id' => $form_id],
			['%d']
		);
	}

	/**
	 * Parse WSForm String-ID zu unserem Format
	 * 
	 * Format: wsf-{object_type}-{object_id}-{property}
	 * Beispiele:
	 * - wsf-field-123-label
	 * - wsf-field-123-placeholder
	 * - wsf-section-45-label
	 * 
	 * @param string $string_id
	 * @return array|null
	 */
	private function parse_string_id($string_id) {
		// Pattern: wsf-{type}-{id}-{property}
		if (!preg_match('/^wsf-(field|section|group|form)-(\d+)-(.+)$/', $string_id, $matches)) {
			return null;
		}

		$object_type = $matches[1];
		$object_id = (int)$matches[2];
		$property = $matches[3];

		// Konvertiere property (z.B. 'invalid-feedback' -> 'invalid_feedback')
		$property_type = str_replace('-', '_', $property);

		// Baue field_path (vereinfacht, könnte erweitert werden)
		$field_path = "/{$object_type}[{$object_id}]";

		return [
			'object_type' => $object_type,
			'field_id' => $object_id,
			'field_path' => $field_path,
			'property_type' => $property_type
		];
	}

	/**
	 * Hole Übersetzung aus Datenbank
	 */
	private function get_translation($form_id, $field_id, $field_path, $property_type, $language_code) {
		global $wpdb;
		$trans_table = $wpdb->prefix . 'wsform_ml_translations';

		$translation = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$trans_table} 
			WHERE form_id = %d 
			AND field_id = %d 
			AND property_type = %s 
			AND language_code = %s
			LIMIT 1",
			$form_id,
			$field_id,
			$property_type,
			$language_code
		));

		return $translation;
	}

	/**
	 * Synchronisiere registrierten String mit unserem Cache
	 */
	private function sync_to_cache($form_id, $field_data, $string_value, $type) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'wsform_ml_field_cache';

		// Prüfe ob bereits existiert
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$cache_table} 
			WHERE form_id = %d 
			AND field_id = %d 
			AND field_path = %s",
			$form_id,
			$field_data['field_id'],
			$field_data['field_path']
		));

		if ($exists) {
			// Update
			$wpdb->update(
				$cache_table,
				[
					'translatable_properties' => json_encode([
						[
							'type' => $field_data['property_type'],
							'value' => $string_value
						]
					]),
					'last_scan' => current_time('mysql')
				],
				[
					'form_id' => $form_id,
					'field_id' => $field_data['field_id'],
					'field_path' => $field_data['field_path']
				],
				['%s', '%s'],
				['%d', '%d', '%s']
			);
		} else {
			// Insert
			$wpdb->insert(
				$cache_table,
				[
					'form_id' => $form_id,
					'field_id' => $field_data['field_id'],
					'field_path' => $field_data['field_path'],
					'field_type' => $field_data['object_type'],
					'field_label' => '',
					'translatable_properties' => json_encode([
						[
							'type' => $field_data['property_type'],
							'value' => $string_value
						]
					]),
					'last_scan' => current_time('mysql')
				],
				['%d', '%d', '%s', '%s', '%s', '%s', '%s']
			);
		}
	}
}
