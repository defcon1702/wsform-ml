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
		}, 999);
	}

	public function translate_form($form, $preview = false) {
		
		if ($preview) {
			return $form;
		}

		$this->current_language = WSForm_ML_Polylang_Integration::get_current_language();
		
		if (!$this->current_language) {
			return $form;
		}

		$form_id = $form->id ?? 0;
		
		if (!$form_id) {
			return $form;
		}

		// Lade Übersetzungen für die aktuelle Sprache (inkl. Standard-Sprache!)
		// So kann der User für JEDE Sprache Übersetzungen eingeben
		$translations = $this->translation_manager->get_form_translations($form_id, $this->current_language);
		
		if (empty($translations)) {
			return $form;
		}

		$translation_map = $this->build_translation_map($translations);
		
		$form = $this->apply_translations($form, $translation_map);

		return $form;
	}

	private function build_translation_map($translations) {
		$map = [];

		foreach ($translations as $translation) {
			// WICHTIG: Verwende field_id als Key (stabil!) statt field_path (instabil!)
			// field_path ändert sich beim Hinzufügen/Entfernen von Feldern
			$key = $translation->field_id . '::' . $translation->property_type;
			$map[$key] = $translation->translated_value;
			
		}

		return $map;
	}

	private function apply_translations($form_object, $translation_map) {
		if (empty($form_object->groups)) {
			return $form_object;
		}

		foreach ($form_object->groups as $group_index => $group) {
			// Übersetze Group Label (Tab-Name) - Groups haben keine field_id, nutze group_index
			$group_label_key = "group_{$group_index}::group_label";
			if (isset($translation_map[$group_label_key])) {
				$group->label = $translation_map[$group_label_key];
			}
			
			if (empty($group->sections)) {
				continue;
			}

			foreach ($group->sections as $section_index => $section) {
				if (empty($section->fields)) {
					continue;
				}

				foreach ($section->fields as $field_index => $field) {
					// Verwende field->id statt field_path für Translation Lookup
					$this->translate_field($field, $translation_map);
				}
			}
		}

		return $form_object;
	}

	private function translate_field(&$field, $translation_map) {
		// Verwende field->id als Key (stabil!)
		$field_id = $field->id ?? null;
		if (!$field_id) {
			return;
		}
		
		if (isset($translation_map["{$field_id}::label"])) {
			$field->label = $translation_map["{$field_id}::label"];
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
				// Verwende field_id als Key
				$key1 = "{$field_id}::{$prop}";
				$key2 = "{$field_id}::meta.{$prop}";
				
				if (isset($translation_map[$key1])) {
					$field->meta->{$prop} = $translation_map[$key1];
				} elseif (isset($translation_map[$key2])) {
					$field->meta->{$prop} = $translation_map[$key2];
				}
			}

			// Prüfe field-type-spezifische data_grid Properties
			$data_grid_property = 'data_grid_' . ($field->type ?? '');
			if (isset($field->meta->{$data_grid_property}->groups)) {
				$this->translate_options($field, $field_id, $translation_map, $data_grid_property);
			}
		}

		if (isset($field->type) && $field->type === 'repeater' && isset($field->meta->repeater_sections)) {
			$this->translate_repeater_fields($field, $field_id, $translation_map);
		}
	}

	private function translate_options(&$field, $field_id, $translation_map, $data_grid_property) {
		$data_grid = $field->meta->{$data_grid_property};
		
		foreach ($data_grid->groups as $group_index => $group) {
			if (!isset($group->rows)) {
				continue;
			}

			foreach ($group->rows as $row_index => $row) {
				if (isset($row->data) && is_array($row->data)) {
					foreach ($row->data as $col_index => $value) {
						// Verwende field_id für Options
						// Format: field_id.meta.data_grid_TYPE.groups.X.rows.Y.data.Z::option
						$key = "{$field_id}.meta.{$data_grid_property}.groups.{$group_index}.rows.{$row_index}.data.{$col_index}::option";
						
						if (isset($translation_map[$key])) {
							$row->data[$col_index] = $translation_map[$key];
						}
					}
				}
			}
		}
	}

	private function translate_repeater_fields(&$field, $parent_field_id, $translation_map) {
		foreach ($field->meta->repeater_sections as $section_index => $section) {
			if (empty($section->fields)) {
				continue;
			}

			foreach ($section->fields as $field_index => $repeater_field) {
				// Repeater-Felder haben ihre eigene field_id
				$this->translate_field($repeater_field, $translation_map);
			}
		}
	}
}
