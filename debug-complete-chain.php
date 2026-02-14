<?php
/**
 * KOMPLETTE KETTEN-ANALYSE
 * Prüft JEDEN Schritt von Scanner → DB → Renderer
 */

require_once(__DIR__ . '/../../../wp-load.php');

if (!defined('ABSPATH')) {
	die('WordPress nicht geladen');
}

$form_id = 4;
$language = 'de';

echo "================================================================================\n";
echo "KOMPLETTE KETTEN-ANALYSE für Form #{$form_id} (Sprache: {$language})\n";
echo "================================================================================\n\n";

global $wpdb;

// ============================================================================
// SCHRITT 1: DB SCHEMA PRÜFEN
// ============================================================================
echo "=== SCHRITT 1: DB SCHEMA PRÜFEN ===\n\n";

$table_translations = $wpdb->prefix . 'wsform_ml_translations';
$table_cache = $wpdb->prefix . 'wsform_ml_field_cache';

// Prüfe ob Tabellen existieren
$tables_exist = $wpdb->get_results("SHOW TABLES LIKE '{$table_translations}'");
if (empty($tables_exist)) {
	echo "❌ FEHLER: Tabelle {$table_translations} existiert NICHT!\n";
	echo "   → Plugin deaktivieren + aktivieren!\n\n";
	die();
}
echo "✅ Tabelle {$table_translations} existiert\n";

$tables_exist = $wpdb->get_results("SHOW TABLES LIKE '{$table_cache}'");
if (empty($tables_exist)) {
	echo "❌ FEHLER: Tabelle {$table_cache} existiert NICHT!\n";
	echo "   → Plugin deaktivieren + aktivieren!\n\n";
	die();
}
echo "✅ Tabelle {$table_cache} existiert\n\n";

// Prüfe Spalten der translations Tabelle
echo "Spalten in {$table_translations}:\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_translations}");
$has_field_path_hash = false;
foreach ($columns as $col) {
	echo "  - {$col->Field} ({$col->Type})\n";
	if ($col->Field === 'field_path_hash') {
		$has_field_path_hash = true;
	}
}

if (!$has_field_path_hash) {
	echo "\n❌ KRITISCHER FEHLER: Spalte 'field_path_hash' fehlt!\n";
	echo "   → Tabelle hat ALTE Struktur (v1.5.5 oder früher)\n";
	echo "   → DROP TABLE + Plugin neu aktivieren!\n\n";
	die();
}
echo "\n✅ Spalte 'field_path_hash' vorhanden (v1.6.0 Schema)\n\n";

// Prüfe Unique Index
echo "Unique Indexes in {$table_translations}:\n";
$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_translations} WHERE Non_unique = 0");
foreach ($indexes as $idx) {
	echo "  - {$idx->Key_name}: {$idx->Column_name} (Seq: {$idx->Seq_in_index})\n";
}
echo "\n";

// ============================================================================
// SCHRITT 2: GECACHTE FELDER PRÜFEN
// ============================================================================
echo "=== SCHRITT 2: GECACHTE FELDER PRÜFEN ===\n\n";

$cached_fields = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM {$table_cache} WHERE form_id = %d ORDER BY field_path",
	$form_id
));

if (empty($cached_fields)) {
	echo "❌ FEHLER: Keine gecachten Felder gefunden!\n";
	echo "   → Formular scannen!\n\n";
	die();
}

echo "Gecachte Felder: " . count($cached_fields) . "\n\n";

// Prüfe Group Labels (Tabs)
echo "--- GROUP LABELS (TABS) ---\n";
$group_fields = array_filter($cached_fields, function($f) {
	return $f->field_type === 'group';
});

if (empty($group_fields)) {
	echo "❌ FEHLER: Keine Group Labels gefunden!\n";
	echo "   → Scanner hat Groups nicht erkannt!\n\n";
} else {
	foreach ($group_fields as $field) {
		echo "Field ID: {$field->field_id}\n";
		echo "Field Path: {$field->field_path}\n";
		echo "Field Label: {$field->field_label}\n";
		
		if ($field->field_id >= 0) {
			echo "❌ FEHLER: field_id ist POSITIV ({$field->field_id})!\n";
			echo "   → Sollte NEGATIV sein (z.B. -4, -6)\n";
			echo "   → Scanner verwendet falsche field_id!\n";
		} else {
			echo "✅ field_id ist NEGATIV ({$field->field_id}) - KORREKT!\n";
		}
		echo "\n";
	}
}

