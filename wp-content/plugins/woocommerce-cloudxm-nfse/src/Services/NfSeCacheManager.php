<?php
/**
 * NFSe Cache Manager Service
 *
 * Advanced caching system for municipal parameters and API data
 *
 * @package CloudXM\NFSe\Services
 */

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Utilities\Logger;
use Exception;

/**
 * Class NfSeCacheManager
 *
 * Handles caching of municipal parameters, tax rates, and API responses
 * for optimized NFSe integration with Brazilian municipal systems
 */
class NfSeCacheManager
{
    /**
     * Logger instance
     */
    private $logger;

    /**
     * Cache group prefix
     */
    private string $cacheGroup = 'wc_nfse';

    /**
     * Default expiration time (1 hour)
     */
    private int $defaultExpiration = 3600;

    /**
     * Cache statistics
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }

    /**
     * Get cached data
     */
    public function get(string $key, ?string $group = null): mixed
    {
        $fullKey = $this->getCacheKey($key, $group);

        $cachedData = wp_cache_get($fullKey, $this->cacheGroup);

        if ($cachedData !== false) {
            $this->stats['hits']++;
            $this->logger->debug('Cache hit', ['key' => $fullKey]);

            // Check if data has expiration and is still valid
            if (is_array($cachedData) && isset($cachedData['expires_at'])) {
                if (time() > $cachedData['expires_at']) {
                    $this->delete($key, $group);
                    $this->stats['misses']++;
                    return false;
                }
                return $cachedData['data'];
            }

            return $cachedData;
        }

        $this->stats['misses']++;
        $this->logger->debug('Cache miss', ['key' => $fullKey]);
        return false;
    }

    /**
     * Set cached data
     */
    public function set(string $key, mixed $data, ?int $expiration = null, ?string $group = null): bool
    {
        $fullKey = $this->getCacheKey($key, $group);
        $expiration = $expiration ?? $this->defaultExpiration;

        // Prepare cache data with expiration
        $cacheData = [
            'data' => $data,
            'created_at' => time(),
            'expires_at' => time() + $expiration
        ];

        $result = wp_cache_set($fullKey, $cacheData, $this->cacheGroup, $expiration);

        if ($result) {
            $this->stats['sets']++;
            $this->logger->debug('Cache set', [
                'key' => $fullKey,
                'expiration' => $expiration,
                'size' => $this->getDataSize($data)
            ]);
        } else {
            $this->logger->warning('Falha ao definir cache', ['key' => $fullKey]);
        }

        return $result;
    }

    /**
     * Delete cached data
     */
    public function delete(string $key, ?string $group = null): bool
    {
        $fullKey = $this->getCacheKey($key, $group);

        $result = wp_cache_delete($fullKey, $this->cacheGroup);

        if ($result) {
            $this->stats['deletes']++;
            $this->logger->debug('Cache deleted', ['key' => $fullKey]);
        }

        return $result;
    }

    /**
     * Flush cache group
     */
    public function flushGroup(?string $group = null): bool
    {
        $groupKey = $group ?? 'default';

        // WordPress doesn't have a direct way to flush by group
        // We'll use a versioning approach
        $versionKey = $this->cacheGroup . '_version_' . $groupKey;
        $currentVersion = wp_cache_get($versionKey, $this->cacheGroup);

        if ($currentVersion === false) {
            $currentVersion = 1;
        } else {
            $currentVersion++;
        }

        wp_cache_set($versionKey, $currentVersion, $this->cacheGroup, 0);

        $this->logger->info('Cache group flushed', [
            'group' => $groupKey,
            'new_version' => $currentVersion
        ]);

        return true;
    }

    /**
     * Get cache key with versioning
     */
    private function getCacheKey(string $key, ?string $group = null): string
    {
        $groupKey = $group ?? 'default';
        $versionKey = $this->cacheGroup . '_version_' . $groupKey;

        $version = wp_cache_get($versionKey, $this->cacheGroup);
        if ($version === false) {
            $version = 1;
            wp_cache_set($versionKey, $version, $this->cacheGroup, 0);
        }

        return $this->cacheGroup . '_' . $groupKey . '_v' . $version . '_' . md5($key);
    }

    /**
     * Cache municipal parameters
     */
    public function cacheMunicipalParameters(string $municipalityCode, array $parameters, int $expiration = 86400): bool
    {
        $key = 'municipal_params_' . $municipalityCode;
        return $this->set($key, $parameters, $expiration, 'municipal');
    }

