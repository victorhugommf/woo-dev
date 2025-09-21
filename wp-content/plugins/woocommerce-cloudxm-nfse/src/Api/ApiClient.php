<?php

/**
 * CloudXM NFS-e API Client Class
 */

namespace CloudXM\NFSe\Api;

if (!defined('ABSPATH')) {
    exit;
}

class ApiClient
{

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Settings instance
     */
    private $settings;

    /**
     * Certificate manager instance
     */
    private $certificate_manager;

    /**
     * API endpoints
     */
    private $endpoints;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
        $this->settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
        $this->certificate_manager = new \CloudXM\NFSe\Services\NfSeCertificateManager();

        $this->setupEndpoints();
    }

    /**
     * Setup API endpoints
     */
    private function setupEndpoints()
    {
        $environment = $this->settings->get_environment();

        if ($environment === 'production') {
            $this->endpoints = array(
                'adn_base' => 'https://sefin.nfse.gov.br/sefinnacional',
                'sefin_base' => 'https://sefin.nfse.gov.br',
                'cnc_base' => 'https://cnc.nfse.gov.br'
            );
        } else {
            $this->endpoints = array(
                'adn_base' => 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional',
                'sefin_base' => 'https://sefin-homologacao.nfse.gov.br',
                'cnc_base' => 'https://cnc-homologacao.nfse.gov.br'
            );
        }
    }

    /**
     * Submit DPS for emission
     */
    public function submitDps($signed_xml, $dps_data)
    {
        try {
            // Compress and encode XML
            $compressed_xml = $this->compressXml($signed_xml);

            // Prepare request payload
            $payload = array(
                'dps' => $compressed_xml,
                'prestador' => array(
                    'cnpj' => $dps_data['prestador']['cnpj'] ?? '',
                    'inscricao_municipal' => $dps_data['prestador']['inscricao_municipal'] ?? ''
                )
            );

            // Submit to ADN
            $endpoint = $this->endpoints['adn_base'] . '/nfse';
            $response = $this->makeRequest('POST', $endpoint, $payload);

            if ($response['success']) {
                $this->logger->info('DPS submetida com sucesso', array(
                    'dps_number' => $dps_data['identificacao_dps']['numero'],
                    'response_code' => $response['http_code'],
                    'protocol' => $response['data']['protocolo'] ?? null
                ));

                return array(
                    'success' => true,
                    'protocol' => $response['data']['protocolo'] ?? null,
                    'access_key' => $response['data']['chave_acesso'] ?? null,
                    'message' => $response['data']['mensagem'] ?? __('DPS submetida com sucesso', 'wc-nfse'),
                    'response_data' => $response['data']
                );
            } else {
                throw new \Exception($response['message']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro na submissão da DPS: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Query NFS-e by access key
     */
    public function query_nfse_by_access_key($access_key)
    {
        try {
            $endpoint = $this->endpoints['sefin_base'] . '/api/v1/nfse/consulta/chave-acesso';

            $payload = array(
                'chave_acesso' => $access_key
            );

            $response = $this->makeRequest('POST', $endpoint, $payload);

            if ($response['success']) {
                $this->logger->info('Consulta por chave de acesso realizada', array(
                    'access_key' => $access_key,
                    'status' => $response['data']['status'] ?? 'unknown'
                ));

                return array(
                    'success' => true,
                    'nfse_data' => $response['data'],
                    'status' => $response['data']['status'] ?? 'unknown'
                );
            } else {
                throw new \Exception($response['message']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro na consulta por chave de acesso: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Query municipal parameters
     */
    public function queryMunicipalParameters($municipality_code)
    {
        try {
            $endpoint = $this->endpoints['sefin_base'] . '/api/v1/parametros-municipais/' . $municipality_code;

            $response = $this->makeRequest('GET', $endpoint);

            if ($response['success']) {
                // Cache parameters for 24 hours
                $cache_key = 'wc_nfse_municipal_params_' . $municipality_code;
                set_transient($cache_key, $response['data'], 24 * HOUR_IN_SECONDS);

                $this->logger->info('Parâmetros municipais consultados', array(
                    'municipality_code' => $municipality_code,
                    'cached' => true
                ));

                return array(
                    'success' => true,
                    'parameters' => $response['data']
                );
            } else {
                throw new \Exception($response['message']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro na consulta de parâmetros municipais: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get cached municipal parameters
     */
    public function get_cached_municipal_parameters($municipality_code)
    {
        $cache_key = 'wc_nfse_municipal_params_' . $municipality_code;
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return array(
                'success' => true,
                'parameters' => $cached_data,
                'cached' => true
            );
        }

        return $this->queryMunicipalParameters($municipality_code);
    }

    /**
     * Query municipal tax rate
     */
    public function queryMunicipalTaxRate($municipality_code, $service_code)
    {
        try {
            $endpoint = $this->endpoints['sefin_base'] . '/api/v1/aliquota-municipal';

            $payload = array(
                'codigo_municipio' => $municipality_code,
                'codigo_servico' => $service_code
            );

            $response = $this->makeRequest('POST', $endpoint, $payload);

            if ($response['success']) {
                $this->logger->info('Alíquota municipal consultada', array(
                    'municipality_code' => $municipality_code,
                    'service_code' => $service_code,
                    'tax_rate' => $response['data']['aliquota'] ?? null
                ));

                return array(
                    'success' => true,
                    'tax_rate' => $response['data']['aliquota'] ?? 0,
                    'tax_data' => $response['data']
                );
            } else {
                throw new \Exception($response['message']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro na consulta de alíquota municipal: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel NFS-e
     */
    public function cancelNfse($access_key, $cancellation_reason)
    {
        try {
            $endpoint = $this->endpoints['adn_base'] . '/api/v1/nfse/cancelamento';

            $payload = array(
                'chave_acesso' => $access_key,
                'motivo_cancelamento' => $cancellation_reason
            );

            $response = $this->makeRequest('POST', $endpoint, $payload);

            if ($response['success']) {
                $this->logger->info('NFS-e cancelada com sucesso', array(
                    'access_key' => $access_key,
                    'reason' => $cancellation_reason
                ));

                return array(
                    'success' => true,
                    'message' => $response['data']['mensagem'] ?? __('NFS-e cancelada com sucesso', 'wc-nfse'),
                    'cancellation_data' => $response['data']
                );
            } else {
                throw new \Exception($response['message']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro no cancelamento da NFS-e: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test connection with API
     */
    public function testConnection()
    {
        try {
            $endpoint = $this->endpoints['sefin_base'] . '/api/v1/status';

            $response = $this->makeRequest('GET', $endpoint, null, 10); // 10 second timeout

            if ($response['success']) {
                $this->logger->info('Teste de conexão com API realizado com sucesso');

                return array(
                    'success' => true,
                    'message' => __('Conexão com API estabelecida com sucesso', 'wc-nfse'),
                    'response_time' => $response['response_time'] ?? null,
                    'api_status' => $response['data']['status'] ?? 'online'
                );
            } else {
                throw new \Exception($response['message']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro no teste de conexão: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Make HTTP request to API
     */
    private function makeRequest($method, $url, $payload = null, $timeout = 30)
    {
        $start_time = microtime(true);

        try {
            // Load certificate for SSL client authentication
            $certificate_data = $this->certificate_manager->loadCertificateData();

            // Create temporary certificate files
            $cert_file = $this->createTempCertFile($certificate_data['certificate']);
            $key_file = $this->createTempKeyFile($certificate_data['private_key']);

            // Setup cURL
            $ch = curl_init();

            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLCERT => $cert_file,
                CURLOPT_SSLKEY => $key_file,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: WooCommerce-NFSe-Plugin/' . WC_NFSE_VERSION,
                    'X-Forwarded-For: ' . $this->getClientIp()
                )
            ));

            // Set method and payload
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($payload) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                }
            } elseif ($method === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($payload) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                }
            } elseif ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }

            // Execute request
            $response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);

            curl_close($ch);

            // Clean up temporary files
            unlink($cert_file);
            unlink($key_file);

            $response_time = round((microtime(true) - $start_time) * 1000, 2);

            // Check for cURL errors
            if ($curl_error) {
                throw new \Exception(__('Erro de conexão: ', 'wc-nfse') . $curl_error);
            }

            // Parse response
            $response_data = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(__('Resposta da API inválida: ', 'wc-nfse') . json_last_error_msg());
            }

            // Log request details
            $this->logger->debug('Requisição API realizada', array(
                'method' => $method,
                'url' => $url,
                'http_code' => $http_code,
                'response_time' => $response_time . 'ms',
                'payload_size' => $payload ? strlen(json_encode($payload)) : 0
            ));

            // Check HTTP status
            if ($http_code >= 200 && $http_code < 300) {
                return array(
                    'success' => true,
                    'data' => $response_data,
                    'http_code' => $http_code,
                    'response_time' => $response_time
                );
            } else {
                $error_message = $response_data['mensagem'] ?? $response_data['message'] ?? __('Erro na API', 'wc-nfse');

                return array(
                    'success' => false,
                    'message' => $error_message,
                    'http_code' => $http_code,
                    'response_data' => $response_data
                );
            }
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'http_code' => $http_code ?? 0
            );
        }
    }

    /**
     * Compress XML for transmission
     */
    private function compressXml($xml)
    {
        // Remove unnecessary whitespace
        $xml = preg_replace('/>\s+</', '><', $xml);

        // Compress with gzip
        $compressed = gzencode($xml, 9);

        if ($compressed === false) {
            throw new \Exception(__('Erro na compressão do XML.', 'wc-nfse'));
        }

        // Encode in base64
        $encoded = base64_encode($compressed);

        // Check size limit (1MB)
        if (strlen($encoded) > 1048576) {
            throw new \Exception(__('XML muito grande após compressão.', 'wc-nfse'));
        }

        $this->logger->debug('XML comprimido', array(
            'original_size' => strlen($xml),
            'compressed_size' => strlen($compressed),
            'encoded_size' => strlen($encoded),
            'compression_ratio' => round((1 - strlen($compressed) / strlen($xml)) * 100, 2) . '%'
        ));

        return $encoded;
    }

    /**
     * Decompress XML from API response
     */
    public function decompressXml($encoded_xml)
    {
        try {
            // Decode from base64
            $compressed = base64_decode($encoded_xml);

            if ($compressed === false) {
                throw new \Exception(__('Erro na decodificação base64.', 'wc-nfse'));
            }

            // Decompress
            $xml = gzdecode($compressed);

            if ($xml === false) {
                throw new \Exception(__('Erro na descompressão do XML.', 'wc-nfse'));
            }

            return $xml;
        } catch (\Exception $e) {
            $this->logger->error('Erro na descompressão do XML: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create temporary certificate file
     */
    private function createTempCertFile($certificate_pem)
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'wc_nfse_cert_');

        if (file_put_contents($temp_file, $certificate_pem) === false) {
            throw new \Exception(__('Erro ao criar arquivo temporário do certificado.', 'wc-nfse'));
        }

        return $temp_file;
    }

    /**
     * Create temporary key file
     */
    private function createTempKeyFile($private_key_pem)
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'wc_nfse_key_');

        if (file_put_contents($temp_file, $private_key_pem) === false) {
            throw new \Exception(__('Erro ao criar arquivo temporário da chave.', 'wc-nfse'));
        }

        return $temp_file;
    }

    /**
     * Get client IP address
     */
    private function getClientIp()
    {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get API status
     */
    public function getApiStatus()
    {
        $status = array(
            'adn' => 'unknown',
            'sefin' => 'unknown',
            'cnc' => 'unknown'
        );

        // Test each API endpoint
        foreach ($this->endpoints as $service => $base_url) {
            try {
                $endpoint = $base_url . '/api/v1/status';
                $response = $this->makeRequest('GET', $endpoint, null, 5);

                $status[str_replace('_base', '', $service)] = $response['success'] ? 'online' : 'offline';
            } catch (\Exception $e) {
                $status[str_replace('_base', '', $service)] = 'offline';
            }
        }

        return $status;
    }

    /**
     * Clear cached data
     */
    public function clearCache()
    {
        global $wpdb;

        // Clear municipal parameters cache
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_nfse_municipal_params_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_nfse_municipal_params_%'");

        $this->logger->info('Cache da API limpo');
    }
}
