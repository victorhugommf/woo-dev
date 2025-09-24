<?php

/**
 * NFSe Digital Signer Service
 *
 * Handles digital signing of XML documents for NFS-e using digital certificates
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
 * Class NfSeDigitalSigner
 *
 * Provides digital signing and verification functionality for XML documents
 * conforming to Brazilian NFS-e standards
 */
class NfSeDigitalSigner
{
    /**
     * Logger instance
     */
    private $logger;

    /**
     * Certificate manager instance
     */
    private $certificateManager;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->certificateManager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
    }

    /**
     * Sign XML with digital certificate
     */
    public function signXml(string $xmlContent, ?string $certificateId = null): string
    {
        try {
            // Normalize XML before signing (best practice)
            // Remove excessive whitespace between tags for consistent canonicalization
            $normalizedXml = preg_replace('/>\s+</', '><', $xmlContent);

            // Load certificate data
            $certificateData = $this->certificateManager->loadCertificateData($certificateId);

            // Create DOM document
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;
            $dom->loadXML($normalizedXml);

            // Find the element to sign (InfDPS)
            $infDps = $dom->getElementsByTagName('infDPS')->item(0);
            if (!$infDps) {
                throw new Exception(__('Elemento InfDPS não encontrado no XML.', 'wc-nfse'));
            }

            // Get the Id attribute
            $id = $infDps->getAttribute('Id');
            if (empty($id)) {
                throw new Exception(__('Atributo Id não encontrado no elemento InfDPS.', 'wc-nfse'));
            }

            // Canonicalize the element to sign
            $canonicalXml = $this->canonicalizeElement($infDps);

            // Calculate digest
            $digest = base64_encode(hash('sha1', $canonicalXml, true));

            // Create signature
            $signature = $this->createSignature($dom, $id, $digest, $certificateData);

            // Add signature to DPS
            $dpsElement = $dom->getElementsByTagName('DPS')->item(0);
            $dpsElement->appendChild($signature);

            $signedXml = $dom->saveXML();

            $this->logger->info('XML assinado digitalmente com sucesso', [
                'certificate_id' => $certificateId,
                'element_id' => $id,
                'digest_length' => strlen($digest)
            ]);

            return $signedXml;
        } catch (Exception $e) {
            $this->logger->error('Erro na assinatura digital: ' . $e->getMessage(), [
                'certificate_id' => $certificateId
            ]);
            throw $e;
        }
    }

    /**
     * Create XML signature
     */
    private function createSignature(DOMDocument $dom, string $referenceId, string $digest, array $certificateData): \DOMElement
    {
        // Create Signature element
        $signature = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');

        // Create SignedInfo
        $signedInfo = $dom->createElement('SignedInfo');
        $signature->appendChild($signedInfo);

        // CanonicalizationMethod
        $canonicalizationMethod = $dom->createElement('CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonicalizationMethod);

        // SignatureMethod
        $signatureMethod = $dom->createElement('SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($signatureMethod);

        // Reference
        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', '#' . $referenceId);
        $signedInfo->appendChild($reference);

        // Transforms
        $transforms = $dom->createElement('Transforms');
        $reference->appendChild($transforms);

        $transform = $dom->createElement('Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);

        $transform2 = $dom->createElement('Transform');
        $transform2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($transform2);

        // DigestMethod
        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);

        // DigestValue
        $digestValue = $dom->createElement('DigestValue', $digest);
        $reference->appendChild($digestValue);

        // Calculate SignedInfo digest and signature
        $signedInfoCanonical = $this->canonicalizeElement($signedInfo);
        $signatureValue = $this->calculateSignatureValue($signedInfoCanonical, $certificateData['private_key']);

        // SignatureValue
        $signatureValueElement = $dom->createElement('SignatureValue', $signatureValue);
        $signature->appendChild($signatureValueElement);

        // KeyInfo
        $keyInfo = $this->createKeyInfo($dom, $certificateData['certificate']);
        $signature->appendChild($keyInfo);

        return $signature;
    }

    /**
     * Create KeyInfo element
     */
    private function createKeyInfo(DOMDocument $dom, string $certificatePem): \DOMElement
    {
        $keyInfo = $dom->createElement('KeyInfo');

        // X509Data
        $x509Data = $dom->createElement('X509Data');
        $keyInfo->appendChild($x509Data);

        // X509Certificate (without headers and line breaks)
        $certificateContent = preg_replace('/-----[^-]+-----/', '', $certificatePem);
        $certificateContent = preg_replace('/\s+/', '', $certificateContent);

        $x509Certificate = $dom->createElement('X509Certificate', $certificateContent);
        $x509Data->appendChild($x509Certificate);

        return $keyInfo;
    }

    /**
     * Canonicalize XML element
     */
    private function canonicalizeElement($element): string
    {
        if ($element instanceof DOMDocument) {
            return $element->C14N(false, false);
        } else {
            return $element->C14N(false, false);
        }
    }

    /**
     * Calculate signature value
     */
    private function calculateSignatureValue(string $data, string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if (!$privateKey) {
            throw new Exception(__('Não foi possível carregar a chave privada.', 'wc-nfse'));
        }

        $signature = '';
        if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA1)) {
            openssl_free_key($privateKey);
            throw new Exception(__('Erro ao calcular assinatura digital.', 'wc-nfse'));
        }


        return base64_encode($signature);
    }

    /**
     * Verify XML signature
     */
    public function verifySignature(string $signedXml): bool
    {
        try {
            // Normalize XML before verification (consistent with signing process)
            $normalizedXml = preg_replace('/>\s+</', '><', $signedXml);

            $dom = new DOMDocument();
            $dom->loadXML($normalizedXml);

            // Find signature
            $signatureNodes = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
            if ($signatureNodes->length === 0) {
                throw new Exception(__('Assinatura não encontrada no XML.', 'wc-nfse'));
            }

            $signatureNode = $signatureNodes->item(0);

            // Get SignedInfo
            $signedInfoNodes = $signatureNode->getElementsByTagName('SignedInfo');
            if ($signedInfoNodes->length === 0) {
                throw new Exception(__('SignedInfo não encontrado.', 'wc-nfse'));
            }

            $signedInfo = $signedInfoNodes->item(0);

            // Get Reference URI
            $referenceNodes = $signedInfo->getElementsByTagName('Reference');
            if ($referenceNodes->length === 0) {
                throw new Exception(__('Reference não encontrado.', 'wc-nfse'));
            }

            $reference = $referenceNodes->item(0);
            $uri = $reference->getAttribute('URI');

            if (empty($uri) || $uri[0] !== '#') {
                throw new Exception(__('URI de referência inválido.', 'wc-nfse'));
            }

            $elementId = substr($uri, 1);

            // Find referenced element
            $xpath = new DOMXPath($dom);
            $referencedElements = $xpath->query("//*[@Id='$elementId']");

            if ($referencedElements->length === 0) {
                throw new Exception(__('Elemento referenciado não encontrado.', 'wc-nfse'));
            }

            $referencedElement = $referencedElements->item(0);

            // Remove signature for verification
            $signatureNode->parentNode->removeChild($signatureNode);

            // Verify digest
            $canonicalElement = $this->canonicalizeElement($referencedElement);
            $calculatedDigest = base64_encode(hash('sha1', $canonicalElement, true));

            $digestValueNodes = $reference->getElementsByTagName('DigestValue');
            if ($digestValueNodes->length === 0) {
                throw new Exception(__('DigestValue não encontrado.', 'wc-nfse'));
            }

            $storedDigest = $digestValueNodes->item(0)->nodeValue;

            if ($calculatedDigest !== $storedDigest) {
                throw new Exception(__('Digest não confere.', 'wc-nfse'));
            }

            // Verify signature
            $signedInfoCanonical = $this->canonicalizeElement($signedInfo);

            $signatureValueNodes = $signatureNode->getElementsByTagName('SignatureValue');
            if ($signatureValueNodes->length === 0) {
                throw new Exception(__('SignatureValue não encontrado.', 'wc-nfse'));
            }

            $signatureValue = base64_decode($signatureValueNodes->item(0)->nodeValue);

            // Get certificate
            $x509CertNodes = $signatureNode->getElementsByTagName('X509Certificate');
            if ($x509CertNodes->length === 0) {
                throw new Exception(__('Certificado não encontrado na assinatura.', 'wc-nfse'));
            }

            $certContent = $x509CertNodes->item(0)->nodeValue;
            $certificatePem = "-----BEGIN CERTIFICATE-----\n" .
                chunk_split($certContent, 64, "\n") .
                "-----END CERTIFICATE-----\n";

            $publicKey = openssl_pkey_get_public($certificatePem);
            if (!$publicKey) {
                throw new Exception(__('Não foi possível extrair chave pública do certificado.', 'wc-nfse'));
            }

            $verificationResult = openssl_verify($signedInfoCanonical, $signatureValue, $publicKey, OPENSSL_ALGO_SHA1);

            if ($verificationResult === 1) {
                $this->logger->info('Assinatura XML verificada com sucesso');
                return true;
            } elseif ($verificationResult === 0) {
                throw new Exception(__('Assinatura inválida.', 'wc-nfse'));
            } else {
                throw new Exception(__('Erro na verificação da assinatura.', 'wc-nfse'));
            }
        } catch (Exception $e) {
            $this->logger->error('Erro na verificação de assinatura: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract certificate info from signed XML
     */
    public function extractCertificateInfo(string $signedXml): array
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($signedXml);

            $x509CertNodes = $dom->getElementsByTagName('X509Certificate');
            if ($x509CertNodes->length === 0) {
                throw new Exception(__('Certificado não encontrado no XML assinado.', 'wc-nfse'));
            }

            $certContent = $x509CertNodes->item(0)->nodeValue;
            $certificatePem = "-----BEGIN CERTIFICATE-----\n" .
                chunk_split($certContent, 64, "\n") .
                "-----END CERTIFICATE-----\n";

            $certData = openssl_x509_parse($certificatePem);
            if (!$certData) {
                throw new Exception(__('Não foi possível analisar o certificado.', 'wc-nfse'));
            }

            return [
                'subject_name' => $certData['subject']['CN'] ?? 'Unknown',
                'issuer_name' => $certData['issuer']['CN'] ?? 'Unknown',
                'serial_number' => $certData['serialNumber'] ?? '',
                'valid_from' => date('Y-m-d H:i:s', $certData['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $certData['validTo_time_t']),
                'certificate_pem' => $certificatePem
            ];
        } catch (Exception $e) {
            $this->logger->error('Erro ao extrair informações do certificado: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if XML is signed
     */
    public function isXmlSigned(string $xmlContent): bool
    {
        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);

        $signatureNodes = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');

        return $signatureNodes->length > 0;
    }

    /**
     * Remove signature from XML
     */
    public function removeSignature(string $signedXml): string
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($signedXml);

        $signatureNodes = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');

        foreach ($signatureNodes as $signatureNode) {
            $signatureNode->parentNode->removeChild($signatureNode);
        }

        return $dom->saveXML();
    }

    /**
     * Get signature timestamp
     */
    public function getSignatureTimestamp(string $signedXml): string
    {
        // For basic implementation, return current time
        // In a full implementation, this would extract timestamp from signature
        return current_time('mysql');
    }

    /**
     * Validate signature certificate
     */
    public function validateSignatureCertificate(string $signedXml): bool
    {
        try {
            $certInfo = $this->extractCertificateInfo($signedXml);

            // Check if certificate is expired
            $validTo = strtotime($certInfo['valid_to']);
            if (time() > $validTo) {
                throw new Exception(__('Certificado usado na assinatura está expirado.', 'wc-nfse'));
            }

            // Check if certificate is not yet valid
            $validFrom = strtotime($certInfo['valid_from']);
            if (time() < $validFrom) {
                throw new Exception(__('Certificado usado na assinatura ainda não é válido.', 'wc-nfse'));
            }

            $this->logger->info('Certificado da assinatura validado com sucesso', [
                'subject_name' => $certInfo['subject_name'],
                'issuer_name' => $certInfo['issuer_name'],
                'valid_to' => $certInfo['valid_to']
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Erro na validação do certificado da assinatura: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create signature hash for verification
     */
    public function createSignatureHash(string $xmlContent): string
    {
        return hash('sha1', $xmlContent);
    }

    /**
     * Verify signature hash
     */
    public function verifySignatureHash(string $xmlContent, string $expectedHash): bool
    {
        $calculatedHash = $this->createSignatureHash($xmlContent);
        return hash_equals($expectedHash, $calculatedHash);
    }
}
