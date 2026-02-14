<?php
/**
 * DEBUG: HTML-Feld Struktur analysieren
 * Zeigt die komplette Struktur eines HTML-Feldes
 */

require_once(__DIR__ . '/../../../wp-load.php');

if (!defined('ABSPATH')) {
	die('WordPress nicht geladen');
}

$form_id = 4; // Deine Form ID

echo "================================================================================\n";
echo "HTML-FELD STRUKTUR ANALYSE für Form #{$form_id}\n";
echo "================================================================================\n\n";

// Hole Form Object
$form_object = wsf_form_get_object($form_id);

if (!$form_object || empty($form_object->groups)) {
	die("❌ Form nicht gefunden oder leer\n");
}

echo "=== SUCHE NACH HTML-FELDERN ===\n\n";

foreach ($form_object->groups as $group_index => $group) {
	if (empty($group->sections)) continue;
	
	foreach ($group->sections as $section_index => $section) {
		if (empty($section->fields)) continue;
		
		foreach ($section->fields as $field_index => $field) {
			// Prüfe ob es ein HTML-Feld ist
			if ($field->type === 'html' || $field->type === 'message' || $field->type === 'textarea') {
				echo "--- FELD GEFUNDEN ---\n";
				echo "Type: {$field->type}\n";
				echo "ID: {$field->id}\n";
				echo "Label: " . ($field->label ?? '(kein Label)') . "\n";
				echo "Field Path: groups.{$group_index}.sections.{$section_index}.fields.{$field_index}\n\n";
				
				echo "=== KOMPLETTE FELD-STRUKTUR ===\n";
				echo json_encode($field, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
				
				echo "=== META PROPERTIES ===\n";
				if (isset($field->meta)) {
					foreach ($field->meta as $key => $value) {
						if (is_string($value) && !empty($value)) {
							echo "meta->{$key}: ";
							if (strlen($value) > 100) {
								echo substr($value, 0, 100) . "... (gekürzt)\n";
							} else {
								echo $value . "\n";
							}
						} elseif (is_bool($value)) {
							echo "meta->{$key}: " . ($value ? 'true' : 'false') . "\n";
						} elseif (is_numeric($value)) {
							echo "meta->{$key}: {$value}\n";
						}
					}
				} else {
					echo "(keine meta properties)\n";
				}
				echo "\n";
				
				echo "=== MÖGLICHE HTML-CONTENT PROPERTIES ===\n";
				$html_props = ['html', 'text_editor', 'text', 'content', 'value', 'default_value'];
				foreach ($html_props as $prop) {
					if (isset($field->meta->{$prop})) {
						echo "✅ meta->{$prop} VORHANDEN:\n";
						$content = $field->meta->{$prop};
						if (strlen($content) > 200) {
							echo "   " . substr($content, 0, 200) . "... (gekürzt)\n";
						} else {
							echo "   {$content}\n";
						}
					} else {
						echo "❌ meta->{$prop} NICHT vorhanden\n";
					}
				}
				echo "\n";
				
				echo "================================================================================\n\n";
			}
		}
	}
}

echo "=== ANALYSE ABGESCHLOSSEN ===\n";
