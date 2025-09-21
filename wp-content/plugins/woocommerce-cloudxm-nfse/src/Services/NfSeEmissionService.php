<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Services\Interfaces\NfSeEmissionServiceInterface;
use CloudXM\NFSe\Utilities\Logger;
use CloudXM\NFSe\Services\NfSeSettings;
use CloudXM\NFSe\Services\NfSeDpsGenerator;
use CloudXM\NFSe\Services\NfSeDigitalSigner;
use CloudXM\NFSe\Api\ApiClient;
use CloudXM\NFSe\Services\NfSeCertificateManager;
use CloudXM\NFSe\Persistence\NfSeEmissionRepository;
use CloudXM\NFSe\Services\NfSeRtcValidator;
use Exception;

/**
 * NFSe Emission Service
 *
 * Modern service for handling NFSe emission operations
 */
class NfSeEmissionService implements NfSeEmissionServiceInterface
{
    private Logger $logger;
    private NfSeSettings $settings;
    private NfSeDpsGenerator $dpsGenerator;
    private NfSeDigitalSigner $digitalSigner;
    private ApiClient $apiClient;
    private NfSeCertificateManager $certificateManager;
    private NfSeEmissionRepository $emissionRepository;
    private NfSeRtcValidator $rtcValidator;

    public function __construct(
        Logger $logger,
        NfSeSettings $settings,
        NfSeDpsGenerator $dpsGenerator,
        NfSeDigitalSigner $digitalSigner,
        ApiClient $apiClient,
        NfSeCertificateManager $certificateManager,
        NfSeEmissionRepository $emissionRepository,
        NfSeRtcValidator $rtcValidator
    ) {
        $this->logger = $logger;
        $this->settings = $settings;
        $this->dpsGenerator = $dpsGenerator;
        $this->digitalSigner = $digitalSigner;
        $this->apiClient = $apiClient;
        $this->certificateManager = $certificateManager;
        $this->emissionRepository = $emissionRepository;
        $this->rtcValidator = $rtcValidator;
    }

