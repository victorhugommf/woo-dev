<?php
/**
 * NFSe Certificate Validator Service
 *
 * Service class for validating digital certificates used in NFS-e emission
 *
 * @package CloudXM\NFSe\Services
 */

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

class NfSeCertificateValidator
{

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
    }

    /**
     * Validate certificate completely
     */
    public function validateCertificate($certificate_pem, $private_key_pem = null) {
        $validation_result = array(
            'valid' => false,
            'errors' => array(),
            'warnings' => array(),
            'info' => array()
        );

        try {
            // Parse certificate
            $cert_data = openssl_x509_parse($certificate_pem);
            if (!$cert_data) {
                $validation_result['errors'][] = __('Não foi possível analisar o certificado.', 'wc-nfse');
                return $validation_result;
            }

            // Basic validations
            $this->validateCertificateDates($cert_data, $validation_result);
            $this->validateCertificatePurpose($cert_data, $validation_result);
            $this->validateIcpBrasil($cert_data, $validation_result);
            $this->validateKeyUsage($cert_data, $validation_result);
            $this->validateSubjectInfo($cert_data, $validation_result);

            // Validate private key if provided
            if ($private_key_pem) {
                $this->validatePrivateKeyMatch($certificate_pem, $private_key_pem, $validation_result);
            }

            // Certificate chain validation
            $this->validateCertificateChain($certificate_pem, $validation_result);

            // Set overall validity
            $validation_result['valid'] = empty($validation_result['errors']);

            // Add certificate info
            $validation_result['info'] = $this->extractCertificateInfo($cert_data);

        } catch (Exception $e) {
            $validation_result['errors'][] = __('Erro na validação: ', 'wc-nfse') . $e->getMessage();
            $this->logger->error('Erro na validação de certificado: ' . $e->getMessage());
        }

        return $validation_result;
    }

    /**
     * Validate certificate dates
     */
    private function validateCertificateDates($cert_data, &$validation_result) {
        $now = time();
        $valid_from = $cert_data['validFrom_time_t'];
        $valid_to = $cert_data['validTo_time_t'];

        if ($now < $valid_from) {
            $validation_result['errors'][] = sprintf(
                __('Certificado ainda não é válido. Válido a partir de: %s', 'wc-nfse'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $valid_from)
            );
        }

        if ($now > $valid_to) {
            $validation_result['errors'][] = sprintf(
                __('Certificado expirado em: %s', 'wc-nfse'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $valid_to)
            );
        }

        // Warning for certificates expiring soon (30 days)
        $thirty_days = 30 * 24 * 60 * 60;
        if ($now + $thirty_days > $valid_to && $now < $valid_to) {
            $days_remaining = ceil(($valid_to - $now) / (24 * 60 * 60));
            $validation_result['warnings'][] = sprintf(
                __('Certificado expira em %d dias (%s)', 'wc-nfse'),
                $days_remaining,
                date_i18n(get_option('date_format'), $valid_to)
            );
        }
    }

    /**
     * Validate certificate purpose
     */
    private function validateCertificatePurpose($cert_data, &$validation_result) {
        // Check if certificate is for digital signature
        $purposes = $cert_data['purposes'] ?? array();

        $has_digital_signature = false;
        foreach ($purposes as $purpose) {
            if (isset($purpose[0]) && $purpose[0] === 1) { // Digital signature purpose
                $has_digital_signature = true;
                break;
            }
        }

        if (!$has_digital_signature) {
            $validation_result['warnings'][] = __('Certificado pode não ter capacidade de assinatura digital.', 'wc-nfse');
        }
    }

    /**
     * Validate ICP-Brasil certificate
     */
    private function validateIcpBrasil($cert_data, &$validation_result) {
        $issuer_cn = $cert_data['issuer']['CN'] ?? '';
        $subject_cn = $cert_data['subject']['CN'] ?? '';

        // Known ICP-Brasil Certificate Authorities
        $icp_brasil_cas = array(
            'AC CERTISIGN RFB',
            'AC SERASA RFB',
            'AC VALID RFB',
            'AC SOLUTI RFB',
            'AC DIGITALSIGN RFB',
            'AC SAFEWEB RFB',
            'AC LINK RFB',
            'AC SINCOR RFB',
            'AC PRODEMGE RFB',
            'AC FENACON RFB'
        );

        $is_icp_brasil = false;
        foreach ($icp_brasil_cas as $ca) {
            if (strpos($issuer_cn, $ca) !== false) {
                $is_icp_brasil = true;
                break;
            }
        }

        if (!$is_icp_brasil) {
            // Check for other ICP-Brasil indicators
            if (strpos($issuer_cn, 'ICP-Brasil') !== false ||
                strpos($issuer_cn, 'AC ') === 0 ||
                strpos($issuer_cn, 'RFB') !== false) {
                $is_icp_brasil = true;
            }
        }

        if (!$is_icp_brasil) {
            $validation_result['warnings'][] = sprintf(
                __('Certificado pode não ser ICP-Brasil. Emissor: %s', 'wc-nfse'),
                $issuer_cn
            );
        } else {
            $validation_result['info']['icp_brasil'] = true;
        }

        // Check certificate type (A1, A3, etc.)
        $cert_type = $this->detectCertificateType($cert_data);
        if ($cert_type) {
            $validation_result['info']['certificate_type'] = $cert_type;
        }
    }

    /**
     * Validate key usage
     */
    private function validateKeyUsage($cert_data, &$validation_result) {
        $extensions = $cert_data['extensions'] ?? array();

        if (isset($extensions['keyUsage'])) {
            $key_usage = $extensions['keyUsage'];

            if (strpos($key_usage, 'Digital Signature') === false) {
                $validation_result['warnings'][] = __('Certificado pode não ter permissão para assinatura digital.', 'wc-nfse');
            }

            if (strpos($key_usage, 'Non Repudiation') !== false) {
                $validation_result['info']['non_repudiation'] = true;
            }
        }

        // Check extended key usage
        if (isset($extensions['extendedKeyUsage'])) {
            $ext_key_usage = $extensions['extendedKeyUsage'];
            $validation_result['info']['extended_key_usage'] = $ext_key_usage;
        }
    }

    /**
     * Validate subject information
     */
    private function validateSubjectInfo($cert_data, &$validation_result) {
        $subject = $cert_data['subject'];

        // Check required fields
        $required_fields = array('CN', 'C');
        foreach ($required_fields as $field) {
            if (empty($subject[$field])) {
                $validation_result['warnings'][] = sprintf(
                    __('Campo obrigatório ausente no certificado: %s', 'wc-nfse'),
                    $field
                );
            }
        }

        // Check country
        if (isset($subject['C']) && $subject['C'] !== 'BR') {
            $validation_result['warnings'][] = sprintf(
                __('Certificado não é brasileiro. País: %s', 'wc-nfse'),
                $subject['C']
            );
        }

        // Extract CNPJ from certificate if present
        $cnpj = $this->extractCnpjFromCertificate($cert_data);
        if ($cnpj) {
            $validation_result['info']['cnpj'] = $cnpj;

            // Validate CNPJ format
            $settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
            if (!$settings->validate_cnpj($cnpj)) {
                $validation_result['warnings'][] = __('CNPJ extraído do certificado é inválido.', 'wc-nfse');
            }
        }
    }

    /**
     * Validate private key matches certificate
     */
    private function validatePrivateKeyMatch($certificate_pem, $private_key_pem, &$validation_result) {
        try {
            // Get public key from certificate
            $cert_resource = openssl_x509_read($certificate_pem);
            $public_key = openssl_pkey_get_public($cert_resource);

            // Get private key
            $private_key = openssl_pkey_get_private($private_key_pem);

            if (!$public_key || !$private_key) {
                $validation_result['errors'][] = __('Não foi possível carregar as chaves do certificado.', 'wc-nfse');
                return;
            }

            // Test if keys match by signing and verifying
            $test_data = 'test_signature_' . time();
            $signature = '';

            if (openssl_sign($test_data, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
                if (openssl_verify($test_data, $signature, $public_key, OPENSSL_ALGO_SHA256) === 1) {
                    $validation_result['info']['key_match'] = true;
                } else {
                    $validation_result['errors'][] = __('Chave privada não corresponde ao certificado.', 'wc-nfse');
                }
            } else {
                $validation_result['errors'][] = __('Não foi possível testar a chave privada.', 'wc-nfse');
            }

            // Clean up resources
            openssl_free_key($public_key);
            openssl_free_key($private_key);
            openssl_x509_free($cert_resource);

        } catch (Exception $e) {
            $validation_result['errors'][] = __('Erro ao validar chave privada: ', 'wc-nfse') . $e->getMessage();
        }
    }

    /**
     * Validate certificate chain
     */
    private function validateCertificateChain($certificate_pem, &$validation_result) {
        try {
            // For now, we'll do basic chain validation
            // In production, you might want to validate against ICP-Brasil root CAs

            $cert_resource = openssl_x509_read($certificate_pem);
            if (!$cert_resource) {
                $validation_result['errors'][] = __('Não foi possível ler o certificado para validação da cadeia.', 'wc-nfse');
                return;
            }

            // Check if certificate is self-signed (not recommended for production)
            $cert_data = openssl_x509_parse($cert_resource);
            $issuer = $cert_data['issuer'];
            $subject = $cert_data['subject'];

            if ($issuer === $subject) {
                $validation_result['warnings'][] = __('Certificado é auto-assinado.', 'wc-nfse');
            }

            openssl_x509_free($cert_resource);

        } catch (Exception $e) {
            $validation_result['warnings'][] = __('Não foi possível validar a cadeia de certificação.', 'wc-nfse');
        }
    }

    /**
     * Extract certificate information
     */
    private function extractCertificateInfo($cert_data) {
        return array(
            'subject_name' => $cert_data['subject']['CN'] ?? 'Unknown',
            'issuer_name' => $cert_data['issuer']['CN'] ?? 'Unknown',
            'serial_number' => $cert_data['serialNumber'] ?? '',
            'valid_from' => date('Y-m-d H:i:s', $cert_data['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $cert_data['validTo_time_t']),
            'signature_algorithm' => $cert_data['signatureTypeSN'] ?? 'Unknown',
            'public_key_bits' => $cert_data['extensions']['subjectKeyIdentifier'] ?? 'Unknown',
            'version' => $cert_data['version'] ?? 'Unknown'
        );
    }

    /**
     * Detect certificate type (A1, A3, etc.)
     */
    private function detectCertificateType($cert_data) {
        $subject_cn = $cert_data['subject']['CN'] ?? '';
        $issuer_cn = $cert_data['issuer']['CN'] ?? '';

        // Look for type indicators in certificate
        if (preg_match('/A[1-4]/', $subject_cn, $matches)) {
            return $matches[0];
        }

        if (preg_match('/A[1-4]/', $issuer_cn, $matches)) {
            return $matches[0];
        }

        // Try to determine by key storage (this is a simplified approach)
        $extensions = $cert_data['extensions'] ?? array();
        if (isset($extensions['keyUsage'])) {
            // A1 certificates typically allow key export, A3 don't
            // This is a simplified detection
            return 'A1'; // Default assumption
        }

        return null;
    }

    /**
     * Extract CNPJ from certificate
     */
    private function extractCnpjFromCertificate($cert_data) {
        $subject_cn = $cert_data['subject']['CN'] ?? '';

        // Try to extract CNPJ from CN field
        if (preg_match('/(\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2})/', $subject_cn, $matches)) {
            return preg_replace('/\D/', '', $matches[1]);
        }

        // Try other subject fields
        $subject_fields = array('O', 'OU', 'emailAddress');
        foreach ($subject_fields as $field) {
            if (isset($cert_data['subject'][$field])) {
                $value = $cert_data['subject'][$field];
                if (preg_match('/(\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2})/', $value, $matches)) {
                    return preg_replace('/\D/', '', $matches[1]);
                }
            }
        }

        return null;
    }

    /**
     * Test certificate with real NFS-e API call
     */
    public function testCertificateWithApi($certificate_data) {
        $test_result = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );

        $start_time = microtime(true);

        try {
            $this->logger->info('Testing certificate with real NFS-e API call');

            // Use São Paulo municipality code for testing (3550308)
            $test_municipality_code = '3550308';
            $test_result['details']['test_municipality'] = $test_municipality_code;

            // Make real API call to get municipality agreement info
            $response = $this->makeTestApiRequest($certificate_data, $test_municipality_code);

            $response_time = round((microtime(true) - $start_time) * 1000, 2);

            if ($response['success']) {
                $test_result['success'] = true;
                $test_result['message'] = __('Certificado validado com sucesso na API NFS-e.', 'wc-nfse');
                $test_result['details'] = array_merge($test_result['details'], array(
                    'api_response_time' => $response_time . 'ms',
                    'api_status' => 'OK',
                    'certificate_accepted' => true,
                    'http_code' => $response['http_code'],
                    'municipality_found' => !empty($response['data']),
                    'endpoint_teste' => 'parametros_municipais/convenio',
                    'test_timestamp' => current_time('mysql')
                ));

                if (!empty($response['data'])) {
                    $test_result['details']['municipality_info'] = array(
                        'tipo' => $response['data']['parametrosConvenio']['tipo'] ?? 'unknown',
                        'aderenteAmbienteNacional' => $response['data']['parametrosConvenio']['aderenteAmbienteNacional'] ?? 'unknown'
                    );
                }

                $this->logger->info('Certificate test successful', array(
                    'response_time' => $response_time . 'ms',
                    'http_code' => $response['http_code'],
                    'municipality_code' => $test_municipality_code
                ));

            } else {
                $test_result['message'] = __('Falha na validação do certificado: ', 'wc-nfse') . $response['message'];
                $test_result['details'] = array_merge($test_result['details'], array(
                    'api_response_time' => $response_time . 'ms',
                    'certificate_accepted' => false,
                    'http_code' => $response['http_code'] ?? 'unknown',
                    'error_details' => $response['message'],
                    'test_timestamp' => current_time('mysql')
                ));

                $this->logger->warning('Certificate test failed', array(
                    'response_time' => $response_time . 'ms',
                    'http_code' => $response['http_code'] ?? 'unknown',
                    'error' => $response['message']
                ));
            }

        } catch (Exception $e) {
            $response_time = round((microtime(true) - $start_time) * 1000, 2);
            $test_result['message'] = __('Erro no teste do certificado: ', 'wc-nfse') . $e->getMessage();
            $test_result['details'] = array(
                'api_response_time' => $response_time . 'ms',
                'certificate_accepted' => false,
                'error_type' => 'exception',
                'error_details' => $e->getMessage(),
                'test_timestamp' => current_time('mysql')
            );

            $this->logger->error('Certificate test exception: ' . $e->getMessage(), array(
                'response_time' => $response_time . 'ms',
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine()
            ));
        }

        return $test_result;
    }

    /**
     * Make test API request to NFS-e homologation portal
     */
    private function makeTestApiRequest($certificate_data, $municipality_code) {
        try {
            // Get settings for environment
            $settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
            $environment = $settings->get_environment();

            // Setup endpoint based on environment
            if ($environment === 'production') {
                $base_url = 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional';
            } else {
                $base_url = 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional'; // Use homologation for testing
            }

            $endpoint = $base_url . '/parametros_municipais/' . $municipality_code . '/convenio';

            // Create temporary certificate files
            $cert_file = $this->createTempCertFile($certificate_data['certificate']);
            $key_file = $this->createTempKeyFile($certificate_data['private_key']);

            // Setup cURL request
            $ch = curl_init();

            curl_setopt_array($ch, array(
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLCERT => $cert_file,
                CURLOPT_SSLKEY => $key_file,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: WooCommerce-NFSe-CertTest/1.0.0'
                )
            ));

            // Execute request
            $response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);

            curl_close($ch);

            // Clean up temporary files
            unlink($cert_file);
            unlink($key_file);

            // Check for cURL errors
            if ($curl_error) {
                return array(
                    'success' => false,
                    'message' => __('Connection error: ', 'wc-nfse') . $curl_error,
                    'http_code' => $http_code
                );
            }

            // Parse response
            $response_data = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return array(
                    'success' => false,
                    'message' => __('Invalid API response: ', 'wc-nfse') . json_last_error_msg(),
                    'http_code' => $http_code
                );
            }

            // Check HTTP status
            if ($http_code >= 200 && $http_code < 300) {
                return array(
                    'success' => true,
                    'data' => $response_data,
                    'http_code' => $http_code
                );
            } else {
                $error_message = $response_data['mensagem'] ??
                                $response_data['message'] ??
                                __('API returned error code: ', 'wc-nfse') . $http_code;

                return array(
                    'success' => false,
                    'message' => $error_message,
                    'http_code' => $http_code,
                    'response_data' => $response_data
                );
            }

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('Falha na requisição da API de teste: ', 'wc-nfse') . $e->getMessage(),
                'http_code' => 0
            );
        }
    }

    /**
     * Create temporary certificate file
     */
    private function createTempCertFile($certificate_pem) {
        $temp_file = tempnam(sys_get_temp_dir(), 'nfse_cert_test_');

        if (file_put_contents($temp_file, $certificate_pem) === false) {
            throw new Exception(__('Failed to create temporary certificate file.', 'wc-nfse'));
        }

        return $temp_file;
    }

    /**
     * Create temporary key file
     */
    private function createTempKeyFile($private_key_pem) {
        $temp_file = tempnam(sys_get_temp_dir(), 'nfse_key_test_');

        if (file_put_contents($temp_file, $private_key_pem) === false) {
            throw new Exception(__('Failed to create temporary key file.', 'wc-nfse'));
        }

        return $temp_file;
    }

    /**
     * Get certificate expiration status
     */
    public function getExpirationStatus($valid_to_timestamp) {
        $now = time();
        $days_until_expiry = ceil(($valid_to_timestamp - $now) / (24 * 60 * 60));

        if ($days_until_expiry < 0) {
            return array(
                'status' => 'expired',
                'message' => __('Certificado expirado', 'wc-nfse'),
                'days' => abs($days_until_expiry),
                'class' => 'error'
            );
        } elseif ($days_until_expiry <= 7) {
            return array(
                'status' => 'critical',
                'message' => sprintf(__('Expira em %d dias', 'wc-nfse'), $days_until_expiry),
                'days' => $days_until_expiry,
                'class' => 'error'
            );
        } elseif ($days_until_expiry <= 30) {
            return array(
                'status' => 'warning',
                'message' => sprintf(__('Expira em %d dias', 'wc-nfse'), $days_until_expiry),
                'days' => $days_until_expiry,
                'class' => 'warning'
            );
        } else {
            return array(
                'status' => 'valid',
                'message' => sprintf(__('Válido por %d dias', 'wc-nfse'), $days_until_expiry),
                'days' => $days_until_expiry,
                'class' => 'success'
            );
        }
    }

    /**
     * Check if certificate needs renewal notification
     */
    public function needsRenewalNotification($certificate_id, $valid_to_timestamp) {
        $days_until_expiry = ceil(($valid_to_timestamp - time()) / (24 * 60 * 60));

        // Check if we should send notification (30, 15, 7, 3, 1 days before expiry)
        $notification_days = array(30, 15, 7, 3, 1);

        if (in_array($days_until_expiry, $notification_days)) {
            // Check if notification was already sent for this day
            $last_notification = get_option("wc_nfse_cert_{$certificate_id}_last_notification", 0);
            $today = date('Y-m-d');

            if ($last_notification !== $today) {
                // Update last notification date
                update_option("wc_nfse_cert_{$certificate_id}_last_notification", $today);
                return true;
            }
        }

        return false;
    }
}