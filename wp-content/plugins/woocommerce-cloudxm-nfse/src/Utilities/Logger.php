<?php

declare(strict_types=1);
namespace CloudXM\NFSe\Utilities;

/**
 * NFSE Logger Class
 *
 * Comprehensive logging utility with PII protection and structured logging
 *
 * @package WooCommerce_CloudXM_NFSE
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NFSE Logger
 */
class Logger {

    /**
     * Singleton instance
     *
     * @var Logger|null
     */
    private static $instance = null;

    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    /**
     * Current log level
     *
     * @var string
     */
    private $log_level;

    /**
     * Log level hierarchy
     *
     * @var array
     */
    private static $level_hierarchy = array(
        self::DEBUG => 7,
        self::INFO => 6,
        self::NOTICE => 5,
        self::WARNING => 4,
        self::ERROR => 3,
        self::CRITICAL => 2,
        self::ALERT => 1,
        self::EMERGENCY => 0,
    );

    /**
     * Recursion protection flag
     *
     * @var bool
     */
    private static $inLogging = false;

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Get singleton instance of Logger
     *
     * @return Logger
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->log_level = get_option('wc_nfse_log_level', self::INFO);
        $this->setupLogFile();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {}

    /**
     * Setup log file
     */
    private function setupLogFile() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wc-nfse-logs/';

        // Ensure log directory exists
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $this->log_file = $log_dir . 'wc-nfse-' . date('Y-m-d') . '.log';
    }

    /**
     * Log emergency message
     */
    public function emergency($message, $context = array(), $correlation_id = null) {
        $this->log(self::EMERGENCY, $message, $context, $correlation_id);
    }

    /**
     * Log alert message
     */
    public function alert($message, $context = array(), $correlation_id = null) {
        $this->log(self::ALERT, $message, $context, $correlation_id);
    }

    /**
     * Log critical message
     */
    public function critical($message, $context = array(), $correlation_id = null) {
        $this->log(self::CRITICAL, $message, $context, $correlation_id);
    }

    /**
     * Log error message
     */
    public function error($message, $context = array(), $correlation_id = null) {
        $this->log(self::ERROR, $message, $context, $correlation_id);
    }

    /**
     * Log warning message
     */
    public function warning($message, $context = array(), $correlation_id = null) {
        $this->log(self::WARNING, $message, $context, $correlation_id);
    }

    /**
     * Log notice message
     */
    public function notice($message, $context = array(), $correlation_id = null) {
        $this->log(self::NOTICE, $message, $context, $correlation_id);
    }

    /**
     * Log info message
     */
    public function info($message, $context = array(), $correlation_id = null) {
        $this->log(self::INFO, $message, $context, $correlation_id);
    }

    /**
     * Log debug message
     */
    public function debug($message, $context = array(), $correlation_id = null) {
        $this->log(self::DEBUG, $message, $context, $correlation_id);
    }

    /**
     * Log message with specified level
     */
    public function log($level, $message, $context = array(), $correlation_id = null) {
        // Recursion protection
        if (self::$inLogging) {
            return;
        }

        // Check if we should log this level
        if (!$this->shouldLog($level)) {
            return;
        }

        self::$inLogging = true;
        try {
            $correlation_id = $correlation_id ?: uniqid('nfse-', true);

            // Scrub PII from context
            $scrubbed_context = $this->scrubPii($context);

            $timestamp = date('Y-m-d H:i:s');
            $context_str = !empty($scrubbed_context) ? ' | Context: ' . wp_json_encode($scrubbed_context) : '';

            $log_entry = sprintf(
                "[%s] %s: %s%s\n",
                $timestamp,
                strtoupper($level),
                $message,
                $context_str
            );

            // Write to file
            file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);

            // Also log to WordPress debug log if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WC_NFSe [{$level}]: {$message}{$context_str}");
            }

            // Trigger action for external logging systems - updated to match src
            do_action('wc_nfse_log_entry', array(
                'timestamp' => $timestamp,
                'level' => $level,
                'message' => $message,
                'context' => $scrubbed_context,
                'correlation_id' => $correlation_id,
                'user_id' => get_current_user_id(),
                'plugin_version' => defined('WC_NFSE_VERSION') ? WC_NFSE_VERSION : 'unknown'
            ));

        } finally {
            self::$inLogging = false;
        }
    }

    /**
     * Scrub personally identifiable information
     */
    private function scrubPii($context) {
        $scrub_fields = array(
            'card_number', 'cvv', 'card_cvv', 'document', 'cpf', 'cnpj',
            'phone', 'email', 'birth_date', 'password', 'api_key', 'secret'
        );

        $scrubbed = array();

        foreach ($context as $key => $value) {
            if (in_array(strtolower($key), $scrub_fields) && is_scalar($value)) {
                $scrubbed[$key] = '[REDACTED]';
            } else {
                $scrubbed[$key] = $value;
            }
        }

        return $scrubbed;
    }

    /**
     * Get client IP address - kept from includes
     */
    private function getClientIp() {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '';
    }

    /**
     * Check if we should log this level
     */
    private function shouldLog($level) {
        // Check if debug mode is enabled for debug messages
        if ($level === self::DEBUG && !$this->is_debug_enabled()) {
            return false;
        }

        if (!isset(self::$level_hierarchy[$level])) {
            return false;
        }

        if (!isset(self::$level_hierarchy[$this->log_level])) {
            return false;
        }

        return self::$level_hierarchy[$level] <= self::$level_hierarchy[$this->log_level];
    }

    /**
     * Check if debug mode is enabled
     */
    private function is_debug_enabled() {
        $settings = get_option('wc_nfse_settings', array());
        return isset($settings['debug_mode']) && $settings['debug_mode'] === 'yes';
    }

    /**
     * Get recent log entries
     */
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }

        $logs = array();
        $file = new \SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();

        $start_line = max(0, $total_lines - $lines);
        $file->seek($start_line);

        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (!empty($line)) {
                $logs[] = $line;
            }
        }

        return $logs;
    }

    /**
     * Clear log file
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
        }
    }

    /**
     * Get log file size
     */
    public function get_log_size() {
        if (file_exists($this->log_file)) {
            return filesize($this->log_file);
        }
        return 0;
    }

    /**
     * Format file size
     */
    public function format_file_size($size) {
        $units = array('B', 'KB', 'MB', 'GB');
        $unit_index = 0;

        while ($size >= 1024 && $unit_index < count($units) - 1) {
            $size /= 1024;
            $unit_index++;
        }

        return round($size, 2) . ' ' . $units[$unit_index];
    }

    /**
     * Set log level
     */
    public function setLogLevel($level) {
        if (isset(self::$level_hierarchy[$level])) {
            $this->log_level = $level;
        }
    }

    /**
     * Get current log level
     */
    public function getLogLevel() {
        return $this->log_level;
    }
}