    /**
     * Get cached municipal parameters
     */
    public function getMunicipalParameters(string $municipalityCode): mixed
    {
        $key = 'municipal_params_' . $municipalityCode;
        return $this->get($key, 'municipal');
    }

    /**
     * Cache ISS tax rate
     */
    public function cacheIssTaxRate(string $municipalityCode, string $serviceCode, float $rate, int $expiration = 604800): bool
    {
        $key = 'iss_rate_' . $municipalityCode . '_' . $serviceCode;
        return $this->set($key, $rate, $expiration, 'tax_rates');
    }

    /**
     * Get cached ISS tax rate
     */
    public function getIssTaxRate(string $municipalityCode, string $serviceCode): mixed
    {
        $key = 'iss_rate_' . $municipalityCode . '_' . $serviceCode;
        return $this->get($key, 'tax_rates');
    }

    /**
     * Cache NFS-e query result
     */
    public function cacheNfseQuery(string $accessKey, array $result, int $expiration = 3600): bool
    {
        $key = 'nfse_query_' . md5($accessKey);
        return $this->set($key, $result, $expiration, 'nfse_queries');
    }

    /**
     * Get cached NFS-e query result
     */
    public function getNfseQuery(string $accessKey): mixed
    {
        $key = 'nfse_query_' . md5($accessKey);
        return $this->get($key, 'nfse_queries');
    }

    /**
     * Cache API response
     */
    public function cacheApiResponse(string $endpoint, array $params, array $response, int $expiration = 1800): bool
    {
        $key = 'api_response_' . md5($endpoint . serialize($params));
        return $this->set($key, $response, $expiration, 'api_responses');
    }

    /**
     * Get cached API response
     */
    public function getApiResponse(string $endpoint, array $params): mixed
    {
        $key = 'api_response_' . md5($endpoint . serialize($params));
        return $this->get($key, 'api_responses');
    }

    /**
     * Warm up cache with common data
     */
    public function warmUpCache(array $municipalityCodes = []): int
    {
        $this->logger->info('Iniciando aquecimento do cache');

        $apiClient = new NfSeApiClient();
        $warmedItems = 0;

        foreach ($municipalityCodes as $code) {
            try {
                // Warm up municipal parameters
                $params = $apiClient->getMunicipalParameters($code);
                if ($params['success']) {
                    $this->cacheMunicipalParameters($code, $params['data']);
                    $warmedItems++;
                }

                // Warm up common service codes ISS rates
                $commonServiceCodes = ['01.01', '01.02', '01.03', '01.04', '01.05'];

                foreach ($commonServiceCodes as $serviceCode) {
                    $rate = $apiClient->getIssTaxRate($code, $serviceCode);
                    if ($rate['success']) {
                        $this->cacheIssTaxRate($code, $serviceCode, $rate['data']);
                        $warmedItems++;
                    }
                }

                // Small delay to avoid overwhelming the API
                usleep(100000); // 100ms

            } catch (Exception $e) {
                $this->logger->warning('Erro no aquecimento do cache para município ' . $code, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Aquecimento do cache concluído', [
            'municipalities' => count($municipalityCodes),
            'warmed_items' => $warmedItems
        ]);

        return $warmedItems;
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        $totalRequests = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $totalRequests > 0 ? ($this->stats['hits'] / $totalRequests) * 100 : 0;

        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'sets' => $this->stats['sets'],
            'deletes' => $this->stats['deletes'],
            'total_requests' => $totalRequests,
            'hit_rate' => round($hitRate, 2),
            'cache_info' => $this->getCacheInfo()
        ];
    }

    /**
     * Get cache information
     */
    private function getCacheInfo(): array
    {
        global $wp_object_cache;

        $info = [
            'type' => 'WordPress Object Cache',
            'persistent' => false,
            'groups' => []
        ];

        // Check if using persistent cache
        if (wp_using_ext_object_cache()) {
            $info['persistent'] = true;

            if (class_exists('Redis')) {
                $info['type'] = 'Redis';
            } elseif (class_exists('Memcached')) {
                $info['type'] = 'Memcached';
            } elseif (function_exists('apcu_fetch')) {
                $info['type'] = 'APCu';
            }
        }

        return $info;
    }

