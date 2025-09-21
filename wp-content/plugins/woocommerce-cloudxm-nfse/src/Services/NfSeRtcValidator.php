<?php
/**
 * NFSe RTC Validator Service
 *
 * RTC-compliant validation service for NFS-e DPS operations
 *
 * @package CloudXM\NFSe\Services
 */

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Utilities\Logger;
use Exception;
use DOMDocument;
use DOMXPath;
use DateTime;

/**
 * Class NfSeRtcValidator
 *
 * Handles RTC-compliant validation for NFS-e DPS XML according to
 * Brazilian national standard requirements (layout v01.01.01)
 */
class NfSeRtcValidator
{
    /**
     * Logger instance
     */
    protected $logger;

    /**
     * RTC validation rules
     */
    private $rtc_rules;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->setupRtcRules();
    }

    /**
     * Setup RTC validation rules
     */
    private function setupRtcRules(): void
    {
        $this->rtc_rules = [
            'structure' => [
                'root_element' => 'NFSe',
                'namespace' => 'http://www.nfse.gov.br/schema/nfse_v1.xsd',
                'required_path' => 'NFSe/infNFSe/DPS/infDPS'
            ],
            'dps_id' => [
                'length' => 45,
                'pattern' => '/^DPS\d{42}$/',
                'format' => 'DPS + CódMun(7) + TipoInsc(1) + InscFed(14) + Série(5) + Número(15)'
            ],
            'required_fields' => [
                'infDPS' => ['id'],
                'infDPS_content' => ['tpAmb', 'dhEmi', 'tpEmi', 'nDPS', 'prest', 'tom', 'serv'],
                'prestador' => ['tpInsc', 'nInsc', 'IM', 'xNome', 'end'],
                'tomador' => ['tpInsc', 'xNome'],
                'servico' => ['cTribNac', 'xTribNac', 'cLocIncid', 'xDisc', 'vServ', 'vISS', 'vLiq']
            ],
            'field_formats' => [
                'tpAmb' => ['type' => 'numeric', 'values' => [1, 2]],
                'dhEmi' => ['type' => 'datetime', 'format' => 'Y-m-d\TH:i:s\Z'],
                'tpEmi' => ['type' => 'numeric', 'values' => [1, 2, 3]],
                'nDPS' => ['type' => 'numeric', 'length' => 15],
                'tpInsc' => ['type' => 'numeric', 'values' => [1, 2]],
                'CEP' => ['type' => 'numeric', 'length' => 8],
                'cMun' => ['type' => 'numeric', 'length' => 7]
            ],
            'field_lengths' => [
                'xNome' => 115,
                'xFant' => 60,
                'xLgr' => 255,
                'nro' => 60,
                'xCpl' => 156,
                'xBairro' => 60,
                'xMun' => 60,
                'email' => 80,
                'xDisc' => 2000,
                'xTribNac' => 600
            ]
        ];
    }

    /**
     * Validate DPS XML against RTC specifications
     */
    public function validateDpsXml(string $xml): array
    {
        $validation_result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'details' => []
        ];

        try {
            // Load XML
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);

            if (!$dom->loadXML($xml)) {
                $xml_errors = libxml_get_errors();
                foreach ($xml_errors as $error) {
                    $validation_result['errors'][] = 'XML Parse Error: ' . trim($error->message);
                }
                $validation_result['valid'] = false;
                return $validation_result;
            }

            $xpath = new DOMXPath($dom);

            // Validate structure
            $structure_result = $this->validateXmlStructure($xpath);
            $validation_result = $this->mergeValidationResults($validation_result, $structure_result);

            // Validate DPS ID
            $id_result = $this->validateDpsId($xpath);
            $validation_result = $this->mergeValidationResults($validation_result, $id_result);

            // Validate required fields
            $fields_result = $this->validateRequiredFields($xpath);
            $validation_result = $this->mergeValidationResults($validation_result, $fields_result);

            // Validate field formats
            $formats_result = $this->validateFieldFormats($xpath);
            $validation_result = $this->mergeValidationResults($validation_result, $formats_result);

            // Validate field lengths
            $lengths_result = $this->validateFieldLengths($xpath);
            $validation_result = $this->mergeValidationResults($validation_result, $lengths_result);

            // Validate business rules
            $business_result = $this->validateBusinessRules($xpath);
            $validation_result = $this->mergeValidationResults($validation_result, $business_result);

        } catch (Exception $e) {
            $validation_result['valid'] = false;
            $validation_result['errors'][] = 'Validation Exception: ' . $e->getMessage();
        }

        // Log validation result
        if ($validation_result['valid']) {
            $this->logger->info('DPS XML válido conforme RTC', [
                'warnings_count' => count($validation_result['warnings'])
            ]);
        } else {
            $this->logger->error('DPS XML inválido conforme RTC', [
                'errors_count' => count($validation_result['errors']),
                'errors' => $validation_result['errors']
            ]);
        }

        return $validation_result;
    }

    /**
     * Validate XML structure
     */
    private function validateXmlStructure(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        // Check root element
        $root_nodes = $xpath->query('/NFSe');
        if ($root_nodes->length === 0) {
            $result['valid'] = false;
            $result['errors'][] = 'Elemento raiz deve ser "NFSe"';
        } else {
            // Check namespace
            $root_element = $root_nodes->item(0);
            $namespace = $root_element->getAttribute('xmlns');
            if ($namespace !== $this->rtc_rules['structure']['namespace']) {
                $result['warnings'][] = 'Namespace pode estar incorreto: ' . $namespace;
            }
        }

        // Check required path
        $path_nodes = $xpath->query($this->rtc_rules['structure']['required_path']);
        if ($path_nodes->length === 0) {
            $result['valid'] = false;
            $result['errors'][] = 'Estrutura XML inválida: caminho ' . $this->rtc_rules['structure']['required_path'] . ' não encontrado';
        }

        return $result;
    }

    /**
     * Validate DPS ID
     */
    private function validateDpsId(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        $inf_dps_nodes = $xpath->query('/NFSe/infNFSe/DPS/infDPS');
        if ($inf_dps_nodes->length === 0) {
            $result['valid'] = false;
            $result['errors'][] = 'Elemento infDPS não encontrado';
            return $result;
        }

        $id_attr = $inf_dps_nodes->item(0)->getAttribute('id');

        // Check length
        if (strlen($id_attr) !== $this->rtc_rules['dps_id']['length']) {
            $result['valid'] = false;
            $result['errors'][] = sprintf(
                'DPS ID deve ter %d caracteres. Encontrado: %d caracteres (%s)',
                $this->rtc_rules['dps_id']['length'],
                strlen($id_attr),
                $id_attr
            );
        }

        // Check pattern
        if (!preg_match($this->rtc_rules['dps_id']['pattern'], $id_attr)) {
            $result['valid'] = false;
            $result['errors'][] = 'DPS ID não segue o padrão: ' . $this->rtc_rules['dps_id']['format'];
        }

        // Validate ID components
        if (strlen($id_attr) === 45) {
            $components = $this->parseDpsId($id_attr);
            $component_result = $this->validateDpsIdComponents($components);
            $result = $this->mergeValidationResults($result, $component_result);
        }

        return $result;
    }

    /**
     * Parse DPS ID components
     */
    private function parseDpsId(string $dps_id): array
    {
        return [
            'prefix' => substr($dps_id, 0, 3),      // DPS
            'cod_mun' => substr($dps_id, 3, 7),     // Código município
            'tipo_insc' => substr($dps_id, 10, 1),  // Tipo inscrição
            'insc_fed' => substr($dps_id, 11, 14),  // Inscrição federal
            'serie' => substr($dps_id, 25, 5),      // Série
            'numero' => substr($dps_id, 30, 15)     // Número
        ];
    }

    /**
     * Validate DPS ID components
     */
    private function validateDpsIdComponents(array $components): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        // Validate prefix
        if ($components['prefix'] !== 'DPS') {
            $result['valid'] = false;
            $result['errors'][] = 'DPS ID deve começar com "DPS"';
        }

        // Validate municipality code
        if (!preg_match('/^\d{7}$/', $components['cod_mun'])) {
            $result['valid'] = false;
            $result['errors'][] = 'Código município deve ter 7 dígitos numéricos';
        }

        // Validate inscription type
        if (!in_array($components['tipo_insc'], ['1', '2'])) {
            $result['valid'] = false;
            $result['errors'][] = 'Tipo inscrição deve ser 1 (CPF) ou 2 (CNPJ)';
        }

        // Validate federal inscription
        if (!preg_match('/^\d{14}$/', $components['insc_fed'])) {
            $result['valid'] = false;
            $result['errors'][] = 'Inscrição federal deve ter 14 dígitos numéricos';
        } else {
            // Validate CPF/CNPJ
            if ($components['tipo_insc'] === '1') {
                // CPF validation
                $cpf = $components['insc_fed'];
                if (!$this->validateCpf($cpf)) {
                    $result['warnings'][] = 'CPF pode estar inválido: ' . $cpf;
                }
            } else {
                // CNPJ validation
                $cnpj = $components['insc_fed'];
                if (!$this->validateCnpj($cnpj)) {
                    $result['warnings'][] = 'CNPJ pode estar inválido: ' . $cnpj;
                }
            }
        }

        // Validate series
        if (!preg_match('/^\d{5}$/', $components['serie'])) {
            $result['valid'] = false;
            $result['errors'][] = 'Série deve ter 5 dígitos numéricos';
        }

        // Validate number
        if (!preg_match('/^\d{15}$/', $components['numero'])) {
            $result['valid'] = false;
            $result['errors'][] = 'Número deve ter 15 dígitos numéricos';
        }

        return $result;
    }

    /**
     * Validate required fields
     */
    private function validateRequiredFields(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        foreach ($this->rtc_rules['required_fields'] as $section => $fields) {
            $section_result = $this->validateSectionFields($xpath, $section, $fields);
            $result = $this->mergeValidationResults($result, $section_result);
        }

        return $result;
    }

    /**
     * Validate section fields
     */
    private function validateSectionFields(DOMXPath $xpath, string $section, array $fields): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        $base_path = $this->getSectionBasePath($section);

        foreach ($fields as $field) {
            $field_path = $base_path . '/' . $field;
            $field_nodes = $xpath->query($field_path);

            if ($field_nodes->length === 0) {
                $result['valid'] = false;
                $result['errors'][] = sprintf('Campo obrigatório ausente: %s (%s)', $field, $section);
            } else {
                // Check if field has content
                $field_value = trim($field_nodes->item(0)->textContent);
                if (empty($field_value) && $field !== 'id') {
                    $result['warnings'][] = sprintf('Campo obrigatório vazio: %s (%s)', $field, $section);
                }
            }
        }

        return $result;
    }

    /**
     * Get section base path
     */
    private function getSectionBasePath(string $section): string
    {
        $paths = [
            'infDPS' => '/NFSe/infNFSe/DPS/infDPS',
            'infDPS_content' => '/NFSe/infNFSe/DPS/infDPS',
            'prestador' => '/NFSe/infNFSe/DPS/infDPS/prest',
            'tomador' => '/NFSe/infNFSe/DPS/infDPS/tom',
            'servico' => '/NFSe/infNFSe/DPS/infDPS/serv'
        ];

        return $paths[$section] ?? '/NFSe/infNFSe/DPS/infDPS';
    }

    /**
     * Validate field formats
     */
    private function validateFieldFormats(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        foreach ($this->rtc_rules['field_formats'] as $field => $rules) {
            $format_result = $this->validateFieldFormat($xpath, $field, $rules);
            $result = $this->mergeValidationResults($result, $format_result);
        }

        return $result;
    }

    /**
     * Validate field format
     */
    private function validateFieldFormat(DOMXPath $xpath, string $field, array $rules): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        $field_nodes = $xpath->query("//*[local-name()='$field']");

        foreach ($field_nodes as $node) {
            $value = trim($node->textContent);

            if (empty($value)) {
                continue;
            }

            switch ($rules['type']) {
                case 'numeric':
                    if (!is_numeric($value)) {
                        $result['valid'] = false;
                        $result['errors'][] = sprintf('Campo %s deve ser numérico: %s', $field, $value);
                    }

                    if (isset($rules['values']) && !in_array((int)$value, $rules['values'])) {
                        $result['valid'] = false;
                        $result['errors'][] = sprintf('Campo %s deve ter um dos valores: %s. Encontrado: %s',
                            $field, implode(', ', $rules['values']), $value);
                    }

                    if (isset($rules['length']) && strlen($value) !== $rules['length']) {
                        $result['valid'] = false;
                        $result['errors'][] = sprintf('Campo %s deve ter %d dígitos. Encontrado: %d (%s)',
                            $field, $rules['length'], strlen($value), $value);
                    }
                    break;

                case 'datetime':
                    $datetime = DateTime::createFromFormat($rules['format'], $value);
                    if (!$datetime || $datetime->format($rules['format']) !== $value) {
                        $result['valid'] = false;
                        $result['errors'][] = sprintf('Campo %s deve estar no formato %s. Encontrado: %s',
                            $field, $rules['format'], $value);
                    }
                    break;
            }
        }

        return $result;
    }

    /**
     * Validate field lengths
     */
    private function validateFieldLengths(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        foreach ($this->rtc_rules['field_lengths'] as $field => $max_length) {
            $length_result = $this->validateFieldLength($xpath, $field, $max_length);
            $result = $this->mergeValidationResults($result, $length_result);
        }

        return $result;
    }

    /**
     * Validate field length
     */
    private function validateFieldLength(DOMXPath $xpath, string $field, int $max_length): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        $field_nodes = $xpath->query("//*[local-name()='$field']");

        foreach ($field_nodes as $node) {
            $value = $node->textContent;
            $length = strlen($value);

            if ($length > $max_length) {
                $result['valid'] = false;
                $result['errors'][] = sprintf('Campo %s excede tamanho máximo de %d caracteres. Encontrado: %d',
                    $field, $max_length, $length);
            }
        }

        return $result;
    }

    /**
     * Validate business rules
     */
    public function validateBusinessRules(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        // Rule: Prestador must have CNPJ if tpInsc = 2
        $prest_tipo_nodes = $xpath->query('/NFSe/infNFSe/DPS/infDPS/prest/tpInsc');
        $prest_insc_nodes = $xpath->query('/NFSe/infNFSe/DPS/infDPS/prest/nInsc');

        if ($prest_tipo_nodes->length > 0 && $prest_insc_nodes->length > 0) {
            $tipo = $prest_tipo_nodes->item(0)->textContent;
            $inscricao = $prest_insc_nodes->item(0)->textContent;

            if ($tipo === '2' && strlen($inscricao) !== 14) {
                $result['valid'] = false;
                $result['errors'][] = 'Prestador com tpInsc=2 deve ter CNPJ (14 dígitos)';
            } elseif ($tipo === '1' && strlen($inscricao) !== 11) {
                $result['valid'] = false;
                $result['errors'][] = 'Prestador com tpInsc=1 deve ter CPF (11 dígitos)';
            }
        }

        // Rule: Tomador validation
        $tom_tipo_nodes = $xpath->query('/NFSe/infNFSe/DPS/infDPS/tom/tpInsc');
        $tom_insc_nodes = $xpath->query('/NFSe/infNFSe/DPS/infDPS/tom/nInsc');

        if ($tom_tipo_nodes->length > 0 && $tom_insc_nodes->length > 0) {
            $tipo = $tom_tipo_nodes->item(0)->textContent;
            $inscricao = $tom_insc_nodes->item(0)->textContent;

            if ($tipo === '2' && strlen($inscricao) !== 14) {
                $result['valid'] = false;
                $result['errors'][] = 'Tomador com tpInsc=2 deve ter CNPJ (14 dígitos)';
            } elseif ($tipo === '1' && strlen($inscricao) !== 11) {
                $result['valid'] = false;
                $result['errors'][] = 'Tomador com tpInsc=1 deve ter CPF (11 dígitos)';
            }
        }

        // Rule: Service values consistency
        $vserv_nodes = $xpath->query('/NFSe/infNFSe/DPS/infDPS/serv/vServ');
        $viss_nodes = $xpath->query('/NFSe/infNFSe/DPS/infDPS/serv/vISS');
        $vliq_nodes = $xpath->query('/NFSe/infNFSe/DPS/infDPS/serv/vLiq');

        if ($vserv_nodes->length > 0 && $viss_nodes->length > 0 && $vliq_nodes->length > 0) {
            $vserv = (float) $vserv_nodes->item(0)->textContent;
            $viss = (float) $viss_nodes->item(0)->textContent;
            $vliq = (float) $vliq_nodes->item(0)->textContent;

            $calculated_vliq = $vserv - $viss;

            if (abs($vliq - $calculated_vliq) > 0.01) {
                $result['warnings'][] = sprintf('Inconsistência nos valores: vLiq (%.2f) ≠ vServ (%.2f) - vISS (%.2f) = %.2f',
                    $vliq, $vserv, $viss, $calculated_vliq);
            }
        }

        return $result;
    }

    /**
     * Validate CPF
     */
    private function validateCpf(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate CNPJ
     */
    private function validateCnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }

        $resto = $soma % 11;

        if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }

        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }

        $resto = $soma % 11;

        return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
    }

    /**
     * Merge validation results
     */
    public function mergeValidationResults(array $result1, array $result2): array
    {
        return [
            'valid' => $result1['valid'] && $result2['valid'],
            'errors' => array_merge($result1['errors'], $result2['errors']),
            'warnings' => array_merge($result1['warnings'], $result2['warnings'])
        ];
    }

    /**
     * Generate validation report
     */
    public function generateValidationReport(array $validation_result): array
    {
        $report = [
            'summary' => [
                'valid' => $validation_result['valid'],
                'total_errors' => count($validation_result['errors']),
                'total_warnings' => count($validation_result['warnings']),
                'status' => $validation_result['valid'] ? 'CONFORME RTC' : 'NÃO CONFORME RTC'
            ],
            'errors' => $validation_result['errors'],
            'warnings' => $validation_result['warnings'],
            'recommendations' => $this->generateRecommendations($validation_result)
        ];

        return $report;
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations(array $validation_result): array
    {
        $recommendations = [];

        if (!$validation_result['valid']) {
            $recommendations[] = 'Corrija todos os erros antes de enviar para a API gov.br';

            if (count($validation_result['errors']) > 5) {
                $recommendations[] = 'Muitos erros encontrados. Revise a implementação do gerador DPS';
            }
        }

        if (count($validation_result['warnings']) > 0) {
            $recommendations[] = 'Revise os avisos para garantir qualidade dos dados';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'DPS está conforme o layout RTC. Pode ser enviado para a API';
        }

        return $recommendations;
    }

    /**
     * Test RTC validator
     */
    public function testValidator(): array
    {
        $test_results = [];

        // Test valid DPS ID
        $valid_id = 'DPS3550308212345678000195000010000000000000001';
        $components = $this->parseDpsId($valid_id);
        $test_results['dps_id_parsing'] = [
            'success' => $components['prefix'] === 'DPS',
            'components' => $components
        ];

        // Test CPF validation
        $test_results['cpf_validation'] = [
            'valid_cpf' => $this->validateCpf('12345678901'),
            'invalid_cpf' => !$this->validateCpf('11111111111')
        ];

        // Test CNPJ validation
        $test_results['cnpj_validation'] = [
            'valid_cnpj' => $this->validateCnpj('11222333000181'),
            'invalid_cnpj' => !$this->validateCnpj('11111111111111')
        ];

        return $test_results;
    }
}