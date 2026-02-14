<?php
/**
 * Debug-Check für WSForm ML v1.2.0
 * 
 * Aufruf: https://deine-domain.de/wp-content/plugins/wsform-ml/debug-check.php
 */

// WordPress laden
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
	die('Keine Berechtigung');
}

echo '<h1>WSForm ML Debug Check</h1>';
echo '<pre>';

// 1. Plugin-Version prüfen
echo "=== PLUGIN VERSION ===\n";
if (defined('WSFORM_ML_VERSION')) {
	echo "✅ WSFORM_ML_VERSION: " . WSFORM_ML_VERSION . "\n";
} else {
	echo "❌ WSFORM_ML_VERSION nicht definiert\n";
}

// 2. Klassen-Existenz prüfen
echo "\n=== KLASSEN GELADEN ===\n";
$classes = [
	'WSForm_ML',
	'WSForm_ML_Database',
	'WSForm_ML_Field_Scanner',
	'WSForm_ML_Translation_Manager',
	'WSForm_ML_Renderer',
	'WSForm_ML_Polylang_Integration',
	'WSForm_ML_Language_Field_Manager',
	'WSForm_ML_Feature_Manager',
	'WSForm_ML_Native_Adapter',
	'WSForm_ML_REST_API',
	'WSForm_ML_Admin_Menu',
	'WSForm_ML_Settings_Page'
];

foreach ($classes as $class) {
	if (class_exists($class)) {
		echo "✅ $class\n";
	} else {
		echo "❌ $class - FEHLT!\n";
	}
}

// 3. Datei-Existenz prüfen
echo "\n=== DATEIEN VORHANDEN ===\n";
$files = [
	'includes/class-language-field-manager.php',
	'admin/class-settings-page.php',
	'admin/class-rest-api.php',
	'includes/class-field-scanner.php',
	'admin/assets/js/admin.js',
	'admin/assets/css/admin.css',
	'VERSION',
	'CHANGELOG.md'
];

foreach ($files as $file) {
	$path = plugin_dir_path(__FILE__) . $file;
	if (file_exists($path)) {
		echo "✅ $file (" . filesize($path) . " bytes)\n";
	} else {
		echo "❌ $file - FEHLT!\n";
	}
}

// 4. Language Field Manager Test
echo "\n=== LANGUAGE FIELD MANAGER TEST ===\n";
if (class_exists('WSForm_ML_Language_Field_Manager')) {
	try {
		$manager = WSForm_ML_Language_Field_Manager::instance();
		echo "✅ Instance erstellt\n";
		
		$forms = $manager->get_available_forms();
		echo "✅ get_available_forms(): " . count($forms) . " Forms gefunden\n";
		
		$configured = $manager->get_configured_fields();
		echo "✅ get_configured_fields(): " . count($configured) . " konfiguriert\n";
	} catch (Exception $e) {
		echo "❌ Fehler: " . $e->getMessage() . "\n";
	}
} else {
	echo "❌ Klasse nicht geladen\n";
}

// 5. Settings Page Test
echo "\n=== SETTINGS PAGE TEST ===\n";
if (class_exists('WSForm_ML_Settings_Page')) {
	try {
		$settings = WSForm_ML_Settings_Page::instance();
		echo "✅ Instance erstellt\n";
		
		// Prüfe ob render_settings_page Methode existiert
		if (method_exists($settings, 'render_settings_page')) {
			echo "✅ render_settings_page() Methode vorhanden\n";
		} else {
			echo "❌ render_settings_page() Methode fehlt\n";
		}
	} catch (Exception $e) {
		echo "❌ Fehler: " . $e->getMessage() . "\n";
	}
} else {
	echo "❌ Klasse nicht geladen\n";
}

// 6. WordPress Hooks prüfen
echo "\n=== WORDPRESS HOOKS ===\n";
global $wp_filter;

if (isset($wp_filter['admin_menu'])) {
	echo "✅ admin_menu Hook registriert\n";
	foreach ($wp_filter['admin_menu']->callbacks as $priority => $callbacks) {
		foreach ($callbacks as $callback) {
			if (is_array($callback['function']) && is_object($callback['function'][0])) {
				$class = get_class($callback['function'][0]);
				if (strpos($class, 'WSForm_ML') !== false) {
					echo "   → $class::{$callback['function'][1]} (Priority: $priority)\n";
				}
			}
		}
	}
} else {
	echo "❌ admin_menu Hook nicht registriert\n";
}

// 7. PHP Info
echo "\n=== PHP INFO ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Opcode Cache: " . (function_exists('opcache_get_status') ? 'Aktiv' : 'Inaktiv') . "\n";
if (function_exists('opcache_get_status')) {
	$status = opcache_get_status();
	echo "Opcode Cache Enabled: " . ($status['opcache_enabled'] ? 'Ja' : 'Nein') . "\n";
}

echo "\n=== FERTIG ===\n";
echo "Wenn Language Field Manager ❌ ist, dann ist die Datei nicht korrekt hochgeladen.\n";
echo "Wenn Language Field Manager ✅ ist, aber Settings Page nicht, dann ist ein Init-Problem.\n";
echo '</pre>';