// Prüfe Price Radio Feld
echo "--- PRICE RADIO FELD ---\n";
$price_radio_fields = array_filter($cached_fields, function($f) {
	return $f->field_type === 'price_radio';
});

if (empty($price_radio_fields)) {
	echo "❌ FEHLER: Kein Price Radio Feld gefunden!\n\n";
} else {
	foreach ($price_radio_fields as $field) {
		echo "Field ID: {$field->field_id}\n";
		echo "Field Path: {$field->field_path}\n";
		echo "Field Label: {$field->field_label}\n";
		
		$props = json_decode($field->translatable_properties, true);
		echo "Translatable Properties: " . count($props) . "\n";
		
		$option_count = 0;
		foreach ($props as $prop) {
			if ($prop['type'] === 'option') {
				$option_count++;
				echo "  Option {$option_count}: {$prop['path']}\n";
				echo "    Value: {$prop['value']}\n";
			}
		}
		
		if ($option_count < 3) {
			echo "❌ FEHLER: Nur {$option_count} Options gefunden (sollten 3 sein)!\n";
			echo "   → Scanner hat nicht alle Options erkannt!\n";
		} else {
			echo "✅ {$option_count} Options gefunden - KORREKT!\n";
		}
		echo "\n";
	}
}

// ============================================================================
// SCHRITT 3: GESPEICHERTE ÜBERSETZUNGEN PRÜFEN
// ============================================================================
echo "=== SCHRITT 3: GESPEICHERTE ÜBERSETZUNGEN PRÜFEN ===\n\n";

$translations = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM {$table_translations} WHERE form_id = %d AND language_code = %s ORDER BY field_path, property_type",
	$form_id,
	$language
));

if (empty($translations)) {
	echo "❌ FEHLER: Keine Übersetzungen gefunden!\n";
	echo "   → Übersetzungen eingeben!\n\n";
	die();
}

echo "Gespeicherte Übersetzungen: " . count($translations) . "\n\n";

// Prüfe Group Label Übersetzungen
echo "--- GROUP LABEL ÜBERSETZUNGEN ---\n";
$group_translations = array_filter($translations, function($t) {
	return $t->property_type === 'group_label';
});

if (empty($group_translations)) {
	echo "❌ FEHLER: Keine Group Label Übersetzungen gefunden!\n\n";
} else {
	foreach ($group_translations as $trans) {
		echo "Field ID: {$trans->field_id}\n";
		echo "Field Path: {$trans->field_path}\n";
		echo "Field Path Hash: {$trans->field_path_hash}\n";
		echo "Original: {$trans->original_value}\n";
		echo "Translated: {$trans->translated_value}\n";
		
		if ($trans->field_id >= 0) {
			echo "❌ FEHLER: field_id ist POSITIV ({$trans->field_id})!\n";
			echo "   → Sollte NEGATIV sein (z.B. -4, -6)\n";
			echo "   → Translation Manager speichert falsche field_id!\n";
		} else {
			echo "✅ field_id ist NEGATIV ({$trans->field_id}) - KORREKT!\n";
		}
		
		if (empty($trans->field_path_hash)) {
			echo "❌ FEHLER: field_path_hash ist LEER!\n";
			echo "   → Translation Manager berechnet Hash nicht!\n";
		} else {
			$expected_hash = hash('sha256', $trans->field_path);
			if ($trans->field_path_hash !== $expected_hash) {
				echo "❌ FEHLER: field_path_hash ist FALSCH!\n";
				echo "   Erwartet: {$expected_hash}\n";
				echo "   Gespeichert: {$trans->field_path_hash}\n";
			} else {
				echo "✅ field_path_hash ist KORREKT!\n";
			}
		}
		echo "\n";
	}
}

