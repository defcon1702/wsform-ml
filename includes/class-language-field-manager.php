<?php
/**
 * Language Field Manager
 * 
 * Verwaltet die Erstellung und Konfiguration von Sprachfeldern in WSForm Formularen
 * 
 * @package WSForm_ML
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Language_Field_Manager {

	private static $instance = null;
	private $logger = null;
	private $option_name = 'wsform_ml_language_fields';

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->logger = WSForm_ML_Logger::instance();
		add_filter('wsf_pre_render', [$this, 'set_language_field_value'], 10, 2);
	}

	/**
	 * Hole alle konfigurierten Language Fields
	 * 
	 * @return array Format: [form_id => field_id]
	 */
	public function get_configured_fields() {
		return get_option($this->option_name, []);
	}

	/**
	 * Speichere Language Field Konfiguration
	 * 
	 * @param int $form_id
	 * @param int $field_id
	 * @return bool
	 */
	public function save_field_config($form_id, $field_id) {
		$fields = $this->get_configured_fields();
		$fields[$form_id] = $field_id;
		return update_option($this->option_name, $fields);
	}

	/**
	 * Entferne Language Field Konfiguration
	 * 
	 * @param int $form_id
	 * @return bool
	 */
	public function remove_field_config($form_id) {
		$fields = $this->get_configured_fields();
		
		// Lösche das Feld aus WSForm, falls vorhanden
		if (isset($fields[$form_id])) {
			$field_id = $fields[$form_id];
			
			try {
				$field = new WS_Form_Field();
				$field->id = absint($field_id);
				$field->db_delete();
				
				// Publiziere Formular nach Löschung
				$ws_form = new WS_Form_Form();
				$ws_form->id = absint($form_id);
				$ws_form->db_publish();
				
				// Deleted language field {$field_id} from form {$form_id}");
			} catch (Exception $e) {
				// Error deleting language field - " . $e->getMessage());
			}
		}
		
		unset($fields[$form_id]);
		return update_option($this->option_name, $fields);
	}

	/**
	 * Erstelle ein Language Field in einem Form
	 * 
	 * @param int $form_id
	 * @return array ['success' => bool, 'field_id' => int|null, 'error' => string|null]
	 */
	public function create_language_field($form_id) {
		if (!class_exists('WS_Form_Field')) {
			return [
				'success' => false,
				'error' => __('WS Form nicht installiert', 'wsform-ml')
			];
		}

		try {
			// Lade Form
			$ws_form = new WS_Form_Form();
			$ws_form->id = absint($form_id);
			$form_object = $ws_form->db_read(true, true);

			if (!$form_object || empty($form_object->groups)) {
				return [
					'success' => false,
					'error' => __('Formular konnte nicht geladen werden', 'wsform-ml')
				];
			}

			// Finde erste Group und Section
			$first_group = $form_object->groups[0];
			$first_section = $first_group->sections[0] ?? null;

			if (!$first_section) {
				return [
					'success' => false,
					'error' => __('Keine Section im Formular gefunden', 'wsform-ml')
				];
			}

			// Erstelle Hidden Field
			$field = new WS_Form_Field();
			$field->form_id = $form_id;
			$field->section_id = $first_section->id;
			$field->type = 'hidden';
			$field->label = __('Sprache / Language', 'wsform-ml');
			
			// Setze Meta-Daten VOR db_create()
			$field->meta = (object)[
				'label_render' => '',
				'exclude_email' => '',
				'default_value' => ''
			];

			// Speichere Field in DB
			$field_id = $field->db_create();

			if (!$field_id) {
				return [
					'success' => false,
					'error' => __('Field konnte nicht erstellt werden', 'wsform-ml')
				];
			}

			// Publiziere Formular, damit WSForm das neue Feld erkennt
			$ws_form->db_publish();

			// Speichere Konfiguration
			$this->save_field_config($form_id, $field_id);

			return [
				'success' => true,
				'field_id' => $field_id
			];

		} catch (Exception $e) {
			// Language Field creation error - ' . $e->getMessage());
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Setze Language Field Wert beim Form Rendering
	 * 
	 * @param object $form
	 * @param bool $preview
	 * @return object
	 */
	public function set_language_field_value($form, $preview = false) {
		if ($preview) {
			return $form;
		}

		$form_id = $form->id ?? 0;
		if (!$form_id) {
			return $form;
		}

		// Prüfe ob für dieses Form ein Language Field konfiguriert ist
		$configured_fields = $this->get_configured_fields();
		if (!isset($configured_fields[$form_id])) {
			return $form;
		}

		$language_field_id = $configured_fields[$form_id];
		$current_language = WSForm_ML_Polylang_Integration::get_current_language();

		if (!$current_language) {
			return $form;
		}

		// Finde das Field und setze den Wert
		foreach ($form->groups as $group) {
			if (empty($group->sections)) {
				continue;
			}

			foreach ($group->sections as $section) {
				if (empty($section->fields)) {
					continue;
				}

				foreach ($section->fields as $field) {
					if ($field->id == $language_field_id) {
						// Setze default_value auf aktuellen Sprachcode
						if (!isset($field->meta)) {
							$field->meta = new stdClass();
						}
						$field->meta->default_value = $current_language;
						
						// Set language field value to '{$current_language}' for form {$form_id}, field {$language_field_id}");
						return $form;
					}
				}
			}
		}

		return $form;
	}

	/**
	 * Hole alle verfügbaren Forms
	 * 
	 * @return array
	 */
	public function get_available_forms() {
		global $wpdb;
		
		$forms = $wpdb->get_results(
			"SELECT id, label FROM {$wpdb->prefix}wsf_form WHERE status != 'trash' ORDER BY label ASC"
		);

		return $forms ?: [];
	}

	/**
	 * Prüfe ob ein Form bereits ein Language Field hat
	 * 
	 * @param int $form_id
	 * @return bool
	 */
	public function form_has_language_field($form_id) {
		$configured_fields = $this->get_configured_fields();
		return isset($configured_fields[$form_id]);
	}
}
