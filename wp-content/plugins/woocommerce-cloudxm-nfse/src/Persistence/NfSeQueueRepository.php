<?php

namespace CloudXM\NFSe\Persistence;

use CloudXM\NFSe\Utilities\Logger;

/**
 * NFSe Queue Repository
 *
 * Handles database operations for NFSe processing queue
 */
class NfSeQueueRepository implements RepositoryInterface
{
    private Logger $logger;
    private string $tableName;

    public function __construct(Logger $logger, string $tablePrefix = 'cloudxm_nfse')
    {
        global $wpdb;
        $this->logger = $logger;
        $this->tableName = $wpdb->prefix . $tablePrefix . '_queue';
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d",
            $id
        ), ARRAY_A);

        return $row ?: null;
    }

    /**
     * @inheritDoc
     */
    public function findAll(array $conditions = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $whereClause = '';
        $whereParams = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                if (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '%s'));
                    $whereParts[] = $wpdb->prepare("{$field} IN ({$placeholders})", ...$value);
                } else {
                    $whereParts[] = $wpdb->prepare("{$field} = %s", $value);
                }
            }
            $whereClause = ' WHERE ' . implode(' AND ', $whereParts);

            // Flatten arrays for whereParams
            foreach ($conditions as $value) {
                if (is_array($value)) {
                    $whereParams = array_merge($whereParams, $value);
                } else {
                    $whereParams[] = $value;
                }
            }
        }

        $params = array_merge($whereParams, [$limit, $offset]);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tableName}{$whereClause} ORDER BY priority DESC, scheduled_at ASC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * @inheritDoc
     */
    public function findOneBy(array $conditions): ?array
    {
        $results = $this->findAll($conditions, 1);
        return $results[0] ?? null;
    }

    /**
     * Find pending items ready for processing
     */
    public function findPendingItems(int $limit = 10): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE status = 'pending'
             AND scheduled_at <= NOW()
             ORDER BY priority DESC, scheduled_at ASC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * Find stuck processing items
     */
    public function findStuckItems(int $minutesThreshold = 30): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE status = 'processing'
             AND started_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $minutesThreshold
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * Find items by order ID
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->findAll(['order_id' => $orderId], 100); // Reasonable limit
    }

    /**
     * Check if order is already in queue
     */
    public function isOrderQueued(int $orderId): bool
    {
        $existing = $this->findOneBy([
            'order_id' => $orderId,
            'status' => ['pending', 'processing']
        ]);

        return !empty($existing);
    }

    /**
     * @inheritDoc
     */
    public function save(array $data): int
    {
        global $wpdb;

        $insertData = $this->prepareInsertData($data);
        $result = $wpdb->insert(
            $this->tableName,
            $insertData,
            $this->getFormatArray($insertData)
        );

        if ($result === false) {
            $this->logger->error('Failed to save queue item', [
                'error' => $wpdb->last_error,
                'table' => $this->tableName,
                'order_id' => $data['order_id'] ?? null
            ]);
            throw new \Exception('Failed to save queue item: ' . $wpdb->last_error);
        }

        $newId = $wpdb->insert_id;

        $this->logger->info('Queue item saved successfully', [
            'id' => $newId,
            'order_id' => $data['order_id'] ?? null,
            'trigger_type' => $data['trigger_type'] ?? 'unknown'
        ]);

        return $newId;
    }

    /**
     * @inheritDoc
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $updateData = $this->prepareUpdateData($data);
        $updateData['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->tableName,
            $updateData,
            ['id' => $id],
            $this->getFormatArray($updateData),
            ['%d']
        );

        if ($result === false) {
            $this->logger->error('Failed to update queue item', [
                'id' => $id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }

        $this->logger->info('Queue item updated successfully', [
            'id' => $id,
            'affected_rows' => $result,
            'new_status' => $data['status'] ?? null
        ]);

        return true;
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(array $ids, string $status): int
    {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = $wpdb->prepare(
            "UPDATE {$this->tableName}
             SET status = %s, updated_at = %s
             WHERE id IN ({$placeholders})",
            array_merge([$status, current_time('mysql')], $ids)
        );

        $result = $wpdb->query($query);

        if ($result !== false) {
            $this->logger->info('Bulk status update completed', [
                'ids_count' => count($ids),
                'new_status' => $status,
                'affected_rows' => $result
            ]);
        }

        return $result !== false ? $result : 0;
    }

    /**
     * Reset stuck items to pending
     */
    public function resetStuckItems(): int
    {
        return $this->bulkUpdateStatus(array_keys($this->findStuckItems()), 'pending');
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->tableName,
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error('Failed to delete queue item', [
                'id' => $id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }

        $this->logger->info('Queue item deleted successfully', [
            'id' => $id,
            'affected_rows' => $result
        ]);

        return $result > 0;
    }

    /**
     * @inheritDoc
     */
    public function count(array $conditions = []): int
    {
        global $wpdb;

        $whereClause = '';
        $whereParams = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = $wpdb->prepare("{$field} = %s", $value);
            }
            $whereClause = ' WHERE ' . implode(' AND ', $whereParts);
            $whereParams = array_values($conditions);
        }

        if (!empty($whereParams)) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableName}{$whereClause}",
                ...$whereParams
            ));
        } else {
            $result = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName}");
        }

        return (int) $result;
    }

    /**
     * Get queue statistics
     */
    public function getStatistics(int $periodInHours = 24): array
    {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_items,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_items,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_items,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_items,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_items,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_items,
                AVG(CASE WHEN status IN ('completed')
                         AND started_at IS NOT NULL
                         AND completed_at IS NOT NULL
                         THEN TIMESTAMPDIFF(SECOND, started_at, completed_at)
                         ELSE NULL END) as avg_processing_time,
                MAX(updated_at) as last_activity
            FROM {$this->tableName}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
        ", $periodInHours));

        if (!$stats) {
            return [
                'total_items' => 0,
                'pending_items' => 0,
                'processing_items' => 0,
                'completed_items' => 0,
                'failed_items' => 0,
                'cancelled_items' => 0,
                'success_rate' => 0,
                'avg_processing_time' => null,
                'last_activity' => null,
            ];
        }

        $total = (int) $stats->total_items;
        $completed = (int) $stats->completed_items;

        return [
            'total_items' => $total,
            'pending_items' => (int) $stats->pending_items,
            'processing_items' => (int) $stats->processing_items,
            'completed_items' => $completed,
            'failed_items' => (int) $stats->failed_items,
            'cancelled_items' => (int) $stats->cancelled_items,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'avg_processing_time' => $stats->avg_processing_time ? round((float) $stats->avg_processing_time, 2) : null,
            'last_activity' => $stats->last_activity,
        ];
    }

    /**
     * Get health status
     */
    public function getHealthStatus(): array
    {
        $stats = $this->getStatistics();
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => []
        ];

        // Check for high pending count
        if ($stats['pending_items'] > 100) {
            $health['status'] = $health['status'] === 'critical' ? 'critical' : 'warning';
            $health['issues'][] = sprintf('%d items pending', $stats['pending_items']);
            $health['recommendations'][] = 'Increase processing frequency or review failed items';
        }

        // Check for high failure rate
        if ($stats['total_items'] > 10 && $stats['success_rate'] < 80) {
            $health['status'] = 'critical';
            $health['issues'][] = sprintf('Low success rate: %.1f%%', $stats['success_rate']);
            $health['recommendations'][] = 'Review error patterns and service configuration';
        }

        // Check for stuck items
        $stuckItems = $this->findStuckItems();
        if (!empty($stuckItems)) {
            $health['status'] = $health['status'] === 'critical' ? 'critical' : 'warning';
            $health['issues'][] = sprintf('%d stuck processing items', count($stuckItems));
            $health['recommendations'][] = 'Reset stuck items manually or increase timeout';
        }

        return $health;
    }

    /**
     * Clear old items
     */
    public function clearOldItems(int $olderThanDays = 7): int
    {
        global $wpdb;

        $affected = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tableName}
             WHERE status IN ('completed', 'failed', 'cancelled')
             AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $olderThanDays
        ));

        if ($affected !== false) {
            $this->logger->info('Old queue items cleaned up', [
                'items_removed' => $affected,
                'older_than_days' => $olderThanDays
            ]);
        }

        return $affected !== false ? $affected : 0;
    }

    /**
     * Retry failed items with max attempts check
     */
    public function retryEligibleFailedItems(int $maxAttempts = 3, int $limit = 10): array
    {
        global $wpdb;

        $failedItems = $wpdb->get_results($wpdb->prepare("
            SELECT id, attempts FROM {$this->tableName}
            WHERE status = 'failed'
            AND attempts < %d
            ORDER BY updated_at DESC
            LIMIT %d
        ", $maxAttempts, $limit), ARRAY_A);

        $retriedIds = [];

        foreach ($failedItems as $item) {
            // Reset to pending with increased attempts
            $this->update($item['id'], [
                'status' => 'pending',
                'attempts' => $item['attempts'] + 1,
                'scheduled_at' => current_time('mysql'),
                'error_message' => null,
                'last_error_at' => null,
            ]);

            $retriedIds[] = $item['id'];
        }

        $this->logger->info('Failed items retried', [
            'items_retried' => count($retriedIds),
            'max_attempts' => $maxAttempts
        ]);

        return $retriedIds;
    }

    /**
     * Prepare insert data
     */
    private function prepareInsertData(array $data): array
    {
        $insertData = $data;

        // Set defaults
        $currentTime = current_time('mysql');
        $insertData['created_at'] = $insertData['created_at'] ?? $currentTime;
        $insertData['updated_at'] = $insertData['updated_at'] ?? $currentTime;
        $insertData['attempts'] = $insertData['attempts'] ?? 0;
        $insertData['scheduled_at'] = $insertData['scheduled_at'] ?? $currentTime;

        return $insertData;
    }

    /**
     * Prepare update data
     */
    private function prepareUpdateData(array $data): array
    {
        return $data; // No special processing needed
    }

    /**
     * Get format array for wpdb prepare
     */
    private function getFormatArray(array $data): array
    {
        $formats = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'order_id', 'priority', 'attempts'])) {
                $formats[] = '%d';
            } elseif (in_array($key, ['created_at', 'updated_at', 'scheduled_at', 'started_at', 'completed_at', 'failed_at', 'last_error_at'])) {
                $formats[] = '%s';
            } elseif (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
