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
     * Compressor instance
     */
    private $compressor;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->certificateManager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
        $this->compressor = new NfSeCompressor();
    }

    /**
     * Sign XML with digital certificate following DPS → Signature → Send sequence
     */
    public function signXml(string $xmlContent, ?string $certificateId = null): string
    {
        try {
            // 0) Entrada e pré-condições
            // Verificar se XML tem namespace raiz correto e elemento infDPS com Id
            if (strpos($xmlContent, 'xmlns="http://www.sped.fazenda.gov.br/nfse"') === false) {
                throw new Exception(__('XML deve ter namespace raiz: xmlns="http://www.sped.fazenda.gov.br/nfse"', 'wc-nfse'));
            }

            // Load certificate data
            $certificateData = $this->certificateManager->loadCertificateData($certificateId);

            // 1) Normalização + 2) Preparar DOM (otimizado)
            // Usar cleanXmlToDom do NfSeCompressor para limpeza + DOM em uma operação
            $dom = $this->compressor->cleanXmlToDom($xmlContent);

            if (!$dom) {
                // Fallback: criar DOM manualmente se cleanXmlToDom falhar
                $dom = new DOMDocument('1.0', 'UTF-8');
                $dom->formatOutput = false;
                $dom->preserveWhiteSpace = false;
                $dom->loadXML($xmlContent);
            }

            // Localizar <infDPS>, capturar Id
            $infDps = $dom->getElementsByTagName('infDPS')->item(0);
            if (!$infDps) {
                throw new Exception(__('Elemento infDPS não encontrado no XML.', 'wc-nfse'));
            }

            $id = $infDps->getAttribute('Id');
            if (empty($id)) {
                throw new Exception(__('Atributo Id não encontrado no elemento infDPS.', 'wc-nfse'));
            }

            // Marcar no DOM: setIdAttribute('Id', true)
            $infDps->setIdAttribute('Id', true);

            // 3) e 4) Montar a estrutura de assinatura com cálculo seguro do digest
            // Passar o elemento infDPS para cálculo interno do digest (maior segurança)
            $signature = $this->createEnvelopedSignature($dom, $id, $infDps, $certificateData);

            // 7) Montar <Signature> e anexar
            // Anexar <Signature> como filho de <DPS> (enveloped)
            $dpsElement = $dom->getElementsByTagName('DPS')->item(0);
            if (!$dpsElement) {
                throw new Exception(__('Elemento DPS não encontrado no XML.', 'wc-nfse'));
            }
            $dpsElement->appendChild($signature);

            // 8) Saída assinada → não tocar no XML depois (sem pretty print, sem re-serialização)
            $signedXml = $dom->saveXML();

            $this->logger->info('XML assinado digitalmente seguindo sequência DPS → Assinatura → Envio', [
                'certificate_id' => $certificateId,
                'element_id' => $id,
                'signed_xml_size' => strlen($signedXml)
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
     * Create XMLDSig Enveloped signature following v1.00 algorithms
     * All elements must be in XMLDSig namespace
     * Calculates digest internally for enhanced security
     */
    private function createEnvelopedSignature(DOMDocument $dom, string $referenceId, \DOMElement $infDpsElement, array $certificateData): \DOMElement
    {
        $xmldsigNS = 'http://www.w3.org/2000/09/xmldsig#';

        // 4) Canonicalizar o alvo e calcular digest (segurança interna)
        // Alvo: elemento <infDPS> após aplicar o transform enveloped
        $canonicalInfDps = $infDpsElement->C14N(false, false); // C14N 20010315 (inclusive), sem comentários

        // Calcular DigestValue = base64( SHA1( C14N(infDPS) ) )
        $digestValue = base64_encode(hash('sha1', $canonicalInfDps, true));

        // Log do digest calculado internamente (segurança aprimorada)
        $this->logger->debug('Digest calculado internamente no createEnvelopedSignature', [
            'digest_length' => strlen($digestValue),
            'canonical_size' => strlen($canonicalInfDps),
            'reference_id' => $referenceId
        ]);

        // 3) Montar a estrutura de assinatura
        // Tipo: XMLDSig Enveloped (a assinatura dentro do documento)
        $signature = $dom->createElementNS($xmldsigNS, 'Signature');

        // 5) Montar <SignedInfo> - TODOS os elementos no namespace XMLDSig
        $signedInfo = $dom->createElementNS($xmldsigNS, 'SignedInfo');
        $signature->appendChild($signedInfo);

        // CanonicalizationMethod = C14N 20010315 (inclusive)
        $canonicalizationMethod = $dom->createElementNS($xmldsigNS, 'CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonicalizationMethod);

        // SignatureMethod = RSA-SHA1
        $signatureMethod = $dom->createElementNS($xmldsigNS, 'SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($signatureMethod);

        // Uma Reference: URI="#<Id de infDPS>"
        $reference = $dom->createElementNS($xmldsigNS, 'Reference');
        $reference->setAttribute('URI', '#' . $referenceId);
        $signedInfo->appendChild($reference);

        // Transforms da Reference (sobre o <infDPS>)
        $transforms = $dom->createElementNS($xmldsigNS, 'Transforms');
        $reference->appendChild($transforms);

        // Transform 1: http://www.w3.org/2000/09/xmldsig#enveloped-signature
        $transform1 = $dom->createElementNS($xmldsigNS, 'Transform');
        $transform1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform1);

        // Transform 2: C14N 20010315 também como transform (para manter coerência)
        $transform2 = $dom->createElementNS($xmldsigNS, 'Transform');
        $transform2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($transform2);

        // DigestMethod = SHA1
        $digestMethod = $dom->createElementNS($xmldsigNS, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);

        // DigestValue calculado em (4)
        $digestValueElement = $dom->createElementNS($xmldsigNS, 'DigestValue', $digestValue);
        $reference->appendChild($digestValueElement);

        // 6) Assinar o <SignedInfo>
        // Canonicalizar o <SignedInfo> com C14N 20010315 (inclusive)
        $signedInfoCanonical = $signedInfo->C14N(false, false);

        // Assinar com RSA-SHA1 usando a chave privada do A1
        $signatureValue = $this->calculateRsaSha1Signature($signedInfoCanonical, $certificateData['private_key']);

        // SignatureValue = base64( RSA_SHA1( C14N(SignedInfo) ) )
        $signatureValueElement = $dom->createElementNS($xmldsigNS, 'SignatureValue', $signatureValue);
        $signature->appendChild($signatureValueElement);

        // 7) KeyInfo com apenas o certificado do titular (sem cadeia)
        $keyInfo = $this->createKeyInfoWithSingleCertificate($dom, $certificateData['certificate']);
        $signature->appendChild($keyInfo);

        return $signature;
    }

    /**
     * Create KeyInfo element with single certificate (without chain)
     * All elements in XMLDSig namespace
     */
    private function createKeyInfoWithSingleCertificate(DOMDocument $dom, string $certificatePem): \DOMElement
    {
        $xmldsigNS = 'http://www.w3.org/2000/09/xmldsig#';

        $keyInfo = $dom->createElementNS($xmldsigNS, 'KeyInfo');

        // X509Data
        $x509Data = $dom->createElementNS($xmldsigNS, 'X509Data');
        $keyInfo->appendChild($x509Data);

        // X509Certificate - apenas o certificado do titular (sem cadeia)
        // Remove headers and line breaks
        $certificateContent = preg_replace('/-----[^-]+-----/', '', $certificatePem);
        $certificateContent = preg_replace('/\s+/', '', $certificateContent);

        $x509Certificate = $dom->createElementNS($xmldsigNS, 'X509Certificate', $certificateContent);
        $x509Data->appendChild($x509Certificate);

        return $keyInfo;
    }

    /**
     * Create KeyInfo element (legacy method for compatibility)
     */
    private function createKeyInfo(DOMDocument $dom, string $certificatePem): \DOMElement
    {
        return $this->createKeyInfoWithSingleCertificate($dom, $certificatePem);
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
     * Calculate RSA-SHA1 signature value
     */
    private function calculateRsaSha1Signature(string $data, string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if (!$privateKey) {
            throw new Exception(__('Não foi possível carregar a chave privada.', 'wc-nfse'));
        }

        $signature = '';
        if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA1)) {
            throw new Exception(__('Erro ao calcular assinatura RSA-SHA1.', 'wc-nfse'));
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

    /**
     * Prepare signed XML for sending (step 8: compression and base64 encoding)
     * Uses the existing NfSeCompressor service
     * 
     * @param string $signedXml The signed XML content
     * @return string Base64 encoded gzipped XML for dpsXmlGZipB64 field
     */
    public function prepareXmlForSending(string $signedXml): string
    {
        try {
            // 8) Saída assinada → compactação e envio
            // Usar o serviço NfSeCompressor existente
            $dpsXmlGZipB64 = $this->compressor->compressAndEncode($signedXml);

            $this->logger->info('XML preparado para envio usando NfSeCompressor', [
                'original_size' => strlen($signedXml),
                'encoded_size' => strlen($dpsXmlGZipB64)
            ]);

            return $dpsXmlGZipB64;
        } catch (Exception $e) {
            $this->logger->error('Erro ao preparar XML para envio: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sign and prepare XML for sending in one step
     * 
     * @param string $xmlContent Original XML content
     * @param string|null $certificateId Certificate ID to use
     * @return array Array with 'signedXml' and 'dpsXmlGZipB64' keys
     */
    public function signAndPrepareForSending(string $xmlContent, ?string $certificateId = null): array
    {
        try {
            // Sequência completa: DPS → Assinatura → Envio
            $signedXml = $this->signXml($xmlContent, $certificateId);
            $dpsXmlGZipB64 = $this->prepareXmlForSending($signedXml);

            return [
                'signedXml' => $signedXml,
                'dpsXmlGZipB64' => $dpsXmlGZipB64
            ];
        } catch (Exception $e) {
            $this->logger->error('Erro na sequência completa de assinatura e preparação: ' . $e->getMessage());
            throw $e;
        }
    }
}
