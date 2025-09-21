<?php
/**
 * Migration: Create NFSe Certificates Table
 *
 * Creates the cloudxm_nfse_certificates table for storing digital certificates
 *
 * @package WooCommerce_CloudXM_NFSE
 * @since 1.0.0
 */

use CloudXM\NFSe\Utilities\Logger;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

try {
    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = $wpdb->prefix . 'cloudxm_nfse_';

    // Create certificates table
    $certificates_table = $table_prefix . 'certificates';
    $certificates_sql = "CREATE TABLE {$certificates_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        file_path varchar(500) NOT NULL,
        file_size bigint(20) DEFAULT 0,
        password_hash varchar(255) NOT NULL,
        subject_name varchar(255) DEFAULT NULL,
        issuer_name varchar(255) DEFAULT NULL,
        serial_number varchar(100) DEFAULT NULL,
        valid_from datetime DEFAULT NULL,
        valid_to datetime DEFAULT NULL,
        certificate_version varchar(10) DEFAULT NULL,
        algorithm varchar(50) DEFAULT NULL,
        key_size int(4) DEFAULT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 0,
        last_used datetime DEFAULT NULL,
        usage_count bigint(20) DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_is_active (is_active),
        KEY idx_name (name),
        KEY idx_subject_name (subject_name),
        KEY idx_valid_to (valid_to),
        KEY idx_created_at (created_at)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($certificates_sql);

    Logger::getInstance()->info('Migration 003 completed successfully', [
        'migration' => '003',
        'version' => '1.0.0',
        'operation' => 'create_nfse_certificates_table',
        'timing' => date('Y-m-d H:i:s'),
        'table' => $certificates_table
    ]);

    // Set global result for MigrationRunner
    global $migration_execution_result;
    $migration_execution_result['success'] = true;

} catch (Exception $e) {
    Logger::getInstance()->error('Migration 003 failed: ' . $e->getMessage(), [
        'migration' => '003',
        'version' => '1.0.0',
        'operation' => 'create_nfse_certificates_table',
        'timing' => date('Y-m-d H:i:s')
    ]);

    // Set global result for MigrationRunner
    global $migration_execution_result;
    $migration_execution_result['success'] = false;
    $migration_execution_result['error'] = 'Migration 003 failed: ' . $e->getMessage();

    throw $e;
}