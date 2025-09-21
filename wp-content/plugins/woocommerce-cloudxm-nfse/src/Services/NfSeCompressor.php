<?php
/**
 * NFSe Compressor Service
 *
 * Handles GZip + Base64 compression/decompression for NFS-e API communication
 *
 * @package CloudXM\NFSe\Services
 */

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Utilities\Logger;
use DOMDocument;
use DOMXPath;
use Exception;

/**
 * Class NfSeCompressor
 *
 * Handles XML compression and decompression with Base64 encoding for API transmission
 * following Brazilian NFS-e standards (GZip compression + Base64 encoding)
 */
class NfSeCompressor
{
    /**
     * Logger instance
     */
    private $logger;

    /**
     * Compression settings
     */
    private int $compressionLevel = 9; // Maximum compression
    private float $maxSizeMb = 1.0; // 1MB limit as per gov.br specs

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }

    /**
     * Compress and encode XML for API transmission
     */
    public function compressAndEncode(string $xmlContent): string
    {
        try {
            // Validate input
            if (empty($xmlContent)) {
                throw new Exception(__('Conteúdo XML vazio para compactação.', 'wc-nfse'));
            }

            // Check original size
            $originalSize = strlen($xmlContent);
            $maxSizeBytes = $this->maxSizeMb * 1024 * 1024;

            if ($originalSize > $maxSizeBytes) {
                throw new Exception(sprintf(
                    __('XML muito grande: %s bytes. Limite: %s bytes.', 'wc-nfse'),
                    number_format($originalSize),
                    number_format($maxSizeBytes)
                ));
            }

            // Clean XML before compression
            $cleanedXml = $this->cleanXml($xmlContent);

            // Compress using GZip
            $compressed = gzcompress($cleanedXml, $this->compressionLevel);

            if ($compressed === false) {
                throw new Exception(__('Falha na compactação GZip do XML.', 'wc-nfse'));
            }

            // Encode to Base64
            $encoded = base64_encode($compressed);

            // Validate final size
            $finalSize = strlen($encoded);
            if ($finalSize > $maxSizeBytes) {
                throw new Exception(sprintf(
                    __('XML compactado muito grande: %s bytes. Limite: %s bytes.', 'wc-nfse'),
                    number_format($finalSize),
                    number_format($maxSizeBytes)
                ));
            }

            // Log compression statistics
            $compressionRatio = ($originalSize > 0) ? (($originalSize - strlen($compressed)) / $originalSize) * 100 : 0;

            $this->logger->info('XML compactado com sucesso', [
                'original_size' => $originalSize,
                'compressed_size' => strlen($compressed),
                'encoded_size' => $finalSize,
                'compression_ratio' => round($compressionRatio, 2) . '%',
                'compression_level' => $this->compressionLevel
            ]);

            return $encoded;

        } catch (Exception $e) {
            $this->logger->error('Erro na compactação do XML: ' . $e->getMessage(), [
                'original_size' => strlen($xmlContent)
            ]);
            throw $e;
        }
    }

    /**
     * Decode and decompress XML from API response
     */
    public function decodeAndDecompress(string $encodedContent): string
    {
        try {
            // Validate input
            if (empty($encodedContent)) {
                throw new Exception(__('Conteúdo codificado vazio para descompactação.', 'wc-nfse'));
            }

            // Decode from Base64
            $compressed = base64_decode($encodedContent, true);

            if ($compressed === false) {
                throw new Exception(__('Falha na decodificação Base64.', 'wc-nfse'));
            }

            // Decompress using GZip
            $decompressed = gzuncompress($compressed);

            if ($decompressed === false) {
                throw new Exception(__('Falha na descompactação GZip.', 'wc-nfse'));
            }

            // Validate XML
            if (!$this->isValidXml($decompressed)) {
                throw new Exception(__('XML descompactado é inválido.', 'wc-nfse'));
            }

            $this->logger->info('XML descompactado com sucesso', [
                'encoded_size' => strlen($encodedContent),
                'compressed_size' => strlen($compressed),
                'decompressed_size' => strlen($decompressed)
            ]);

            return $decompressed;

        } catch (Exception $e) {
            $this->logger->error('Erro na descompactação do XML: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clean XML for optimal compression
     */
    private function cleanXml(string $xmlContent): string
    {
        try {
            // Load XML
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;

            if (!$dom->loadXML($xmlContent)) {
                throw new Exception(__('XML inválido para limpeza.', 'wc-nfse'));
            }

            // Remove unnecessary whitespace and comments
            $xpath = new DOMXPath($dom);

            // Remove comments
            $comments = $xpath->query('//comment()');
            foreach ($comments as $comment) {
                $comment->parentNode->removeChild($comment);
            }

            // Remove empty text nodes
            $this->removeEmptyTextNodes($dom->documentElement);

            // Return cleaned XML
            return $dom->saveXML();

        } catch (Exception $e) {
            $this->logger->warning('Falha na limpeza do XML, usando original: ' . $e->getMessage());
            return $xmlContent;
        }
    }

    /**
     * Remove empty text nodes recursively
     */
    private function removeEmptyTextNodes(\DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                if (trim($child->nodeValue) === '') {
                    $node->removeChild($child);
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $this->removeEmptyTextNodes($child);
            }
        }
    }

    /**
     * Validate XML content
     */
    private function isValidXml(string $xmlContent): bool
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $result = $dom->loadXML($xmlContent);
        libxml_clear_errors();
        return $result !== false;
    }

    /**
     * Get compression statistics
     */
    public function getCompressionStats(string $xmlContent): ?array
    {
        try {
            $originalSize = strlen($xmlContent);
            $cleanedXml = $this->cleanXml($xmlContent);
            $cleanedSize = strlen($cleanedXml);

            $compressed = gzcompress($cleanedXml, $this->compressionLevel);
            $compressedSize = strlen($compressed);

            $encoded = base64_encode($compressed);
            $encodedSize = strlen($encoded);

            return [
                'original_size' => $originalSize,
                'cleaned_size' => $cleanedSize,
                'compressed_size' => $compressedSize,
                'encoded_size' => $encodedSize,
                'cleaning_ratio' => $originalSize > 0 ? (($originalSize - $cleanedSize) / $originalSize) * 100 : 0,
                'compression_ratio' => $cleanedSize > 0 ? (($cleanedSize - $compressedSize) / $cleanedSize) * 100 : 0,
                'total_reduction' => $originalSize > 0 ? (($originalSize - $encodedSize) / $originalSize) * 100 : 0,
                'within_limits' => $encodedSize <= ($this->maxSizeMb * 1024 * 1024)
            ];

        } catch (Exception $e) {
            $this->logger->error('Erro ao calcular estatísticas de compactação: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Test compression with sample data
     */
    public function testCompression(?string $sampleXml = null): array
    {
        try {
            if (!$sampleXml) {
                $sampleXml = $this->getSampleXml();
            }

            $startTime = microtime(true);

            // Test compression
            $encoded = $this->compressAndEncode($sampleXml);

            // Test decompression
            $decoded = $this->decodeAndDecompress($encoded);

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

            // Verify integrity
            $integrityCheck = ($sampleXml === $decoded);

            $stats = $this->getCompressionStats($sampleXml);

            return [
                'success' => true,
                'integrity_check' => $integrityCheck,
                'execution_time_ms' => round($executionTime, 2),
                'stats' => $stats,
                'encoded_length' => strlen($encoded),
                'message' => $integrityCheck ?
                    __('Teste de compactação bem-sucedido', 'wc-nfse') :
                    __('Falha na verificação de integridade', 'wc-nfse')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'execution_time_ms' => 0,
                'stats' => null
            ];
        }
    }

    /**
     * Get sample XML for testing
     */
    private function getSampleXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
        <DPS xmlns="http://www.nfse.gov.br/schema/dps_v1.xsd">
            <InfDPS Id="DPS000000000000001">
                <IdentificacaoDPS>
                    <Numero>1</Numero>
                    <DataEmissao>2025-01-09T10:30:00</DataEmissao>
                    <Competencia>2025-01</Competencia>
                </IdentificacaoDPS>
                <Prestador>
                    <IdentificacaoPrestador>
                        <CpfCnpj>
                            <Cnpj>12345678000195</Cnpj>
                        </CpfCnpj>
                        <InscricaoMunicipal>123456</InscricaoMunicipal>
                    </IdentificacaoPrestador>
                    <RazaoSocial>Empresa Teste LTDA</RazaoSocial>
                    <Endereco>
                        <Endereco>Rua Teste, 123</Endereco>
                        <Numero>123</Numero>
                        <Bairro>Centro</Bairro>
                        <CodigoMunicipio>3550308</CodigoMunicipio>
                        <Uf>SP</Uf>
                        <Cep>01000000</Cep>
                    </Endereco>
                </Prestador>
                <Tomador>
                    <IdentificacaoTomador>
                        <CpfCnpj>
                            <Cpf>12345678901</Cpf>
                        </CpfCnpj>
                    </IdentificacaoTomador>
                    <RazaoSocial>Cliente Teste</RazaoSocial>
                </Tomador>
                <Servico>
                    <Valores>
                        <ValorServicos>1000.00</ValorServicos>
                        <ValorIss>50.00</ValorIss>
                        <ValorLiquidoNfse>950.00</ValorLiquidoNfse>
                    </Valores>
                    <ItemListaServico>01.01</ItemListaServico>
                    <Discriminacao>Desenvolvimento de software personalizado para gestão empresarial com integração de sistemas legados e implementação de novas funcionalidades.</Discriminacao>
                    <CodigoMunicipio>3550308</CodigoMunicipio>
                </Servico>
            </InfDPS>
        </DPS>';
    }

    /**
     * Set compression level
     */
    public function setCompressionLevel(int $level): void
    {
        if ($level >= 1 && $level <= 9) {
            $this->compressionLevel = $level;
            $this->logger->info('Nível de compactação alterado para: ' . $level);
        } else {
            throw new \InvalidArgumentException(__('Nível de compactação deve ser entre 1 e 9.', 'wc-nfse'));
        }
    }

    /**
     * Get compression level
     */
    public function getCompressionLevel(): int
    {
        return $this->compressionLevel;
    }

    /**
     * Set maximum size limit
     */
    public function setMaxSizeMb(float $sizeMb): void
    {
        if ($sizeMb > 0 && $sizeMb <= 10) {
            $this->maxSizeMb = $sizeMb;
            $this->logger->info('Limite de tamanho alterado para: ' . $sizeMb . 'MB');
        } else {
            throw new \InvalidArgumentException(__('Tamanho máximo deve ser entre 0.1 e 10 MB.', 'wc-nfse'));
        }
    }

    /**
     * Get maximum size limit
     */
    public function getMaxSizeMb(): float
    {
        return $this->maxSizeMb;
    }

    /**
     * Compress multiple XMLs in batch
     */
    public function batchCompress(array $xmlArray): array
    {
        $results = [];
        $totalStartTime = microtime(true);

        foreach ($xmlArray as $index => $xmlContent) {
            try {
                $startTime = microtime(true);
                $encoded = $this->compressAndEncode($xmlContent);
                $endTime = microtime(true);

                $results[$index] = [
                    'success' => true,
                    'encoded_content' => $encoded,
                    'original_size' => strlen($xmlContent),
                    'encoded_size' => strlen($encoded),
                    'execution_time_ms' => round(($endTime - $startTime) * 1000, 2)
                ];

            } catch (Exception $e) {
                $results[$index] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'original_size' => strlen($xmlContent),
                    'encoded_size' => 0,
                    'execution_time_ms' => 0
                ];
            }
        }

        $totalEndTime = microtime(true);
        $totalExecutionTime = ($totalEndTime - $totalStartTime) * 1000;

        $this->logger->info('Compactação em lote concluída', [
            'total_items' => count($xmlArray),
            'successful' => count(array_filter($results, function($r) { return $r['success']; })),
            'failed' => count(array_filter($results, function($r) { return !$r['success']; })),
            'total_execution_time_ms' => round($totalExecutionTime, 2)
        ]);

        return $results;
    }

    /**
     * Get compression recommendations
     */
    public function getCompressionRecommendations(string $xmlContent): array
    {
        $stats = $this->getCompressionStats($xmlContent);

        if (!$stats) {
            return [];
        }

        $recommendations = [];

        // Size recommendations
        if (!$stats['within_limits']) {
            $recommendations[] = [
                'type' => 'error',
                'message' => __('XML excede o limite de 1MB após compactação. Considere reduzir o conteúdo.', 'wc-nfse')
            ];
        }

        // Compression efficiency
        if ($stats['compression_ratio'] < 50) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => __('Taxa de compactação baixa. Verifique se o XML contém dados redundantes.', 'wc-nfse')
            ];
        }

        // Cleaning efficiency
        if ($stats['cleaning_ratio'] > 10) {
            $recommendations[] = [
                'type' => 'info',
                'message' => __('XML contém muito espaço em branco. A limpeza melhorou significativamente o tamanho.', 'wc-nfse')
            ];
        }

        // Performance recommendations
        if ($stats['original_size'] > 500000) { // 500KB
            $recommendations[] = [
                'type' => 'warning',
                'message' => __('XML muito grande pode impactar a performance. Considere otimizar o conteúdo.', 'wc-nfse')
            ];
        }

        return $recommendations;
    }

    /**
     * Validate compression requirements
     */
    public function validateCompressionRequirements(): array
    {
        $requirements = [
            'gzip_available' => function_exists('gzcompress') && function_exists('gzuncompress'),
            'base64_available' => function_exists('base64_encode') && function_exists('base64_decode'),
            'dom_available' => class_exists('DOMDocument'),
            'libxml_available' => function_exists('libxml_use_internal_errors')
        ];

        $allMet = array_reduce($requirements, function($carry, $item) {
            return $carry && $item;
        }, true);

        $missingReqs = array_keys(array_filter($requirements, function($met) {
            return !$met;
        }));

        return [
            'requirements_met' => $allMet,
            'details' => $requirements,
            'missing' => $missingReqs
        ];
    }
}