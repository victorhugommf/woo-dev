<?php
/**
 * NFSe Signature Validator Service
 *
 * Provides comprehensive XML signature validation according to NFS-e standards
 *
 * @package CloudXM\NFSe\Services
 */

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Utilities\Logger;
use Exception;
use DOMDocument;
use DOMXPath;

/**
 * Class NfSeSignatureValidator
 *
 * Handles XML signature validation using native PHP implementation
 * with optional XMLSecLibs enhancement support
 */
class NfSeSignatureValidator
{
    /**
     * Logger instance
     */
    private $logger;

    /**
     * Digital signer instance
     */
    private $digitalSigner;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->digitalSigner = \CloudXM\NFSe\Bootstrap\Factories::nfSeDigitalSigner();
    }

    /**
     * Validate XML signature
     */
    public function validateSignature(string $signedXml): bool
    {
        try {
            return $this->digitalSigner->verifySignature($signedXml);
        } catch (Exception $e) {
            $this->logger->error('Error validating signature: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get signature algorithms used in the XML
     */
    public function getSignatureAlgorithms(string $signedXml): array
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($signedXml);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

            $canonicalizationMethod = $xpath->query('//ds:CanonicalizationMethod/@Algorithm');
            $signatureMethod = $xpath->query('//ds:SignatureMethod/@Algorithm');
            $digestMethod = $xpath->query('//ds:DigestMethod/@Algorithm');

            return [
                'canonicalization' => $canonicalizationMethod->length > 0 ? $canonicalizationMethod->item(0)->nodeValue : null,
                'signature' => $signatureMethod->length > 0 ? $signatureMethod->item(0)->nodeValue : null,
                'digest' => $digestMethod->length > 0 ? $digestMethod->item(0)->nodeValue : null,
            ];
        } catch (Exception $e) {
            $this->logger->error('Error extracting signature algorithms: ' . $e->getMessage());
            return [
                'canonicalization' => null,
                'signature' => null,
                'digest' => null,
            ];
        }
    }

    /**
     * Extract certificate information from signed XML
     */
    public function getCertificateInfo(string $signedXml): array
    {
        try {
            return $this->digitalSigner->extractCertificateInfo($signedXml);
        } catch (Exception $e) {
            $this->logger->error('Error extracting certificate info: ' . $e->getMessage());
            return [
                'subject_name' => 'Unknown',
                'issuer_name' => 'Unknown',
                'serial_number' => '',
                'valid_from' => null,
                'valid_to' => null,
                'is_expired' => true,
            ];
        }
    }

    /**
     * Get comprehensive validation report
     */
    public function getValidationReport(string $signedXml): array
    {
        try {
            $report = [
                'valid' => false,
                'errors' => [],
                'warnings' => [],
                'certificate_info' => [],
                'algorithms' => [],
                'validation_time' => date('c')
            ];

            // Validate signature
            $isValid = $this->validateSignature($signedXml);
            $report['valid'] = $isValid;

            // Get certificate info
            $certInfo = $this->getCertificateInfo($signedXml);
            $report['certificate_info'] = $certInfo;

            // Get algorithms
            $algorithms = $this->getSignatureAlgorithms($signedXml);
            $report['algorithms'] = $algorithms;

            // Check for issues
            if (!$isValid) {
                $report['errors'][] = 'Signature validation failed';
            }

            // Check certificate expiration
            if (isset($certInfo['valid_to'])) {
                $validTo = strtotime($certInfo['valid_to']);
                if (time() > $validTo) {
                    $report['errors'][] = 'Certificate is expired';
                    $certInfo['is_expired'] = true;
                } elseif (time() > ($validTo - (30 * 24 * 60 * 60))) { // 30 days
                    $report['warnings'][] = 'Certificate expires soon';
                }
            }

            // Validate expected algorithms
            if ($algorithms['canonicalization'] !== 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315') {
                $report['warnings'][] = 'Non-standard canonicalization method used';
            }

            if ($algorithms['signature'] !== 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256') {
                $report['warnings'][] = 'Non-standard signature algorithm used';
            }

            if ($algorithms['digest'] !== 'http://www.w3.org/2001/04/xmlenc#sha256') {
                $report['warnings'][] = 'Non-standard digest algorithm used';
            }

            return $report;

        } catch (Exception $e) {
            $this->logger->error('Error generating validation report: ' . $e->getMessage());
            return [
                'valid' => false,
                'errors' => ['Error generating validation report: ' . $e->getMessage()],
                'warnings' => [],
                'certificate_info' => [],
                'algorithms' => [],
                'validation_time' => date('c')
            ];
        }
    }

    /**
     * Batch validate multiple signed XMLs
     */
    public function batchValidate(array $signedXmls): array
    {
        $results = [];

        foreach ($signedXmls as $index => $signedXml) {
            try {
                $report = $this->getValidationReport($signedXml);
                $results[$index] = [
                    'valid' => $report['valid'],
                    'certificate_info' => $report['certificate_info'],
                    'errors' => $report['errors'],
                    'warnings' => $report['warnings']
                ];
            } catch (Exception $e) {
                $results[$index] = [
                    'valid' => false,
                    'certificate_info' => [],
                    'errors' => ['Validation error: ' . $e->getMessage()],
                    'warnings' => []
                ];
            }
        }

        return $results;
    }

    /**
     * Check if XML contains a signature
     */
    public function isSigned(string $xmlContent): bool
    {
        return $this->digitalSigner->isXmlSigned($xmlContent);
    }

    /**
     * Get signature timestamp (when the XML was signed)
     */
    public function getSignatureTimestamp(string $signedXml): string
    {
        return $this->digitalSigner->getSignatureTimestamp($signedXml);
    }

    /**
     * Validate only the certificate used in signature
     */
    public function validateSignatureCertificate(string $signedXml): bool
    {
        try {
            return $this->digitalSigner->validateSignatureCertificate($signedXml);
        } catch (Exception $e) {
            $this->logger->error('Error validating signature certificate: ' . $e->getMessage());
            return false;
        }
    }
}