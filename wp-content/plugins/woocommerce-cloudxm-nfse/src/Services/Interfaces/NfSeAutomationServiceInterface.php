<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Services\Interfaces;

use Exception;

/**
 * NFSe Automation Service Interface
 *
 * Defines the contract for automated NFSe processing operations
 */
interface NfSeAutomationServiceInterface
{
    /**
     * Process pending emissions in the queue
     *
     * @param int $limit Maximum items to process
     * @return int Number of items processed
     */
    public function processEmissionQueue(int $limit = 10): int;

    /**
     * Add order to emission queue
     *
     * @param int $orderId WooCommerce order ID
     * @param string $triggerType Trigger type (order_processing, payment_complete, etc)
     * @param int $delay Delay in seconds
     * @param int $priority Queue priority
     * @return int Queue item ID
     * @throws Exception When queue operation fails
     */
    public function scheduleEmission(int $orderId, string $triggerType = 'manual', int $delay = 0, int $priority = 5): int;

    /**
     * Check if order should be processed for emission
     *
     * @param int $orderId WooCommerce order ID
     * @return array Validation result with decision details
     */
    public function shouldProcessOrder(int $orderId): array;

    /**
     * Get pending emissions count
     *
     * @return int Number of pending items
     */
    public function getPendingEmissionsCount(): int;

    /**
     * Clear the emission queue
     *
     * @return int Number of items cleared
     */
    public function clearQueue(): int;

    /**
     * Pause automation processing
     */
    public function pauseAutomation(): void;

    /**
     * Resume automation processing
     */
    public function resumeAutomation(): void;

    /**
     * Check if automation is currently paused
     *
     * @return bool Whether automation is paused
     */
    public function isAutomationPaused(): bool;

    /**
     * Enable automation
     */
    public function enableAutomation(): void;

    /**
     * Disable automation
     */
    public function disableAutomation(): void;

    /**
     * Test automation with a specific order
     *
     * @param int $orderId WooCommerce order ID
     * @return array Test results
     */
    public function testAutomation(int $orderId): array;

    /**
     * Get automation statistics
     *
     * @return array Statistics data
     */
    public function getAutomationStatistics(): array;

    /**
     * Get queue health status
     *
     * @return array Health status with issues and recommendations
     */
    public function getQueueHealth(): array;

    /**
     * Reset stuck queue items
     *
     * @return int Number of items reset
     */
    public function resetStuckItems(): int;

    /**
     * Retry failed emissions
     *
     * @param int $limit Maximum number of retries
     * @return array Retry results
     */
    public function retryFailedEmissions(int $limit = 10): array;

    /**
     * Register WordPress hooks and actions
     */
    public function registerHooks(): void;

    /**
     * Unregister WordPress hooks and actions
     */
    public function unregisterHooks(): void;

    /**
     * Handle order status change
     *
     * @param int $orderId WooCommerce order ID
     * @param string $fromStatus Previous status
     * @param string $toStatus New status
     */
    public function onOrderStatusChange(int $orderId, string $fromStatus, string $toStatus): void;

    /**
     * Handle payment completion
     *
     * @param int $orderId WooCommerce order ID
     */
    public function onPaymentComplete(int $orderId): void;
}