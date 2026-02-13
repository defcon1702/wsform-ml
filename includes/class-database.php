<?php
if (!defined('ABSPATH')) {
	exit;
}

class WSForm_ML_Database {
	const TABLE_TRANSLATIONS = 'wsform_ml_translations';
	const TABLE_FIELD_CACHE = 'wsform_ml_field_cache';
	const TABLE_SCAN_LOG = 'wsform_ml_scan_log';

	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$table_translations = $wpdb->prefix . self::TABLE_TRANSLATIONS;
		$table_field_cache = $wpdb->prefix . self::TABLE_FIELD_CACHE;
		$table_scan_log = $wpdb->prefix . self::TABLE_SCAN_LOG;

		$sql_translations = "CREATE TABLE IF NOT EXISTS $table_translations (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id bigint(20) UNSIGNED NOT NULL,
			field_id bigint(20) UNSIGNED NOT NULL,
			field_path varchar(500) NOT NULL,
			property_type varchar(50) NOT NULL,
			language_code varchar(10) NOT NULL,
			original_value longtext,
			translated_value longtext,
			context varchar(255) DEFAULT NULL,
			is_auto_generated tinyint(1) DEFAULT 0,
			last_synced datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_translation (form_id, field_id, field_path, property_type, language_code),
			KEY idx_form_lang (form_id, language_code),
			KEY idx_field (field_id),
			KEY idx_sync (last_synced)
		) $charset_collate;";

		$sql_field_cache = "CREATE TABLE IF NOT EXISTS $table_field_cache (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id bigint(20) UNSIGNED NOT NULL,
			field_id bigint(20) UNSIGNED NOT NULL,
			field_type varchar(50) NOT NULL,
			field_path varchar(500) NOT NULL,
			field_label varchar(255) DEFAULT NULL,
			parent_field_id bigint(20) UNSIGNED DEFAULT NULL,
			is_repeater tinyint(1) DEFAULT 0,
			has_options tinyint(1) DEFAULT 0,
			translatable_properties text,
			field_structure longtext,
			last_scanned datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_field (form_id, field_id, field_path),
			KEY idx_form (form_id),
			KEY idx_parent (parent_field_id),
			KEY idx_scanned (last_scanned)
		) $charset_collate;";

		$sql_scan_log = "CREATE TABLE IF NOT EXISTS $table_scan_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id bigint(20) UNSIGNED NOT NULL,
			scan_type varchar(50) NOT NULL,
			fields_found int(11) DEFAULT 0,
			new_fields int(11) DEFAULT 0,
			updated_fields int(11) DEFAULT 0,
			deleted_fields int(11) DEFAULT 0,
			scan_status varchar(20) DEFAULT 'success',
			error_message text,
			scan_duration float DEFAULT NULL,
			scanned_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_form (form_id),
			KEY idx_date (scanned_at)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql_translations);
		dbDelta($sql_field_cache);
		dbDelta($sql_scan_log);

		update_option('wsform_ml_db_version', WSFORM_ML_VERSION);
	}

	public static function drop_tables() {
		global $wpdb;
		$wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . self::TABLE_TRANSLATIONS);
		$wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . self::TABLE_FIELD_CACHE);
		$wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . self::TABLE_SCAN_LOG);
		delete_option('wsform_ml_db_version');
	}

	public static function get_table_name($table) {
		global $wpdb;
		return $wpdb->prefix . $table;
	}
}
