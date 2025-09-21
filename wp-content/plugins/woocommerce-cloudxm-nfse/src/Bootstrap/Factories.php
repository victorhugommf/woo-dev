<?php

namespace CloudXM\NFSe\Bootstrap;

use CloudXM\NFSe\Utilities\Logger;
use CloudXM\NFSe\Services\NfSeSettings;
use CloudXM\NFSe\Services\NfSeDpsGenerator;
use CloudXM\NFSe\Services\NfSeEmissionService;
use CloudXM\NFSe\Services\NfSeAutomationService;
use CloudXM\NFSe\Services\NfSeQueueService;
use CloudXM\NFSe\Api\ApiClient;
use CloudXM\NFSe\Services\NfSeCertificateManager;
use CloudXM\NFSe\Services\NfSeCertificateValidator;
use CloudXM\NFSe\Services\NfSeCompressor;
use CloudXM\NFSe\Services\NfSeRtcValidator;
use CloudXM\NFSe\Services\NfSeDigitalSigner;
use CloudXM\NFSe\Services\NfSeSignatureValidator;
use CloudXM\NFSe\Services\NfSeXmlSecLibsIntegration;
use CloudXM\NFSe\Persistence\NfSeEmissionRepository;
use CloudXM\NFSe\Persistence\NfSeQueueRepository;
use CloudXM\NFSe\Persistence\NfSeCertificateRepository;
use CloudXM\NFSe\Persistence\MigrationRunner;

/**
 * Dependency Injection Factory
 *
 * Central place for creating service instances with proper dependency injection
 */
class Factories
{
    /**
     * Logger instance cache
     */
    private static ?Logger $logger = null;

    /**
     * Settings instance cache
     */
    private static ?NfSeSettings $settings = null;

    /**
     * Service instance caches
     */
    private static ?NfSeDpsGenerator $dpsGenerator = null;
    private static ?NfSeEmissionService $emissionService = null;
    private static ?NfSeAutomationService $automationService = null;
    private static ?NfSeQueueService $queueService = null;
    private static ?ApiClient $apiClient = null;
    private static ?NfSeCertificateManager $certificateManager = null;
    private static ?NfSeCertificateValidator $certificateValidator = null;
    private static ?NfSeCompressor $compressor = null;
    private static ?NfSeDigitalSigner $digitalSigner = null;
    private static ?NfSeSignatureValidator $signatureValidator = null;
    private static ?NfSeXmlSecLibsIntegration $xmlSecLibsIntegration = null;

    // Service factory methods

    /**
     * Get NFSe DPS Generator
     */
    public static function nfSeDpsGenerator(): NfSeDpsGenerator
    {
        if (self::$dpsGenerator === null) {
            self::$dpsGenerator = new NfSeDpsGenerator(
                self::logger(),
                self::nfSeSettings()
            );
        }
        return self::$dpsGenerator;
    }

    /**
     * Get API Client
     */
    public static function apiClient(): ApiClient
    {
        if (self::$apiClient === null) {
            self::$apiClient = new ApiClient();
        }
        return self::$apiClient;
    }

    /**
     * Get NFSe Certificate Manager
     */
    public static function nfSeCertificateManager(): NfSeCertificateManager
    {
        if (self::$certificateManager === null) {
            self::$certificateManager = new NfSeCertificateManager();
        }
        return self::$certificateManager;
    }

    /**
     * Get NFSe Certificate Validator
     */
    public static function nfSeCertificateValidator(): NfSeCertificateValidator
    {
        if (self::$certificateValidator === null) {
            self::$certificateValidator = new NfSeCertificateValidator(
                self::logger(),
                self::nfSeCertificateManager()
            );
        }
        return self::$certificateValidator;
    }

    /**
     * Get NFSe Compressor
     */
    public static function nfSeCompressor(): NfSeCompressor
    {
        if (self::$compressor === null) {
            self::$compressor = new NfSeCompressor();
        }
        return self::$compressor;
    }

