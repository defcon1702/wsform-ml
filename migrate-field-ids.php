<?php
/**
 * Migration Script: Aktualisiert field_ids in translations Tabelle
 * 
 * Problem: Nach v1.5.3 verwenden Groups negative field_ids (-4, -6)
 * Alte Übersetzungen haben noch positive oder 0 field_ids
 * 
 * Lösung: Aktualisiere field_ids basierend auf field_path
 */

// WordPress laden
require_once(__DIR__ . '/../../../wp-load.php');

if (!defined('ABSPATH')) {
	die('WordPress nicht gefunden!');
}

$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 4;
$dry_run = isset($_GET['dry_run']) ? true : false;

echo "<pre>";
echo "Migration: Aktualisiere field_ids in translations Tabelle\n";
echo str_repeat("=", 80) . "\n\n";
echo "Form ID: {$form_id}\n";
echo "Dry Run: " . ($dry_run ? "JA (keine Änderungen)" : "NEIN (Änderungen werden gespeichert)") . "\n\n";

global $wpdb;
$translations_table = $wpdb->prefix . 'wsform_ml_translations';
$cache_table = $wpdb->prefix . 'wsform_ml_field_cache';

// Hole alle Übersetzungen für dieses Form
$translations = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM $translations_table WHERE form_id = %d ORDER BY field_id, property_type",
	$form_id
));

echo "✅ " . count($translations) . " Übersetzungen gefunden\n\n";

// Hole field_cache (hat die korrekten field_ids)
$cache = $wpdb->get_results($wpdb->prepare(
	"SELECT field_id, field_path, field_type FROM $cache_table WHERE form_id = %d",
	$form_id
), ARRAY_A);

// Baue Map: field_path → field_id
$path_to_id = [];
foreach ($cache as $field) {
	$path_to_id[$field['field_path']] = $field['field_id'];
}

echo "✅ " . count($path_to_id) . " Felder im Cache\n\n";

echo str_repeat("=", 80) . "\n";
echo "UPDATES:\n";
echo str_repeat("=", 80) . "\n\n";

$updates = 0;
$skipped = 0;

foreach ($translations as $t) {
	// Prüfe ob field_id aktualisiert werden muss
	$correct_field_id = null;
	
	if ($t->property_type === 'option') {
		// Options: Extrahiere field_path aus dem vollständigen Path
		// Beispiel: "groups.0.sections.0.fields.1.meta.data_grid_checkbox_price..." → "groups.0.sections.0.fields.1"
		if (preg_match('/^(groups\.\d+\.sections\.\d+\.fields\.\d+)\./', $t->field_path, $matches)) {
			$field_path = $matches[1];
			if (isset($path_to_id[$field_path])) {
				$correct_field_id = $path_to_id[$field_path];
			}
		}
	} else {
		// Normale Properties und Group Labels: field_path ist direkt der Lookup-Key
		if (isset($path_to_id[$t->field_path])) {
			$correct_field_id = $path_to_id[$t->field_path];
		}
	}
	
	if ($correct_field_id !== null && $correct_field_id != $t->field_id) {
		echo "Translation ID: {$t->id}\n";
		echo "  Field Path: {$t->field_path}\n";
		echo "  Property Type: {$t->property_type}\n";
		echo "  Alt field_id: {$t->field_id}\n";
		echo "  Neu field_id: {$correct_field_id}\n";
		echo "  Original: " . substr($t->original_value, 0, 50) . "...\n";
		echo "  Translated: " . substr($t->translated_value, 0, 50) . "...\n";
		
		if (!$dry_run) {
			$result = $wpdb->update(
				$translations_table,
				['field_id' => $correct_field_id],
				['id' => $t->id]
			);
			
			if ($result !== false) {
				echo "  ✅ AKTUALISIERT\n";
				$updates++;
			} else {
				echo "  ❌ FEHLER: " . $wpdb->last_error . "\n";
			}
		} else {
			echo "  🔍 WÜRDE AKTUALISIERT (Dry Run)\n";
			$updates++;
		}
		echo "\n";
	} else {
		$skipped++;
	}
}

echo str_repeat("=", 80) . "\n";
echo "ZUSAMMENFASSUNG:\n";
echo str_repeat("=", 80) . "\n\n";
echo "Aktualisiert: {$updates}\n";
echo "Übersprungen: {$skipped}\n";
echo "Gesamt: " . count($translations) . "\n\n";

if ($dry_run) {
	echo "⚠️ DRY RUN - Keine Änderungen gespeichert!\n";
	echo "Führe aus ohne ?dry_run um Änderungen zu speichern:\n";
	echo "http://wsform-plugins.local/wp-content/plugins/wsform-ml/migrate-field-ids.php?form_id={$form_id}\n";
} else {
	echo "✅ Migration abgeschlossen!\n";
}

echo "</pre>";
