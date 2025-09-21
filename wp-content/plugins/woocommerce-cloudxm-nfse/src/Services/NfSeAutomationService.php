<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Services\Interfaces\NfSeAutomationServiceInterface;
use CloudXM\NFSe\Utilities\Logger;
use CloudXM\NFSe\Services\NfSeSettings;
use CloudXM\NFSe\Services\Interfaces\NfSeEmissionServiceInterface;
use CloudXM\NFSe\Services\Interfaces\NfSeQueueServiceInterface;
use Exception;

/**
 * NFSe Automation Service
 *
 * Handles automated NFSe emission based on triggers and business rules
 */
class NfSeAutomationService implements NfSeAutomationServiceInterface
{
    private Logger $logger;
    private NfSeSettings $settings;
    private NfSeEmissionServiceInterface $emissionService;
    private NfSeQueueServiceInterface $queueService;

    public function __construct(
        Logger $logger,
        NfSeSettings $settings,
        NfSeEmissionServiceInterface $emissionService,
        NfSeQueueServiceInterface $queueService
    ) {
        $this->logger = $logger;
        $this->settings = $settings;
        $this->emissionService = $emissionService;
        $this->queueService = $queueService;

        $this->registerHooks();
    }

    /**
     * {@inheritDoc}
     */
    public function processEmissionQueue(int $limit = 10): int
    {
        $this->logger->info('Processing emission queue', ['limit' => $limit]);
        return $this->queueService->processQueue($limit);
    }

    /**
     * {@inheritDoc}
     */
    public function scheduleEmission(int $orderId, string $triggerType = 'manual', int $delay = 0, int $priority = 5): int
    {
        if (!$this->settings->isAutoEmitEnabled()) {
            $this->logger->info('Auto emission is disabled, skipping scheduling', [
                'order_id' => $orderId,
                'trigger_type' => $triggerType
            ]);
            throw new Exception(__('Auto emission is disabled', 'wc-nfse'));
        }

        // Validate that order should be processed
        $shouldProcess = $this->shouldProcessOrder($orderId);
        if (!$shouldProcess['should_process']) {
            $this->logger->info('Order should not be processed', [
                'order_id' => $orderId,
                'reasons' => $shouldProcess['reasons']
            ]);
            throw new Exception(__('Order does not meet processing criteria', 'wc-nfse'));
        }

        return $this->queueService->addToQueue($orderId, $triggerType, $delay, $priority);
    }

