<?php
/**
 * Debug Script für Group Labels (Tabs)
 * Zeigt welche field_id für Tabs verwendet wird
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
	die('Keine Berechtigung');
}

$form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 4;

echo "<h1>Debug: Group Labels (Tabs) für Form #{$form_id}</h1>";
echo "<pre>";

// Lade Formular
if (function_exists('wsf_form_get_object')) {
	$form_object = wsf_form_get_object($form_id);
} else {
	$ws_form = new WS_Form_Form();
	$ws_form->id = $form_id;
	$form_object = $ws_form->db_read(true, true);
}

if (!$form_object) {
	die("❌ Formular nicht gefunden!\n");
}

echo "=== FORMULAR STRUKTUR ===\n";
echo "Form ID: {$form_object->id}\n";
echo "Form Label: {$form_object->label}\n\n";

echo "=== GROUPS (TABS) ===\n";
foreach ($form_object->groups as $group_index => $group) {
	echo "Group {$group_index}:\n";
	echo "  WSForm Group ID: {$group->id}\n";
	echo "  Label: {$group->label}\n";
	echo "  Scanner field_id (ALT): " . (-($group_index + 1)) . " (negativ, Index-basiert)\n";
	echo "  Scanner field_id (NEU): {$group->id} (WSForm Group ID, stabil!) ✅\n";
	echo "\n";
}

echo "\n=== GECACHTE FELDER ===\n";
global $wpdb;
$table = $wpdb->prefix . 'wsform_ml_field_cache';
$cached_fields = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $table WHERE form_id = %d AND field_type = 'group' ORDER BY field_id",
	$form_id
));

if ($cached_fields) {
	foreach ($cached_fields as $field) {
		echo "Field ID: {$field->field_id}\n";
		echo "Field Path: {$field->field_path}\n";
		echo "Field Label: {$field->field_label}\n";
		echo "Properties: {$field->translatable_properties}\n";
		echo "\n";
	}
} else {
	echo "❌ Keine gecachten Group-Felder gefunden!\n";
}

echo "\n=== ÜBERSETZUNGEN ===\n";
$trans_table = $wpdb->prefix . 'wsform_ml_translations';
$translations = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $trans_table WHERE form_id = %d AND property_type = 'group_label' ORDER BY field_id",
	$form_id
));

if ($translations) {
	foreach ($translations as $trans) {
		echo "Field ID: {$trans->field_id}\n";
		echo "Field Path: {$trans->field_path}\n";
		echo "Language: {$trans->language_code}\n";
		echo "Original: {$trans->original_value}\n";
		echo "Translated: {$trans->translated_value}\n";
		echo "\n";
	}
} else {
	echo "❌ Keine Übersetzungen für Group Labels gefunden!\n";
}

echo "</pre>";
