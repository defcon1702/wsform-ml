<?php
/**
 * Debug Script: Zeigt Translation Map und Renderer-Verhalten
 */

// WordPress laden
require_once(__DIR__ . '/../../../wp-load.php');

if (!defined('ABSPATH')) {
	die('WordPress nicht gefunden!');
}

$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 4;
$lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'de';

echo "<pre>";
echo "Debug: Renderer Translation Map für Form #{$form_id} (Sprache: {$lang})\n";
echo str_repeat("=", 80) . "\n\n";

// Lade Renderer - verwende getInstance() da Constructor private ist
require_once(plugin_dir_path(__FILE__) . 'includes/class-translation-manager.php');
require_once(plugin_dir_path(__FILE__) . 'includes/class-renderer.php');

// Hole Übersetzungen aus DB
global $wpdb;
$table = $wpdb->prefix . 'wsform_ml_translations';
$translations = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $table WHERE form_id = %d AND language_code = %s ORDER BY field_id, property_type",
	$form_id,
	$lang
));

echo "✅ " . count($translations) . " Übersetzungen in DB\n\n";

// Baue Translation Map (wie Renderer es tut)
echo "=== TRANSLATION MAP (wie Renderer sie baut) ===\n\n";

$translation_map = [];
foreach ($translations as $t) {
	// Renderer baut Keys mit field_id::property_type für normale Properties
	// Und field_path.property_path::property_type für Options
	
	if ($t->property_type === 'group_label') {
		// Group Labels: field_id::group_label
		$key = "{$t->field_id}::group_label";
		$translation_map[$key] = $t->translated_value;
		echo "Group Label Key: {$key}\n";
		echo "  → Value: {$t->translated_value}\n\n";
	} elseif ($t->property_type === 'option') {
		// Options: field_path.meta.data_grid_TYPE...::option
		$key = "{$t->field_path}::{$t->property_type}";
		$translation_map[$key] = $t->translated_value;
		echo "Option Key: {$key}\n";
		echo "  → Value: {$t->translated_value}\n\n";
	} else {
		// Normale Properties: field_id::property_type
		$key = "{$t->field_id}::{$t->property_type}";
		$translation_map[$key] = $t->translated_value;
		echo "Property Key: {$key}\n";
		echo "  → Value: {$t->translated_value}\n\n";
	}
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "TRANSLATION MAP KEYS (alle):\n";
echo str_repeat("=", 80) . "\n\n";
foreach (array_keys($translation_map) as $key) {
	echo "- {$key}\n";
}

// Jetzt teste Renderer
echo "\n\n" . str_repeat("=", 80) . "\n";
echo "=== RENDERER TEST ===\n";
echo str_repeat("=", 80) . "\n\n";

// Hole Form Object
$form_object = wsf_form_get_object($form_id, true, true);

echo "Form Object Groups: " . count($form_object->groups) . "\n\n";

// Zeige was Renderer für Group Labels sucht
foreach ($form_object->groups as $group_index => $group) {
	echo "--- GROUP {$group_index}: {$group->label} ---\n";
	echo "Group ID: {$group->id}\n";
	
	// Renderer sucht mit negativer group->id
	$group_label_key = (-($group->id)) . "::group_label";
	echo "Renderer sucht Key: {$group_label_key}\n";
	
	if (isset($translation_map[$group_label_key])) {
		echo "✅ GEFUNDEN: {$translation_map[$group_label_key]}\n";
	} else {
		echo "❌ NICHT GEFUNDEN!\n";
		echo "   Verfügbare Group Label Keys in Map:\n";
		foreach (array_keys($translation_map) as $key) {
			if (strpos($key, '::group_label') !== false) {
				echo "   - {$key}\n";
			}
		}
	}
	echo "\n";
	
	// Zeige erste paar Felder
	if (!empty($group->sections[0]->fields)) {
		$fields_to_show = array_slice($group->sections[0]->fields, 0, 3);
		foreach ($fields_to_show as $field_index => $field) {
			echo "  FIELD {$field_index}: {$field->label} (ID: {$field->id}, Type: {$field->type})\n";
			
			// Label
			$label_key = "{$field->id}::label";
			echo "    Label Key: {$label_key}\n";
			if (isset($translation_map[$label_key])) {
				echo "    ✅ GEFUNDEN: {$translation_map[$label_key]}\n";
			} else {
				echo "    ❌ NICHT GEFUNDEN\n";
			}
			
			// Prüfe auf Options
			if (isset($field->meta)) {
				$data_grid_props = ['data_grid_checkbox', 'data_grid_select', 'data_grid_radio',
					'data_grid_checkbox_price', 'data_grid_select_price', 'data_grid_radio_price'];
				
				foreach ($data_grid_props as $prop) {
					if (isset($field->meta->{$prop})) {
						echo "    Data Grid: {$prop}\n";
						
						// Baue field_path
						$field_path = "groups.{$group_index}.sections.0.fields.{$field_index}";
						
						// Zeige erste Option
						if (isset($field->meta->{$prop}->groups[0]->rows[0]->data[0])) {
							$option_key = "{$field_path}.meta.{$prop}.groups.0.rows.0.data.0::option";
							echo "    Option Key: {$option_key}\n";
							if (isset($translation_map[$option_key])) {
								echo "    ✅ GEFUNDEN: {$translation_map[$option_key]}\n";
							} else {
								echo "    ❌ NICHT GEFUNDEN\n";
								echo "       Verfügbare Option Keys für dieses Feld:\n";
								foreach (array_keys($translation_map) as $key) {
									if (strpos($key, "fields.{$field_index}.meta.") !== false && strpos($key, '::option') !== false) {
										echo "       - {$key}\n";
									}
								}
							}
						}
						break;
					}
				}
			}
			
			echo "\n";
		}
	}
}

echo "</pre>";
