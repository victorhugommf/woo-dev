<?php

declare(strict_types=1);
namespace CloudXM\Nfse\Utilities;

/**
 * NFSE Config Class
 *
 * Centralized configuration management for NFSE plugin
 *
 * @package WooCommerce_NFSe
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NFSE Configuration Manager
 */
class Config {

    /**
     * Singleton instance
     *
     * @var Config|null
     */
    private static $instance = null;

    /**
     * Cached configuration
     *
     * @var array
     */
    private $config = array();

    /**
     * Get singleton instance of Config
     *
     * @return Config
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
        $this->loadConfiguration();
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
     * Load configuration from WordPress options
     */
    private function loadConfiguration() {
        $this->config = array(
            // Plugin settings
            'version' => defined('WC_NFSE_VERSION') ? WC_NFSE_VERSION : '3.0.0',
            'text_domain' => 'wc-nfse',

            // API configuration
            'api_settings' => get_option('wc_nfse_settings', array()),

            // Log configuration
            'log_settings' => array(
                'level' => get_option('wc_nfse_log_level', 'info'),
                'enabled' => true,
            ),

            // Certificate configuration
            'certificate_settings' => array(
                'active_id' => get_option('wc_nfse_active_certificate_id', 0),
                'validate_on_save' => true,
                'auto_expire_check' => true,
            ),

            // DPS configuration
            'dps_settings' => array(
                'auto_emit' => get_option('wc_nfse_auto_emit', 'no') === 'yes',
                'validate_before_emit' => true,
                'compression_enabled' => true,
                'signature_required' => true,
            ),

            // Emission limits and controls
            'emission_limits' => array(
                'max_daily_emissions' => (int) get_option('wc_nfse_max_daily_emissions', 100),
                'rate_limit_per_minute' => (int) get_option('wc_nfse_rate_limit', 10),
                'auto_pause_on_error' => get_option('wc_nfse_auto_pause', 'no') === 'yes',
            ),

            // Database table prefixes
            'table_prefixes' => array(
                'certificates' => 'cloudxm_nfse_certificates',
                'queue' => 'wc_nfse_emission_queue',
                'logs' => 'wc_nfse_logs',
                'cache' => 'wc_nfse_cache',
            ),

            // Path configurations
            'paths' => array(
                'certificate_dir' => wp_upload_dir()['basedir'] . '/wc-nfse-certificates/',
                'log_dir' => wp_upload_dir()['basedir'] . '/wc-nfse-logs/',
                'temp_dir' => wp_upload_dir()['basedir'] . '/wc-nfse-temp/',
            ),

            // Environment detection
            'environment' => array(
                'is_development' => $this->isDevelopmentEnv(),
                'is_production' => !$this->isDevelopmentEnv(),
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
                'debug_mode' => isset($this->config['api_settings']['debug_mode']) && $this->config['api_settings']['debug_mode'] === 'yes',
            ),

            // Feature flags
            'features' => array(
                'async_emission' => true,
                'batch_processing' => true,
                'advanced_logging' => true,
                'compression' => true,
                'digital_signature' => true,
                'webhooks' => true,
            ),
        );
    }

    /**
     * Check if we're in development environment
     */
    private function isDevelopmentEnv() {
        // Check for development indicators
        $is_dev = (
            defined('WP_DEBUG') && WP_DEBUG ||
            defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development' ||
            strpos(get_site_url(), 'localhost') !== false ||
            strpos(get_site_url(), '.local') !== false ||
            strpos(get_site_url(), 'dev.') !== false ||
            strpos(get_site_url(), '-dev.') !== false ||
            strpos(get_site_url(), 'staging') !== false
        );

        return $is_dev;
    }

    /**
     * Get configuration value by key
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Set configuration value
     */
    public function set($key, $value) {
        $keys = explode('.', $key);
        $temp = &$this->config;

        foreach ($keys as $k) {
            if (!isset($temp[$k])) {
                $temp[$k] = array();
            }
            $temp = &$temp[$k];
        }

        $temp = $value;
        return $this;
    }

    /**
     * Get all configuration
     */
    public function all() {
        return $this->config;
    }

    /**
     * Check if a feature is enabled
     */
    public function hasFeature($feature) {
        return isset($this->config['features'][$feature]) && $this->config['features'][$feature];
    }

    /**
     * Get API settings
     */
    public function getApiSettings() {
        return $this->config['api_settings'];
    }

    /**
     * Get table name with prefix
     */
    public function getTableName($table) {
        global $wpdb;

        if (isset($this->config['table_prefixes'][$table])) {
            return $wpdb->prefix . $this->config['table_prefixes'][$table];
        }

        return $wpdb->prefix . 'wc_nfse_' . $table;
    }

    /**
     * Get upload path for specific type
     */
    public function getPath($type) {
        if (isset($this->config['paths'][$type])) {
            $path = $this->config['paths'][$type];

            // Ensure directory exists
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }

            return $path;
        }

        return wp_upload_dir()['basedir'] . '/wc-nfse/';
    }

    /**
     * Get path to certificate directory
     */
    public function getCertificatePath() {
        return $this->getPath('certificate_dir');
    }

    /**
     * Get path to log directory
     */
    public function getLogPath() {
        return $this->getPath('log_dir');
    }

    /**
     * Get path to temp directory
     */
    public function getTempPath() {
        return $this->getPath('temp_dir');
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugMode() {
        return $this->config['environment']['debug_mode'];
    }

    /**
     * Check if we're in development environment
     */
    public function isDevelopment() {
        return $this->config['environment']['is_development'];
    }

    /**
     * Get plugin version
     */
    public function getVersion() {
        return $this->config['version'];
    }

    /**
     * Refresh configuration from database
     */
    public function refresh() {
        $this->loadConfiguration();
        return $this;
    }

    /**
     * Get emission limits
     */
    public function getEmissionLimits() {
        return $this->config['emission_limits'];
    }

    /**
     * Get certificate settings
     */
    public function getCertificateSettings() {
        return $this->config['certificate_settings'];
    }

    /**
     * Get DPS settings
     */
    public function getDpsSettings() {
        return $this->config['dps_settings'];
    }
}