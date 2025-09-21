<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Services\Interfaces\NfSeQueueServiceInterface;
use CloudXM\NFSe\Utilities\Logger;
use CloudXM\NFSe\Services\NfSeSettings;
use CloudXM\NFSe\Persistence\NfSeQueueRepository;
use CloudXM\NFSe\Services\Interfaces\NfSeEmissionServiceInterface;
use Exception;

/**
 * NFSe Queue Service
 *
 * Handles queue processing operations for NFSe emissions
 */
class NfSeQueueService implements NfSeQueueServiceInterface
{
    private Logger $logger;
    private NfSeSettings $settings;
    private NfSeQueueRepository $queueRepository;
    private NfSeEmissionServiceInterface $emissionService;

    public function __construct(
        Logger $logger,
        NfSeSettings $settings,
        NfSeQueueRepositoryInterface $queueRepository,
        NfSeEmissionServiceInterface $emissionService
    ) {
        $this->logger = $logger;
        $this->settings = $settings;
        $this->queueRepository = $queueRepository;
        $this->emissionService = $emissionService;
    }

    /**
     * {@inheritDoc}
     */
    public function addToQueue(int $orderId, string $triggerType, int $delay = 0, int $priority = 5): int
    {
        try {
            // Check for existing queued item
            $existingItems = $this->queueRepository->findByOrderId($orderId);
            foreach ($existingItems as $item) {
                if (in_array($item['status'], ['pending', 'processing'])) {
                    $this->logger->info('Order already in queue', [
                        'order_id' => $orderId,
                        'existing_item_id' => $item['id']
                    ]);
                    return $item['id'];
                }
            }

            $scheduledAt = date('Y-m-d H:i:s', time() + $delay);

            $queueItem = [
                'order_id' => $orderId,
                'trigger_type' => $triggerType,
                'priority' => max(1, min(10, $priority)),
                'status' => 'pending',
                'scheduled_at' => $scheduledAt,
                'attempts' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];

            $queueItemId = $this->queueRepository->save($queueItem);

            $this->logger->info('Item added to queue', [
                'queue_item_id' => $queueItemId,
                'order_id' => $orderId,
                'trigger_type' => $triggerType,
                'scheduled_at' => $scheduledAt
            ]);

            return $queueItemId;

        } catch (Exception $e) {
            $this->logger->error('Failed to add item to queue', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function processQueue(int $limit = 10): int
    {
        if ($this->isQueuePaused()) {
            $this->logger->info('Queue processing is paused');
            return 0;
        }

        try {
            $pendingItems = $this->queueRepository->findPendingItems($limit);
            $processedCount = 0;

            foreach ($pendingItems as $item) {
                try {
                    $this->processQueueItem($item);
                    $processedCount++;
                } catch (Exception $e) {
                    $this->logger->error('Error processing queue item', [
                        'queue_item_id' => $item['id'],
                        'order_id' => $item['order_id'],
                        'error' => $e->getMessage()
                    ]);

                    // Mark as failed if max attempts reached
                    if ($item['attempts'] >= 3) {
                        $this->markQueueItemFailed($item['id'], $e->getMessage());
                    } else {
                        // Reset for retry with exponential backoff
                        $retryDelay = pow(2, $item['attempts']) * 900; // 15 min, 30 min, 1 hour
                        $this->queueRepository->update($item['id'], [
                            'status' => 'pending',
                            'scheduled_at' => date('Y-m-d H:i:s', time() + $retryDelay),
                            'attempts' => $item['attempts'] + 1,
                            'error_message' => $e->getMessage()
                        ]);
                    }
                }
            }

            if ($processedCount > 0) {
                $this->logger->info('Queue processing completed', [
                    'processed_count' => $processedCount
                ]);
            }

            return $processedCount;

        } catch (Exception $e) {
            $this->logger->error('Queue processing failed', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingCount(): int
    {
        return $this->queueRepository->count(['status' => 'pending']);
    }

    /**
     * {@inheritDoc}
     */
    public function getProcessingCount(): int
    {
        return $this->queueRepository->count(['status' => 'processing']);
    }

    /**
     * {@inheritDoc}
     */
    public function getFailedCount(): int
    {
        return $this->queueRepository->count(['status' => 'failed']);
    }

    /**
     * {@inheritDoc}
     */
    public function getQueueStatistics(): array
    {
        return $this->queueRepository->getStatistics();
    }

    /**
     * {@inheritDoc}
     */
    public function getQueueItems(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $conditions = $status ? ['status' => $status] : [];
        return $this->queueRepository->findAll($conditions, $limit, $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function removeFromQueue(int $itemId): bool
    {
        return $this->queueRepository->delete($itemId);
    }

    /**
     * {@inheritDoc}
     */
    public function clearCompletedItems(int $olderThanDays = 7): int
    {
        return $this->queueRepository->deleteOldItems(['completed'], $olderThanDays);
    }

    /**
     * {@inheritDoc}
     */
    public function clearQueue(): int
    {
        $allItems = $this->queueRepository->findAll();
        $deletedCount = 0;

        foreach ($allItems as $item) {
            if ($this->queueRepository->delete($item['id'])) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * {@inheritDoc}
     */
    public function retryFailedItems(int $limit = 10): int
    {
        $failedItems = $this->queueRepository->findAll(['status' => 'failed'], $limit);
        $retriedCount = 0;

        foreach ($failedItems as $item) {
            if ($this->queueRepository->update($item['id'], [
                'status' => 'pending',
                'scheduled_at' => current_time('mysql'),
                'attempts' => 0,
                'error_message' => null
            ])) {
                $retriedCount++;
            }
        }

        return $retriedCount;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueueItem(int $itemId): ?array
    {
        return $this->queueRepository->find($itemId);
    }

    /**
     * {@inheritDoc}
     */
    public function getQueueItemsByOrder(int $orderId): array
    {
        return $this->queueRepository->findByOrderId($orderId);
    }

    /**
     * {@inheritDoc}
     */
    public function updateQueueItem(int $itemId, array $data): bool
    {
        $data['updated_at'] = current_time('mysql');
        return $this->queueRepository->update($itemId, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function pauseQueue(): void
    {
        update_option('wc_nfse_queue_paused', true);
        $this->logger->info('Queue processing paused');
    }

    /**
     * {@inheritDoc}
     */
    public function resumeQueue(): void
    {
        delete_option('wc_nfse_queue_paused');
        $this->logger->info('Queue processing resumed');
    }

    /**
     * {@inheritDoc}
     */
    public function isQueuePaused(): bool
    {
        return get_option('wc_nfse_queue_paused', false);
    }

    /**
     * {@inheritDoc}
     */
    public function getQueueHealth(): array
    {
        $stats = $this->getQueueStatistics();

        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => []
        ];

        // Check for stuck items
        $stuckCount = $this->queueRepository->countStuckItems();
        if ($stuckCount > 0) {
            $health['status'] = 'warning';
            $health['issues'][] = sprintf('%d itens presos no processamento', $stuckCount);
            $health['recommendations'][] = 'Considere limpar itens presos';
        }

        // Check for high failure rate
        if ($stats['total_items'] > 0) {
            $failureRate = ($stats['failed_items'] / $stats['total_items']) * 100;
            if ($failureRate > 50) {
                $health['status'] = 'critical';
                $health['issues'][] = sprintf('Taxa de falha alta: %.1f%%', $failureRate);
                $health['recommendations'][] = 'Verificar configurações e logs de erro';
            }
        }

        return $health;
    }

    /**
     * {@inheritDoc}
     */
    public function resetStuckItems(int $stuckThresholdSeconds = 1800): int
    {
        return $this->queueRepository->resetStuckItems($stuckThresholdSeconds);
    }

    /**
     * {@inheritDoc}
     */
    public function getAverageProcessingTime(int $periodInDays = 7): float
    {
        // This would typically require tracking processing start/end times
        // For now, return a default value
        return 30.0; // 30 seconds average
    }

    /**
     * {@inheritDoc}
     */
    public function markQueueItemFailed(int $itemId, string $errorMessage): bool
    {
        return $this->queueRepository->update($itemId, [
            'status' => 'failed',
            'error_message' => $errorMessage,
            'failed_at' => current_time('mysql')
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function markQueueItemCompleted(int $itemId, array $resultData): bool
    {
        return $this->queueRepository->update($itemId, [
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'result_data' => json_encode($resultData)
        ]);
    }

    /**
     * Process a single queue item
     */
    private function processQueueItem(array $item): void
    {
        // Mark as processing
        $this->queueRepository->update($item['id'], [
            'status' => 'processing',
            'started_at' => current_time('mysql'),
            'attempts' => $item['attempts'] + 1
        ]);

        // Process emission
        $result = $this->emissionService->processEmission($item['order_id']);

        // Mark as completed
        if ($result['success']) {
            $this->markQueueItemCompleted($item['id'], $result);
        } else {
            throw new Exception($result['message'] ?? 'Processing failed');
        }
    }
}