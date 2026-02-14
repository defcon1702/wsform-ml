<?php
if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Translation_Manager {
	private static $instance = null;

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_translation($form_id, $field_id, $field_path, $property_type, $language_code) {
		global $wpdb;
		$table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_TRANSLATIONS);

		// Berechne field_path_hash für eindeutige Identifikation
		// WICHTIG: Für Options mit gleichem field_id aber unterschiedlichem field_path
		// müssen wir auch field_path_hash prüfen, sonst überschreiben sie sich!
		$field_path_hash = hash('sha256', $field_path);

		return $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE form_id = %d AND field_id = %s AND field_path_hash = %s AND property_type = %s AND language_code = %s",
			$form_id,
			$field_id,
			$field_path_hash,
			$property_type,
			$language_code
		));
	}

	public function save_translation($data) {
		global $wpdb;
		$table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_TRANSLATIONS);

		$existing = $this->get_translation(
			$data['form_id'],
			$data['field_id'],
			$data['field_path'],
			$data['property_type'],
			$data['language_code']
		);

		$translation_data = [
			'form_id' => $data['form_id'],
			'field_id' => $data['field_id'],
			'field_path' => $data['field_path'],
			'field_path_hash' => hash('sha256', $data['field_path']),
			'property_type' => $data['property_type'],
			'language_code' => $data['language_code'],
			'original_value' => $data['original_value'] ?? null,
			'translated_value' => $data['translated_value'],
			'context' => $data['context'] ?? null,
			'is_auto_generated' => $data['is_auto_generated'] ?? 0,
			'last_synced' => current_time('mysql')
		];

		if ($existing) {
			$wpdb->update(
				$table,
				array_merge($translation_data, ['updated_at' => current_time('mysql')]),
				['id' => $existing->id]
			);
			return $existing->id;
		} else {
			$wpdb->insert($table, $translation_data);
			return $wpdb->insert_id;
		}
	}

	public function get_form_translations($form_id, $language_code = null) {
		global $wpdb;
		$table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_TRANSLATIONS);

		if ($language_code) {
			$translations = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM $table WHERE form_id = %d AND language_code = %s ORDER BY field_path, property_type",
				$form_id,
				$language_code
			));
		} else {
			$translations = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM $table WHERE form_id = %d ORDER BY language_code, field_path, property_type",
				$form_id
			));
		}

		return $translations;
	}

	public function delete_translation($id) {
		global $wpdb;
		$table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_TRANSLATIONS);

		return $wpdb->delete($table, ['id' => $id]);
	}

	public function get_missing_translations($form_id, $language_code) {
		global $wpdb;
		$cache_table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_FIELD_CACHE);
		$trans_table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_TRANSLATIONS);

		// 1. Lade alle gecachten Felder
		$cached_fields = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $cache_table WHERE form_id = %d",
			$form_id
		));

		// 2. Lade alle existierenden Übersetzungen für Form + Sprache auf einmal (N+1 Fix!)
		$existing_translations = $wpdb->get_results($wpdb->prepare(
			"SELECT field_id, property_type 
			 FROM $trans_table 
			 WHERE form_id = %d AND language_code = %s",
			$form_id,
			$language_code
		));

		// 3. Erstelle Lookup-Map für O(1) Zugriff
		// WICHTIG: Verwende nur field_id als Key (stabil!), nicht field_path (instabil!)
		$translation_map = [];
		foreach ($existing_translations as $trans) {
			$key = "{$trans->field_id}::{$trans->property_type}";
			$translation_map[$key] = true;
		}

		// 4. Prüfe gegen Map statt DB
		$missing = [];

		foreach ($cached_fields as $field) {
			$translatable_props = json_decode($field->translatable_properties, true);
			
			if (empty($translatable_props)) {
				continue;
			}

			foreach ($translatable_props as $prop) {
				// Verwende nur field_id als Key (stabil!)
				$key = "{$field->field_id}::{$prop['type']}";
				
				if (!isset($translation_map[$key])) {
					$missing[] = [
						'field_id' => $field->field_id,
						'field_path' => $field->field_path,
						'field_label' => $field->field_label,
						'property_type' => $prop['type'],
						'original_value' => $prop['value'],
						'context' => $prop['context'] ?? null
					];
				}
			}
		}

		return $missing;
	}

	public function bulk_save_translations($translations) {
		$results = [
			'success' => 0,
			'failed' => 0,
			'errors' => []
		];

		foreach ($translations as $translation) {
			try {
				$this->save_translation($translation);
				$results['success']++;
			} catch (Exception $e) {
				$results['failed']++;
				$results['errors'][] = $e->getMessage();
			}
		}

		return $results;
	}

	public function get_translation_stats($form_id) {
		global $wpdb;
		$cache_table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_FIELD_CACHE);
		$trans_table = WSForm_ML_Database::get_table_name(WSForm_ML_Database::TABLE_TRANSLATIONS);

		$cache_exists = $wpdb->get_var("SHOW TABLES LIKE '$cache_table'") === $cache_table;
		$trans_exists = $wpdb->get_var("SHOW TABLES LIKE '$trans_table'") === $trans_table;

		if (!$cache_exists || !$trans_exists) {
			return [
				'total_fields' => 0,
				'languages' => []
			];
		}

		$total_fields = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $cache_table WHERE form_id = %d",
			$form_id
		));

		$languages = WSForm_ML_Polylang_Integration::get_languages();
		$stats = [
			'total_fields' => (int)$total_fields,
			'languages' => []
		];

		foreach ($languages as $lang) {
			if (!is_array($lang) || !isset($lang['code']) || !isset($lang['name'])) {
				continue;
			}
			
			$lang_code = (string)$lang['code'];
			$lang_name = (string)$lang['name'];
			
			$translated_count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(DISTINCT CONCAT(field_id, '-', property_type)) FROM $trans_table WHERE form_id = %d AND language_code = %s",
				$form_id,
				$lang_code
			));

			$total_translatable = $wpdb->get_var($wpdb->prepare(
				"SELECT SUM(JSON_LENGTH(translatable_properties)) FROM $cache_table WHERE form_id = %d",
				$form_id
			));

			$stats['languages'][$lang_code] = [
				'name' => $lang_name,
				'translated' => (int)$translated_count,
				'total' => (int)$total_translatable,
				'percentage' => $total_translatable > 0 ? round(($translated_count / $total_translatable) * 100, 2) : 0
			];
		}

		return $stats;
	}
}
