<?php

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Utilities\Logger;

/**
 * NFSe Settings Service
 *
 * Manages all NFSe configuration and business logic independently of WordPress dependencies
 */
class NfSeSettings
{
    /**
     * Logger instance
     */
    private Logger $logger;

    /**
     * Settings array
     */
    private array $settings = [];

    /**
     * Constructor
     */
    public function __construct(Logger $logger, array $settings = [])
    {
        $this->logger = $logger;
        $this->settings = $settings;
    }

    /**
     * Get setting value
     */
    public function get(string $key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Set setting value
     */
    public function set(string $key, $value): void
    {
        $this->settings[$key] = $value;
    }

    /**
     * Get all settings
     */
    public function getAll(): array
    {
        return $this->settings;
    }

    /**
     * Update multiple settings
     */
    public function update(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);
    }

    // Specific getters for common settings

    /**
     * Check if plugin is enabled
     */
    public function isEnabled(): bool
    {
        return $this->get('enabled') === 'yes';
    }

    /**
     * Get environment (production/homologation)
     */
    public function getEnvironment(): string
    {
        return $this->get('environment', 'homologation');
    }

    /**
     * Check if auto emission is enabled
     */
    public function isAutoEmitEnabled(): bool
    {
        return $this->get('auto_emit') === 'yes';
    }

    /**
     * Get prestador CNPJ
     */
    public function getPrestadorCnpj(): string
    {
        return $this->get('prestador_cnpj', '');
    }

    /**
     * Get prestador municipal registration
     */
    public function getPrestadorInscricaoMunicipal(): string
    {
        return $this->get('prestador_inscricao_municipal', '');
    }

    /**
     * Get prestador company name
     */
    public function getPrestadorRazaoSocial(): string
    {
        return $this->get('prestador_razao_social', '');
    }

    /**
     * Get prestador trade name
     */
    public function getPrestadorNomeFantasia(): string
    {
        return $this->get('prestador_nome_fantasia', '');
    }

    /**
     * Get prestador address data
     */
    public function getPrestadorAddress(): array
    {
        return [
            'endereco' => $this->get('prestador_endereco', ''),
            'numero' => $this->get('prestador_numero', ''),
            'complemento' => $this->get('prestador_complemento', ''),
            'bairro' => $this->get('prestador_bairro', ''),
            'cidade' => $this->get('prestador_cidade', ''),
            'uf' => $this->get('prestador_uf', ''),
            'cep' => $this->get('prestador_cep', ''),
        ];
    }

    /**
     * Get prestador contact information
     */
    public function getPrestadorContact(): array
    {
        return [
            'telefone' => $this->get('prestador_telefone', ''),
            'email' => $this->get('prestador_email', ''),
        ];
    }

    /**
     * Get tax regime
     */
    public function getRegimeTributario(): string
    {
        return $this->get('regime_tributario', 'simples_nacional');
    }

    /**
     * Get default NBS code
     */
    public function getDefaultNbsCode(): string
    {
        return $this->get('default_nbs_code', '01.01');
    }

