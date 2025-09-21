<?php

/**
 * NFSe Plugin Bootstrap Entry Point
 *
 * Minimal bootstrap that initializes PSR-4 classes safely.
 * Hook bindings remain in includes/bootstrap.php for backwards compatibility.
 *
 * @package CloudXM\NFSe\Bootstrap
 * @since 3.1.0
 */

namespace CloudXM\NFSe\Bootstrap;

/**
 * NFSe Bootstrap
 */
final class Plugin
{
    /**
     * Initialize PSR-4 bootstrap
     */
    public static function init(): void
    {
        // PSR-4 is available - log initialization if logger exists
        if (class_exists('\\CloudXM\\NFSe\\Utilities\\Logger')) {
            $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
            $logger->debug('NFSe PSR-4 bootstrap initialized', [
                'bootstrap_class' => 'CloudXM\\NFSe\\Bootstrap\\Plugin',
                'psr4_namespace' => 'CloudXM\\NFSe',
                'version' => defined('WC_NFSE_VERSION') ? WC_NFSE_VERSION : 'unknown'
            ]);
        }

        // Check and run pending database migrations
        self::checkAndRunMigrations();

        // Add custom cron intervals for NFSe operations
        self::registerCronSchedules();

        // Bootstrap complete - hook bindings handled in includes/bootstrap.php
    }

    /**
     * Register custom cron schedules for NFSe operations
     */
    private static function registerCronSchedules(): void
    {
        add_filter('cron_schedules', function ($schedules) {
            $schedules['nfse_hourly'] = array(
                'interval' => HOUR_IN_SECONDS,
                'display'  => __('Once Every Hour', 'wc-nfse')
            );
            $schedules['nfse_daily'] = array(
                'interval' => DAY_IN_SECONDS,
                'display'  => __('Once Daily', 'wc-nfse')
            );
            return $schedules;
        });
    }

    /**
     * Check and run pending database migrations
     */
    private static function checkAndRunMigrations(): void
    {
        // Only run migrations if MigrationRunner is available
        if (!class_exists('\\CloudXM\\NFSe\\Persistence\\MigrationRunner')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[NFSE DEBUG] MigrationRunner not available - skipping migration check');
            }
            return;
        }

        try {
            $migrationRunner = new \CloudXM\NFSe\Persistence\MigrationRunner();
            $results = $migrationRunner->runMigrations();

            // Log migration results
            if (class_exists('\\CloudXM\\NFSe\\Utilities\\Logger')) {
                $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();

                if ($results['success']) {
                    $logger->info('NFSE Migrations completed during plugin initialization', [
                        'migrations_executed' => count($results['migrations']),
                        'errors_count' => count($results['errors']),
                        'correlation_id' => uniqid('nfse-migration-', true)
                    ]);

                    // Log details for each migration
                    foreach ($results['migrations'] as $migration) {
                        $logger->debug('NFSE Migration executed', [
                            'migration' => $migration['name'],
                            'success' => $migration['success'],
                            'execution_time' => $migration['execution_time']
                        ]);
                    }
                } else {
                    $logger->error('NFSE Migrations failed during plugin initialization', [
                        'migrations_executed' => count($results['migrations']),
                        'errors_count' => count($results['errors']),
                        'errors' => $results['errors'],
                        'correlation_id' => uniqid('nfse-migration-fail-', true)
                    ]);
                }
            }

            // Log to standard error log for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[NFSE DEBUG] Migration check completed: ' . ($results['success'] ? 'SUCCESS' : 'FAILED') . ' - ' . count($results['migrations']) . ' migrations executed');
            }
        } catch (Exception $e) {
            // Ensure migration failures don't break plugin initialization
            if (class_exists('\\CloudXM\\NFSe\\Utilities\\Logger')) {
                $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
                $logger->error('NFSE Migration initialization failed', [
                    'error' => $e->getMessage(),
                    'correlation_id' => uniqid('nfse-migration-error-', true)
                ]);
            }

            error_log('[NFSE ERROR] Migration initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if a service is available
     */
    public static function hasService(string $service): bool
    {
        return match ($service) {
            'api_client' => class_exists('\\CloudXM\\NFSe\\Api\\ApiClient'),
            'certificate_manager' => class_exists('\\CloudXM\\NFSe\\Services\\NfSeCertificateManager'),
            'certificate_validator' => class_exists('\\CloudXM\\NFSe\\Services\\NfSeCertificateValidator'),
            'config' => class_exists('\\CloudXM\\NFSe\\Utilities\\Config'),
            'logger' => class_exists('\\CloudXM\\NFSe\\Utilities\\Logger'),
            default => false
        };
    }
}
