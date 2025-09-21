<?php
/**
 * Migration: Create NFSe Migrations Table
 *
 * Creates the cloudxm_nfse_migrations table to track executed migrations
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

    // Create migrations table
    $migrations_table = $table_prefix . 'migrations';
    $migrations_sql = "CREATE TABLE {$migrations_table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        migration varchar(255) NOT NULL,
        batch int(11) UNSIGNED NOT NULL,
        executed_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY migration_batch_unique (migration, batch),
        KEY idx_migration (migration),
        KEY idx_batch (batch)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($migrations_sql);

    Logger::getInstance()->info('Migration 000 completed successfully', [
        'migration' => '000',
        'version' => '1.0.0',
        'operation' => 'create_nfse_migrations_table',
        'timing' => date('Y-m-d H:i:s'),
        'table' => $migrations_table
    ]);

    // Set global result for MigrationRunner
    global $migration_execution_result;
    $migration_execution_result['success'] = true;

} catch (\Exception $e) {
    Logger::getInstance()->error('Migration 000 failed: ' . $e->getMessage(), [
        'migration' => '000',
        'version' => '1.0.0',
        'operation' => 'create_nfse_migrations_table',
        'timing' => date('Y-m-d H:i:s')
    ]);

    // Set global result for MigrationRunner
    global $migration_execution_result;
    $migration_execution_result['success'] = false;
    $migration_execution_result['error'] = 'Migration 000 failed: ' . $e->getMessage();

    throw $e;
}