    /**
     * {@inheritDoc}
     */
    public function processEmission(int $orderId, bool $forceReEmit = false): array
    {
        try {
            $this->logger->info('Starting NFSe emission process', [
                'order_id' => $orderId,
                'force_reemit' => $forceReEmit
            ]);

            // Validate prerequisites
            $this->checkEmissionPrerequisites($orderId, $forceReEmit);

            // Generate DPS XML
            $dpsResult = $this->dpsGenerator->generateDpsXml($orderId);

            // Sign XML digitally
            $signedXml = $this->digitalSigner->signXml($dpsResult['xml']);

            //esse método não é da apiClient - olhar compressAndEncode() da classe NfSeCompressor
            // Compress XML for transmission
            // $compressedXml = $this->apiClient->compressXml($signedXml);

            // Submit to API
            $apiResult = $this->apiClient->submitDps($signedXml, $dpsResult['dps_data']);

            // Save emission record
            $emissionId = $this->saveEmissionRecord($orderId, $apiResult, $dpsResult, $signedXml);

            // Update order with NFSe information
            $this->updateOrder($orderId, $apiResult, $dpsResult);

            $this->logger->info('NFSe emission completed successfully', [
                'order_id' => $orderId,
                'emission_id' => $emissionId,
                'access_key' => $apiResult['access_key'] ?? null
            ]);

            return [
                'success' => true,
                'emission_id' => $emissionId,
                'access_key' => $apiResult['access_key'] ?? null,
                'dps_number' => $dpsResult['dps_number'],
                'protocol' => $apiResult['protocol'] ?? null,
                'message' => __('NFSe emitida com sucesso!', 'wc-nfse')
            ];
        } catch (Exception $e) {
            $this->logger->error('NFSe emission failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);

            // Save failed emission record
            try {
                $this->saveFailedEmissionRecord($orderId, $e);
            } catch (Exception $saveException) {
                $this->logger->error('Failed to save emission error record', [
                    'order_id' => $orderId,
                    'original_error' => $e->getMessage(),
                    'save_error' => $saveException->getMessage()
                ]);
            }

            // Add order note about failure
            $this->addOrderNote($orderId, sprintf(
                __('Erro na emissão da NFSe: %s', 'wc-nfse'),
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function processBatchEmission(array $orderIds, bool $forceReEmit = false): array
    {
        $results = [
            'total' => count($orderIds),
            'successful' => 0,
            'failed' => 0,
            'results' => []
        ];

        foreach ($orderIds as $orderId) {
            try {
                $result = $this->processEmission($orderId, $forceReEmit);
                $results['successful']++;
                $results['results'][] = [
                    'order_id' => $orderId,
                    'success' => true,
                    'access_key' => $result['access_key'] ?? null
                ];
            } catch (Exception $e) {
                $results['failed']++;
                $results['results'][] = [
                    'order_id' => $orderId,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->logger->info('Batch emission processing completed', [
            'total_processed' => $results['total'],
            'successful' => $results['successful'],
            'failed' => $results['failed']
        ]);

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function cancelNfse(int $orderId, string $cancellationReason): array
    {
        try {
            // Get emission record
            $emission = $this->emissionRepository->findByOrderId($orderId);

            if (!$emission) {
                throw new Exception(__('NFSe não encontrada.', 'wc-nfse'));
            }

            if ($emission['status'] !== 'success') {
                throw new Exception(__('NFSe não foi emitida ou já foi cancelada.', 'wc-nfse'));
            }

            if (empty($emission['access_key'])) {
                throw new Exception(__('Chave de acesso não disponível.', 'wc-nfse'));
            }

            // Submit cancellation to API
            $result = $this->apiClient->cancelNfse($emission['access_key'], $cancellationReason);

            if ($result['success']) {
                // Update emission record
                $this->emissionRepository->update($emission['id'], [
                    'status' => 'cancelled',
                    'cancellation_reason' => $cancellationReason,
                    'cancellation_date' => current_time('mysql')
                ]);

                // Add order note
                $this->addOrderNote($orderId, sprintf(
                    __('NFSe cancelada. Motivo: %s', 'wc-nfse'),
                    $cancellationReason
                ));

                $this->logger->info('NFSe cancelled successfully', [
                    'order_id' => $orderId,
                    'access_key' => $emission['access_key'],
                    'reason' => $cancellationReason
                ]);

                return [
                    'success' => true,
                    'message' => __('NFSe cancelada com sucesso.', 'wc-nfse')
                ];
            } else {
                throw new Exception($result['message']);
            }
        } catch (Exception $e) {
            $this->logger->error('NFSe cancellation failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function queryNfseStatus(int $orderId): array
    {
        try {
            $emission = $this->emissionRepository->findByOrderId($orderId);

            if (!$emission || empty($emission['access_key'])) {
                throw new Exception(__('NFSe não encontrada ou chave de acesso não disponível.', 'wc-nfse'));
            }

            $result = $this->apiClient->query_nfse_by_access_key($emission['access_key']);

            if ($result['success']) {
                // Update emission record with latest status
                $updateData = ['api_response' => json_encode($result['nfse_data'])];
                if (isset($result['nfse_data']['status'])) {
                    $updateData['status'] = $result['nfse_data']['status'];
                }
                $this->emissionRepository->update($emission['id'], $updateData);

                return [
                    'success' => true,
                    'status' => $result['status'] ?? $result['nfse_data']['status'] ?? 'unknown',
                    'nfse_data' => $result['nfse_data']
                ];
            } else {
                throw new Exception($result['message']);
            }
        } catch (Exception $e) {
            $this->logger->error('NFSe status query failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function queryNfseStatusByAccessKey(string $accessKey): array
    {
        try {
            $result = $this->apiClient->query_nfse_by_access_key($accessKey);

            if ($result['success']) {
                return [
                    'success' => true,
                    'status' => $result['status'] ?? $result['nfse_data']['status'] ?? 'unknown',
                    'nfse_data' => $result['nfse_data']
                ];
            } else {
                throw new Exception($result['message']);
            }
        } catch (Exception $e) {
            $this->logger->error('NFSe status query by access key failed', [
                'access_key' => $accessKey,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEmissionByOrder(int $orderId): ?array
    {
        return $this->emissionRepository->findByOrderId($orderId);
    }

    /**
     * {@inheritDoc}
     */
    public function getEmissionById(int $emissionId): ?array
    {
        return $this->emissionRepository->find($emissionId);
    }

    /**
     * {@inheritDoc}
     */
    public function downloadXml(int $orderId): string
    {
        $emission = $this->emissionRepository->findByOrderId($orderId);

        if (!$emission || $emission['status'] !== 'success') {
            throw new Exception(__('NFSe não encontrada ou não foi emitida.', 'wc-nfse'));
        }

        if (empty($emission['xml_content'])) {
            throw new Exception(__('Conteúdo XML não disponível.', 'wc-nfse'));
        }

        // Decompress if needed
        $xmlContent = $emission['xml_content'];
        if (!preg_match('/^<\?xml/', $xmlContent)) {
            $xmlContent = $this->apiClient->decompressXml($xmlContent);
        }

        return $xmlContent;
    }

    /**
     * {@inheritDoc}
     */
    public function retryFailedEmissions(int $limit = 10): array
    {
        // Get failed emissions that are older than 1 hour
        $failedEmissions = $this->emissionRepository->findAll(['status' => 'error'], $limit);

        $results = [];
        foreach ($failedEmissions as $emission) {
            try {
                $lastUpdate = strtotime($emission['updated_at']);
                if ((time() - $lastUpdate) < 3600) { // Less than 1 hour ago
                    continue;
                }

                $result = $this->processEmission($emission['order_id'], true);
                $results[] = [
                    'order_id' => $emission['order_id'],
                    'success' => true,
                    'message' => $result['message']
                ];
            } catch (Exception $e) {
                $results[] = [
                    'order_id' => $emission['order_id'],
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function validateEmissionPrerequisites(int $orderId): array
    {
        $result = [
            'valid' => true,
            'checks' => [],
            'errors' => [],
            'warnings' => []
        ];

        $order = wc_get_order($orderId);
        if (!$order) {
            $result['valid'] = false;
            $result['errors'][] = 'Pedido não encontrado';
            return $result;
        }

        // Plugin configuration check
        if (!$this->settings->isConfigured()) {
            $result['valid'] = false;
            $result['errors'][] = 'Plugin não está configurado';
        }
        $result['checks']['plugin_configured'] = $this->settings->isConfigured();

        // Certificate validation
        $certValid = $this->certificateManager->isCertificateValid();
        if (!$certValid) {
            $result['valid'] = false;
            $result['errors'][] = 'Certificado digital inválido';
        }
        $result['checks']['certificate_valid'] = $certValid;

        // Order status check
        $allowedStatuses = ['processing', 'completed'];
        $statusValid = in_array($order->get_status(), $allowedStatuses);
        if (!$statusValid) {
            $result['valid'] = false;
            $result['errors'][] = 'Status do pedido não permite emissão';
        }
        $result['checks']['order_status_valid'] = $statusValid;

        // Order customer data check
        $emailPresent = !empty($order->get_billing_email());
        if (!$emailPresent) {
            $result['valid'] = false;
            $result['errors'][] = 'Email do cliente é obrigatório';
        }
        $result['checks']['customer_email_present'] = $emailPresent;

        // Order items check
        $hasItems = count($order->get_items()) > 0;
        if (!$hasItems) {
            $result['valid'] = false;
            $result['errors'][] = 'Pedido não possui itens';
        }
        $result['checks']['has_items'] = $hasItems;

        // Order total check
        $totalValid = $order->get_total() > 0;
        if (!$totalValid) {
            $result['valid'] = false;
            $result['errors'][] = 'Valor do pedido deve ser maior que zero';
        }
        $result['checks']['total_greater_than_zero'] = $totalValid;

        // Business rules validation
        $businessRules = $this->settings->shouldProcessOrder(
            $order->get_status(),
            $order->get_total(),
            $order->get_payment_method(),
            $this->getCustomerType($order)
        );

        if (!$businessRules['should_process']) {
            $result['valid'] = false;
            $result['errors'] = array_merge($result['errors'], $businessRules['reasons']);
        }
        $result['checks']['business_rules_passed'] = $businessRules['should_process'];

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getEmissionStatistics(string $period = '30_days'): array
    {
        return $this->emissionRepository->getStatistics((int) str_replace('_days', '', $period));
    }

    /**
     * Private helper methods
     */
    private function checkEmissionPrerequisites(int $orderId, bool $forceReEmit): void
    {
        // Skip validation if forcing re-emit
        if ($forceReEmit) {
            return;
        }

        // Check if already emitted
        $existingEmission = $this->getEmissionByOrder($orderId);
        if ($existingEmission && $existingEmission['status'] === 'success') {
            throw new Exception(__('NFSe já foi emitida para este pedido.', 'wc-nfse'));
        }

        // Run validation
        $validation = $this->validateEmissionPrerequisites($orderId);
        if (!$validation['valid']) {
            throw new Exception('Pré-requisitos não atendidos: ' . implode(', ', $validation['errors']));
        }
    }

    //revisar os métodos da api
    private function submitToApi(string $compressedXml, array $dpsData): array
    {
        $payload = [
            'dps' => $compressedXml,
            'prestador' => [
                'cnpj' => $dpsData['prestador']['cnpj'],
                'inscricao_municipal' => $dpsData['prestador']['inscricao_municipal']
            ]
        ];


        $response = $this->apiClient->makeRequest('POST', $this->getApiEndpoint(), $payload);

        if ($response['success']) {
            return [
                'success' => true,
                'protocol' => $response['data']['protocolo'] ?? null,
                'access_key' => $response['data']['chave_acesso'] ?? null,
                'response_data' => $response['data']
            ];
        } else {
            throw new Exception($response['message'] ?? 'Erro na API');
        }
    }

    private function saveEmissionRecord(int $orderId, array $apiResult, array $dpsResult, string $xmlContent): int
    {
        $data = [
            'order_id' => $orderId,
            'status' => 'success',
            'dps_number' => $dpsResult['dps_number'],
            'access_key' => $apiResult['access_key'],
            'protocol' => $apiResult['protocol'],
            'xml_content' => $xmlContent,
            'emission_date' => current_time('mysql'),
            'api_response' => json_encode($apiResult['response_data'])
        ];

        return $this->emissionRepository->save($data);
    }

    private function saveFailedEmissionRecord(int $orderId, Exception $exception): void
    {
        $data = [
            'order_id' => $orderId,
            'status' => 'error',
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode()
        ];

        $this->emissionRepository->save($data);
    }

    private function updateOrder(int $orderId, array $apiResult, array $dpsResult): void
    {
        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        $order->add_order_note(sprintf(
            __('NFSe emitida com sucesso. Chave de acesso: %s', 'wc-nfse'),
            $apiResult['access_key']
        ));

        // Update order meta
        $order->update_meta_data('_nfse_access_key', $apiResult['access_key']);
        $order->update_meta_data('_nfse_protocol', $apiResult['protocol']);
        $order->update_meta_data('_nfse_dps_number', $dpsResult['dps_number']);
        $order->update_meta_data('_nfse_emission_date', current_time('mysql'));

        $order->save();
    }

    private function addOrderNote(int $orderId, string $note): void
    {
        $order = wc_get_order($orderId);
        if ($order) {
            $order->add_order_note($note);
        }
    }

    private function getApiEndpoint(): string
    {
        $environment = $this->settings->getEnvironment();
        $base = $environment === 'production'
            ? 'https://adn.nfse.gov.br'
            : 'https://adn-homologacao.nfse.gov.br';

        return $base . '/api/v1/dfe';
    }

    private function getCustomerType(\WC_Order $order): string
    {
        $billingCompany = $order->get_billing_company();
        $cnpj = $order->get_meta('_billing_cnpj');

        if (!empty($billingCompany) || !empty($cnpj)) {
            return 'business';
        }

        return 'individual';
    }
}
