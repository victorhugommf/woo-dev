<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Services\Interfaces;

use Exception;

/**
 * NFSe Queue Service Interface
 *
 * Defines the contract for queue processing operations
 */
interface NfSeQueueServiceInterface
{
    /**
     * Add item to emission queue
     *
     * @param int $orderId WooCommerce order ID
     * @param string $triggerType Trigger type
     * @param int $delay Delay in seconds
     * @param int $priority Queue priority (1-10, lower = higher priority)
     * @return int Queue item ID
     * @throws Exception When queue operation fails
     */
    public function addToQueue(int $orderId, string $triggerType, int $delay = 0, int $priority = 5): int;

    /**
     * Process pending queue items
     *
     * @param int $limit Maximum items to process
     * @return int Number of items processed
     */
    public function processQueue(int $limit = 10): int;

    /**
     * Get pending items count
     *
     * @return int Number of pending items
     */
    public function getPendingCount(): int;

    /**
     * Get processing items count
     *
     * @return int Number of items being processed
     */
    public function getProcessingCount(): int;

    /**
     * Get failed items count
     *
     * @return int Number of failed items
     */
    public function getFailedCount(): int;

    /**
     * Get queue statistics
     *
     * @return array Queue statistics
     */
    public function getQueueStatistics(): array;

    /**
     * Get queue items with pagination
     *
     * @param string|null $status Filter by status (pending, processing, completed, failed)
     * @param int $limit Maximum items to return
     * @param int $offset Pagination offset
     * @return array Queue items
     */
    public function getQueueItems(?string $status = null, int $limit = 50, int $offset = 0): array;

    /**
     * Remove item from queue
     *
     * @param int $itemId Queue item ID
     * @return bool Success status
     */
    public function removeFromQueue(int $itemId): bool;

    /**
     * Clear completed items older than specified days
     *
     * @param int $olderThanDays Age threshold in days
     * @return int Number of items cleared
     */
    public function clearCompletedItems(int $olderThanDays = 7): int;

    /**
     * Clear entire queue
     *
     * @return int Number of items cleared
     */
    public function clearQueue(): int;

    /**
     * Retry failed items
     *
     * @param int $limit Maximum items to retry
     * @return int Number of items retried
     */
    public function retryFailedItems(int $limit = 10): int;

    /**
     * Get queue item by ID
     *
     * @param int $itemId Queue item ID
     * @return array|null Queue item data or null if not found
     */
    public function getQueueItem(int $itemId): ?array;

    /**
     * Get queue items by order ID
     *
     * @param int $orderId WooCommerce order ID
     * @return array Queue items for the order
     */
    public function getQueueItemsByOrder(int $orderId): array;

    /**
     * Update queue item
     *
     * @param int $itemId Queue item ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function updateQueueItem(int $itemId, array $data): bool;

    /**
     * Pause queue processing
     */
    public function pauseQueue(): void;

    /**
     * Resume queue processing
     */
    public function resumeQueue(): void;

    /**
     * Check if queue processing is paused
     *
     * @return bool Pause status
     */
    public function isQueuePaused(): bool;

    /**
     * Get queue health status
     *
     * @return array Health status information
     */
    public function getQueueHealth(): array;

    /**
     * Reset stuck items (items stuck in processing state too long)
     *
     * @param int $stuckThresholdSeconds Time in seconds to consider an item stuck
     * @return int Number of items reset
     */
    public function resetStuckItems(int $stuckThresholdSeconds = 1800): int; // 30 minutes

    /**
     * Get average processing time
     *
     * @param int $periodInDays Period for calculation in days
     * @return float Average processing time in seconds
     */
    public function getAverageProcessingTime(int $periodInDays = 7): float;

    /**
     * Mark queue item as failed
     *
     * @param int $itemId Queue item ID
     * @param string $errorMessage Error message
     * @return bool Success status
     */
    public function markQueueItemFailed(int $itemId, string $errorMessage): bool;

    /**
     * Mark queue item as completed
     *
     * @param int $itemId Queue item ID
     * @param array $resultData Result data from processing
     * @return bool Success status
     */
    public function markQueueItemCompleted(int $itemId, array $resultData): bool;
}