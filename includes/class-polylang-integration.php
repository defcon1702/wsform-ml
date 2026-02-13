<?php
if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Polylang_Integration {
	private static $instance = null;

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function is_polylang_active() {
		return function_exists('pll_languages_list') && function_exists('pll_current_language');
	}

	public static function get_languages() {
		if (!self::is_polylang_active()) {
			return [
				[
					'code' => 'en',
					'name' => 'English',
					'flag' => '',
					'is_default' => true
				]
			];
		}

		$languages = [];
		
		try {
			$pll_languages = pll_languages_list(['fields' => false]);
			$default_lang = pll_default_language();

			if (empty($pll_languages)) {
				return [
					[
						'code' => 'en',
						'name' => 'English',
						'flag' => '',
						'is_default' => true
					]
				];
			}

			foreach ($pll_languages as $lang_code) {
				if (!function_exists('PLL') || !isset(PLL()->model)) {
					$languages[] = [
						'code' => $lang_code,
						'name' => $lang_code,
						'flag' => '',
						'is_default' => $lang_code === $default_lang
					];
					continue;
				}

				$lang_obj = PLL()->model->get_language($lang_code);
				
				$languages[] = [
					'code' => $lang_code,
					'name' => $lang_obj->name ?? $lang_code,
					'flag' => $lang_obj->flag ?? '',
					'is_default' => $lang_code === $default_lang
				];
			}
		} catch (Exception $e) {
			error_log('WSForm ML: Error getting Polylang languages - ' . $e->getMessage());
			return [
				[
					'code' => 'en',
					'name' => 'English',
					'flag' => '',
					'is_default' => true
				]
			];
		}

		return $languages;
	}

	public static function get_current_language() {
		if (!self::is_polylang_active()) {
			return 'en';
		}

		return pll_current_language();
	}

	public static function get_default_language() {
		if (!self::is_polylang_active()) {
			return 'en';
		}

		return pll_default_language();
	}

	public static function register_strings($form_id, $fields) {
		if (!self::is_polylang_active() || !function_exists('pll_register_string')) {
			return;
		}

		foreach ($fields as $field) {
			$translatable_props = json_decode($field->translatable_properties, true);
			
			if (empty($translatable_props)) {
				continue;
			}

			foreach ($translatable_props as $prop) {
				$string_name = sprintf(
					'WSForm %d - Field %d - %s',
					$form_id,
					$field->field_id,
					$prop['type']
				);

				pll_register_string(
					$string_name,
					$prop['value'],
					'wsform-ml',
					false
				);
			}
		}
	}

	public static function translate_string($string, $language_code = null) {
		if (!self::is_polylang_active() || !function_exists('pll__')) {
			return $string;
		}

		if ($language_code) {
			return pll_translate_string($string, $language_code);
		}

		return pll__($string);
	}
}
