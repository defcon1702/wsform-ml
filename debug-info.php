<?php
/**
 * Debug-Script für WSForm ML
 * Aufruf: /wp-content/plugins/wsform-ml/debug-info.php
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
	die('Keine Berechtigung');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
	<title>WSForm ML Debug Info</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; }
		.section { background: #f0f0f1; padding: 15px; margin: 15px 0; border-radius: 4px; }
		.success { color: #00a32a; }
		.error { color: #d63638; }
		.warning { color: #dba617; }
		pre { background: #fff; padding: 10px; overflow-x: auto; }
		h2 { margin-top: 0; }
	</style>
</head>
<body>
	<h1>WSForm ML Debug Information</h1>

	<div class="section">
		<h2>1. Plugin Status</h2>
		<?php
		$plugin_active = is_plugin_active('wsform-ml/wsform-ml.php');
		echo $plugin_active ? '<p class="success">✓ Plugin ist aktiv</p>' : '<p class="error">✗ Plugin ist nicht aktiv</p>';
		
		echo '<p>Plugin Version: ' . (defined('WSFORM_ML_VERSION') ? WSFORM_ML_VERSION : 'Nicht definiert') . '</p>';
		?>
	</div>

	<div class="section">
		<h2>2. WS Form Status</h2>
		<?php
		$wsform_active = class_exists('WS_Form_Form');
		echo $wsform_active ? '<p class="success">✓ WS Form ist installiert</p>' : '<p class="error">✗ WS Form ist NICHT installiert</p>';
		
		global $wpdb;
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wsf_form'") === $wpdb->prefix . 'wsf_form';
		echo $table_exists ? '<p class="success">✓ WS Form Tabelle existiert</p>' : '<p class="error">✗ WS Form Tabelle existiert NICHT</p>';
		
		if ($table_exists) {
			$form_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsf_form");
			echo "<p>Anzahl Formulare: <strong>$form_count</strong></p>";
		}
		?>
	</div>

	<div class="section">
		<h2>3. Polylang Status</h2>
		<?php
		$polylang_active = function_exists('pll_languages_list');
		echo $polylang_active ? '<p class="success">✓ Polylang ist aktiv</p>' : '<p class="warning">⚠ Polylang ist nicht aktiv (Fallback zu English)</p>';
		
		if ($polylang_active) {
			$languages = pll_languages_list(['fields' => false]);
			$lang_codes = array_map(function($lang) {
				return is_object($lang) && isset($lang->slug) ? $lang->slug : (string)$lang;
			}, $languages);
			echo '<p>Verfügbare Sprachen: <strong>' . implode(', ', $lang_codes) . '</strong></p>';
			echo '<p>Standard-Sprache: <strong>' . pll_default_language() . '</strong></p>';
			echo '<p>Aktuelle Sprache: <strong>' . pll_current_language() . '</strong></p>';
		}
		
		echo '<h3>WSForm_ML_Polylang_Integration::get_languages()</h3>';
		if (class_exists('WSForm_ML_Polylang_Integration')) {
			$ml_languages = WSForm_ML_Polylang_Integration::get_languages();
			echo '<pre>' . print_r($ml_languages, true) . '</pre>';
		} else {
			echo '<p class="error">Klasse nicht gefunden</p>';
		}
		?>
	</div>

	<div class="section">
		<h2>4. Datenbank-Tabellen</h2>
		<?php
		$tables = [
			'wsform_ml_translations' => $wpdb->prefix . 'wsform_ml_translations',
			'wsform_ml_field_cache' => $wpdb->prefix . 'wsform_ml_field_cache',
			'wsform_ml_scan_log' => $wpdb->prefix . 'wsform_ml_scan_log'
		];
		
		foreach ($tables as $name => $full_name) {
			$exists = $wpdb->get_var("SHOW TABLES LIKE '$full_name'") === $full_name;
			if ($exists) {
				$count = $wpdb->get_var("SELECT COUNT(*) FROM $full_name");
				echo "<p class='success'>✓ $name ($count Einträge)</p>";
			} else {
				echo "<p class='error'>✗ $name existiert NICHT</p>";
			}
		}
		?>
	</div>

	<div class="section">
		<h2>5. REST API Test</h2>
		<?php
		$rest_url = rest_url('wsform-ml/v1/forms');
		echo "<p>REST URL: <code>$rest_url</code></p>";
		
		$request = new WP_REST_Request('GET', '/wsform-ml/v1/forms');
		$response = rest_do_request($request);
		
		if (is_wp_error($response)) {
			echo '<p class="error">✗ REST API Fehler: ' . $response->get_error_message() . '</p>';
		} else {
			$data = $response->get_data();
			if (isset($data['code'])) {
				echo '<p class="error">✗ API Error: ' . $data['message'] . '</p>';
			} else {
				echo '<p class="success">✓ REST API funktioniert</p>';
				echo '<p>Anzahl Formulare: <strong>' . count($data) . '</strong></p>';
				if (!empty($data)) {
					echo '<h3>Erstes Formular:</h3>';
					echo '<pre>' . print_r($data[0], true) . '</pre>';
				}
			}
		}
		?>
	</div>

	<div class="section">
		<h2>6. JavaScript-Konfiguration</h2>
		<?php
		if (class_exists('WSForm_ML_Polylang_Integration')) {
			$js_config = [
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'restUrl' => rest_url('wsform-ml/v1'),
				'nonce' => wp_create_nonce('wp_rest'),
				'languages' => WSForm_ML_Polylang_Integration::get_languages(),
				'currentLanguage' => WSForm_ML_Polylang_Integration::get_current_language(),
				'defaultLanguage' => WSForm_ML_Polylang_Integration::get_default_language()
			];
			echo '<pre>' . json_encode($js_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>';
		}
		?>
	</div>

	<div class="section">
		<h2>7. PHP Fehler-Log (letzte 20 Zeilen)</h2>
		<?php
		$error_log = ini_get('error_log');
		if ($error_log && file_exists($error_log)) {
			$lines = file($error_log);
			$last_lines = array_slice($lines, -20);
			echo '<pre>' . htmlspecialchars(implode('', $last_lines)) . '</pre>';
		} else {
			echo '<p class="warning">Fehler-Log nicht gefunden oder nicht konfiguriert</p>';
		}
		?>
	</div>

	<div class="section">
		<h2>8. Geladene Klassen</h2>
		<?php
		$classes = [
			'WSForm_ML',
			'WSForm_ML_Database',
			'WSForm_ML_Field_Scanner',
			'WSForm_ML_Translation_Manager',
			'WSForm_ML_Renderer',
			'WSForm_ML_Polylang_Integration',
			'WSForm_ML_Admin_Menu',
			'WSForm_ML_REST_API'
		];
		
		foreach ($classes as $class) {
			$exists = class_exists($class);
			echo $exists ? "<p class='success'>✓ $class</p>" : "<p class='error'>✗ $class</p>";
		}
		?>
	</div>

	<p style="margin-top: 30px; color: #646970;">
		<strong>Hinweis:</strong> Diese Seite nur für Debugging verwenden und danach löschen!
	</p>
</body>
</html>
