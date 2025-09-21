<?php
/**
 * NFSe XMLSecLibs Integration Service
 *
 * Handles XMLSecLibs library installation, management and integration for enhanced XML signing
 *
 * @package CloudXM\NFSe\Services
 */

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Utilities\Logger;
use Exception;
use DOMDocument;

/**
 * Class NfSeXmlSecLibsIntegration
 *
 * Provides XMLSecLibs integration for high-performance XML signing and verification
 * with automatic installation and management capabilities
 */
class NfSeXmlSecLibsIntegration
{
    /**
     * Logger instance
     */
    private $logger;

    /**
     * Check if xmlseclibs is available
     */
    private $xmlseclibsAvailable = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->xmlseclibsAvailable = $this->isXmlSecLibsAvailable();
    }

    /**
     * Check if xmlseclibs is available
     */
    private function isXmlSecLibsAvailable(): bool
    {
        return class_exists('RobRichards\XMLSecLibs\XMLSecurityDSig');
    }

    /**
     * Check if xmlseclibs is available (public method)
     */
    public function isAvailable(): bool
    {
        return $this->xmlseclibsAvailable;
    }

    /**
     * Sign XML using xmlseclibs
     *
     * @param string $xmlContent XML content to sign
     * @param array $certificateData Certificate and private key data
     * @return string Signed XML content
     * @throws Exception If xmlseclibs is not available or signing fails
     */
    public function signXmlWithXmlseclibs(string $xmlContent, array $certificateData): string
    {
        if (!$this->xmlseclibsAvailable) {
            throw new Exception(__('xmlseclibs library not available', 'wc-nfse'));
        }

        try {
            // Create DOM document
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;
            $dom->loadXML($xmlContent);

            // Find the element to sign
            $infDps = $dom->getElementsByTagName('InfDPS')->item(0);
            if (!$infDps) {
                throw new Exception(__('Elemento InfDPS não encontrado no XML.', 'wc-nfse'));
            }

            // Create XMLSecurityDSig object
            $objDSig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();

            // Set canonicalization method
            $objDSig->setCanonicalMethod(\RobRichards\XMLSecLibs\XMLSecurityDSig::EXC_C14N);

            // Add reference to the element to be signed
            $id = $infDps->getAttribute('Id');
            if (empty($id)) {
                throw new Exception(__('Atributo Id não encontrado no elemento InfDPS.', 'wc-nfse'));
            }

            $objDSig->addReference(
                $infDps,
                \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
                ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
                ['id_name' => 'Id', 'id_ns_prefix' => null]
            );

            // Create new XMLSecurityKey object
            $objKey = new \RobRichards\XMLSecLibs\XMLSecurityKey(
                \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
                ['type' => 'private']
            );

            // Load private key
            $objKey->loadKey($certificateData['private_key'], false);

            // Sign the XML
            $objDSig->sign($objKey);

            // Add certificate to signature
            $objDSig->add509Cert($certificateData['certificate']);

            // Append signature to DPS element
            $dpsElement = $dom->getElementsByTagName('DPS')->item(0);
            $objDSig->appendSignature($dpsElement);

            $signedXml = $dom->saveXML();

            $this->logger->info('XML signed successfully using xmlseclibs');

            return $signedXml;

        } catch (Exception $e) {
            $this->logger->error('Error signing XML with xmlseclibs: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify XML signature using xmlseclibs
     *
     * @param string $signedXml Signed XML content
     * @return bool True if signature is valid
     * @throws Exception If xmlseclibs is not available or verification fails
     */
    public function verifyXmlWithXmlseclibs(string $signedXml): bool
    {
        if (!$this->xmlseclibsAvailable) {
            throw new Exception(__('xmlseclibs library not available', 'wc-nfse'));
        }

        try {
            // Create DOM document
            $dom = new DOMDocument();
            $dom->loadXML($signedXml);

            // Create XMLSecurityDSig object
            $objDSig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();

            // Locate signature
            $objDSig->locateSignature($dom);

            if (!$objDSig->validateReference()) {
                throw new Exception(__('Reference validation failed', 'wc-nfse'));
            }

            // Get certificate from signature
            $objKey = $objDSig->locateKey();
            if (!$objKey) {
                throw new Exception(__('Key not found in signature', 'wc-nfse'));
            }

            // Load certificate
            $cert = $objDSig->locateX509Cert();
            if ($cert) {
                $objKey->loadKey($cert, false, true);
            }

            // Verify signature
            $result = $objDSig->verify($objKey);

            if ($result === 1) {
                $this->logger->info('XML signature verified successfully using xmlseclibs');
                return true;
            } else {
                $this->logger->error('XML signature verification failed using xmlseclibs');
                return false;
            }

        } catch (Exception $e) {
            $this->logger->error('Error verifying XML with xmlseclibs: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Install xmlseclibs via Composer
     *
     * @return bool True if installation succeeded
     */
    public function installXmlseclibs(): bool
    {
        try {
            $composerJson = WC_NFSE_PLUGIN_PATH . 'composer.json';

            // Create composer.json if it doesn't exist
            if (!file_exists($composerJson)) {
                $composerConfig = [
                    'name' => 'woocommerce/nfse-plugin',
                    'description' => 'CloudXM NFS-e Plugin',
                    'type' => 'wordpress-plugin',
                    'require' => [
                        'php' => '>=7.4',
                        'robrichards/xmlseclibs' => '^3.1'
                    ],
                    'autoload' => [
                        'psr-4' => [
                            'WC_NFSe\\' => 'includes/'
                        ]
                    ]
                ];

                file_put_contents($composerJson, json_encode($composerConfig, JSON_PRETTY_PRINT));
            }

            // Check if composer is available
            $composerPath = $this->findComposer();
            if (!$composerPath) {
                throw new Exception(__('Composer not found. Please install Composer first.', 'wc-nfse'));
            }

            // Run composer install
            chdir(WC_NFSE_PLUGIN_PATH);

            $command = $composerPath . ' install --no-dev --optimize-autoloader';
            $output = [];
            $returnCode = 0;

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $this->logger->info('xmlseclibs installed successfully via Composer');

                // Update availability flag
                $this->xmlseclibsAvailable = $this->isXmlSecLibsAvailable();

                return true;
            } else {
                throw new Exception(__('Composer install failed: ', 'wc-nfse') . implode("\n", $output));
            }

        } catch (Exception $e) {
            $this->logger->error('Error installing xmlseclibs: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Find Composer executable
     *
     * @return string|false Path to composer or false if not found
     */
    private function findComposer()
    {
        $possiblePaths = [
            'composer',
            'composer.phar',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            getcwd() . '/composer.phar'
        ];

        foreach ($possiblePaths as $path) {
            if ($this->isExecutable($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Check if command is executable
     *
     * @param string $command Command path to check
     * @return bool True if executable
     */
    private function isExecutable(string $command): bool
    {
        $output = [];
        $returnCode = 0;

        exec("which $command 2>/dev/null", $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Download and install xmlseclibs manually
     *
     * @return bool True if installation succeeded
     */
    public function installXmlseclibsManual(): bool
    {
        try {
            $vendorDir = WC_NFSE_PLUGIN_PATH . 'vendor/robrichards/xmlseclibs/src/';

            if (!file_exists($vendorDir)) {
                wp_mkdir_p($vendorDir);
            }

            // Download xmlseclibs files
            $filesToDownload = [
                'XMLSecurityDSig.php' => 'https://raw.githubusercontent.com/robrichards/xmlseclibs/master/src/XMLSecurityDSig.php',
                'XMLSecurityKey.php' => 'https://raw.githubusercontent.com/robrichards/xmlseclibs/master/src/XMLSecurityKey.php',
                'XMLSecEnc.php' => 'https://raw.githubusercontent.com/robrichards/xmlseclibs/master/src/XMLSecEnc.php'
            ];

            foreach ($filesToDownload as $filename => $url) {
                $filePath = $vendorDir . $filename;

                if (!file_exists($filePath)) {
                    $response = wp_remote_get($url);

                    if (is_wp_error($response)) {
                        throw new Exception(__('Failed to download: ', 'wc-nfse') . $filename);
                    }

                    $body = wp_remote_retrieve_body($response);

                    if (empty($body)) {
                        throw new Exception(__('Empty response for: ', 'wc-nfse') . $filename);
                    }

                    file_put_contents($filePath, $body);
                }
            }

            // Update availability flag
            $this->xmlseclibsAvailable = $this->isXmlSecLibsAvailable();

            $this->logger->info('xmlseclibs installed manually');
            return true;

        } catch (Exception $e) {
            $this->logger->error('Error installing xmlseclibs manually: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get xmlseclibs status
     *
     * @return array Status information
     */
    public function getStatus(): array
    {
        return [
            'available' => $this->xmlseclibsAvailable,
            'version' => $this->getVersion(),
            'installation_method' => $this->getInstallationMethod(),
            'can_install_composer' => $this->findComposer() !== false,
            'can_install_manual' => function_exists('wp_remote_get')
        ];
    }

    /**
     * Get xmlseclibs version
     *
     * @return string|null Version string or null if not available
     */
    private function getVersion(): ?string
    {
        if (!$this->xmlseclibsAvailable) {
            return null;
        }

        // Try to get version from composer.lock
        $composerLock = WC_NFSE_PLUGIN_PATH . 'composer.lock';
        if (file_exists($composerLock)) {
            $lockData = json_decode(file_get_contents($composerLock), true);

            if (isset($lockData['packages'])) {
                foreach ($lockData['packages'] as $package) {
                    if ($package['name'] === 'robrichards/xmlseclibs') {
                        return $package['version'];
                    }
                }
            }
        }

        return 'unknown';
    }

    /**
     * Get installation method
     *
     * @return string Installation method
     */
    private function getInstallationMethod(): string
    {
        if (!$this->xmlseclibsAvailable) {
            return 'not_installed';
        }

        if (file_exists(WC_NFSE_PLUGIN_PATH . 'composer.lock')) {
            return 'composer';
        }

        return 'manual';
    }

    /**
     * Uninstall xmlseclibs
     *
     * @return bool True if uninstallation succeeded
     */
    public function uninstallXmlseclibs(): bool
    {
        try {
            $vendorDir = WC_NFSE_PLUGIN_PATH . 'vendor/';

            if (file_exists($vendorDir)) {
                $this->deleteDirectory($vendorDir);
            }

            // Remove composer files
            $composerFiles = ['composer.json', 'composer.lock'];
            foreach ($composerFiles as $file) {
                $filePath = WC_NFSE_PLUGIN_PATH . $file;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $this->xmlseclibsAvailable = false;
            $this->logger->info('xmlseclibs uninstalled');

            return true;

        } catch (Exception $e) {
            $this->logger->error('Error uninstalling xmlseclibs: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete directory recursively
     *
     * @param string $dir Directory path
     * @return bool True if deletion succeeded
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * Test xmlseclibs functionality
     *
     * @return array Test results
     */
    public function testXmlseclibs(): array
    {
        if (!$this->xmlseclibsAvailable) {
            return [
                'success' => false,
                'message' => __('xmlseclibs not available', 'wc-nfse')
            ];
        }

        try {
            // Create a simple test XML
            $testXml = '<?xml version="1.0" encoding="UTF-8"?>
                <TestDocument>
                    <Data Id="test-data">Test content for signing</Data>
                </TestDocument>';

            // Create test certificate data (this would normally come from the certificate manager)
            $certificateManager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
            $certificateData = $certificateManager->loadCertificateData();

            if (!$certificateData) {
                return [
                    'success' => false,
                    'message' => __('No certificate available for testing', 'wc-nfse')
                ];
            }

            // Test signing
            $signedXml = $this->signXmlWithXmlseclibs($testXml, $certificateData);

            // Test verification
            $verificationResult = $this->verifyXmlWithXmlseclibs($signedXml);

            return [
                'success' => $verificationResult,
                'message' => $verificationResult ?
                    __('xmlseclibs test successful', 'wc-nfse') :
                    __('xmlseclibs test failed', 'wc-nfse'),
                'signed_xml_length' => strlen($signedXml)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('xmlseclibs test error: ', 'wc-nfse') . $e->getMessage()
            ];
        }
    }

    /**
     * Get recommended xmlseclibs configuration
     *
     * @return array Configuration parameters
     */
    public function getRecommendedConfig(): array
    {
        return [
            'version' => '^3.1',
            'algorithms' => [
                'canonicalization' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
                'signature' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                'digest' => 'http://www.w3.org/2001/04/xmlenc#sha256'
            ],
            'requirements' => [
                'php' => '>=7.4',
                'openssl' => 'required',
                'dom' => 'required',
                'libxml' => 'required'
            ]
        ];
    }
}