    /**
     * {@inheritDoc}
     */
    public function shouldProcessOrder(int $orderId): array
    {
        $order = wc_get_order($orderId);
        if (!$order) {
            return $this->createResult(false, ['Order not found']);
        }

        // Check basic prerequisites
        $prerequisites = $this->emissionService->validateEmissionPrerequisites($orderId);
        if (!$prerequisites['valid']) {
            $errors = array_merge(['Prerequisites not met'], $prerequisites['errors']);
            return $this->createResult(false, $errors);
        }

        // Check automation rules
        $automationRulesResult = $this->checkAutomationRules($order);
        if (!$automationRulesResult['valid']) {
            return $this->createResult(false, $automationRulesResult['reasons']);
        }

        return $this->createResult(true);
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingEmissionsCount(): int
    {
        return $this->queueService->getPendingCount();
    }

    /**
     * {@inheritDoc}
     */
    public function clearQueue(): int
    {
        $this->logger->info('Clearing emission queue');
        return $this->queueService->clearQueue();
    }

    /**
     * {@inheritDoc}
     */
    public function pauseAutomation(): void
    {
        update_option('wc_nfse_automation_paused', true);
        $this->logger->info('Automation processing paused');
    }

    /**
     * {@inheritDoc}
     */
    public function resumeAutomation(): void
    {
        delete_option('wc_nfse_automation_paused', false);
        $this->logger->info('Automation processing resumed');
    }

    /**
     * {@inheritDoc}
     */
    public function isAutomationPaused(): bool
    {
        return get_option('wc_nfse_automation_paused', false);
    }

    /**
     * {@inheritDoc}
     */
    public function enableAutomation(): void
    {
        $this->settings->set('auto_emit_enabled', 'yes');
        $this->settings->save();
        $this->registerHooks();
        $this->logger->info('Automation enabled');
    }

    /**
     * {@inheritDoc}
     */
    public function disableAutomation(): void
    {
        $this->settings->set('auto_emit_enabled', 'no');
        $this->settings->save();
        $this->logger->info('Automation disabled');
    }

    /**
     * {@inheritDoc}
     */
    public function testAutomation(int $orderId): array
    {
        try {
            $shouldProcess = $this->shouldProcessOrder($orderId);
            $order = wc_get_order($orderId);

            $testResults = [
                'order_id' => $orderId,
                'order_status' => $order ? $order->get_status() : 'not_found',
                'auto_emit_enabled' => $this->settings->isAutoEmitEnabled(),
                'should_process' => $shouldProcess['should_process'],
                'reasons' => $shouldProcess['reasons'],
                'queue_items' => $this->queueService->getQueueItemsByOrder($orderId),
                'prerequisites_check' => $this->emissionService->validateEmissionPrerequisites($orderId),
                'timestamp' => current_time('mysql')
            ];

            $this->logger->info('Automation test completed', [
                'order_id' => $orderId,
                'result' => $testResults
            ]);

            return $testResults;

        } catch (Exception $e) {
            $this->logger->error('Automation test failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'timestamp' => current_time('mysql')
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAutomationStatistics(): array
    {
        $queueStats = $this->queueService->getQueueStatistics();
        $emissionStats = $this->emissionService->getEmissionStatistics('30_days');

        return [
            'automation_enabled' => $this->settings->isAutoEmitEnabled(),
            'automation_paused' => $this->isAutomationPaused(),
            'pending_emissions' => $this->getPendingEmissionsCount(),
            'queue_stats' => $queueStats,
            'emission_stats' => $emissionStats,
            'business_hours' => [
                'enabled' => $this->settings->get('auto_emit_business_hours_enabled', 'no') === 'yes',
                'current_status' => $this->settings->isWithinBusinessHours() ? 'within' : 'outside',
                'start_time' => $this->settings->get('auto_emit_business_hours_start', '08:00'),
                'end_time' => $this->settings->get('auto_emit_business_hours_end', '18:00')
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getQueueHealth(): array
    {
        return $this->queueService->getQueueHealth();
    }

    /**
     * {@inheritDoc}
     */
    public function resetStuckItems(): int
    {
        $this->logger->info('Resetting stuck queue items');
        return $this->queueService->resetStuckItems();
    }

    /**
     * {@inheritDoc}
     */
    public function retryFailedEmissions(int $limit = 10): array
    {
        $this->logger->info('Retrying failed emissions', ['limit' => $limit]);
        return $this->emissionService->retryFailedEmissions($limit);
    }

    /**
     * {@inheritDoc}
     */
    public function registerHooks(): void
    {
        if (!wp_doing_ajax() && !wp_doing_cron()) {
            // Only register in frontend/context where it's safe
            add_action('woocommerce_order_status_processing', [$this, 'onOrderStatusChange'], 10, 1);
            add_action('woocommerce_order_status_completed', [$this, 'onOrderStatusChange'], 10, 1);
            add_action('woocommerce_payment_complete', [$this, 'onPaymentComplete'], 10, 1);
            add_action('woocommerce_checkout_order_processed', [$this, 'onCheckoutOrderProcessed'], 10, 3);

            add_action('wc_nfse_trigger_emission', [$this, 'triggerEmission'], 10, 2);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function unregisterHooks(): void
    {
        remove_action('woocommerce_order_status_processing', [$this, 'onOrderStatusChange']);
        remove_action('woocommerce_order_status_completed', [$this, 'onOrderStatusChange']);
        remove_action('woocommerce_payment_complete', [$this, 'onPaymentComplete']);
        remove_action('woocommerce_checkout_order_processed', [$this, 'onCheckoutOrderProcessed']);
        remove_action('wc_nfse_trigger_emission', [$this, 'triggerEmission']);
    }

    /**
     * {@inheritDoc}
     */
    public function onOrderStatusChange(int $orderId, ?string $fromStatus = null, string $toStatus): void
    {
        if (!$this->settings->isAutoEmitEnabled() || $this->isAutomationPaused()) {
            return;
        }

        $triggerConditions = $this->settings->getAutoEmissionConfig()['triggers'];

        if (in_array('order_' . $toStatus, $triggerConditions)) {
            try {
                $this->scheduleEmission($orderId, 'order_' . $toStatus);
            } catch (Exception $e) {
                $this->logger->warning('Failed to schedule emission on order status change', [
                    'order_id' => $orderId,
                    'to_status' => $toStatus,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function onPaymentComplete(int $orderId): void
    {
        if (!$this->settings->isAutoEmitEnabled() || $this->isAutomationPaused()) {
            return;
        }

        $triggerConditions = $this->settings->getAutoEmissionConfig()['triggers'];

        if (in_array('payment_complete', $triggerConditions)) {
            try {
                $this->scheduleEmission($orderId, 'payment_complete');
            } catch (Exception $e) {
                $this->logger->warning('Failed to schedule emission on payment complete', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle checkout order processed
     */
    public function onCheckoutOrderProcessed(int $orderId, array $postedData, $order): void
    {
        if (!$this->settings->isAutoEmitEnabled() || $this->isAutomationPaused()) {
            return;
        }

        // Check for immediate payment methods
        $immediatePaymentMethods = $this->settings->get('immediate_emit_payment_methods', []);

        if ($order instanceof \WC_Order) {
            $paymentMethod = $order->get_payment_method();
            if (in_array($paymentMethod, $immediatePaymentMethods)) {
                try {
                    $this->scheduleEmission($orderId, 'checkout_order_processed', 0); // Immediate
                } catch (Exception $e) {
                    $this->logger->warning('Failed to schedule immediate emission', [
                        'order_id' => $orderId,
                        'payment_method' => $paymentMethod,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Custom trigger for emission
     */
    public function triggerEmission(int $orderId, string $triggerType = 'manual'): void
    {
        try {
            $this->scheduleEmission($orderId, $triggerType);
        } catch (Exception $e) {
            $this->logger->warning('Failed to trigger emission', [
                'order_id' => $orderId,
                'trigger_type' => $triggerType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check automation-specific rules
     */
    private function checkAutomationRules(\WC_Order $order): array
    {
        $reasons = [];

        // Check business hours
        if (!$this->settings->isWithinBusinessHours()) {
            $reasons[] = 'Outside business hours';
        }

        // Check order total limits
        $orderTotal = $order->get_total();
        $minTotal = $this->settings->get('auto_emit_min_total', 0);
        if ($orderTotal < $minTotal) {
            $reasons[] = 'Order total below minimum threshold';
        }

        // Check customer type requirements
        $customerType = $this->getCustomerType($order);
        $customerTypes = $this->settings->getAutoEmissionConfig()['customer_types'];
        if (!in_array('all', $customerTypes) && !in_array($customerType, $customerTypes)) {
            $reasons[] = 'Customer type does not match requirements';
        }

        // Check payment method restrictions
        $paymentMethod = $order->get_payment_method();
        $excludedPaymentMethods = $this->settings->getAutoEmissionConfig()['excluded_payment_methods'];
        if (in_array($paymentMethod, $excludedPaymentMethods)) {
            $reasons[] = 'Payment method is excluded';
        }

        return [
            'valid' => empty($reasons),
            'reasons' => $reasons
        ];
    }

    /**
     * Get customer type from order
     */
    private function getCustomerType(\WC_Order $order): string
    {
        $billingCompany = $order->get_billing_company();
        $cnpj = $order->get_meta('_billing_cnpj');

        if (!empty($billingCompany) || !empty($cnpj)) {
            return 'business';
        }

        return 'individual';
    }

    /**
     * Create standard result array
     */
    private function createResult(bool $shouldProcess, array $reasons = []): array
    {
        return [
            'should_process' => $shouldProcess,
            'reasons' => $reasons
        ];
    }

    /**
     * Check if automation should be running
     */
    private function isAutomationActive(): bool
    {
        return $this->settings->isAutoEmitEnabled() && !$this->isAutomationPaused();
    }
}