// Prüfe Price Radio Options Übersetzungen
echo "--- PRICE RADIO OPTIONS ÜBERSETZUNGEN ---\n";
$price_radio_translations = array_filter($translations, function($t) {
	return strpos($t->field_path, 'data_grid_radio_price') !== false && $t->property_type === 'option';
});

if (empty($price_radio_translations)) {
	echo "❌ FEHLER: Keine Price Radio Options Übersetzungen gefunden!\n\n";
} else {
	echo "Gefundene Price Radio Options: " . count($price_radio_translations) . "\n\n";
	
	foreach ($price_radio_translations as $trans) {
		echo "Field ID: {$trans->field_id}\n";
		echo "Field Path: {$trans->field_path}\n";
		echo "Field Path Hash: {$trans->field_path_hash}\n";
		echo "Original: {$trans->original_value}\n";
		echo "Translated: {$trans->translated_value}\n";
		
		if (empty($trans->field_path_hash)) {
			echo "❌ FEHLER: field_path_hash ist LEER!\n";
		} else {
			$expected_hash = hash('sha256', $trans->field_path);
			if ($trans->field_path_hash !== $expected_hash) {
				echo "❌ FEHLER: field_path_hash ist FALSCH!\n";
			} else {
				echo "✅ field_path_hash ist KORREKT!\n";
			}
		}
		echo "\n";
	}
	
	if (count($price_radio_translations) < 3) {
		echo "❌ FEHLER: Nur " . count($price_radio_translations) . " Options gespeichert (sollten 3 sein)!\n";
		echo "   → Übersetzungen überschreiben sich!\n";
		echo "   → Prüfe Unique Index!\n\n";
		
		// Prüfe ob es Duplikate gibt
		$hashes = array_map(function($t) { return $t->field_path_hash; }, $price_radio_translations);
		$unique_hashes = array_unique($hashes);
		if (count($hashes) !== count($unique_hashes)) {
			echo "❌ KRITISCHER FEHLER: field_path_hash Duplikate gefunden!\n";
			echo "   → Verschiedene Options haben gleichen Hash!\n";
			echo "   → Hash-Berechnung ist FALSCH!\n\n";
		}
	}
}

// ============================================================================
// SCHRITT 4: TRANSLATION MAP PRÜFEN
// ============================================================================
echo "=== SCHRITT 4: TRANSLATION MAP PRÜFEN ===\n\n";

// Simuliere Renderer build_translation_map()
$translation_map = [];
foreach ($translations as $trans) {
	if ($trans->property_type === 'option') {
		// Options: field_path::option
		$key = $trans->field_path . '::' . $trans->property_type;
	} else {
		// Normale Properties: field_id::property_type
		$key = $trans->field_id . '::' . $trans->property_type;
	}
	$translation_map[$key] = $trans->translated_value;
}

echo "Translation Map Keys: " . count($translation_map) . "\n\n";

// Prüfe Group Label Keys
echo "--- GROUP LABEL KEYS IN MAP ---\n";
$group_keys = array_filter(array_keys($translation_map), function($k) {
	return strpos($k, '::group_label') !== false;
});

if (empty($group_keys)) {
	echo "❌ FEHLER: Keine Group Label Keys in Map!\n\n";
} else {
	foreach ($group_keys as $key) {
		echo "Key: {$key}\n";
		echo "Value: {$translation_map[$key]}\n";
		
		// Extrahiere field_id aus Key
		$parts = explode('::', $key);
		$field_id = $parts[0];
		
		if ($field_id >= 0) {
			echo "❌ FEHLER: Key verwendet POSITIVE field_id ({$field_id})!\n";
			echo "   → Renderer wird Key NICHT finden!\n";
		} else {
			echo "✅ Key verwendet NEGATIVE field_id ({$field_id}) - KORREKT!\n";
		}
		echo "\n";
	}
}

// Prüfe Price Radio Option Keys
echo "--- PRICE RADIO OPTION KEYS IN MAP ---\n";
$price_radio_keys = array_filter(array_keys($translation_map), function($k) {
	return strpos($k, 'data_grid_radio_price') !== false && strpos($k, '::option') !== false;
});

