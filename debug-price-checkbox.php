<?php
/**
 * Debug Script für Price Checkbox Felder
 * 
 * Verwendung:
 * 1. Ersetze FORM_ID mit deiner Formular-ID
 * 2. Lade diese Datei im Browser: /wp-content/plugins/wsform-ml/debug-price-checkbox.php
 */

// WordPress laden
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
	die('Keine Berechtigung');
}

// Formular-ID hier eintragen
$form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;

if (!$form_id) {
	die('Bitte Form-ID angeben: ?form_id=DEINE_ID');
}

echo "<h1>Debug: Price Checkbox Felder (Form ID: {$form_id})</h1>";
echo "<pre>";

// 1. Prüfe ob Formular existiert
global $wpdb;
$form_exists = $wpdb->get_var($wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->prefix}wsf_form WHERE id = %d",
	$form_id
));

if (!$form_exists) {
	die("Formular mit ID {$form_id} existiert nicht!");
}

// 2. Lade Formular-Objekt
try {
	$ws_form = new WS_Form_Form();
	$ws_form->id = $form_id;
	$form_object = $ws_form->db_read(true, true);
} catch (Exception $e) {
	die("Fehler beim Laden des Formulars: " . $e->getMessage());
}

echo "=== FORMULAR STRUKTUR ===\n\n";

// 2. Durchsuche alle Felder
foreach ($form_object->groups as $group_index => $group) {
	foreach ($group->sections as $section_index => $section) {
		foreach ($section->fields as $field_index => $field) {
			
			// Nur price_checkbox Felder anzeigen
			if ($field->type === 'price_checkbox') {
				echo "GEFUNDEN: Price Checkbox Feld\n";
				echo "Field Path: groups.{$group_index}.sections.{$section_index}.fields.{$field_index}\n";
				echo "Field ID: {$field->id}\n";
				echo "Field Label: {$field->label}\n";
				echo "Field Type: {$field->type}\n\n";
				
				// Zeige Meta-Struktur
				echo "META PROPERTIES:\n";
				if (isset($field->meta)) {
					foreach ($field->meta as $key => $value) {
						if (strpos($key, 'data_grid') === 0) {
							echo "  - {$key}: " . (is_object($value) ? 'OBJECT' : gettype($value)) . "\n";
							
							// Zeige data_grid Struktur
							if (is_object($value) && isset($value->groups)) {
								echo "    Groups: " . count($value->groups) . "\n";
								foreach ($value->groups as $g_idx => $g) {
									if (isset($g->rows)) {
										echo "      Group {$g_idx}: " . count($g->rows) . " rows\n";
										foreach ($g->rows as $r_idx => $r) {
											if (isset($r->data) && is_array($r->data)) {
												echo "        Row {$r_idx}: " . count($r->data) . " columns\n";
												foreach ($r->data as $c_idx => $c_val) {
													$preview = strlen($c_val) > 50 ? substr($c_val, 0, 50) . '...' : $c_val;
													echo "          Col {$c_idx}: " . json_encode($preview) . "\n";
												}
											}
										}
									}
								}
							}
						}
					}
				}
				echo "\n";
				
				// Zeige komplette Meta-Struktur als JSON
				echo "KOMPLETTE META (JSON):\n";
				echo json_encode($field->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
				echo "\n\n";
				echo str_repeat('=', 80) . "\n\n";
			}
		}
	}
}

// 3. Prüfe was der Scanner findet
echo "\n=== SCANNER ERGEBNIS ===\n\n";

$scanner = WSForm_ML_Field_Scanner::instance();
$result = $scanner->scan_form($form_id);

global $wpdb;
$cache_table = $wpdb->prefix . 'wsform_ml_field_cache';
$cached_fields = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM {$cache_table} WHERE form_id = %d AND field_label LIKE %s",
	$form_id,
	'%checkbox%'
));

echo "Gecachte Checkbox-Felder: " . count($cached_fields) . "\n\n";

foreach ($cached_fields as $field) {
	echo "Field ID: {$field->field_id}\n";
	echo "Field Label: {$field->field_label}\n";
	echo "Field Path: {$field->field_path}\n";
	echo "Translatable Properties:\n";
	$props = json_decode($field->translatable_properties, true);
	foreach ($props as $prop) {
		echo "  - {$prop['type']}: {$prop['path']}\n";
		echo "    Value: " . (strlen($prop['value']) > 100 ? substr($prop['value'], 0, 100) . '...' : $prop['value']) . "\n";
	}
	echo "\n";
}

echo "</pre>";
