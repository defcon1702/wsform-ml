<?php
if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_REST_API {
	private static $instance = null;
	private $namespace = 'wsform-ml/v1';

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/forms', [
			'methods' => 'GET',
			'callback' => [$this, 'get_forms'],
			'permission_callback' => [$this, 'check_permission']
		]);

		register_rest_route($this->namespace, '/forms/(?P<id>\d+)/scan', [
			'methods' => 'POST',
			'callback' => [$this, 'scan_form'],
			'permission_callback' => [$this, 'check_permission'],
			'args' => [
				'id' => [
					'required' => true,
					'validate_callback' => function($param) {
						return is_numeric($param);
					}
				]
			]
		]);

		register_rest_route($this->namespace, '/forms/(?P<id>\d+)/fields', [
			'methods' => 'GET',
			'callback' => [$this, 'get_form_fields'],
			'permission_callback' => [$this, 'check_permission'],
			'args' => [
				'id' => [
					'required' => true,
					'validate_callback' => function($param) {
						return is_numeric($param);
					}
				]
			]
		]);

		register_rest_route($this->namespace, '/forms/(?P<id>\d+)/translations', [
			'methods' => 'GET',
			'callback' => [$this, 'get_form_translations'],
			'permission_callback' => [$this, 'check_permission'],
			'args' => [
				'id' => [
					'required' => true,
					'validate_callback' => function($param) {
						return is_numeric($param);
					}
				],
				'language' => [
					'required' => false,
					'default' => null
				]
			]
		]);

		register_rest_route($this->namespace, '/forms/(?P<id>\d+)/translations/missing', [
			'methods' => 'GET',
			'callback' => [$this, 'get_missing_translations'],
			'permission_callback' => [$this, 'check_permission'],
			'args' => [
				'id' => [
					'required' => true,
					'validate_callback' => function($param) {
						return is_numeric($param);
					}
				],
				'language' => [
					'required' => true
				]
			]
		]);

		register_rest_route($this->namespace, '/forms/(?P<id>\d+)/stats', [
			'methods' => 'GET',
			'callback' => [$this, 'get_translation_stats'],
			'permission_callback' => [$this, 'check_permission'],
			'args' => [
				'id' => [
					'required' => true,
					'validate_callback' => function($param) {
						return is_numeric($param);
					}
				]
			]
		]);

		register_rest_route($this->namespace, '/translations', [
			'methods' => 'POST',
			'callback' => [$this, 'save_translation'],
			'permission_callback' => [$this, 'check_permission']
		]);

		register_rest_route($this->namespace, '/translations/bulk', [
			'methods' => 'POST',
			'callback' => [$this, 'bulk_save_translations'],
			'permission_callback' => [$this, 'check_permission']
		]);

		register_rest_route($this->namespace, '/translations/(?P<id>\d+)', [
			'methods' => 'DELETE',
			'callback' => [$this, 'delete_translation'],
			'permission_callback' => [$this, 'check_permission'],
			'args' => [
				'id' => [
					'required' => true,
					'validate_callback' => function($param) {
						return is_numeric($param);
					}
				]
			]
		]);
	}

	public function check_permission() {
		return current_user_can('manage_options');
	}

	public function get_forms($request) {
		try {
			// Prüfe Cache (5 Minuten TTL)
			$cache_key = 'wsform_ml_forms_list_v1';
			$cache_hash_key = 'wsform_ml_forms_hash_v1';
			$refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
			
			if (!$refresh) {
				$cached = get_transient($cache_key);
				$cached_hash = get_transient($cache_hash_key);
				
				// Prüfe ob sich die Formularliste geändert hat
				global $wpdb;
				$current_hash = $wpdb->get_var(
					"SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', date_updated) ORDER BY id)) 
					 FROM {$wpdb->prefix}wsf_form 
					 WHERE status != 'trash'"
				);
				
				// Verwende Cache nur wenn Hash übereinstimmt
				if ($cached !== false && $cached_hash === $current_hash) {
					return rest_ensure_response($cached);
				}
			}

			if (!class_exists('WS_Form_Form')) {
				return new WP_Error('wsform_not_found', __('WS Form nicht installiert', 'wsform-ml'), ['status' => 404]);
			}

			global $wpdb;
			
			$table_name = $wpdb->prefix . 'wsf_form';
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
			
			if (!$table_exists) {
				return new WP_Error('wsform_table_missing', __('WS Form Datenbank-Tabelle nicht gefunden', 'wsform-ml'), ['status' => 500]);
			}

			$forms = $wpdb->get_results(
				"SELECT id, label, date_added, date_updated FROM {$wpdb->prefix}wsf_form WHERE status != 'trash' ORDER BY label ASC"
			);

			if ($wpdb->last_error) {
				return new WP_Error('database_error', $wpdb->last_error, ['status' => 500]);
			}

			if (empty($forms)) {
				return rest_ensure_response([]);
			}

			$scanner = WSForm_ML_Field_Scanner::instance();
			$translation_manager = WSForm_ML_Translation_Manager::instance();

			foreach ($forms as &$form) {
				try {
					$cached_fields = $scanner->get_cached_fields($form->id);
					$form->cached_fields_count = count($cached_fields);
					$form->last_scanned = null;

					if (!empty($cached_fields)) {
						$form->last_scanned = $cached_fields[0]->last_scanned;
					}

					$form->translation_stats = $translation_manager->get_translation_stats($form->id);
				} catch (Exception $e) {
					// Error processing form ' . $form->id . ' - ' . $e->getMessage());
					$form->cached_fields_count = 0;
					$form->last_scanned = null;
					$form->translation_stats = ['total_fields' => 0, 'languages' => []];
				}
			}

			// Berechne Hash für Cache-Validierung
			$current_hash = $wpdb->get_var(
				"SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', date_updated) ORDER BY id)) 
				 FROM {$wpdb->prefix}wsf_form 
				 WHERE status != 'trash'"
			);
		
			// Speichere im Cache (5 Minuten)
			set_transient($cache_key, $forms, 5 * MINUTE_IN_SECONDS);
			set_transient($cache_hash_key, $current_hash, 5 * MINUTE_IN_SECONDS);

			return rest_ensure_response($forms);
			
		} catch (Exception $e) {
			return new WP_Error('internal_error', $e->getMessage(), ['status' => 500]);
		}
	}

	public function scan_form($request) {
		$form_id = $request->get_param('id');
		
		if (!class_exists('WS_Form_Form')) {
			return new WP_Error('wsform_not_found', __('WS Form nicht installiert', 'wsform-ml'), ['status' => 404]);
		}
		
		// Sauberer Output Buffer für JSON Response
		ob_start();
		
		try {
			$scanner = WSForm_ML_Field_Scanner::instance();
			$result = $scanner->scan_form($form_id);

			// Verwerfe jeglichen Output
			ob_end_clean();

			if ($result['success']) {
				// Invalidiere Forms-Liste Cache nach erfolgreichem Scan
				delete_transient('wsform_ml_forms_list_v1');
				
				return rest_ensure_response($result);
			} else {
				return new WP_Error('scan_failed', $result['error'], ['status' => 500]);
			}
		} catch (Exception $e) {
			ob_end_clean();
			// Scan error - ' . $e->getMessage());
			return new WP_Error('scan_exception', $e->getMessage(), ['status' => 500]);
		}
	}

	public function get_form_fields($request) {
		$form_id = $request->get_param('id');
		$scanner = WSForm_ML_Field_Scanner::instance();
		
		$fields = $scanner->get_cached_fields($form_id);

		return rest_ensure_response($fields);
	}

	public function get_form_translations($request) {
		$form_id = $request->get_param('id');
		$language = $request->get_param('language');
		
		$translation_manager = WSForm_ML_Translation_Manager::instance();
		$translations = $translation_manager->get_form_translations($form_id, $language);

		return rest_ensure_response($translations);
	}

	public function get_missing_translations($request) {
		$form_id = $request->get_param('id');
		$language = $request->get_param('language');
		
		$translation_manager = WSForm_ML_Translation_Manager::instance();
		$missing = $translation_manager->get_missing_translations($form_id, $language);

		return rest_ensure_response($missing);
	}

	public function get_translation_stats($request) {
		$form_id = $request->get_param('id');
		
		$translation_manager = WSForm_ML_Translation_Manager::instance();
		$stats = $translation_manager->get_translation_stats($form_id);

		return rest_ensure_response($stats);
	}

	public function save_translation($request) {
		$data = $request->get_json_params();
		
		// Sanitize Input
		$sanitized = [
			'form_id' => absint($data['form_id'] ?? 0),
			'field_id' => sanitize_text_field($data['field_id'] ?? ''),
			'field_path' => sanitize_text_field($data['field_path'] ?? ''),
			'property_type' => sanitize_key($data['property_type'] ?? ''),
			'language_code' => sanitize_key($data['language_code'] ?? ''),
			'original_value' => wp_kses_post($data['original_value'] ?? ''),
			'translated_value' => wp_kses_post($data['translated_value'] ?? '')
		];
		
		// Validiere erforderliche Felder
		// WICHTIG: field_id kann "0" sein für Group Labels (property_type = 'group_label')
		// Prüfe nur ob field_id gesetzt ist, nicht ob es truthy ist
		if (!$sanitized['form_id'] || 
		    !isset($data['field_id']) || $sanitized['field_id'] === '' || 
		    empty($sanitized['field_path']) || 
		    empty($sanitized['property_type']) || 
		    empty($sanitized['language_code'])) {
			return new WP_Error('invalid_data', __('Ungültige oder fehlende Pflichtfelder', 'wsform-ml'), ['status' => 400]);
		}
		
		// Validiere language_code Format (z.B. 'de', 'en', 'es')
		if (!preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $sanitized['language_code'])) {
			return new WP_Error('invalid_language', __('Ungültiger Sprachcode', 'wsform-ml'), ['status' => 400]);
		}

		$translation_manager = WSForm_ML_Translation_Manager::instance();
		$id = $translation_manager->save_translation($sanitized);

		return rest_ensure_response([
			'success' => true,
			'id' => $id
		]);
	}

	public function bulk_save_translations($request) {
		$translations = $request->get_json_params();
		
		if (!is_array($translations)) {
			return new WP_Error('invalid_data', __('Ungültige Daten', 'wsform-ml'), ['status' => 400]);
		}

		$translation_manager = WSForm_ML_Translation_Manager::instance();
		$results = $translation_manager->bulk_save_translations($translations);

		return rest_ensure_response($results);
	}

	public function delete_translation($request) {
		$id = $request->get_param('id');
		
		$translation_manager = WSForm_ML_Translation_Manager::instance();
		$deleted = $translation_manager->delete_translation($id);

		if ($deleted) {
			return rest_ensure_response(['success' => true]);
		} else {
			return new WP_Error('delete_failed', __('Löschen fehlgeschlagen', 'wsform-ml'), ['status' => 500]);
		}
	}

}
