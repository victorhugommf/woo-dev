<?php
/**
 * NFSe PSR-4 Autoloader
 *
 * Simple PSR-4 autoloader for CloudXM NFSe plugin
 *
 * @package WooCommerce_CloudXM_NFSe
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register PSR-4 autoloader for CloudXM\NFSe namespace
 */
spl_autoload_register(function ($class) {
    // Prefix to check for
    $prefix = 'CloudXM\\NFSe\\';

    // Base directory for the namespace prefix
    $base_dir = plugin_dir_path(__DIR__) . 'src/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    // in the relative class name, append with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require_once $file;
    }
});