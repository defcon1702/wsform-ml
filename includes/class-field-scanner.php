<?php
if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Field_Scanner {
	private static $instance = null;

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function scan_form($form_id) {
		error_log("WSForm ML Scanner: ===== SCAN START for Form {$form_id} =====");
		
		$start_time = microtime(true);
		$stats = [
			'fields_found' => 0,
			'new_fields' => 0,
			'updated_fields' => 0,
			'deleted_fields' => 0
		];

		try {
			error_log("WSForm ML Scanner: Getting form object...");
			$form_object = $this->get_form_object($form_id);
			if (!$form_object) {
				throw new Exception(__('Formular nicht gefunden', 'wsform-ml'));
			}

			error_log("WSForm ML Scanner: Form object loaded, discovering fields...");
			$discovered_fields = $this->discover_fields($form_object);
			$stats['fields_found'] = count($discovered_fields);
			error_log("WSForm ML Scanner: Discovered {$stats['fields_found']} fields");

			$this->sync_fields_to_cache($form_id, $discovered_fields, $stats);
			
			$scan_duration = microtime(true) - $start_time;
			$this->log_scan($form_id, 'full', $stats, 'success', null, $scan_duration);

			return [
				'success' => true,
				'stats' => $stats,
				'fields' => $discovered_fields
			];

		} catch (Exception $e) {
			$scan_duration = microtime(true) - $start_time;
			$this->log_scan($form_id, 'full', $stats, 'error', $e->getMessage(), $scan_duration);
			
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	private function get_form_object($form_id) {
		if (!class_exists('WS_Form_Form')) {
			throw new Exception(__('WS Form nicht installiert', 'wsform-ml'));
		}

		try {
			// Validiere Form ID
			if (empty($form_id) || !is_numeric($form_id)) {
				throw new Exception(__('Ungültige Formular-ID', 'wsform-ml'));
			}

			// Buffer Output um HTML-Fehler zu fangen
			ob_start();
			$ws_form = new WS_Form_Form();
			$ws_form->id = absint($form_id);
			
			// Prüfe ob Form existiert bevor wir sie lesen
			global $wpdb;
			$form_exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wsf_form WHERE id = %d AND status != 'trash'",
				$form_id
			));
			
			if (!$form_exists) {
				ob_end_clean();
				throw new Exception(__('Formular nicht gefunden oder im Papierkorb', 'wsform-ml'));
			}
			
			$form_object = $ws_form->db_read(true, true);
			ob_end_clean();
			
			if (!$form_object || !isset($form_object->id)) {
				throw new Exception(__('Formular konnte nicht geladen werden', 'wsform-ml'));
			}
			
			return $form_object;
		} catch (Exception $e) {
			ob_end_clean();
			error_log('WSForm ML: get_form_object error - ' . $e->getMessage());
			throw $e;
		}
	}

	private function discover_fields($form_object, $parent_path = '', $parent_id = null) {
		$fields = [];

		if (empty($form_object->groups)) {
			error_log("WSForm ML Scanner: No groups in form object");
			return $fields;
		}

		error_log("WSForm ML Scanner: Form has " . count($form_object->groups) . " groups");

		foreach ($form_object->groups as $group_index => $group) {
			// Scanne Group Label (Tab-Name)
			if (!empty($group->label)) {
				$group_path = "groups.{$group_index}";
				$fields[] = [
					'field_id' => "group_{$group_index}",
					'field_path' => $group_path,
					'field_type' => 'group',
					'field_label' => "Tab: {$group->label}",
					'translatable_properties' => [
						[
							'type' => 'group_label',
							'value' => $group->label,
							'path' => 'label'
						]
					],
					'has_options' => false,
					'is_repeater' => false
				];
				error_log("WSForm ML Scanner: Found group label: {$group->label}");
			}
			
			if (empty($group->sections)) {
				continue;
			}

			error_log("WSForm ML Scanner: Group {$group_index} has " . count($group->sections) . " sections");

			foreach ($group->sections as $section_index => $section) {
				if (empty($section->fields)) {
					continue;
				}

				error_log("WSForm ML Scanner: Section {$section_index} has " . count($section->fields) . " fields");

				foreach ($section->fields as $field_index => $field) {
					$field_path = $parent_path ? "{$parent_path}.fields.{$field_index}" : "groups.{$group_index}.sections.{$section_index}.fields.{$field_index}";
					
					error_log("WSForm ML Scanner: Processing field {$field->id} at {$field_path}");
					
					$field_data = $this->extract_field_data($field, $field_path, $parent_id);
					if ($field_data) {
						$fields[] = $field_data;
						error_log("WSForm ML Scanner: Field {$field->id} extracted with " . count($field_data['translatable_properties']) . " properties");

						if ($this->is_repeater_field($field)) {
							$repeater_fields = $this->scan_repeater_fields($field, $field_path, $field->id);
							$fields = array_merge($fields, $repeater_fields);
						}
					}
				}
			}
		}

		return $fields;
	}

	private function extract_field_data($field, $field_path, $parent_id = null) {
		if (!isset($field->id)) {
			return null;
		}

		$field_type = $field->type ?? 'unknown';
		$translatable_props = $this->get_translatable_properties($field);

		return [
			'field_id' => $field->id,
			'field_type' => $field_type,
			'field_path' => $field_path,
			'field_label' => $field->label ?? '',
			'parent_field_id' => $parent_id,
			'is_repeater' => $this->is_repeater_field($field),
			'has_options' => $this->has_options($field),
			'translatable_properties' => $translatable_props,
			'field_structure' => json_encode($field)
		];
	}

	private function get_translatable_properties($field) {
		$properties = [];

		error_log("WSForm ML Scanner: get_translatable_properties for field {$field->id} ({$field->type})");
		
		// Dumpe komplette Struktur für Options-Felder
		if (in_array($field->type, ['checkbox', 'radio', 'select'])) {
			error_log("WSForm ML Scanner: FULL FIELD DUMP for {$field->type} field {$field->id}:");
			error_log(json_encode($field, JSON_PRETTY_PRINT));
		}

		if (isset($field->label) && !empty($field->label)) {
			$properties[] = [
				'type' => 'label',
				'path' => 'label',
				'value' => $field->label
			];
		}

		if (isset($field->meta)) {
			$meta_properties = [
				'placeholder' => 'placeholder',
				'help' => 'help',
				'invalid_feedback' => 'invalid_feedback',
				'text_editor' => 'text_editor',
				'html' => 'html',
				'aria_label' => 'aria_label',
				// Range Slider spezifische Properties
				'min_label' => 'min_label',
				'max_label' => 'max_label',
				'prefix' => 'prefix',
				'suffix' => 'suffix',
				// Button/Submit Text
				'text' => 'text',
				'label_mask_row_prepend' => 'label_mask_row_prepend',
				'label_mask_row_append' => 'label_mask_row_append'
			];

			foreach ($meta_properties as $prop => $path) {
				if (isset($field->meta->{$prop}) && !empty($field->meta->{$prop})) {
					$properties[] = [
						'type' => $prop,
						'path' => "meta.{$path}",
						'value' => $field->meta->{$prop}
					];
				}
			}
		}

		$has_opts = $this->has_options($field);
		error_log("WSForm ML Scanner: Field {$field->id} has_options = " . ($has_opts ? 'YES' : 'NO'));

		if ($has_opts) {
			$options = $this->extract_options($field);
			error_log("WSForm ML Scanner: Extracted " . count($options) . " options for field {$field->id}");
			foreach ($options as $option_data) {
				$properties[] = $option_data;
			}
		}

		if (isset($field->meta->conditional)) {
			$conditional_texts = $this->extract_conditional_texts($field->meta->conditional);
			$properties = array_merge($properties, $conditional_texts);
		}

		return $properties;
	}

	private function has_options($field) {
		$option_field_types = ['select', 'radio', 'checkbox', 'price_select', 'price_radio', 'price_checkbox'];
		$is_option_type = in_array($field->type ?? '', $option_field_types);
		
		if (!$is_option_type || !isset($field->meta)) {
			return false;
		}
		
		// WSForm nutzt field-type-spezifische data_grid Properties:
		// checkbox -> data_grid_checkbox
		// radio -> data_grid_radio
		// select -> data_grid_select
		// price_* -> data_grid_price_*
		$data_grid_property = 'data_grid_' . $field->type;
		
		return isset($field->meta->{$data_grid_property});
	}

	private function extract_options($field) {
		$options = [];
		
		// Bestimme field-type-spezifische data_grid Property
		$data_grid_property = 'data_grid_' . $field->type;
		
		if (!isset($field->meta->{$data_grid_property}->groups)) {
			error_log("WSForm ML Scanner: Field {$field->id} has no {$data_grid_property}.groups");
			return $options;
		}

		$data_grid = $field->meta->{$data_grid_property};
		error_log("WSForm ML Scanner: Extracting options for field {$field->id} ({$field->type}) from {$data_grid_property}");

		foreach ($data_grid->groups as $group_index => $group) {
			if (!isset($group->rows)) {
				continue;
			}

			error_log("WSForm ML Scanner: Group {$group_index} has " . count($group->rows) . " rows");

			foreach ($group->rows as $row_index => $row) {
				if (isset($row->data) && is_array($row->data)) {
					foreach ($row->data as $col_index => $value) {
						if (!empty($value)) {
							$options[] = [
								'type' => 'option',
								'path' => "meta.{$data_grid_property}.groups.{$group_index}.rows.{$row_index}.data.{$col_index}",
								'value' => $value,
								'context' => "option_{$row_index}_{$col_index}"
							];
							error_log("WSForm ML Scanner: Found option - Row {$row_index}, Col {$col_index}: {$value}");
						}
					}
				}
			}
		}

		return $options;
	}

	private function extract_conditional_texts($conditional) {
		$texts = [];
		return $texts;
	}

	private function is_repeater_field($field) {
		return isset($field->type) && $field->type === 'repeater';
	}

	private function scan_repeater_fields($repeater_field, $parent_path, $parent_id) {
		$fields = [];

		if (!isset($repeater_field->meta->repeater_sections)) {
			return $fields;
		}

		foreach ($repeater_field->meta->repeater_sections as $section_index => $section) {
			if (empty($section->fields)) {
				continue;
			}

			foreach ($section->fields as $field_index => $field) {
				$field_path = "{$parent_path}.meta.repeater_sections.{$section_index}.fields.{$field_index}";
				
				$field_data = $this->extract_field_data($field, $field_path, $parent_id);
				if ($field_data) {
					$fields[] = $field_data;
				}
			}
		}

		return $fields;
	}

	private function sync_fields_to_cache($form_id, $discovered_fields, &$stats) {
		global $wpdb;
		$table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_FIELD_CACHE);

		// Lade existierende Felder mit field_id + field_path als Key
		$existing_fields = $wpdb->get_results($wpdb->prepare(
			"SELECT field_id, field_path FROM $table WHERE form_id = %d",
			$form_id
		), ARRAY_A);

		// Erstelle Lookup-Map mit field_id + field_path
		$existing_map = [];
		foreach ($existing_fields as $field) {
			$key = $field['field_id'] . '::' . $field['field_path'];
			$existing_map[$key] = true;
		}

		$discovered_keys = [];

		foreach ($discovered_fields as $field_data) {
			$key = $field_data['field_id'] . '::' . $field_data['field_path'];
			$discovered_keys[$key] = true;

			if (isset($existing_map[$key])) {
				// UPDATE existierendes Feld
				$wpdb->update(
					$table,
					[
						'field_type' => $field_data['field_type'],
						'field_label' => $field_data['field_label'],
						'parent_field_id' => $field_data['parent_field_id'],
						'is_repeater' => $field_data['is_repeater'],
						'has_options' => $field_data['has_options'],
						'translatable_properties' => json_encode($field_data['translatable_properties']),
						'field_structure' => $field_data['field_structure'],
						'last_scanned' => current_time('mysql')
					],
					[
						'form_id' => $form_id,
						'field_id' => $field_data['field_id'],
						'field_path' => $field_data['field_path']
					]
				);
				$stats['updated_fields']++;
			} else {
				// INSERT neues Feld
				$wpdb->insert(
					$table,
					[
						'form_id' => $form_id,
						'field_id' => $field_data['field_id'],
						'field_type' => $field_data['field_type'],
						'field_path' => $field_data['field_path'],
						'field_label' => $field_data['field_label'],
						'parent_field_id' => $field_data['parent_field_id'],
						'is_repeater' => $field_data['is_repeater'],
						'has_options' => $field_data['has_options'],
						'translatable_properties' => json_encode($field_data['translatable_properties']),
						'field_structure' => $field_data['field_structure'],
						'last_scanned' => current_time('mysql')
					]
				);
				$stats['new_fields']++;
			}
		}

		// Lösche Felder die nicht mehr existieren
		foreach ($existing_fields as $field) {
			$key = $field['field_id'] . '::' . $field['field_path'];
			if (!isset($discovered_keys[$key])) {
				$wpdb->delete(
					$table,
					[
						'form_id' => $form_id,
						'field_id' => $field['field_id'],
						'field_path' => $field['field_path']
					]
				);
				$stats['deleted_fields']++;
			}
		}
	}

	private function log_scan($form_id, $scan_type, $stats, $status, $error_message, $duration) {
		global $wpdb;
		$table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_SCAN_LOG);

		$wpdb->insert(
			$table,
			[
				'form_id' => $form_id,
				'scan_type' => $scan_type,
				'fields_found' => $stats['fields_found'],
				'new_fields' => $stats['new_fields'],
				'updated_fields' => $stats['updated_fields'],
				'deleted_fields' => $stats['deleted_fields'],
				'scan_status' => $status,
				'error_message' => $error_message,
				'scan_duration' => $duration,
				'scanned_at' => current_time('mysql')
			]
		);
	}

	public function get_cached_fields($form_id) {
		global $wpdb;
		$table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_FIELD_CACHE);

		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
		
		if (!$table_exists) {
			return [];
		}

		$fields = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $table WHERE form_id = %d ORDER BY field_path ASC",
			$form_id
		));

		if (empty($fields)) {
			return [];
		}

		foreach ($fields as &$field) {
			$field->translatable_properties = json_decode($field->translatable_properties, true);
		}

		return $fields;
	}
}
