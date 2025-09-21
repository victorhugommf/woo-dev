<?php
/**
 * Migration: Create NFSe Emissions Table
 *
 * Creates the cloudxm_nfse_emissions table to store NFSe emission records
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

    // Create emissions table
    $emissions_table = $table_prefix . 'emissions';
    $emissions_sql = "CREATE TABLE {$emissions_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        access_key varchar(44) DEFAULT NULL,
        dps_number varchar(20) DEFAULT NULL,
        status enum('pending','processing','success','error','cancelled') NOT NULL DEFAULT 'pending',
        xml_data longtext DEFAULT NULL,
        response_data longtext DEFAULT NULL,
        error_message text DEFAULT NULL,
        error_code varchar(50) DEFAULT NULL,
        emission_date datetime DEFAULT NULL,
        processing_attempts int(11) DEFAULT 0,
        last_attempt_at datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_order_id (order_id),
        KEY idx_access_key (access_key),
        KEY idx_status (status),
        KEY idx_dps_number (dps_number),
        KEY idx_emission_date (emission_date),
        KEY idx_created_at (created_at)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($emissions_sql);

    Logger::getInstance()->info('Migration 001 completed successfully', [
        'migration' => '001',
        'version' => '1.0.0',
        'operation' => 'create_nfse_emissions_table',
        'timing' => date('Y-m-d H:i:s'),
        'table' => $emissions_table
    ]);

    // Set global result for MigrationRunner
    global $migration_execution_result;
    $migration_execution_result['success'] = true;

} catch (Exception $e) {
    Logger::getInstance()->error('Migration 001 failed: ' . $e->getMessage(), [
        'migration' => '001',
        'version' => '1.0.0',
        'operation' => 'create_nfse_emissions_table',
        'timing' => date('Y-m-d H:i:s')
    ]);

    // Set global result for MigrationRunner
    global $migration_execution_result;
    $migration_execution_result['success'] = false;
    $migration_execution_result['error'] = 'Migration 001 failed: ' . $e->getMessage();

    throw $e;
}