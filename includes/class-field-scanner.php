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
		$start_time = microtime(true);
		$stats = [
			'fields_found' => 0,
			'new_fields' => 0,
			'updated_fields' => 0,
			'deleted_fields' => 0
		];

		try {
			$form_object = $this->get_form_object($form_id);
			if (!$form_object) {
				throw new Exception(__('Formular nicht gefunden', 'wsform-ml'));
			}

			$discovered_fields = $this->discover_fields($form_object);
			$stats['fields_found'] = count($discovered_fields);

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
			return false;
		}

		try {
			$ws_form = new WS_Form_Form();
			$ws_form->id = $form_id;
			$form_object = $ws_form->db_read(true, true);
			return $form_object;
		} catch (Exception $e) {
			return false;
		}
	}

	private function discover_fields($form_object, $parent_path = '', $parent_id = null) {
		$fields = [];

		if (empty($form_object->groups)) {
			return $fields;
		}

		foreach ($form_object->groups as $group_index => $group) {
			if (empty($group->sections)) {
				continue;
			}

			foreach ($group->sections as $section_index => $section) {
				if (empty($section->fields)) {
					continue;
				}

				foreach ($section->fields as $field_index => $field) {
					$field_path = $parent_path ? "{$parent_path}.fields.{$field_index}" : "groups.{$group_index}.sections.{$section_index}.fields.{$field_index}";
					
					$field_data = $this->extract_field_data($field, $field_path, $parent_id);
					if ($field_data) {
						$fields[] = $field_data;

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
				'aria_label' => 'aria_label'
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

		if ($this->has_options($field)) {
			$options = $this->extract_options($field);
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
		return in_array($field->type ?? '', $option_field_types) && isset($field->meta->data_grid);
	}

	private function extract_options($field) {
		$options = [];
		
		if (!isset($field->meta->data_grid->groups)) {
			return $options;
		}

		foreach ($field->meta->data_grid->groups as $group) {
			if (!isset($group->rows)) {
				continue;
			}

			foreach ($group->rows as $row_index => $row) {
				if (isset($row->data) && is_array($row->data)) {
					foreach ($row->data as $col_index => $value) {
						if (!empty($value)) {
							$options[] = [
								'type' => 'option',
								'path' => "meta.data_grid.groups.0.rows.{$row_index}.data.{$col_index}",
								'value' => $value,
								'context' => "option_{$row_index}_{$col_index}"
							];
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

		$existing_fields = $wpdb->get_results($wpdb->prepare(
			"SELECT field_id, field_path FROM $table WHERE form_id = %d",
			$form_id
		), OBJECT_K);

		$discovered_paths = [];

		foreach ($discovered_fields as $field_data) {
			$discovered_paths[$field_data['field_path']] = true;
			$key = $field_data['field_path'];

			if (isset($existing_fields[$key])) {
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
						'field_path' => $field_data['field_path']
					]
				);
				$stats['updated_fields']++;
			} else {
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

		foreach ($existing_fields as $path => $field) {
			if (!isset($discovered_paths[$path])) {
				$wpdb->delete(
					$table,
					[
						'form_id' => $form_id,
						'field_path' => $path
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

		$fields = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $table WHERE form_id = %d ORDER BY field_path ASC",
			$form_id
		));

		foreach ($fields as &$field) {
			$field->translatable_properties = json_decode($field->translatable_properties, true);
		}

		return $fields;
	}
}