if (empty($price_radio_keys)) {
	echo "❌ FEHLER: Keine Price Radio Option Keys in Map!\n\n";
} else {
	echo "Gefundene Keys: " . count($price_radio_keys) . "\n\n";
	foreach ($price_radio_keys as $key) {
		echo "Key: {$key}\n";
		echo "Value: {$translation_map[$key]}\n\n";
	}
	
	if (count($price_radio_keys) < 3) {
		echo "❌ FEHLER: Nur " . count($price_radio_keys) . " Keys in Map (sollten 3 sein)!\n";
		echo "   → build_translation_map() überschreibt Keys!\n\n";
	}
}

// ============================================================================
// SCHRITT 5: RENDERER LOOKUP SIMULIEREN
// ============================================================================
echo "=== SCHRITT 5: RENDERER LOOKUP SIMULIEREN ===\n\n";

// Hole Form Object
$form_object = wsf_form_get_object($form_id);

if (!$form_object || empty($form_object->groups)) {
	echo "❌ FEHLER: Form Object konnte nicht geladen werden!\n\n";
	die();
}

echo "--- GROUP LABEL LOOKUP ---\n";
foreach ($form_object->groups as $group_index => $group) {
	$group_id = $group->id;
	$negative_id = -$group_id;
	
	echo "Group {$group_index}: {$group->label} (ID: {$group_id})\n";
	echo "Renderer sucht Key: {$negative_id}::group_label\n";
	
	$lookup_key = $negative_id . '::group_label';
	if (isset($translation_map[$lookup_key])) {
		echo "✅ GEFUNDEN: {$translation_map[$lookup_key]}\n";
	} else {
		echo "❌ NICHT GEFUNDEN!\n";
		echo "   Verfügbare Group Label Keys:\n";
		foreach ($group_keys as $key) {
			echo "   - {$key}\n";
		}
	}
	echo "\n";
}

// Prüfe Price Radio Feld
echo "--- PRICE RADIO OPTIONS LOOKUP ---\n";
foreach ($form_object->groups as $group_index => $group) {
	if (empty($group->sections)) continue;
	
	foreach ($group->sections as $section_index => $section) {
		if (empty($section->fields)) continue;
		
		foreach ($section->fields as $field_index => $field) {
			if ($field->type !== 'price_radio') continue;
			
			echo "Field: {$field->label} (ID: {$field->id})\n";
			
			// Finde data_grid
			$data_grid_key = 'data_grid_radio_price';
			if (!isset($field->meta->{$data_grid_key})) {
				echo "❌ FEHLER: {$data_grid_key} nicht gefunden in meta!\n";
				echo "   Verfügbare Meta Keys: " . implode(', ', array_keys((array)$field->meta)) . "\n\n";
				continue;
			}
			
			$data_grid = $field->meta->{$data_grid_key};
			if (empty($data_grid->groups)) continue;
			
			foreach ($data_grid->groups as $dg_group_index => $dg_group) {
				if (empty($dg_group->rows)) continue;
				
				foreach ($dg_group->rows as $row_index => $row) {
					if (empty($row->data)) continue;
					
					$option_value = $row->data[0] ?? '';
					$field_path = "groups.{$group_index}.sections.{$section_index}.fields.{$field_index}.meta.{$data_grid_key}.groups.{$dg_group_index}.rows.{$row_index}.data.0";
					
					echo "  Option {$row_index}: {$option_value}\n";
					echo "  Renderer sucht Key: {$field_path}::option\n";
					
					$lookup_key = $field_path . '::option';
					if (isset($translation_map[$lookup_key])) {
						echo "  ✅ GEFUNDEN: {$translation_map[$lookup_key]}\n";
					} else {
						echo "  ❌ NICHT GEFUNDEN!\n";
					}
					echo "\n";
				}
			}
		}
	}
}

echo "================================================================================\n";
echo "ANALYSE ABGESCHLOSSEN\n";
echo "================================================================================\n";
