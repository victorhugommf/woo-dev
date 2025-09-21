<?php

namespace CloudXM\NFSe\Compatibility;

use CloudXM\NFSe\Services\NfSeSettings;
use CloudXM\NFSe\Utilities\Logger;

/**
 * Settings Compatibility Layer
 *
 * This class provides backward compatibility for the legacy WC_NFSe_Settings class
 * while using the modern PSR-4 NfSeSettings service underneath.
 */
class SettingsCompatibility
{
    /**
     * Modern PSR-4 settings service
     */
    private NfSeSettings $modernSettings;

    /**
     * Settings option name
     */
    private string $option_name = 'wc_nfse_settings';

    /**
     * Local cache for settings (to maintain legacy behavior)
     */
    private array $settings = [];

    /**
     * Logger instance
     */
    private Logger $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->loadSettingsFromWordPress();
        $this->modernSettings = new NfSeSettings($this->logger, $this->settings);
    }

    /**
     * Load settings from WordPress database (legacy behavior)
     */
    private function loadSettingsFromWordPress(): void
    {
        $this->settings = get_option($this->option_name, []);
    }

    /**
     * Get setting value - legacy interface
     */
    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set setting value - legacy interface
     */
    public function set(string $key, $value): void
    {
        $this->settings[$key] = $value;
        $this->modernSettings->set($key, $value);
    }

    /**
     * Save settings to database - legacy interface
     */
    public function save(): bool
    {
        $saved = update_option($this->option_name, $this->settings);
        if ($saved) {
            $this->logger->debug('Settings saved to WordPress options', ['option_name' => $this->option_name]);
        }
        return $saved;
    }

    /**
     * Get all settings - legacy interface
     */
    public function get_all(): array
    {
        return $this->settings;
    }

    /**
     * Update multiple settings - legacy interface
     */
    public function update(array $settings): bool
    {
        $this->settings = array_merge($this->settings, $settings);
        foreach ($settings as $key => $value) {
            $this->modernSettings->set($key, $value);
        }
        return $this->save();
    }

    // Legacy getter methods - delegating to modern service

    public function is_enabled(): bool
    {
        return $this->modernSettings->isEnabled();
    }

    public function get_environment(): string
    {
        return $this->modernSettings->getEnvironment();
    }

    public function is_auto_emit_enabled(): bool
    {
        return $this->modernSettings->isAutoEmitEnabled();
    }

    public function get_prestador_cnpj(): string
    {
        return $this->modernSettings->getPrestadorCnpj();
    }

    public function get_prestador_inscricao_municipal(): string
    {
        return $this->modernSettings->getPrestadorInscricaoMunicipal();
    }

    public function get_prestador_razao_social(): string
    {
        return $this->modernSettings->getPrestadorRazaoSocial();
    }

    public function get_prestador_nome_fantasia(): string
    {
        return $this->modernSettings->getPrestadorNomeFantasia();
    }

    public function get_prestador_endereco(): string
    {
        $address = $this->modernSettings->getPrestadorAddress();
        return $address['endereco'];
    }

    public function get_prestador_numero(): string
    {
        $address = $this->modernSettings->getPrestadorAddress();
        return $address['numero'];
    }

    public function get_prestador_bairro(): string
    {
        $address = $this->modernSettings->getPrestadorAddress();
        return $address['bairro'];
    }

    public function get_prestador_cidade(): string
    {
        $address = $this->modernSettings->getPrestadorAddress();
        return $address['cidade'];
    }

    public function get_prestador_uf(): string
    {
        $address = $this->modernSettings->getPrestadorAddress();
        return $address['uf'];
    }

    public function get_prestador_cep(): string
    {
        $address = $this->modernSettings->getPrestadorAddress();
        return $address['cep'];
    }

    public function get_prestador_telefone(): string
    {
        $contact = $this->modernSettings->getPrestadorContact();
        return $contact['telefone'];
    }

    public function get_prestador_email(): string
    {
        $contact = $this->modernSettings->getPrestadorContact();
        return $contact['email'];
    }

    public function get_regime_tributario(): string
    {
        return $this->modernSettings->getRegimeTributario();
    }

    public function get_default_nbs_code(): string
    {
        return $this->modernSettings->getDefaultNbsCode();
    }

    public function get_dps_serie(): string
    {
        return $this->modernSettings->getDpsSerie();
    }

    public function is_debug_enabled(): bool
    {
        return $this->modernSettings->isDebugEnabled();
    }

    public function get_active_certificate_id(): int
    {
        return $this->modernSettings->getActiveCertificateId();
    }

    public function is_configured(): bool
    {
        return $this->modernSettings->isConfigured();
    }

    public function get_configuration_status(): array
    {
        return $this->modernSettings->getConfigurationStatus();
    }

    public function validate_cnpj(string $cnpj): bool
    {
        return $this->modernSettings->validateCnpj($cnpj);
    }

    public function format_cnpj(string $cnpj): string
    {
        return $this->modernSettings->formatCnpj($cnpj);
    }

    public function get_brazilian_states(): array
    {
        return $this->modernSettings->getBrazilianStates();
    }

    public function get_tax_regimes(): array
    {
        return $this->modernSettings->getTaxRegimes();
    }

    // Modern access for new code
    public function getModernSettings(): NfSeSettings
    {
        return $this->modernSettings;
    }
}