    /**
     * Clean expired cache entries
     */
    public function cleanExpiredEntries(): int
    {
        // This is a simplified cleanup - in a real implementation,
        // you'd need to track all cache keys and check their expiration

        $cleaned = 0;
        $groups = ['municipal', 'tax_rates', 'nfse_queries', 'api_responses'];

        foreach ($groups as $group) {
            // Force version increment to effectively "clean" the group
            $this->flushGroup($group);
            $cleaned++;
        }

        $this->logger->info('Limpeza de cache concluída', [
            'groups_cleaned' => $cleaned
        ]);

        return $cleaned;
    }

    /**
     * Get data size for logging
     */
    private function getDataSize(mixed $data): int
    {
        return strlen(serialize($data));
    }

    /**
     * Export cache data for debugging
     */
    public function exportCacheData(?string $group = null): array
    {
        // This would export cache data for debugging purposes
        // Implementation would depend on the specific cache backend

        return [
            'message' => 'Cache export not implemented for current backend',
            'group' => $group,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Import cache data
     */
    public function importCacheData(array $data, ?string $group = null): int
    {
        $imported = 0;

        foreach ($data as $key => $value) {
            if ($this->set($key, $value, null, $group)) {
                $imported++;
            }
        }

        $this->logger->info('Importação de cache concluída', [
            'imported_items' => $imported,
            'group' => $group
        ]);

        return $imported;
    }

    /**
     * Test cache functionality
     */
    public function testCache(): array
    {
        $testKey = 'cache_test_' . uniqid();
        $testData = [
            'timestamp' => time(),
            'test_string' => 'Hello, World!',
            'test_array' => [1, 2, 3, 4, 5],
            'test_object' => (object) ['property' => 'value']
        ];

        $results = [];

        // Test set
        $setResult = $this->set($testKey, $testData, 60);
        $results['set'] = $setResult;

        // Test get
        $getResult = $this->get($testKey);
        $results['get'] = ($getResult !== false);
        $results['data_integrity'] = ($getResult === $testData);

        // Test delete
        $deleteResult = $this->delete($testKey);
        $results['delete'] = $deleteResult;

        // Test get after delete
        $getAfterDelete = $this->get($testKey);
        $results['get_after_delete'] = ($getAfterDelete === false);

        // Test expiration
        $expireKey = 'cache_expire_test_' . uniqid();
        $this->set($expireKey, 'expire_test', 1); // 1 second expiration
        sleep(2);
        $expiredResult = $this->get($expireKey);
        $results['expiration'] = ($expiredResult === false);

        $overallSuccess = array_reduce($results, function($carry, $result): bool {
            return $carry && $result;
        }, true);

        return [
            'success' => $overallSuccess,
            'results' => $results,
            'statistics' => $this->getStatistics()
        ];
    }

    /**
     * Get cache recommendations
     */
    public function getRecommendations(): array
    {
        $stats = $this->getStatistics();
        $recommendations = [];

        // Hit rate recommendations
        if ($stats['hit_rate'] < 50) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => __('Taxa de acerto do cache baixa. Considere aumentar os tempos de expiração.', 'wc-nfse')
            ];
        } elseif ($stats['hit_rate'] > 90) {
            $recommendations[] = [
                'type' => 'success',
                'message' => __('Excelente taxa de acerto do cache!', 'wc-nfse')
            ];
        }

        // Persistent cache recommendation
        if (!$stats['cache_info']['persistent']) {
            $recommendations[] = [
                'type' => 'info',
                'message' => __('Considere usar um cache persistente (Redis, Memcached) para melhor performance.', 'wc-nfse')
            ];
        }

        // Usage recommendations
        if ($stats['total_requests'] > 1000 && $stats['hit_rate'] < 70) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => __('Alto volume de requisições com baixa taxa de acerto. Revise a estratégia de cache.', 'wc-nfse')
            ];
        }

        return $recommendations;
    }

    /**
     * Schedule cache maintenance
     */
    public function scheduleMaintenance(): void
    {
        if (!wp_next_scheduled('wc_nfse_cache_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wc_nfse_cache_maintenance');
            $this->logger->info('Manutenção de cache agendada');
        }
    }

    /**
     * Unschedule cache maintenance
     */
    public function unscheduleMaintenance(): void
    {
        wp_clear_scheduled_hook('wc_nfse_cache_maintenance');
        $this->logger->info('Manutenção de cache desagendada');
    }
}