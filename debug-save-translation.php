<?php
/**
 * Debug Script für save_translation REST API
 * 
 * Verwendung:
 * 1. Lade diese Datei im Browser: /wp-content/plugins/wsform-ml/debug-save-translation.php
 * 2. Zeigt welche Daten vom Frontend gesendet werden
 * 3. Zeigt warum die Validierung fehlschlägt
 */

// WordPress laden
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
	die('Keine Berechtigung');
}

echo "<h1>Debug: save_translation REST API</h1>";
echo "<pre>";

// Simuliere einen POST Request mit Test-Daten
$test_data = [
	'form_id' => 1,
	'field_id' => '54', // String!
	'field_path' => 'groups.0.sections.0.fields.0',
	'property_type' => 'label',
	'language_code' => 'de',
	'original_value' => 'Price Select',
	'translated_value' => 'Preis Auswahl'
];

echo "=== TEST DATEN ===\n";
echo json_encode($test_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Sanitize wie in REST API
$sanitized = [
	'form_id' => absint($test_data['form_id'] ?? 0),
	'field_id' => sanitize_text_field($test_data['field_id'] ?? ''),
	'field_path' => sanitize_text_field($test_data['field_path'] ?? ''),
	'property_type' => sanitize_key($test_data['property_type'] ?? ''),
	'language_code' => sanitize_key($test_data['language_code'] ?? ''),
	'original_value' => wp_kses_post($test_data['original_value'] ?? ''),
	'translated_value' => wp_kses_post($test_data['translated_value'] ?? '')
];

echo "=== SANITIZED DATEN ===\n";
echo json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// Validierung wie in REST API
echo "=== VALIDIERUNG ===\n";
echo "form_id: " . ($sanitized['form_id'] ? "✅ {$sanitized['form_id']}" : "❌ LEER") . "\n";
echo "field_id: " . (!empty($sanitized['field_id']) ? "✅ '{$sanitized['field_id']}'" : "❌ LEER") . "\n";
echo "field_path: " . (!empty($sanitized['field_path']) ? "✅ '{$sanitized['field_path']}'" : "❌ LEER") . "\n";
echo "property_type: " . (!empty($sanitized['property_type']) ? "✅ '{$sanitized['property_type']}'" : "❌ LEER") . "\n";
echo "language_code: " . (!empty($sanitized['language_code']) ? "✅ '{$sanitized['language_code']}'" : "❌ LEER") . "\n";

$validation_passed = !empty($sanitized['form_id']) && 
                     !empty($sanitized['field_id']) && 
                     !empty($sanitized['field_path']) && 
                     !empty($sanitized['property_type']) && 
                     !empty($sanitized['language_code']);

echo "\nValidierung: " . ($validation_passed ? "✅ BESTANDEN" : "❌ FEHLGESCHLAGEN") . "\n\n";

// Language Code Validierung
$language_valid = preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $sanitized['language_code']);
echo "Language Code Format: " . ($language_valid ? "✅ GÜLTIG" : "❌ UNGÜLTIG") . "\n";
echo "Pattern: /^[a-z]{2}(_[A-Z]{2})?\$/\n";
echo "Language Code: '{$sanitized['language_code']}'\n\n";

// Prüfe was vom Frontend kommt
echo "=== BROWSER CONSOLE ANLEITUNG ===\n";
echo "1. Öffne Browser Console (F12)\n";
echo "2. Gehe zu Network Tab\n";
echo "3. Versuche eine Übersetzung zu speichern\n";
echo "4. Klicke auf den fehlgeschlagenen Request (rot)\n";
echo "5. Gehe zu 'Payload' oder 'Request'\n";
echo "6. Kopiere die Daten hierher\n\n";

echo "=== ERWARTETES FORMAT ===\n";
echo json_encode([
	'form_id' => 1,
	'field_id' => '54', // Kann String oder Number sein!
	'field_path' => 'groups.0.sections.0.fields.0',
	'property_type' => 'label',
	'language_code' => 'de',
	'original_value' => 'Original Text',
	'translated_value' => 'Übersetzter Text'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n=== HÄUFIGE FEHLER ===\n";
echo "1. field_id ist leer oder undefined\n";
echo "2. language_code hat falsches Format (z.B. 'de_DE' statt 'de')\n";
echo "3. property_type ist leer\n";
echo "4. field_path ist leer\n";

echo "</pre>";
