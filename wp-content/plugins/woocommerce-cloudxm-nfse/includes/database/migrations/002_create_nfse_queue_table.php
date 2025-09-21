<?php
/**
 * Migration: Create NFSe Queue Table
 *
 * Creates the cloudxm_nfse_queue table for processing NFSe emission queue
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

    // Create queue table
    $queue_table = $table_prefix . 'queue';
    $queue_sql = "CREATE TABLE {$queue_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        trigger_type varchar(50) NOT NULL,
        priority tinyint(2) NOT NULL DEFAULT 5,
        status enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
        scheduled_at datetime NOT NULL,
        started_at datetime DEFAULT NULL,
        completed_at datetime DEFAULT NULL,
        failed_at datetime DEFAULT NULL,
        attempts tinyint(2) NOT NULL DEFAULT 0,
        error_message text DEFAULT NULL,
        last_error_at datetime DEFAULT NULL,
        result_data longtext DEFAULT NULL,
        processed_by varchar(100) DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_order_id (order_id),
        KEY idx_status (status),
        KEY idx_trigger_type (trigger_type),
        KEY idx_priority (priority),
        KEY idx_scheduled_at (scheduled_at),
        KEY idx_created_at (created_at),
        UNIQUE KEY idx_order_unique (order_id, status)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($queue_sql);

    Logger::getInstance()->info('Migration 002 completed successfully', [
        'migration' => '002',
        'version' => '1.0.0',
        'operation' => 'create_nfse_queue_table',
        'timing' => date('Y-m-d H:i:s'),
        'table' => $queue_table
    ]);

    // Set global result for MigrationRunner
    global $migration_execution_result;
    $migration_execution_result['success'] = true;

} catch (Exception $e) {
    Logger::getInstance()->error('Migration 002 failed: ' . $e->getMessage(), [
        'migration' => '002',
        'version' => '1.0.0',
        'operation' => 'create_nfse_queue_table',
        'timing' => date('Y-m-d H:i:s')
    ]);

    // Set global result for MigrationRunner
    global $migration_execution_result;
    $migration_execution_result['success'] = false;
    $migration_execution_result['error'] = 'Migration 002 failed: ' . $e->getMessage();

    throw $e;
}