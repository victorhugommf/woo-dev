<?php

/**
 * CloudXM NFS-e Bootstrap
 *
 * Central place for WordPress hook binding and initialization
 * Loads PSR-4 autoloader and binds hooks safely
 *
 * @package WooCommerce_CloudXM_NFSE
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader if available
$autoloader_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader_path)) {
    require_once $autoloader_path;
}

// Load PSR-4 autoloader
$psr4_autoloader_path = __DIR__ . '/../src/Autoload.php';
if (file_exists($psr4_autoloader_path)) {
    require_once $psr4_autoloader_path;
}

// ---- Hook Bindings ----

// Enqueue frontend assets
add_action('wp_enqueue_scripts', function () {
    if (!is_checkout() && !is_cart() && !is_woocommerce()) {
        return;
    }

    $plugin_url = plugin_dir_url(dirname(__DIR__));

    // Load CSS for styling
    wp_enqueue_style(
        'wc-nfse-gateway',
        $plugin_url . 'assets/css/nfse.css',
        array(),
        WC_NFSE_VERSION
    );

    // Load JS if needed for checkout integration
    if (is_checkout()) {
        wp_enqueue_script(
            'wc-nfse-checkout-js',
            $plugin_url . 'assets/js/checkout.js',
            array('jquery'),
            WC_NFSE_VERSION,
            true
        );
    }
});

// Enqueue admin assets
add_action('admin_enqueue_scripts', function ($hook) {
    // Only load on relevant admin pages
    if (!in_array($hook, array('woocommerce_page_wc-settings', 'toplevel_page_wc-nfse'))) {
        return;
    }

    $plugin_url = plugin_dir_url(dirname(__DIR__));

    // Admin styles
    wp_enqueue_style(
        'wc-nfse-admin-css',
        $plugin_url . 'assets/css/admin.css',
        array(),
        WC_NFSE_VERSION
    );

    // Admin scripts
    wp_enqueue_script(
        'wc-nfse-admin-js',
        $plugin_url . 'assets/js/admin.js',
        array('jquery'),
        WC_NFSE_VERSION,
        true
    );
});

// Load plugin textdomain
add_action('plugins_loaded', function () {
    load_plugin_textdomain('wc-nfse', false, dirname(plugin_dir_path(__FILE__)) . '/languages/');
});

// Initialize NFSE when WooCommerce is active
add_action('woocommerce_loaded', function () {
    // Add debug logging to identify initialization path
    error_log('[NFSE DEBUG] woocommerce_loaded hook fired. Starting NFSE initialization...');

    // Initialize PSR-4 bootstrap
    error_log('[NFSE DEBUG] Performing PSR-4 initialization');
    \CloudXM\NFSe\Bootstrap\Plugin::init();

    // CRITICAL: Add admin menu registration for PSR-4 path
    if (is_admin()) {
        // Load admin classes
        require_once WC_NFSE_PLUGIN_PATH . 'includes/admin/class-wc-nfse-admin-settings.php';
        require_once WC_NFSE_PLUGIN_PATH . 'includes/admin/class-wc-nfse-admin-orders.php';
        require_once WC_NFSE_PLUGIN_PATH . 'includes/admin/class-wc-nfse-admin.php';

        // Instantiate admin class - its constructor will properly register the admin_menu hook
        new WC_NFSe_Admin();
    }

    // Log successful PSR-4 initialization
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
        $logger->debug('NFSe PSR-4 initialized in bootstrap', [
            'psr4_namespace' => 'CloudXM\\NFSe',
            'bootstrap_class' => 'CloudXM\\NFSe\\Bootstrap\\Plugin',
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown'
        ]);
    }

    error_log('[NFSE DEBUG] NFSE initialization completed');
});

// Register cron schedules for NFSE operations - moved to Bootstrap\Plugin::init()
add_action('init', function () {
    // Cron schedules are now handled in Bootstrap\Plugin::init() when PSR-4 is available
    // This keeps backwards compatibility for environments without PSR-4
});

// AJAX handlers
add_action('wp_ajax_wc_nfse_test_connection', function () {
    try {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'wc-nfse'));
        }

        check_ajax_referer('wc_nfse_admin', 'nonce');

        // Use PSR-4 API client via Factory
        $api_client = \CloudXM\NFSe\Bootstrap\Factories::apiClient();
        $result = $api_client->testConnection();

        wp_send_json_success(array(
            'message' => __('Connection test successful!', 'wc-nfse'),
            'details' => $result
        ));
    } catch (\Exception $e) {
        $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();

        $logger->error('AJAX connection test failed', array(
            'error_message' => $e->getMessage()
        ));

        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
});

// Certificate management AJAX
add_action('wp_ajax_wc_nfse_upload_certificate', function () {
    // Use PSR-4 services via Factory
    $certificate_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
    $certificate_manager->uploadCertificate();
});

add_action('wp_ajax_wc_nfse_test_certificate', function () {
    // Use PSR-4 services via Factory
    $certificate_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
    $certificate_manager->testCertificate();
});

add_action('wp_ajax_wc_nfse_delete_certificate', function () {
    // Use PSR-4 services via Factory
    $certificate_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
    $certificate_manager->deleteCertificate();
});

add_action('wp_ajax_wc_nfse_activate_certificate', function () {
    // Use PSR-4 services via Factory
    $certificate_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
    $certificate_manager->activateCertificate();
});

add_action('wp_ajax_wc_nfse_validate_certificate', function () {
    // Use PSR-4 services via Factory
    $certificate_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
    $certificate_manager->validateCertificate();
});

// ---- Development/debug hooks ----
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('nfse_log_entry', function ($log_entry) {
        // Additional logging for development
        if (isset($log_entry['correlation_id'])) {
            error_log('[NFSE DEBUG] ' . $log_entry['correlation_id'] . ' - ' . $log_entry['message']);
        }
    });
}

// PSR-4 availability log for debugging
if (defined('WP_DEBUG') && WP_DEBUG && class_exists('\\CloudXM\\NFSe\\Utilities\\Logger')) {
    $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
    $logger->debug('NFSe PSR-4 autoloader loaded successfully');
}

// ---- Plugin health checks ----
add_action('wp_ajax_health_check_nfse', function () {
    try {
        $health_data = array(
            'version' => WC_NFSE_VERSION,
            'wp_version' => get_bloginfo('version'),
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'unknown',
            'php_version' => PHP_VERSION,
            'psr4_available' => class_exists('\\CloudXM\\NFSe\\Utilities\\Logger'),
            'composer_autoloader' => false, // PSR-4 autoloader not used in this setup
            'certificate_dir' => defined('WC_NFSE_CERTIFICATES_DIR') ? is_writable(WC_NFSE_CERTIFICATES_DIR) : false,
            'logs_dir' => defined('WC_NFSE_LOGS_DIR') ? is_writable(WC_NFSE_LOGS_DIR) : false,
        );

        wp_send_json_success($health_data);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});