    /**
     * Get DPS series
     */
    public function getDpsSerie(): string
    {
        return $this->get('dps_serie', '');
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugEnabled(): bool
    {
        return $this->get('debug_mode') === 'yes';
    }

    /**
     * Get active certificate ID
     */
    public function getActiveCertificateId(): int
    {
        return (int) $this->get('active_certificate_id', 0);
    }

    /**
     * Get auto emission configuration
     */
    public function getAutoEmissionConfig(): array
    {
        return [
            'enabled' => $this->isAutoEmitEnabled(),
            'triggers' => $this->get('auto_emit_triggers', ['payment_complete']),
            'delay' => (int) $this->get('auto_emit_delay', 300),
            'order_statuses' => $this->get('auto_emit_order_statuses', ['processing', 'completed']),
            'min_total' => (float) $this->get('auto_emit_min_total', 0),
            'excluded_payment_methods' => $this->get('auto_emit_excluded_payment_methods', []),
            'customer_types' => $this->get('auto_emit_customer_types', ['all']),
            'business_hours_enabled' => $this->get('auto_emit_business_hours_enabled', false) === 'yes',
            'business_hours' => [
                'start' => $this->get('auto_emit_business_hours_start', '08:00'),
                'end' => $this->get('auto_emit_business_hours_end', '18:00'),
                'days' => $this->get('auto_emit_business_days', [1, 2, 3, 4, 5]),
            ],
            'retry_limit' => (int) $this->get('auto_emit_retry_limit', 3),
        ];
    }

    /**
     * Check if plugin is properly configured
     */
    public function isConfigured(): bool
    {
        $requiredFields = [
            'prestador_cnpj',
            'prestador_inscricao_municipal',
            'prestador_razao_social',
            'prestador_endereco',
            'prestador_numero',
            'prestador_bairro',
            'prestador_cidade',
            'prestador_uf',
            'prestador_cep'
        ];

        foreach ($requiredFields as $field) {
            if (empty($this->get($field))) {
                return false;
            }
        }

        // Check if there's an active certificate
        if ($this->getActiveCertificateId() <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Get configuration status
     */
    public function getConfigurationStatus(): array
    {
        $status = [
            'prestador_data' => false,
            'certificate' => false,
            'complete' => false
        ];

        // Check prestador data
        $requiredPrestadorFields = [
            'prestador_cnpj',
            'prestador_inscricao_municipal',
            'prestador_razao_social',
            'prestador_endereco',
            'prestador_numero',
            'prestador_bairro',
            'prestador_cidade',
            'prestador_uf',
            'prestador_cep'
        ];

        $prestadorDataComplete = true;
        foreach ($requiredPrestadorFields as $field) {
            if (empty($this->get($field))) {
                $prestadorDataComplete = false;
                break;
            }
        }
        $status['prestador_data'] = $prestadorDataComplete;

        // Check certificate
        $status['certificate'] = $this->getActiveCertificateId() > 0;

        // Overall status
        $status['complete'] = $status['prestador_data'] && $status['certificate'];

        return $status;
    }

    /**
     * Validate CNPJ format
     */
    public function validateCnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Check for known invalid CNPJs
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Calculate first verification digit
        $sum = 0;
        $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$cnpj[$i] * $weights[$i];
        }

        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

        if ((int)$cnpj[12] !== $digit1) {
            return false;
        }

        // Calculate second verification digit
        $sum = 0;
        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($i = 0; $i < 13; $i++) {
            $sum += (int)$cnpj[$i] * $weights[$i];
        }

        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        return (int)$cnpj[13] === $digit2;
    }

    /**
     * Format CNPJ for display
     */
    public function formatCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) === 14) {
            return substr($cnpj, 0, 2) . '.' .
                   substr($cnpj, 2, 3) . '.' .
                   substr($cnpj, 5, 3) . '/' .
                   substr($cnpj, 8, 4) . '-' .
                   substr($cnpj, 12, 2);
        }

        return $cnpj;
    }

    /**
     * Get Brazilian states
     */
    public function getBrazilianStates(): array
    {
        return [
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins'
        ];
    }

    /**
     * Get tax regimes
     */
    public function getTaxRegimes(): array
    {
        return [
            'simples_nacional' => 'Simples Nacional',
            'lucro_presumido' => 'Lucro Presumido',
            'lucro_real' => 'Lucro Real',
            'mei' => 'MEI'
        ];
    }

    /**
     * Check if current time is within business hours
     */
    public function isWithinBusinessHours(?string $currentTime = null, int $currentDay = null): bool
    {
        $businessHoursEnabled = $this->get('auto_emit_business_hours_enabled') === 'yes';

        if (!$businessHoursEnabled) {
            return true;
        }

        $currentTime = $currentTime ?? date('H:i');
        $currentDay = $currentDay ?? (int)date('w');

        $startTime = $this->get('auto_emit_business_hours_start', '08:00');
        $endTime = $this->get('auto_emit_business_hours_end', '18:00');
        $businessDays = $this->get('auto_emit_business_days', [1, 2, 3, 4, 5]);

        // Check if current day is a business day
        if (!in_array($currentDay, $businessDays)) {
            return false;
        }

        // Check if current time is within business hours
        return ($currentTime >= $startTime && $currentTime <= $endTime);
    }

    /**
     * Check if order should be processed (pure business logic)
     */
    public function shouldProcessOrder(
        string $orderStatus,
        float $orderTotal,
        string $paymentMethod,
        string $customerType
    ): array {
        $result = [
            'should_process' => true,
            'reasons' => []
        ];

        // Check order status
        $allowedStatuses = $this->get('auto_emit_order_statuses', ['processing', 'completed']);
        if (!in_array($orderStatus, $allowedStatuses)) {
            $result['should_process'] = false;
            $result['reasons'][] = 'Invalid order status';
        }

        // Check order total
        $minTotal = (float) $this->get('auto_emit_min_total', 0);
        if ($orderTotal < $minTotal) {
            $result['should_process'] = false;
            $result['reasons'][] = 'Order total below minimum';
        }

        // Check payment method
        $excludedPaymentMethods = $this->get('auto_emit_excluded_payment_methods', []);
        if (in_array($paymentMethod, $excludedPaymentMethods)) {
            $result['should_process'] = false;
            $result['reasons'][] = 'Payment method excluded';
        }

        // Check customer type
        $customerTypes = $this->get('auto_emit_customer_types', ['all']);
        if (!in_array('all', $customerTypes) && !in_array($customerType, $customerTypes)) {
            $result['should_process'] = false;
            $result['reasons'][] = 'Customer type excluded';
        }

        // Check business hours
        if (!$this->isWithinBusinessHours()) {
            $result['should_process'] = false;
            $result['reasons'][] = 'Outside business hours';
        }

        return $result;
    }
}