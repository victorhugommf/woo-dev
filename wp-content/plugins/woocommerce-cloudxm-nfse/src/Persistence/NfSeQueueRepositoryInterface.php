<?php

namespace CloudXM\NFSe\Persistence;

/**
 * NFSe Queue Repository Interface
 */
interface NfSeQueueRepositoryInterface extends RepositoryInterface
{
    /**
     * Find by order ID
     *
     * @param int $orderId
     * @return array
     */
    public function findByOrderId(int $orderId): array;

    /**
     * Find pending items ready for processing
     *
     * @param int $limit
     * @return array
     */
    public function findPendingItems(int $limit = 10): array;

    /**
     * Find stuck items (processing too long)
     *
     * @param int $stuckThresholdSeconds
     * @return array
     */
    public function findStuckItems(int $stuckThresholdSeconds = 1800): array;

    /**
     * Get queue statistics
     *
     * @return array
     */
    public function getStatistics(): array;

    /**
     * Update multiple items
     *
     * @param array $items Array of [id => data] pairs
     * @return int Number of items updated
     */
    public function updateBatch(array $items): int;

    /**
     * Delete old items
     *
     * @param array $statuses Array of statuses to clean
     * @param int $olderThanDays Age threshold
     * @return int Number of items deleted
     */
    public function deleteOldItems(array $statuses = ['completed', 'failed'], int $olderThanDays = 7): int;

    /**
     * Reset stuck items
     *
     * @param int $stuckThresholdSeconds
     * @return int Number of items reset
     */
    public function resetStuckItems(int $stuckThresholdSeconds = 1800): int;
}