    /**
     * Get NFSe Digital Signer
     */
    public static function nfSeDigitalSigner(): NfSeDigitalSigner
    {
        if (self::$digitalSigner === null) {
            self::$digitalSigner = new NfSeDigitalSigner();
        }
        return self::$digitalSigner;
    }

    /**
     * Get NFSe Signature Validator
     */
    public static function nfSeSignatureValidator(): NfSeSignatureValidator
    {
        if (self::$signatureValidator === null) {
            self::$signatureValidator = new NfSeSignatureValidator();
        }
        return self::$signatureValidator;
    }

    /**
     * Get NFSe XMLSecLibs Integration
     */
    public static function nfSeXmlSecLibsIntegration(): NfSeXmlSecLibsIntegration
    {
        if (self::$xmlSecLibsIntegration === null) {
            self::$xmlSecLibsIntegration = new NfSeXmlSecLibsIntegration();
        }
        return self::$xmlSecLibsIntegration;
    }

    /**
     * Get NFSe Emission Service
     */
    public static function nfSeEmissionService(): NfSeEmissionService
    {
        if (self::$emissionService === null) {
            self::$emissionService = new NfSeEmissionService(
                self::logger(),
                self::nfSeSettings(),
                self::nfSeDpsGenerator(),
                self::nfSeDigitalSigner(),
                self::apiClient(),
                self::nfSeCertificateManager(),
                self::nfSeEmissionRepository(),
                new NfSeRtcValidator()
            );
        }
        return self::$emissionService;
    }

    /**
     * Get NFSe Automation Service
     */
    public static function nfSeAutomationService(): NfSeAutomationService
    {
        if (self::$automationService === null) {
            self::$automationService = new NfSeAutomationService(
                self::logger(),
                self::nfSeSettings(),
                self::nfSeEmissionService(),
                self::nfSeQueueService()
            );
        }
        return self::$automationService;
    }

    /**
     * Get NFSe Queue Service
     */
    public static function nfSeQueueService(): NfSeQueueService
    {
        if (self::$queueService === null) {
            self::$queueService = new NfSeQueueService(
                self::logger(),
                self::nfSeSettings(),
                self::nfSeQueueRepository(),
                self::nfSeEmissionService()
            );
        }
        return self::$queueService;
    }

    /**
     * Get Logger instance
     */
    public static function logger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = Logger::getInstance();
        }

        return self::$logger;
    }

    /**
     * Get NFSe Settings service
     */
    public static function nfSeSettings(): NfSeSettings
    {
        if (self::$settings === null) {
            // Load settings from WordPress options
            $settingsData = get_option('wc_nfse_settings', []);
            self::$settings = new NfSeSettings(self::logger(), $settingsData);
        }

        return self::$settings;
    }

    /**
     * Get NFSe Emission Repository
     */
    public static function nfSeEmissionRepository(): NfSeEmissionRepository
    {
        return new NfSeEmissionRepository(
            self::logger(),
            'cloudxm_nfse'
        );
    }

    /**
     * Get NFSe Queue Repository
     */
    public static function nfSeQueueRepository(): NfSeQueueRepository
    {
        return new NfSeQueueRepository(
            self::logger(),
            'cloudxm_nfse'
        );
    }

    /**
     * Get NFSe Certificate Repository
     */
    public static function nfSeCertificateRepository(): NfSeCertificateRepository
    {
        return new NfSeCertificateRepository(
            self::logger(),
            'cloudxm_nfse'
        );
    }

    /**
     * Get Migration Runner
     */
    public static function migrationRunner(): MigrationRunner
    {
        return new MigrationRunner(
            self::logger()
        );
    }

    /**
     * Reset all cached instances (useful for testing)
     */
    public static function reset(): void
    {
        self::$logger = null;
        self::$settings = null;
        self::$dpsGenerator = null;
        self::$emissionService = null;
        self::$automationService = null;
        self::$queueService = null;
        self::$apiClient = null;
        self::$certificateManager = null;
        self::$certificateValidator = null;
        self::$compressor = null;
        self::$digitalSigner = null;
        self::$signatureValidator = null;
        self::$xmlSecLibsIntegration = null;
    }
}