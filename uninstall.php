<?php
/**
 * Uninstall Script für WSForm Multilingual
 * 
 * Wird automatisch ausgeführt wenn das Plugin über WordPress deinstalliert wird.
 * NICHT bei Deaktivierung - nur bei kompletter Deinstallation.
 * 
 * Löscht:
 * - Alle Datenbank-Tabellen (wsform_ml_*)
 * - Alle Plugin-Optionen (wsform_ml_*)
 * - Alle Daten in Multisite-Installationen
 * 
 * @package WSForm_ML
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

WSForm_ML_Database::cleanup_all_data();
