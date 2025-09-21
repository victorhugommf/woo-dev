<?php

/**
 * NFSe XSD Validator Service
 *
 * Complete XSD validation against official Brazilian NFS-e schemas v1.00
 * Phase 3 - XSD Validation and Total Compliance
 *
 * @package CloudXM\NFSe\Services
 */

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Utilities\Logger;
use Exception;
use DOMDocument;

/**
 * Class NfSeXsdValidator
 *
 * Handles XSD validation of NFSe XML documents against official Brazilian government schemas
 * Validates DPS, NFSe, and other NFSe document types for compliance
 */
class NfSeXsdValidator
{
    /**
     * Logger instance
     */
    private $logger;

    /**
     * Schemas directory path
     */
    private string $schemasPath;

    /**
     * Available schemas configuration
     */
    private array $schemas;

    /**
     * Plugin constants
     */
    private const SCHEMAS_DIR = '/schemas/xsd/';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->schemasPath = WC_NFSE_PLUGIN_PATH . self::SCHEMAS_DIR;
        $this->setupSchemas();
    }

    /**
     * Setup available schemas
     */
    private function setupSchemas(): void
    {
        $this->schemas = [
            'dps' => [
                'file' => 'DPS_v1.00.xsd',
                'namespace' => 'http://www.sped.fazenda.gov.br/nfse',
                'description' => 'Schema da Declaração de Prestação de Serviços - DPS'
            ],
            'nfse' => [
                'file' => 'NFSe_v1.00.xsd',
                'namespace' => 'http://www.sped.fazenda.gov.br/nfse',
                'description' => 'Schema da Nota Fiscal de Serviços Eletrônica - NFS-e'
            ],
            'evento' => [
                'file' => 'evento_v1.00.xsd',
                'namespace' => 'http://www.sped.fazenda.gov.br/nfse',
                'description' => 'Schema de Eventos da NFS-e'
            ],
            'pedRegEvento' => [
                'file' => 'pedRegEvento_v1.00.xsd',
                'namespace' => 'http://www.sped.fazenda.gov.br/nfse',
                'description' => 'Schema de Pedido de Registro de Evento'
            ],
            'tiposSimples' => [
                'file' => 'tiposSimples_v1.00.xsd',
                'namespace' => 'http://www.sped.fazenda.gov.br/nfse',
                'description' => 'Tipos simples do sistema NFS-e'
            ],
            'tiposComplexos' => [
                'file' => 'tiposComplexos_v1.00.xsd',
                'namespace' => 'http://www.sped.fazenda.gov.br/nfse',
                'description' => 'Tipos complexos do sistema NFS-e'
            ],
            'tiposEventos' => [
                'file' => 'tiposEventos_v1.00.xsd',
                'namespace' => 'http://www.sped.fazenda.gov.br/nfse',
                'description' => 'Tipos de eventos do sistema NFS-e'
            ],
            'xmldsig' => [
                'file' => 'xmldsig-core-schema_v1.00.xsd',
                'namespace' => 'http://www.w3.org/2000/09/xmldsig#',
                'description' => 'Schema de assinatura digital XML'
            ]
        ];
    }

    /**
     * Validate XML against XSD schema
     */
    public function validateXmlAgainstXsd(string $xml, string $schemaType = 'dps'): array
    {
        $validationResult = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'schema_info' => [],
            'performance' => []
        ];

        $startTime = microtime(true);

        try {
            // Check if schema exists
            if (!isset($this->schemas[$schemaType])) {
                throw new Exception("Schema type '$schemaType' not found");
            }

            $schemaInfo = $this->schemas[$schemaType];
            $schemaPath = $this->schemasPath . $schemaInfo['file'];

            // Check if schema file exists
            if (!file_exists($schemaPath)) {
                throw new Exception("Schema file not found: " . $schemaPath);
            }

            $validationResult['schema_info'] = [
                'type' => $schemaType,
                'file' => $schemaInfo['file'],
                'namespace' => $schemaInfo['namespace'],
                'description' => $schemaInfo['description'],
                'path' => $schemaPath,
                'size' => filesize($schemaPath)
            ];

            // Enable user error handling
            libxml_use_internal_errors(true);
            libxml_clear_errors();

            // Create DOMDocument
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            // Load XML
            if (!$dom->loadXML($xml)) {
                $xmlErrors = libxml_get_errors();
                foreach ($xmlErrors as $error) {
                    $validationResult['errors'][] = 'XML Parse Error: ' . trim($error->message);
                }
                libxml_clear_errors();
                return $validationResult;
            }

            // Validate against schema
            $schemaValidationStart = microtime(true);

            if (!$dom->schemaValidate($schemaPath)) {
                $schemaErrors = libxml_get_errors();

                foreach ($schemaErrors as $error) {
                    $errorMessage = trim($error->message);
                    $errorLine = $error->line;
                    $errorColumn = $error->column;

                    $formattedError = sprintf(
                        'Line %d, Column %d: %s',
                        $errorLine,
                        $errorColumn,
                        $errorMessage
                    );

                    // Classify error severity
                    switch ($error->level) {
                        case LIBXML_ERR_WARNING:
                            $validationResult['warnings'][] = $formattedError;
                            break;
                        case LIBXML_ERR_ERROR:
                        case LIBXML_ERR_FATAL:
                        default:
                            $validationResult['errors'][] = $formattedError;
                            break;
                    }
                }

                libxml_clear_errors();
            } else {
                $validationResult['valid'] = true;
            }

            $schemaValidationEnd = microtime(true);

            // Performance metrics
            $endTime = microtime(true);
            $validationResult['performance'] = [
                'total_time' => round(($endTime - $startTime) * 1000, 2), // ms
                'schema_validation_time' => round(($schemaValidationEnd - $schemaValidationStart) * 1000, 2), // ms
                'xml_size' => strlen($xml),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true)
            ];
        } catch (Exception $e) {
            $validationResult['errors'][] = 'XSD Validation Exception: ' . $e->getMessage();

            $endTime = microtime(true);
            $validationResult['performance']['total_time'] = round(($endTime - $startTime) * 1000, 2);
        }

        // Log validation result
        if ($validationResult['valid']) {
            $this->logger->info('XML válido contra schema XSD', [
                'schema_type' => $schemaType,
                'validation_time' => $validationResult['performance']['total_time'] . 'ms',
                'warnings_count' => count($validationResult['warnings'])
            ]);
        } else {
            $this->logger->error('XML inválido contra schema XSD', [
                'schema_type' => $schemaType,
                'errors_count' => count($validationResult['errors']),
                'warnings_count' => count($validationResult['warnings'])
            ]);
        }

        return $validationResult;
    }

    /**
     * Validate DPS XML against official schema
     */
    public function validateDpsXml(string $xml): array
    {
        return $this->validateXmlAgainstXsd($xml, 'dps');
    }

    /**
     * Validate NFSe XML against official schema
     */
    public function validateNfseXml(string $xml): array
    {
        return $this->validateXmlAgainstXsd($xml, 'nfse');
    }

    /**
     * Validate multiple schemas
     */
    public function validateMultipleSchemas(string $xml, array $schemaTypes = ['dps', 'nfse']): array
    {
        $results = [];

        foreach ($schemaTypes as $schemaType) {
            $results[$schemaType] = $this->validateXmlAgainstXsd($xml, $schemaType);
        }

        return $results;
    }

    /**
     * Get schema information
     */
    public function getSchemaInfo(?string $schemaType = null): ?array
    {
        if ($schemaType) {
            return isset($this->schemas[$schemaType]) ? $this->schemas[$schemaType] : null;
        }

        return $this->schemas;
    }

    /**
     * Check schemas availability
     */
    public function checkSchemasAvailability(): array
    {
        $availability = [];

        foreach ($this->schemas as $type => $schema) {
            $schemaPath = $this->schemasPath . $schema['file'];
            $availability[$type] = [
                'available' => file_exists($schemaPath),
                'path' => $schemaPath,
                'size' => file_exists($schemaPath) ? filesize($schemaPath) : 0,
                'readable' => file_exists($schemaPath) && is_readable($schemaPath),
                'modified' => file_exists($schemaPath) ? filemtime($schemaPath) : 0
            ];
        }

        return $availability;
    }

    /**
     * Validate XML structure before XSD validation
     */
    public function validateXmlStructure(string $xml): array
    {
        $structureResult = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'structure_info' => []
        ];

        try {
            libxml_use_internal_errors(true);
            libxml_clear_errors();

            $dom = new DOMDocument();
            if (!$dom->loadXML($xml)) {
                $xmlErrors = libxml_get_errors();
                foreach ($xmlErrors as $error) {
                    $structureResult['errors'][] = 'XML Structure Error: ' . trim($error->message);
                }
                libxml_clear_errors();
                return $structureResult;
            }

            // Get structure information
            $root = $dom->documentElement;
            $structureResult['structure_info'] = [
                'root_element' => $root->nodeName,
                'namespace' => $root->namespaceURI,
                'encoding' => $dom->encoding,
                'version' => $dom->version,
                'element_count' => $dom->getElementsByTagName('*')->length,
                'has_signature' => $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->length > 0
            ];

            // Check for required namespace
            if ($root->namespaceURI !== 'http://www.sped.fazenda.gov.br/nfse') {
                $structureResult['warnings'][] = 'Namespace may be incorrect. Expected: http://www.sped.fazenda.gov.br/nfse, Found: ' . $root->namespaceURI;
            }

            // Check encoding
            if (strtoupper($dom->encoding) !== 'UTF-8') {
                $structureResult['warnings'][] = 'Encoding should be UTF-8. Found: ' . $dom->encoding;
            }

            $structureResult['valid'] = true;
        } catch (Exception $e) {
            $structureResult['errors'][] = 'XML Structure Exception: ' . $e->getMessage();
        }

        return $structureResult;
    }

    /**
     * Generate comprehensive validation report
     */
    public function generateComprehensiveValidationReport(string $xml, array $schemaTypes = ['dps']): array
    {
        $report = [
            'timestamp' => current_time('mysql'),
            'xml_info' => [
                'size' => strlen($xml),
                'hash' => md5($xml)
            ],
            'structure_validation' => $this->validateXmlStructure($xml),
            'schema_validations' => [],
            'summary' => [],
            'recommendations' => []
        ];

        // Validate against each schema
        foreach ($schemaTypes as $schemaType) {
            $report['schema_validations'][$schemaType] = $this->validateXmlAgainstXsd($xml, $schemaType);
        }

        // Generate summary
        $totalErrors = 0;
        $totalWarnings = 0;
        $validSchemas = 0;

        foreach ($report['schema_validations'] as $schemaType => $result) {
            $totalErrors += count($result['errors']);
            $totalWarnings += count($result['warnings']);
            if ($result['valid']) {
                $validSchemas++;
            }
        }

        $totalErrors += count($report['structure_validation']['errors']);
        $totalWarnings += count($report['structure_validation']['warnings']);

        $report['summary'] = [
            'overall_valid' => $totalErrors === 0,
            'schemas_tested' => count($schemaTypes),
            'schemas_valid' => $validSchemas,
            'total_errors' => $totalErrors,
            'total_warnings' => $totalWarnings,
            'compliance_percentage' => $validSchemas > 0 ? round(($validSchemas / count($schemaTypes)) * 100, 2) : 0
        ];

        // Generate recommendations
        if ($totalErrors > 0) {
            $report['recommendations'][] = "Corrigir $totalErrors erro(s) crítico(s) antes de enviar para produção";
        }

        if ($totalWarnings > 0) {
            $report['recommendations'][] = "Revisar $totalWarnings aviso(s) para melhorar qualidade do XML";
        }

        if ($report['summary']['compliance_percentage'] < 100) {
            $report['recommendations'][] = "Validar contra todos os schemas necessários para garantir conformidade total";
        }

        if ($report['summary']['overall_valid']) {
            $report['recommendations'][] = "XML está conforme os schemas XSD oficiais - pronto para envio";
        }

        return $report;
    }

    /**
     * Validate and fix common XML issues
     */
    public function validateAndSuggestFixes(string $xml): array
    {
        $fixes = [
            'applied_fixes' => [],
            'suggested_fixes' => [],
            'fixed_xml' => $xml
        ];

        try {
            // Fix encoding declaration
            if (!preg_match('/^<\?xml[^>]+encoding=["\']UTF-8["\'][^>]*\?>/i', $xml)) {
                $fixes['suggested_fixes'][] = 'Adicionar declaração de encoding UTF-8';
            }

            // Fix namespace declaration
            if (!preg_match('/xmlns=["\']http:\/\/www\.sped\.fazenda\.gov\.br\/nfse["\']/', $xml)) {
                $fixes['suggested_fixes'][] = 'Adicionar namespace correto: http://www.sped.fazenda.gov.br/nfse';
            }

            // Check for required elements
            $requiredElements = ['DPS', 'infDPS', 'tpAmb', 'dhEmi', 'tpEmi'];
            foreach ($requiredElements as $element) {
                if (strpos($xml, "<$element>") === false && strpos($xml, "<$element ") === false) {
                    $fixes['suggested_fixes'][] = "Adicionar elemento obrigatório: $element";
                }
            }

            // Auto-fix common issues
            $fixedXml = $xml;

            // Fix double spaces
            $fixedXml = preg_replace('/\s+/', ' ', $fixedXml);
            if ($fixedXml !== $xml) {
                $fixes['applied_fixes'][] = 'Removidos espaços duplos';
            }

            // Fix line endings
            $fixedXml = str_replace(["\r\n", "\r"], "\n", $fixedXml);
            if ($fixedXml !== $xml) {
                $fixes['applied_fixes'][] = 'Normalizadas quebras de linha';
            }

            $fixes['fixed_xml'] = $fixedXml;
        } catch (Exception $e) {
            $fixes['error'] = 'Error during fix validation: ' . $e->getMessage();
        }

        return $fixes;
    }

    /**
     * Test XSD validator functionality
     */
    public function testXsdValidator(): array
    {
        $testResults = [];

        // Test schema availability
        $availability = $this->checkSchemasAvailability();
        $testResults['schema_availability'] = [
            'success' => true,
            'available_schemas' => array_filter($availability, function ($schema) {
                return $schema['available'];
            }),
            'missing_schemas' => array_filter($availability, function ($schema) {
                return !$schema['available'];
            })
        ];

        // Test with sample valid XML
        $sampleXml = '<?xml version="1.0" encoding="UTF-8"?>
        <DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.00">
            <infDPS id="DPS3550308212345678000195000010000000000000001">
                <tpAmb>2</tpAmb>
                <dhEmi>2025-01-09T10:30:00Z</dhEmi>
                <tpEmi>1</tpEmi>
                <nDPS>000000000000001</nDPS>
                <cDPS>1</cDPS>
                <serie>00001</serie>
                <prest>
                    <tpInsc>2</tpInsc>
                    <nInsc>12345678000195</nInsc>
                    <IM>123456</IM>
                    <xNome>Empresa Teste LTDA</xNome>
                </prest>
                <tom>
                    <tpInsc>1</tpInsc>
                    <nInsc>12345678901</nInsc>
                    <xNome>João Silva</xNome>
                </tom>
                <serv>
                    <cTribNac>010101</cTribNac>
                    <xTribNac>Análise e desenvolvimento de sistemas</xTribNac>
                    <cLocIncid>3550308</cLocIncid>
                    <xDisc>Prestação de serviços de desenvolvimento de software</xDisc>
                    <vServ>1000.00</vServ>
                    <vISS>50.00</vISS>
                    <vLiq>950.00</vLiq>
                </serv>
            </infDPS>
        </DPS>';

        // Test structure validation
        $structureResult = $this->validateXmlStructure($sampleXml);
        $testResults['structure_validation'] = [
            'success' => $structureResult['valid'],
            'errors' => $structureResult['errors'],
            'warnings' => $structureResult['warnings'],
            'structure_info' => $structureResult['structure_info']
        ];

        // Test XSD validation if DPS schema is available
        if ($availability['dps']['available']) {
            $xsdResult = $this->validateDpsXml($sampleXml);
            $testResults['xsd_validation'] = [
                'success' => $xsdResult['valid'],
                'errors' => $xsdResult['errors'],
                'warnings' => $xsdResult['warnings'],
                'performance' => $xsdResult['performance']
            ];
        } else {
            $testResults['xsd_validation'] = [
                'success' => false,
                'error' => 'DPS schema not available for testing'
            ];
        }

        // Test comprehensive report
        if ($availability['dps']['available']) {
            $comprehensiveReport = $this->generateComprehensiveValidationReport($sampleXml, ['dps']);
            $testResults['comprehensive_report'] = [
                'success' => isset($comprehensiveReport['summary']),
                'summary' => $comprehensiveReport['summary'],
                'recommendations_count' => count($comprehensiveReport['recommendations'])
            ];
        }

        return $testResults;
    }
}
