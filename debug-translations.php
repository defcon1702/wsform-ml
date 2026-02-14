<?php
/**
 * Debug Script: Zeigt alle Übersetzungen für ein Formular
 * 
 * Usage: http://your-site.local/wp-content/plugins/wsform-ml/debug-translations.php?form_id=4&lang=de
 */

// WordPress laden
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
	die('❌ Keine Berechtigung!');
}

$form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
$language_code = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'de';

if (!$form_id) {
	die('❌ Bitte form_id angeben: ?form_id=4&lang=de');
}

header('Content-Type: text/plain; charset=utf-8');

echo "Debug: Übersetzungen für Form #{$form_id} (Sprache: {$language_code})\n";
echo str_repeat('=', 80) . "\n\n";

global $wpdb;
$table = $wpdb->prefix . 'wsform_ml_translations';

// Alle Übersetzungen für dieses Formular
$translations = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $table WHERE form_id = %d AND language_code = %s ORDER BY field_id, property_type",
	$form_id,
	$language_code
));

if (!$translations) {
	echo "❌ Keine Übersetzungen gefunden!\n";
	echo "\nPrüfe ob Tabelle existiert:\n";
	$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
	echo $table_exists ? "✅ Tabelle existiert\n" : "❌ Tabelle existiert NICHT\n";
	exit;
}

echo "✅ " . count($translations) . " Übersetzungen gefunden\n\n";

// Gruppiere nach field_id
$by_field = [];
foreach ($translations as $trans) {
	if (!isset($by_field[$trans->field_id])) {
		$by_field[$trans->field_id] = [];
	}
	$by_field[$trans->field_id][] = $trans;
}

// Zeige gruppiert
foreach ($by_field as $field_id => $field_translations) {
	echo "=== FIELD ID: {$field_id} ===\n";
	
	foreach ($field_translations as $trans) {
		echo "  Property: {$trans->property_type}\n";
		echo "  Path: {$trans->field_path}\n";
		echo "  Original: " . substr($trans->original_value, 0, 100) . "\n";
		echo "  Translated: " . substr($trans->translated_value, 0, 100) . "\n";
		echo "  Context: {$trans->context}\n";
		echo "\n";
	}
	echo "\n";
}

// Zeige speziell Preis-Felder
echo "\n" . str_repeat('=', 80) . "\n";
echo "PREIS-FELD OPTIONEN (property_type = 'option'):\n";
echo str_repeat('=', 80) . "\n\n";

$price_options = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $table 
	 WHERE form_id = %d 
	 AND language_code = %s 
	 AND property_type = 'option'
	 ORDER BY field_id, field_path",
	$form_id,
	$language_code
));

if (!$price_options) {
	echo "❌ Keine Preis-Feld Optionen gefunden!\n";
} else {
	echo "✅ " . count($price_options) . " Preis-Feld Optionen gefunden\n\n";
	
	foreach ($price_options as $opt) {
		echo "Field ID: {$opt->field_id}\n";
		echo "Path: {$opt->field_path}\n";
		echo "Original: {$opt->original_value}\n";
		echo "Translated: {$opt->translated_value}\n";
		echo "Context: {$opt->context}\n";
		echo str_repeat('-', 40) . "\n";
	}
}

// Zeige speziell Group Labels
echo "\n" . str_repeat('=', 80) . "\n";
echo "GROUP LABELS (property_type = 'group_label'):\n";
echo str_repeat('=', 80) . "\n\n";

$group_labels = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $table 
	 WHERE form_id = %d 
	 AND language_code = %s 
	 AND property_type = 'group_label'
	 ORDER BY field_id",
	$form_id,
	$language_code
));

if (!$group_labels) {
	echo "❌ Keine Group Labels gefunden!\n";
} else {
	echo "✅ " . count($group_labels) . " Group Labels gefunden\n\n";
	
	foreach ($group_labels as $label) {
		echo "Field ID: {$label->field_id}\n";
		echo "Path: {$label->field_path}\n";
		echo "Original: {$label->original_value}\n";
		echo "Translated: {$label->translated_value}\n";
		echo str_repeat('-', 40) . "\n";
	}
}
