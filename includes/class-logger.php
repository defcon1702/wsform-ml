<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WSForm ML Logger
 * 
 * Zentralisiertes Logging-System mit konfigurierbaren Log-Levels
 * 
 * @since 1.3.0
 */
class WSForm_ML_Logger {
	private static $instance = null;
	
	// Log-Levels
	const DEBUG = 'debug';
	const INFO = 'info';
	const WARNING = 'warning';
	const ERROR = 'error';
	
	private $enabled = false;
	private $log_level = self::INFO;
	private $log_to_file = false;
	private $log_file_path = '';
	
	/**
	 * Singleton Instance
	 */
	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_settings();
		$this->setup_log_file();
	}
	
	/**
	 * Lade Logging-Einstellungen aus WordPress Options
	 */
	private function load_settings() {
		$settings = get_option('wsform_ml_logger_settings', []);
		
		$this->enabled = isset($settings['enabled']) ? (bool) $settings['enabled'] : false;
		$this->log_level = isset($settings['log_level']) ? $settings['log_level'] : self::INFO;
		$this->log_to_file = isset($settings['log_to_file']) ? (bool) $settings['log_to_file'] : false;
		
		// Erlaube Aktivierung via Konstante (für Debugging)
		if (defined('WSFORM_ML_DEBUG') && WSFORM_ML_DEBUG) {
			$this->enabled = true;
			$this->log_level = self::DEBUG;
		}
	}
	
	/**
	 * Setup Log-Datei
	 */
	private function setup_log_file() {
		if (!$this->log_to_file) {
			return;
		}
		
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/wsform-ml-logs';
		
		// Erstelle Log-Verzeichnis falls nicht vorhanden
		if (!file_exists($log_dir)) {
			wp_mkdir_p($log_dir);
			
			// Schütze Log-Verzeichnis mit .htaccess
			$htaccess = $log_dir . '/.htaccess';
			if (!file_exists($htaccess)) {
				file_put_contents($htaccess, "Deny from all\n");
			}
		}
		
		$this->log_file_path = $log_dir . '/wsform-ml-' . date('Y-m-d') . '.log';
	}
	
	/**
	 * Prüfe ob Log-Level aktiv ist
	 * 
	 * @param string $level
	 * @return bool
	 */
	private function should_log($level) {
		if (!$this->enabled) {
			return false;
		}
		
		$levels = [
			self::DEBUG => 0,
			self::INFO => 1,
			self::WARNING => 2,
			self::ERROR => 3
		];
		
		$current_level = isset($levels[$this->log_level]) ? $levels[$this->log_level] : 1;
		$message_level = isset($levels[$level]) ? $levels[$level] : 1;
		
		return $message_level >= $current_level;
	}
	
	/**
	 * Formatiere Log-Message
	 * 
	 * @param string $level
	 * @param string $message
	 * @param array $context
	 * @return string
	 */
	private function format_message($level, $message, $context = []) {
		$timestamp = current_time('Y-m-d H:i:s');
		$level_str = strtoupper($level);
		
		$formatted = "[{$timestamp}] [{$level_str}] {$message}";
		
		if (!empty($context)) {
			$formatted .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
		}
		
		return $formatted;
	}
	
	/**
	 * Schreibe Log-Message
	 * 
	 * @param string $level
	 * @param string $message
	 * @param array $context
	 */
	private function write_log($level, $message, $context = []) {
		if (!$this->should_log($level)) {
			return;
		}
		
		$formatted = $this->format_message($level, $message, $context);
		
		// Log zu error_log
		error_log("WSForm ML: {$formatted}");
		
		// Log zu Datei
		if ($this->log_to_file && $this->log_file_path) {
			$this->write_to_file($formatted);
		}
	}
	
	/**
	 * Schreibe zu Log-Datei
	 * 
	 * @param string $message
	 */
	private function write_to_file($message) {
		try {
			file_put_contents(
				$this->log_file_path,
				$message . PHP_EOL,
				FILE_APPEND | LOCK_EX
			);
			
			// Rotiere Log-Datei wenn zu groß (> 10MB)
			$this->rotate_log_file();
		} catch (Exception $e) {
			// Fallback zu error_log wenn File-Write fehlschlägt
			error_log("WSForm ML Logger: Failed to write to log file - " . $e->getMessage());
		}
	}
	
	/**
	 * Rotiere Log-Datei wenn zu groß
	 */
	private function rotate_log_file() {
		if (!file_exists($this->log_file_path)) {
			return;
		}
		
		$max_size = 10 * 1024 * 1024; // 10MB
		$file_size = filesize($this->log_file_path);
		
		if ($file_size > $max_size) {
			$backup_path = $this->log_file_path . '.old';
			
			// Lösche alte Backup-Datei
			if (file_exists($backup_path)) {
				unlink($backup_path);
			}
			
			// Rename aktuelle zu .old
			rename($this->log_file_path, $backup_path);
		}
	}
	
	/**
	 * Public Logging-Methoden
	 */
	
	/**
	 * Debug-Level Log
	 * 
	 * @param string $message
	 * @param array $context
	 */
	public function debug($message, $context = []) {
		$this->write_log(self::DEBUG, $message, $context);
	}
	
	/**
	 * Info-Level Log
	 * 
	 * @param string $message
	 * @param array $context
	 */
	public function info($message, $context = []) {
		$this->write_log(self::INFO, $message, $context);
	}
	
	/**
	 * Warning-Level Log
	 * 
	 * @param string $message
	 * @param array $context
	 */
	public function warning($message, $context = []) {
		$this->write_log(self::WARNING, $message, $context);
	}
	
	/**
	 * Error-Level Log
	 * 
	 * @param string $message
	 * @param array $context
	 */
	public function error($message, $context = []) {
		$this->write_log(self::ERROR, $message, $context);
	}
	
	/**
	 * Log mit Exception
	 * 
	 * @param Exception $exception
	 * @param string $message
	 */
	public function exception($exception, $message = '') {
		$context = [
			'exception' => get_class($exception),
			'message' => $exception->getMessage(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString()
		];
		
		$log_message = $message ?: 'Exception occurred';
		$this->error($log_message, $context);
	}
	
	/**
	 * Speichere Logger-Einstellungen
	 * 
	 * @param array $settings
	 * @return bool
	 */
	public function save_settings($settings) {
		$valid_settings = [
			'enabled' => isset($settings['enabled']) ? (bool) $settings['enabled'] : false,
			'log_level' => isset($settings['log_level']) ? $settings['log_level'] : self::INFO,
			'log_to_file' => isset($settings['log_to_file']) ? (bool) $settings['log_to_file'] : false
		];
		
		$result = update_option('wsform_ml_logger_settings', $valid_settings);
		
		// Reload Settings
		$this->load_settings();
		$this->setup_log_file();
		
		return $result;
	}
	
	/**
	 * Hole aktuelle Logger-Einstellungen
	 * 
	 * @return array
	 */
	public function get_settings() {
		return [
			'enabled' => $this->enabled,
			'log_level' => $this->log_level,
			'log_to_file' => $this->log_to_file,
			'log_file_path' => $this->log_file_path
		];
	}
	
	/**
	 * Lösche alte Log-Dateien
	 * 
	 * @param int $days Lösche Logs älter als X Tage
	 * @return int Anzahl gelöschter Dateien
	 */
	public function cleanup_old_logs($days = 30) {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/wsform-ml-logs';
		
		if (!file_exists($log_dir)) {
			return 0;
		}
		
		$deleted = 0;
		$cutoff_time = time() - ($days * DAY_IN_SECONDS);
		
		$files = glob($log_dir . '/wsform-ml-*.log*');
		
		foreach ($files as $file) {
			if (filemtime($file) < $cutoff_time) {
				if (unlink($file)) {
					$deleted++;
				}
			}
		}
		
		return $deleted;
	}
	
	/**
	 * Hole Log-Dateien
	 * 
	 * @return array
	 */
	public function get_log_files() {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/wsform-ml-logs';
		
		if (!file_exists($log_dir)) {
			return [];
		}
		
		$files = glob($log_dir . '/wsform-ml-*.log*');
		$log_files = [];
		
		foreach ($files as $file) {
			$log_files[] = [
				'name' => basename($file),
				'path' => $file,
				'size' => filesize($file),
				'modified' => filemtime($file)
			];
		}
		
		// Sortiere nach Datum (neueste zuerst)
		usort($log_files, function($a, $b) {
			return $b['modified'] - $a['modified'];
		});
		
		return $log_files;
	}
	
	/**
	 * Lese Log-Datei
	 * 
	 * @param string $filename
	 * @param int $lines Anzahl Zeilen (von unten)
	 * @return string|false
	 */
	public function read_log_file($filename, $lines = 100) {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/wsform-ml-logs';
		$file_path = $log_dir . '/' . basename($filename);
		
		if (!file_exists($file_path)) {
			return false;
		}
		
		// Lese letzte N Zeilen
		$file = new SplFileObject($file_path, 'r');
		$file->seek(PHP_INT_MAX);
		$total_lines = $file->key();
		
		$start_line = max(0, $total_lines - $lines);
		$file->seek($start_line);
		
		$content = '';
		while (!$file->eof()) {
			$content .= $file->fgets();
		}
		
		return $content;
	}
}
