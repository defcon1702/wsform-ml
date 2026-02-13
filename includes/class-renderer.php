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
		add_action('ws_form_pre_render', [$this, 'translate_form'], 10, 2);
	}

	public function translate_form($form_object, $preview) {
		if ($preview) {
			return $form_object;
		}

		$this->current_language = WSForm_ML_Polylang_Integration::get_current_language();
		
		if (!$this->current_language || $this->current_language === WSForm_ML_Polylang_Integration::get_default_language()) {
			return $form_object;
		}

		$form_id = $form_object->id ?? 0;
		if (!$form_id) {
			return $form_object;
		}

		$translations = $this->translation_manager->get_form_translations($form_id, $this->current_language);
		
		if (empty($translations)) {
			return $form_object;
		}

		$translation_map = $this->build_translation_map($translations);
		
		$form_object = $this->apply_translations($form_object, $translation_map);

		return $form_object;
	}

	private function build_translation_map($translations) {
		$map = [];

		foreach ($translations as $translation) {
			$key = $translation->field_path . '::' . $translation->property_type;
			$map[$key] = $translation->translated_value;
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
				'aria_label'
			];

			foreach ($meta_properties as $prop) {
				$key = "{$field_path}::meta.{$prop}";
				if (isset($translation_map[$key])) {
					$field->meta->{$prop} = $translation_map[$key];
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
		foreach ($field->meta->data_grid->groups as $group) {
			if (!isset($group->rows)) {
				continue;
			}

			foreach ($group->rows as $row_index => $row) {
				if (isset($row->data) && is_array($row->data)) {
					foreach ($row->data as $col_index => $value) {
						$key = "{$field_path}::meta.data_grid.groups.0.rows.{$row_index}.data.{$col_index}";
						if (isset($translation_map[$key])) {
							$row->data[$col_index] = $translation_map[$key];
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
