<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Persistence;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration Runner Class for NFSE Plugin
 *
 * Handles database migration execution with proper error handling and rollback
 * Follows CloudXM unified migration pattern
 *
 * @package WooCommerce NFSE Plugin
 * @since 2.0.0
 */

/**
 * NFSE Migration Runner
 *
 * Uses dedicated migrations table for reliable state tracking
 * (Production-ready approach - follows Marlim Gateway pattern)
 */
class MigrationRunner
{

    /**
     * Migrations table name - dedicated, production-ready
     */
    const MIGRATIONS_TABLE = 'cloudxm_nfse_migrations';

    const MIGRATION_PREFIX = 'nfse_migration_';

    const MIGRATION_LOG = 'nfse_db_migration_log';

    /**
     * Logger instance
     *
     * @var \CloudXM\NFSe\Utilities\Logger
     */
    private $logger;

    /**
     * WordPress database instance
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Migrations directory path
     *
     * @var string
     */
    private $migrations_path;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
        $this->migrations_path = WC_NFSE_PLUGIN_PATH . 'includes/database/migrations/';
    }

    /**
     * Run all pending migrations
     *
     * @return array Results of migration execution
     */
    public function runMigrations()
    {
        // Verify migrations table exists (created in migration 000)
        if (!$this->ensureMigrationsTableExists()) {
            return array(
                'success' => false,
                'message' => 'NFSE Migration system not initialized - migrations table is missing',
                'migrations' => array(),
                'errors' => array('Migrations table not found'),
            );
        }

        $results = array(
            'success' => true,
            'message' => 'All migrations completed successfully',
            'migrations' => array(),
            'errors' => array(),
        );

        try {
            // Get current batch number for this batch of migrations
            $batch_number = $this->getNextBatchNumber();

            $migration_files = $this->getPendingMigrations();

            if (empty($migration_files)) {
                $results['message'] = 'No pending migrations found';
                return $results;
            }

            foreach ($migration_files as $migration_file) {
                $migration_result = $this->runSingleMigration($migration_file, $batch_number);
                $results['migrations'][] = $migration_result;

                if (!$migration_result['success']) {
                    $results['success'] = false;
                    $results['errors'][] = $migration_result['error'];
                    // Continue with other migrations - don't stop on single failure
                }
            }

            if ($results['success']) {
                $results['message'] = sprintf(
                    'Successfully executed %d migrations',
                    count($results['migrations'])
                );
            } else {
                $results['message'] = sprintf(
                    'Executed %d migrations with %d errors',
                    count($results['migrations']),
                    count($results['errors'])
                );
            }
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['message'] = 'Migration execution failed: ' . $e->getMessage();
            $results['errors'][] = $e->getMessage();

            $this->logger->error('Migration execution failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Get list of pending migrations
     *
     * @return array Array of migration file paths
     */
    private function getPendingMigrations()
    {
        if (!is_dir($this->migrations_path)) {
            $this->logger->error('NFSE Migrations path does not exist', ['path' => $this->migrations_path]);
            return array();
        }

        $migration_files = glob($this->migrations_path . '*.php');
        $this->logger->info('NFSE Migration files discovered', ['count' => count($migration_files)]);
        $pending_migrations = array();

        foreach ($migration_files as $migration_file) {
            $migration_name = $this->getMigrationName($migration_file);
            $this->logger->debug('Checking migration status', ['migration' => $migration_name]);

            if (!$this->isMigrationExecuted($migration_name)) {
                $this->logger->debug('Migration marked as pending', ['migration' => $migration_name]);
                $pending_migrations[] = $migration_file;
            } else {
                $this->logger->debug('Migration already executed, skipping', ['migration' => $migration_name]);
            }
        }

        // Sort migrations by filename to ensure proper execution order
        sort($pending_migrations);
        $this->logger->info('Pending migrations identified', ['count' => count($pending_migrations)]);

        return $pending_migrations;
    }

    /**
     * Run a single migration file
     *
     * @param string $migration_file Path to migration file
     * @param int $batch_number Current batch number
     * @return array Migration execution result
     */
    private function runSingleMigration($migration_file, $batch_number)
    {
        $migration_name = $this->getMigrationName($migration_file);
        $start_time = microtime(true);

        // Initialize global variable that migration files use
        global $migration_execution_result;
        $migration_execution_result = array(
            'name' => $migration_name,
            'file' => basename($migration_file),
            'success' => false,
            'execution_time' => 0,
            'error' => null,
        );

        try {
            // Capture any output from the migration
            ob_start();

            // Include the migration file - this will execute the migration's run() method
            include $migration_file;

            $output = ob_get_clean();

            // CRITICAL PRODUCTION FIX: Only record as successful if migration reports success
            if ($migration_execution_result['success']) {
                // Mark migration as successful in dedicated table - only if migration succeeded
                try {
                    $this->recordMigrationInTable($migration_name, $batch_number);
                    $this->logger->info('NFSE Migration successfully recorded in database', [
                        'migration' => $migration_name,
                        'batch' => $batch_number,
                        'method' => 'dedicated_migrations_table'
                    ]);
                } catch (\Exception $e) {
                    // If marking fails, migration still succeeded but log the issue
                    $migration_execution_result['marking_warning'] = $e->getMessage();
                    $this->logger->warning('NFSE Migration tracking failed, but migration executed', [
                        'migration' => $migration_name,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                // MIGRATION FAILED - DO NOT RECORD IN DATABASE
                $this->logger->error('NFSE Migration failed - NOT recorded in database', [
                    'migration' => $migration_name,
                    'error' => $migration_execution_result['error'],
                    'status' => 'FAILED'
                ]);

                // Ensure error is propagated to caller
                $migration_execution_result['success'] = false;
                $migration_execution_result['fail_reason'] = 'NFSE Migration reported failure - database operation not completed';
            }
            $migration_execution_result['execution_time'] = microtime(true) - $start_time;

            if (!empty($output)) {
                $migration_execution_result['output'] = $output;
            }

            $this->logger->info('NFSE Migration executed successfully', ['migration' => $migration_name, 'execution_time' => round($migration_execution_result['execution_time'], 4)]);
        } catch (\Exception $e) {
            // Clean up output buffer if it exists
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $migration_execution_result['error'] = $e->getMessage();
            $migration_execution_result['execution_time'] = microtime(true) - $start_time;

            $this->logger->error('NFSE Migration execution failed', [
                'migration' => $migration_name,
                'error' => $e->getMessage(),
                'execution_time' => round($migration_execution_result['execution_time'], 4)
            ]);
        } catch (Error $e) {
            // Handle PHP 7+ Error objects (like the scalar/array error)
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $migration_execution_result['error'] = 'PHP Error: ' . $e->getMessage();
            $migration_execution_result['execution_time'] = microtime(true) - $start_time;

            $this->logger->error('NFSE Migration execution failed with PHP Error', [
                'migration' => $migration_name,
                'error' => $e->getMessage(),
                'execution_time' => round($migration_execution_result['execution_time'], 4)
            ]);
        }

        return $migration_execution_result;
    }

    /**
     * Get migration name from file path
     *
     * @param string $migration_file Path to migration file
     * @return string Migration name
     */
    private function getMigrationName($migration_file)
    {
        return basename($migration_file, '.php');
    }

    /**
     * Check if migration has been executed using dedicated table
     *
     * @param string $migration_name Migration name
     * @return bool True if executed, false otherwise
     */
    private function isMigrationExecuted($migration_name)
    {
        // Use dedicated migrations table - PRODUCTION-RELIABLE
        $table_name = $this->wpdb->prefix . self::MIGRATIONS_TABLE;

        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE migration = %s",
            $migration_name
        ));

        return ($result > 0);
    }

    /**
     * Ensure the migrations tracking table exists
     *
     * @return bool True if table exists or was created
     */
    private function ensureMigrationsTableExists()
    {
        $table_name = $this->wpdb->prefix . self::MIGRATIONS_TABLE;

        // Check if table exists
        if ($this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )) === $table_name) {
            return true;
        }

        // Table doesn't exist - create it now (bootstrap case)
        $charset_collate = $this->wpdb->get_charset_collate();
        $migrations_sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INT(11) UNSIGNED NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            migration_version VARCHAR(100) NULL,
            migration_class VARCHAR(255) NULL,
            description TEXT NULL,
            execution_time DECIMAL(10,4) NULL,
            status ENUM('success','failed','rolled_back') NOT NULL DEFAULT 'success',
            error_message TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY migration_batch_unique (migration, batch),
            UNIQUE KEY migration_version_unique (migration, migration_version),
            KEY idx_migration (migration),
            KEY idx_batch (batch),
            KEY idx_migration_version (migration_version),
            KEY idx_status (status),
            KEY idx_executed_at (executed_at)
        ) {$charset_collate};";

        // Execute table creation using dbDelta for safety
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($migrations_sql);

        // Verify table was created
        if ($this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )) === $table_name) {
            $this->logger->info('NFSE Migrations table created during bootstrap', [
                'table' => $table_name
            ]);
            return true;
        }

        // Table creation failed
        $this->logger->error('Failed to create NFSE migrations table', [
            'expected_table' => $table_name,
            'note' => 'Migration system cannot initialize'
        ]);

        return false;
    }

    /**
     * Get the next batch number for grouping migrations
     *
     * @return int Next batch number
     */
    private function getNextBatchNumber()
    {
        $table_name = $this->wpdb->prefix . self::MIGRATIONS_TABLE;

        $result = $this->wpdb->get_var(
            "SELECT MAX(batch) FROM {$table_name}"
        );

        return ($result === null) ? 1 : $result + 1;
    }

    /**
     * Record migration execution in dedicated table
     *
     * @param string $migration_name Migration name
     * @param int $batch_number Batch number
     */
    private function recordMigrationInTable($migration_name, $batch_number)
    {
        $table_name = $this->wpdb->prefix . self::MIGRATIONS_TABLE;

        // Get migration execution details from global
        global $migration_execution_result;
        $version = $migration_execution_result['version'] ?? '1.0.0';
        $description = $migration_execution_result['description'] ?? $migration_name;
        $execution_time = $migration_execution_result['execution_time'] ?? 0;
        $status = $migration_execution_result['success'] ? 'success' : 'failed';
        $error_message = $migration_execution_result['error'] ?? null;

        // Simple, atomic operation - no complex fallbacks needed
        $result = $this->wpdb->insert(
            $table_name,
            array(
                'migration' => $migration_name,
                'batch' => $batch_number,
                'executed_at' => current_time('mysql'),
                'migration_version' => $version,
                'migration_class' => get_class($this), // Or just leave empty
                'description' => $description,
                'execution_time' => $execution_time,
                'status' => $status,
                'error_message' => $error_message,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s')
        );

        if ($result === false) {
            // If primary method fails, provide useful feedback
            $error = $this->wpdb->last_error;
            throw new \Exception("Failed to record NFSE migration $migration_name: $error");
        }

        $this->logger->debug('NFSE Migration recorded in table', [
            'migration' => $migration_name,
            'batch' => $batch_number,
            'method' => 'dedicated_migrations_table'
        ]);
    }

    /**
     * Get migration history
     *
     * @return array Migration history
     */
    public function getMigrationHistory()
    {
        return get_option(self::MIGRATION_LOG, array());
    }

    /**
     * Get list of executed migrations from dedicated table
     *
     * PRODUCTION-RELIABLE: Uses dedicated migrations table instead of WordPress options
     *
     * @return array Executed migrations with details
     */
    public function getExecutedMigrations()
    {
        // Use dedicated migrations table for production reliability
        $table_name = $this->wpdb->prefix . self::MIGRATIONS_TABLE;
        $executed_migrations = array();

        // Get all executed migrations from dedicated table
        $migrations = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT migration, batch, executed_at
                    FROM {$table_name}
                    WHERE 1=%d
                    ORDER BY executed_at, migration",
                1
            )
        );

        foreach ($migrations as $migration_record) {
            $executed_migrations[] = array(
                'name' => $migration_record->migration,
                'executed_at' => $migration_record->executed_at,
                'batch' => $migration_record->batch,
                'version' => 'Production', // Modern migration system
            );
        }

        return $executed_migrations;
    }

    /**
     * Create a new migration file template
     *
     * @param string $migration_name Migration name
     * @param string $description Migration description
     * @return array Creation result
     */
    public function createMigration($migration_name, $description = '')
    {
        if (!is_dir($this->migrations_path)) {
            wp_mkdir_p($this->migrations_path);
        }

        // Generate migration filename with timestamp
        $timestamp = date('Y_m_d_His');
        $filename = sprintf('%03d_%s_%s.php', $this->getNextMigrationNumber(), $timestamp, $migration_name);
        $filepath = $this->migrations_path . $filename;

        if (file_exists($filepath)) {
            return array(
                'success' => false,
                'message' => 'Migration file already exists: ' . $filename,
            );
        }

        $template = $this->getMigrationTemplate($migration_name, $description);

        if (file_put_contents($filepath, $template) !== false) {
            return array(
                'success' => true,
                'message' => 'Migration created successfully',
                'filename' => $filename,
                'filepath' => $filepath,
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to create migration file',
            );
        }
    }

    /**
     * Get next migration number
     *
     * @return int Next migration number
     */
    private function getNextMigrationNumber()
    {
        $existing_files = glob($this->migrations_path . '*.php');
        $max_number = 0;

        foreach ($existing_files as $file) {
            $filename = basename($file);
            if (preg_match('/^(\d{3})_/', $filename, $matches)) {
                $max_number = max($max_number, (int) $matches[1]);
            }
        }

        return $max_number + 1;
    }

    /**
     * Get migration template
     *
     * @param string $migration_name Migration name
     * @param string $description Migration description
     * @return string Migration template
     */
    private function getMigrationTemplate($migration_name, $description)
    {
        $template = '<?php
/**
 * NFSE Migration: ' . ucfirst(str_replace('_', ' ', $migration_name)) . '
 *
 * ' . $description . '
 *
 * @package WooCommerce NFSE Plugin
 * @since 2.0.0
 */

if (!defined(\'ABSPATH\')) {
    exit;
}

global $wpdb;

// Initialize migration result
global $migration_execution_result;
$migration_execution_result = [
    "success" => true,
    "error" => null,
    "description" => "' . $description . '"
];

try {
    // Add your migration code here
    // Example:
    // $table_name = $wpdb->prefix . \'wc_nfse_new_table\';
    // $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN new_column VARCHAR(255) NULL");

    $migration_execution_result["success"] = true;

} catch (\Exception $e) {
    $migration_execution_result["success"] = false;
    $migration_execution_result["error"] = $e->getMessage();
}
';

        return $template;
    }
}
