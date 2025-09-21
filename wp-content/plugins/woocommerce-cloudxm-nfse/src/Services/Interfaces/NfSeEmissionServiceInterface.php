<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Services\Interfaces;

use Exception;

/**
 * NFSe Emission Service Interface
 *
 * Defines the contract for NFSe emission processing operations
 */
interface NfSeEmissionServiceInterface
{
    /**
     * Process NFSe emission for an order
     *
     * @param int $orderId WooCommerce order ID
     * @param bool $forceReEmit Whether to force re-emission
     * @return array Emission result
     * @throws Exception When emission fails
     */
    public function processEmission(int $orderId, bool $forceReEmit = false): array;

    /**
     * Process multiple emissions in batch
     *
     * @param int[] $orderIds Array of order IDs
     * @param bool $forceReEmit Whether to force re-emission
     * @return array Batch processing results
     */
    public function processBatchEmission(array $orderIds, bool $forceReEmit = false): array;

    /**
     * Cancel an NFSe
     *
     * @param int $orderId WooCommerce order ID
     * @param string $cancellationReason Reason for cancellation
     * @return array Cancellation result
     * @throws Exception When cancellation fails
     */
    public function cancelNfse(int $orderId, string $cancellationReason): array;

    /**
     * Query NFSe status by order ID
     *
     * @param int $orderId WooCommerce order ID
     * @return array Status query result
     * @throws Exception When status query fails
     */
    public function queryNfseStatus(int $orderId): array;

    /**
     * Query NFSe status by access key
     *
     * @param string $accessKey NFSe access key
     * @return array Status query result
     * @throws Exception When status query fails
     */
    public function queryNfseStatusByAccessKey(string $accessKey): array;

    /**
     * Get emission by order ID
     *
     * @param int $orderId WooCommerce order ID
     * @return array|null Emission data or null if not found
     */
    public function getEmissionByOrder(int $orderId): ?array;

    /**
     * Get emission by ID
     *
     * @param int $emissionId Emission database ID
     * @return array|null Emission data or null if not found
     */
    public function getEmissionById(int $emissionId): ?array;

    /**
     * Get emission statistics
     *
     * @param string $period Period for statistics (7_days, 30_days, this_month, etc)
     * @return array Statistics data
     */
    public function getEmissionStatistics(string $period = '30_days'): array;

    /**
     * Download XML for an emission
     *
     * @param int $orderId WooCommerce order ID
     * @return string XML content
     * @throws Exception When XML is not available
     */
    public function downloadXml(int $orderId): string;

    /**
     * Retry failed emissions
     *
     * @param int $limit Maximum number of retries
     * @return array Retry results
     */
    public function retryFailedEmissions(int $limit = 10): array;

    /**
     * Validate if order can be processed for NFSe emission
     *
     * @param int $orderId WooCommerce order ID
     * @return array Validation result with details
     */
    public function validateEmissionPrerequisites(int $orderId): array;
}