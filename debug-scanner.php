<?php
/**
 * Debug Script für Field Scanner
 * Zeigt welche Felder gescannt werden und warum
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
	die('Keine Berechtigung');
}

$form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 4;

echo "<h1>Debug: Field Scanner für Form #{$form_id}</h1>";
echo "<pre>";

// Lade Formular
$scanner = WSForm_ML_Field_Scanner::instance();

// Hole Form Object
if (function_exists('wsf_form_get_object')) {
	$form_object = wsf_form_get_object($form_id);
	echo "✅ Verwendet wsf_form_get_object()\n\n";
} else {
	$ws_form = new WS_Form_Form();
	$ws_form->id = $form_id;
	$form_object = $ws_form->db_read(true, true);
	echo "✅ Verwendet db_read() (Fallback)\n\n";
}

if (!$form_object) {
	die("❌ Formular nicht gefunden!\n");
}

echo "=== FORMULAR STRUKTUR ===\n";
echo "Form ID: {$form_object->id}\n";
echo "Form Label: {$form_object->label}\n";
echo "Groups: " . count($form_object->groups) . "\n\n";

// Durchlaufe alle Felder
foreach ($form_object->groups as $group_index => $group) {
	echo "--- GROUP {$group_index}: {$group->label} ---\n";
	echo "Group ID: {$group->id}\n";
	
	foreach ($group->sections as $section_index => $section) {
		echo "  SECTION {$section_index}: {$section->label}\n";
		
		foreach ($section->fields as $field_index => $field) {
			echo "    FIELD {$field_index}:\n";
			echo "      ID: {$field->id}\n";
			echo "      Type: {$field->type}\n";
			echo "      Label: {$field->label}\n";
			
			// Prüfe ob es ein Preis-Feld ist
			$is_price_field = strpos($field->type, 'price_') === 0;
			if ($is_price_field) {
				echo "      🏷️ PREIS-FELD erkannt!\n";
				
				// Prüfe data_grid Property
				$data_grid_property = 'data_grid_' . $field->type;
				echo "      Data Grid Property: {$data_grid_property}\n";
				
				if (isset($field->meta->{$data_grid_property})) {
					echo "      ✅ Data Grid vorhanden\n";
					$data_grid = $field->meta->{$data_grid_property};
					
					if (isset($data_grid->groups)) {
						foreach ($data_grid->groups as $dg_group_index => $dg_group) {
							echo "        Data Grid Group {$dg_group_index}:\n";
							
							if (isset($dg_group->rows)) {
								echo "          Rows: " . count($dg_group->rows) . "\n";
								
								foreach ($dg_group->rows as $row_index => $row) {
									if (isset($row->data) && is_array($row->data)) {
										echo "          Row {$row_index}:\n";
										foreach ($row->data as $col_index => $value) {
											$translatable = ($col_index === 0) ? "✅ ÜBERSETZBAR" : "❌ NICHT übersetzbar";
											echo "            Col {$col_index}: '{$value}' {$translatable}\n";
										}
									}
								}
							} else {
								echo "          ❌ Keine Rows!\n";
							}
						}
					} else {
						echo "        ❌ Keine Groups in Data Grid!\n";
					}
				} else {
					echo "      ❌ Data Grid NICHT vorhanden!\n";
					echo "      Verfügbare Meta Properties:\n";
					foreach ($field->meta as $key => $value) {
						if (strpos($key, 'data_grid') !== false) {
							echo "        - {$key}\n";
						}
					}
				}
			}
			
			echo "\n";
		}
	}
	echo "\n";
}

echo "\n=== SCANNER TEST ===\n";
$result = $scanner->scan_form($form_id);

if ($result['success']) {
	echo "✅ Scan erfolgreich!\n";
	echo "Felder gefunden: {$result['stats']['fields_found']}\n";
	echo "Neue Felder: {$result['stats']['new_fields']}\n";
	echo "Aktualisierte Felder: {$result['stats']['updated_fields']}\n\n";
	
	echo "=== GESCANNTE FELDER ===\n";
	foreach ($result['fields'] as $field) {
		echo "Field ID: {$field['field_id']}\n";
		echo "Field Path: {$field['field_path']}\n";
		echo "Field Type: {$field['field_type']}\n";
		echo "Field Label: {$field['field_label']}\n";
		echo "Translatable Properties: " . count($field['translatable_properties']) . "\n";
		
		foreach ($field['translatable_properties'] as $prop) {
			echo "  - Type: {$prop['type']}, Path: {$prop['path']}\n";
			echo "    Value: " . substr($prop['value'], 0, 50) . "\n";
		}
		echo "\n";
	}
} else {
	echo "❌ Scan fehlgeschlagen: {$result['error']}\n";
}

echo "</pre>";
