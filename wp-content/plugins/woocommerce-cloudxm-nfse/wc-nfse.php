<?php

/**
 * Plugin Name: CloudXM NFS-e
 * Plugin URI: https://cloudxm.com.br/plugins/woocommerce-cloudxm-nfse
 * Description: Professional CloudXM NFS-e Plugin for WooCommerce - Automated electronic invoice generation and management with governo.br compliance.
 * Version: 2.0.1
 * Author: CloudXM
 * Author URI: https://cloudxm.com.br
 * License: Proprietary - All rights reserved to CloudXM
 * License URI: https://cloudxm.com.br/license
 * Text Domain: wc-nfse
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Load Plugin Update Checker safely to avoid conflicts with other CloudXM plugins
if (!defined('CLOUDXM_PUC_LOADED')) {
    $autoload_file = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload_file)) {
        require_once $autoload_file;
        define('CLOUDXM_PUC_LOADED', true);
    }
}

/**
 * Get the appropriate update server URL based on environment
 * Checks if we're in a development environment or production
 */
function wc_nfse_get_update_server_url()
{
    // Check if we're in a development environment
    $is_dev = (
        defined('WP_DEBUG') && WP_DEBUG ||
        defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development' ||
        strpos(get_site_url(), 'localhost') !== false ||
        strpos(get_site_url(), '.local') !== false ||
        strpos(get_site_url(), 'dev.') !== false ||
        strpos(get_site_url(), '-dev.') !== false
    );

    // For local development, disable update checker to avoid connection errors
    if ($is_dev && strpos(get_site_url(), 'localhost') !== false) {
        return false; // Disable update checker in local development
    }

    $base_url = $is_dev ? 'https://dev-plugins.cloudxm.com.br' : 'https://plugins.cloudxm.com.br';
    return $base_url . '/woocommerce-cloudxm-nfse/latest.json';
}

// Initialize Plugin Update Checker safely
$update_url = wc_nfse_get_update_server_url();
if ($update_url && class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    $nfse_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $update_url,
        __FILE__,
        'woocommerce-cloudxm-nfse'
    );
} else {
    // In development or if update checker unavailable, skip initialization
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('CloudXM NFSE: Plugin Update Checker disabled for development environment');
    }
}

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Prevent multiple plugin loads
if (defined('WC_NFSE_PLUGIN_LOADED')) {
    return;
}
define('WC_NFSE_PLUGIN_LOADED', true);

// Define plugin constants
define('WC_NFSE_VERSION', '2.0.0');
define('WC_NFSE_PLUGIN_FILE', __FILE__);
define('WC_NFSE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_NFSE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Define additional required directories
define('WC_NFSE_CERTIFICATES_DIR', WP_CONTENT_DIR . '/uploads/wc-nfse/certificates/');
define('WC_NFSE_LOGS_DIR', WP_CONTENT_DIR . '/uploads/wc-nfse/logs/');

// Ensure necessary directories exist
if (!file_exists(WC_NFSE_CERTIFICATES_DIR)) {
    wp_mkdir_p(WC_NFSE_CERTIFICATES_DIR);
}
if (!file_exists(WC_NFSE_LOGS_DIR)) {
    wp_mkdir_p(WC_NFSE_LOGS_DIR);
}

// Load bootstrap with all hooks and initializations
require_once WC_NFSE_PLUGIN_PATH . 'includes/bootstrap.php';

// ---- PSR-4 Only Architecture ----
// Plugin now uses PSR-4 classes exclusively. No legacy fallbacks.

// WooCommerce dependency check
add_action('admin_notices', function () {
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p>';
        echo __('CloudXM NFSE requires WooCommerce to function. Please install and activate WooCommerce.', 'wc-nfse');
        echo ' <a href="https://cloudxm.com.br/support" target="_blank">' . __('Need help? Contact CloudXM Support', 'wc-nfse') . '</a>';
        echo '</p></div>';
    }
});

// Plugin activation/deactivation hooks
register_activation_hook(__FILE__, function () {
    // Flush rewrite rules
    flush_rewrite_rules();

    // Log activation
    if (class_exists('\\CloudXM\\Nfse\\Utilities\\Logger')) {
        $logger = \CloudXM\Nfse\Utilities\Logger::getInstance();
        $logger->info('Plugin CloudXM NFSE ativado com sucesso', [
            'version' => WC_NFSE_VERSION
        ]);
    } else {
        error_log('NFSE: Plugin activated');
    }
});

register_deactivation_hook(__FILE__, function () {
    // Flush rewrite rules
    flush_rewrite_rules();

    // Log deactivation
    if (class_exists('\\CloudXM\\Nfse\\Utilities\\Logger')) {
        $logger = \CloudXM\Nfse\Utilities\Logger::getInstance();
        $logger->info('Plugin CloudXM NFSE desativado', [
            'version' => WC_NFSE_VERSION
        ]);
    } else {
        error_log('NFSE: Plugin deactivated');
    }
});
