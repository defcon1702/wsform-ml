<?php

	// Exit if accessed directly
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class WS_Form_Translate {

		public $form_object = false;

		public function __construct() {

			// Form actions
			add_action('wsf_form_create', 'form_create', 10, 1);
			add_action('wsf_form_delete', 'form_delete', 10, 1);

			// Translate form when form parsed
			add_filter('wsf_form_translate', array($this, 'form_translate'), 10, 1);
		}

		public function form_create($form_obj) {

			// Load form
			self::form_load($form_obj->id);

			// Register form
			self::form_register($this->form_object);
		}

		public function form_load($form_id) {

			// Read form
			$ws_form_form = new WS_Form_Form();
			$ws_form_form->id = $form_id;

			// Get form object
			$this->form_object = $ws_form_form->db_read(true, true);
		}

		public function form_delete($form_id) {

			// Unregister all translations
			self::unregister_all($form_id);
		}

		public function form_translate($form_object) {

			// Get translatable meta keys
			$meta_keys = self::get_meta_keys_translatable();

			// Set form object
			$this->form_object = $form_object;

			// Form
			$this->form_object = self::form_translate_object($this->form_object, 'form', $meta_keys);

			// Groups
			if(property_exists($this->form_object, 'groups')) {

				$this->form_object->groups = self::form_translate_groups($this->form_object->groups, $meta_keys);
			}

			return $this->form_object;
		}

		public function form_translate_groups($groups, $meta_keys) {

			foreach($groups as $group_index => $group) {

				$groups[$group_index] = self::form_translate_object($group, 'group', $meta_keys);

				if(property_exists($groups[$group_index], 'sections')) {

					$groups[$group_index]->sections = self::form_translate_sections($groups[$group_index]->sections, $meta_keys);
				}
			}

			return $groups;
		}

		public function form_translate_sections($sections, $meta_keys) {

			foreach($sections as $section_index => $section) {

				$sections[$section_index] = self::form_translate_object($section, 'section', $meta_keys);

				if(property_exists($sections[$section_index], 'fields')) {

					$sections[$section_index]->fields = self::form_translate_fields($sections[$section_index]->fields, $meta_keys);
				}
			}

			return $sections;
		}

		public function form_translate_fields($fields, $meta_keys) {

			foreach($fields as $field_index => $field) {

				$fields[$field_index] = self::form_translate_object($field, 'field', $meta_keys);
			}

			return $fields;
		}

		public function form_translate_object($object, $object_type, $meta_keys) {

			// Get object ID
			$object_id = self::get_object_id($object, $object_type);

			// Translate object label
			$object->label = self::translate(

				$object->label,												// String value
				self::get_string_id($object_type, 'label', $object_id)		// String ID
			);

			if(property_exists($object, 'meta')) {

				// Translate meta data
				foreach($object->meta as $meta_key => $meta_value) {

					// Skip empty meta values
					if(empty($meta_value)) { continue; }

					// Skip unknown meta keys or meta keys we should not translate
					if(!isset($meta_keys[$meta_key])) { continue; }

					// Translate meta key
					$object->meta->{$meta_key} = self::translate(

						$object->meta->{$meta_key},														// String value
						self::get_string_id($object_type, self::meta_key_to_id($meta_key), $object_id)	// String ID
					);
				}
			}

			return $object;
		}

		public function form_register($form_object) {

			// Start
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- All hooks prefixed with wsf_
			do_action('wsf_translate_start', $form_object->id, $form_object->label);

			// Get translatable meta keys
			$meta_keys = self::get_meta_keys_translatable();

			// Process form
			self::form_register_object($form_object, 'form', $meta_keys);

			// Process groups
			if(property_exists($form_object, 'groups')) {

				self::form_register_groups($form_object->groups, $meta_keys);
			}

			// Finish
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- All hooks prefixed with wsf_
			do_action('wsf_translate_finish', $form_object->id, $form_object->label);
		}

		public function form_register_groups($groups, $meta_keys) {

			foreach($groups as $group) {

				// Process group
				self::form_register_object($group, 'group', $meta_keys);

				// Process sections
				if(property_exists($group, 'sections')) {

					self::form_register_sections($group->sections, $meta_keys);
				}
			}
		}

		public function form_register_sections($sections, $meta_keys) {

			foreach($sections as $section) {

				// Process section
				self::form_register_object($section, 'section', $meta_keys);

				// Process sections
				if(property_exists($section, 'fields')) {

					self::form_register_fields($section->fields, $meta_keys);
				}
			}
		}

		public function form_register_fields($fields, $meta_keys) {

			foreach($fields as $field) {

				// Process field
				self::form_register_object($field, 'field', $meta_keys);
			}
		}

		public function form_register_object($object, $object_type, $meta_keys) {

			// Get object ID
			$object_id = self::get_object_id($object, $object_type);

			// Get object label
			$object_label = $object->label;

			// Register label translation
			self::register(

				self::get_string_id($object_type, 'label', $object_id),	// String ID
				self::get_object_label($object_type),					// String label
				'text',													// String type
				$object_label											// String value
			);

			if(property_exists($object, 'meta')) {

				// Register meta data translations
				foreach($object->meta as $meta_key => $meta_value) {

					// Skip empty meta values
					if(empty($meta_value)) { continue; }

					// Skip unknown meta keys or meta keys we should not translate
					if(!isset($meta_keys[$meta_key])) { continue; }

					// Get meta config
					$meta_config = $meta_keys[$meta_key];

					// Get meta label
					$meta_label = isset($meta_config['label']) ? $meta_config['label'] : __('Unknown', 'ws-form');

					// Register meta key
					self::register(

						self::get_string_id($object_type, self::meta_key_to_id($meta_key), $object_id),		// String ID
						self::get_string_label($object_type, $object_id, $object_label, $meta_label),		// String label
						$meta_config['type'],																// String type
						$meta_value,																		// String value
					);
				}
			}
		}

		public function get_object_label($object_type) {

			switch($object_type) {

				case 'form' :

					return __('Form Label', 'ws-form');

				case 'group' :

					return __('Tab Label', 'ws-form');

				case 'section' :

					return __('Section Label', 'ws-form');

				case 'field' :

					return __('Field Label', 'ws-form');
			}
		}

		public function get_object_id($object, $object_type) {

			switch($object_type) {

				case 'form' :

					return false;

				case 'group' :
				case 'section' :
				case 'field' :

					return $object->id;
			}
		}

		public function meta_key_to_id($meta_key) {

			return str_replace('_', '-', $meta_key);
		}

		public function get_string_id($object_type, $suffix, $object_id) {

			return ($object_id !== false) ? sprintf('wsf-%s-%u-%s', $object_type, $object_id, $suffix) : sprintf('wsf-%s-%s', $object_type, $suffix);
		}

		public function get_string_label($object_type, $object_id, $object_label, $meta_label) {

			switch($object_type) {

				case 'form' :

					/* translators: %1$u: Object ID, %2$s: Meta label */
					return sprintf(__('Form (%1$u) - %2$s', 'ws-form'), $object_id, $meta_label);

				case 'group' :

					/* translators: %1$s: Object label, %2$u: Object ID, %3$s: Meta label */
					return sprintf(__('Tab: %1$s (%2$u) - %3$s', 'ws-form'), $object_label, $object_id, $meta_label);

				case 'section' :

					/* translators: %1$s: Object label, %2$u: Object ID, %3$s: Meta label */
					return sprintf(__('Section: %1$s (%2$u) - %3$s', 'ws-form'), $object_label, $object_id, $meta_label);

				case 'field' :

					/* translators: %1$s: Object label, %2$u: Object ID, %3$s: Meta label */
					return sprintf(__('Field: %1$s (%2$u) - %3$s', 'ws-form'), $object_label, $object_id, $meta_label);
			}
		}

		public function get_meta_keys_translatable() {

			// Get meta keys
			$meta_keys = array();

			foreach(WS_Form_Config::get_meta_keys(0, false, true) as $meta_key => $meta_key_config) {

				// Add translatable meta keys
				if(
					isset($meta_key_config['translate']) &&
					$meta_key_config['translate']
				) {
					$meta_keys[$meta_key] = $meta_key_config;
				}
			}

			return $meta_keys;
		}

		public function translate($string_value, $string_id) {

			// Translate label
			return apply_filters(

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- All hooks prefixed with wsf_
				'wsf_translate',
				$string_value,				// String value
				$string_id, 				// String ID
				$this->form_object->id,		// Form ID
				$this->form_object->label	// Form label
			);
		}

		public function register($string_id, $string_label, $type, $string_value) {

			// Do action hook for wsf_translation_register
			do_action(

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- All hooks prefixed with wsf_
				'wsf_translate_register',
				$string_value,							// String value
				$string_id,								// String ID
				$string_label,							// String label
				$type,									// Type (WS Form),
				$this->form_object->id,					// Form ID
				$this->form_object->label				// Form label
			);
		}

		public function unregister_all($form_id) {

			// Do action hook for wsf_translation_register_string
			do_action(

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- All hooks prefixed with wsf_
				'wsf_translate_unregister_all',
				$form_id								// Form ID
			);
		}
	}

	new WS_Form_Translate();
