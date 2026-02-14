<?php
if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Renderer {
	private static $instance = null;
	private $translation_manager;
	private $current_language;

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->translation_manager = WSForm_ML_Translation_Manager::instance();
		$this->init_hooks();
	}

	private function init_hooks() {
		// WSForm Hook: wsf_pre_render - wird vor dem Rendern ALLER Formulare aufgerufen
		// Quelle: https://wsform.com/knowledgebase/wsf_pre_render/
		add_filter('wsf_pre_render', [$this, 'translate_form'], 10, 2);
		
		// Debug: Bestätige dass Renderer initialisiert wurde
		add_action('init', function() {
			error_log('WSForm ML: Renderer initialized - Hook registered on wsf_pre_render');
		}, 999);
	}

	public function translate_form($form, $preview = false) {
		error_log('WSForm ML: translate_form called - Form ID: ' . ($form->id ?? 'unknown') . ' - Preview: ' . ($preview ? 'yes' : 'no'));
		
		if ($preview) {
			return $form;
		}

		$this->current_language = WSForm_ML_Polylang_Integration::get_current_language();
		error_log('WSForm ML: Current language: ' . $this->current_language);
		
		if (!$this->current_language) {
			error_log('WSForm ML: No current language');
			return $form;
		}

		$form_id = $form->id ?? 0;
		error_log('WSForm ML: Form ID: ' . $form_id);
		
		if (!$form_id) {
			return $form;
		}

		// Lade Übersetzungen für die aktuelle Sprache (inkl. Standard-Sprache!)
		// So kann der User für JEDE Sprache Übersetzungen eingeben
		$translations = $this->translation_manager->get_form_translations($form_id, $this->current_language);
		error_log('WSForm ML: Found ' . count($translations) . ' translations for language: ' . $this->current_language);
		
		if (empty($translations)) {
			error_log('WSForm ML: No translations found - showing original values');
			return $form;
		}

		$translation_map = $this->build_translation_map($translations);
		error_log('WSForm ML: Translation map keys: ' . implode(', ', array_keys($translation_map)));
		
		$form = $this->apply_translations($form, $translation_map);
		error_log('WSForm ML: Translations applied');

		return $form;
	}

	private function build_translation_map($translations) {
		$map = [];

		foreach ($translations as $translation) {
			// Standard Key: field_path::property_type
			$key = $translation->field_path . '::' . $translation->property_type;
			$map[$key] = $translation->translated_value;
			
			error_log("WSForm ML: Translation Map - Key: {$key}, Type: {$translation->property_type}");
		}

		return $map;
	}

	private function apply_translations($form_object, $translation_map) {
		if (empty($form_object->groups)) {
			return $form_object;
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
					$field_path = "groups.{$group_index}.sections.{$section_index}.fields.{$field_index}";
					$this->translate_field($field, $field_path, $translation_map);
				}
			}
		}

		return $form_object;
	}

	private function translate_field(&$field, $field_path, $translation_map) {
		if (isset($translation_map["{$field_path}::label"])) {
			$field->label = $translation_map["{$field_path}::label"];
		}

		if (isset($field->meta)) {
			$meta_properties = [
				'placeholder',
				'help',
				'invalid_feedback',
				'text_editor',
				'html',
				'aria_label',
				// Range Slider
				'min_label',
				'max_label',
				'prefix',
				'suffix',
				// Button/Submit
				'text',
				'label_mask_row_prepend',
				'label_mask_row_append'
			];

			foreach ($meta_properties as $prop) {
				// Prüfe beide Key-Formate für Kompatibilität
				$key1 = "{$field_path}::{$prop}";
				$key2 = "{$field_path}::meta.{$prop}";
				
				if (isset($translation_map[$key1])) {
					$field->meta->{$prop} = $translation_map[$key1];
				} elseif (isset($translation_map[$key2])) {
					$field->meta->{$prop} = $translation_map[$key2];
				}
			}

			if (isset($field->meta->data_grid->groups)) {
				$this->translate_options($field, $field_path, $translation_map);
			}
		}

		if (isset($field->type) && $field->type === 'repeater' && isset($field->meta->repeater_sections)) {
			$this->translate_repeater_fields($field, $field_path, $translation_map);
		}
	}

	private function translate_options(&$field, $field_path, $translation_map) {
		foreach ($field->meta->data_grid->groups as $group_index => $group) {
			if (!isset($group->rows)) {
				continue;
			}

			foreach ($group->rows as $row_index => $row) {
				if (isset($row->data) && is_array($row->data)) {
					foreach ($row->data as $col_index => $value) {
						// Prüfe beide Key-Formate
						$key1 = "{$field_path}::option";
						$key2 = "{$field_path}::meta.data_grid.groups.{$group_index}.rows.{$row_index}.data.{$col_index}";
						$key3 = "{$field_path}::meta.data_grid.groups.0.rows.{$row_index}.data.{$col_index}";
						
						if (isset($translation_map[$key1])) {
							// Generischer Option-Key (wenn mehrere Options gleich behandelt werden)
							$row->data[$col_index] = $translation_map[$key1];
							error_log("WSForm ML: Translated option (generic) at {$field_path}");
						} elseif (isset($translation_map[$key2])) {
							$row->data[$col_index] = $translation_map[$key2];
							error_log("WSForm ML: Translated option at {$key2}");
						} elseif (isset($translation_map[$key3])) {
							$row->data[$col_index] = $translation_map[$key3];
							error_log("WSForm ML: Translated option at {$key3}");
						}
					}
				}
			}
		}
	}

	private function translate_repeater_fields(&$field, $parent_path, $translation_map) {
		foreach ($field->meta->repeater_sections as $section_index => $section) {
			if (empty($section->fields)) {
				continue;
			}

			foreach ($section->fields as $field_index => $repeater_field) {
				$field_path = "{$parent_path}.meta.repeater_sections.{$section_index}.fields.{$field_index}";
				$this->translate_field($repeater_field, $field_path, $translation_map);
			}
		}
	}
}
