<?php
/**
 * NFSe RTC Validator Complete Service
 *
 * Comprehensive validation service for all mandatory NFSe DPS fields according to RTC v1.01.01
 * Phase 2 - Complete Implementation
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
 * Class NfSeRtcValidatorComplete
 *
 * Extends RTC validator with complete validation for all mandatory NFSe DPS fields
 * according to Brazilian national standard layout v1.01.01
 */
class NfSeRtcValidatorComplete extends NfSeRtcValidator
{
    /**
     * Extended RTC validation rules
     */
    private array $completeRtcRules;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setupCompleteRtcRules();
    }

    /**
     * Setup complete RTC validation rules
     */
    private function setupCompleteRtcRules(): void
    {
        $this->completeRtcRules = [
            'prestador_mandatory' => [
                'tpInsc', 'nInsc', 'IM', 'xNome', 'CNAE', 'cRegTrib',
                'endNac', 'fone', 'email'
            ],
            'prestador_endnac_mandatory' => [
                'xLgr', 'nro', 'xBairro', 'cMun', 'xMun', 'UF', 'CEP', 'cPais', 'xPais'
            ],
            'tomador_mandatory' => [
                'tpInsc', 'nInsc', 'xNome', 'fone', 'email'
            ],
            'tomador_endnac_mandatory' => [
                'xLgr', 'nro', 'xBairro', 'cMun', 'xMun', 'UF', 'CEP', 'cPais', 'xPais'
            ],
            'tomador_endext_mandatory' => [
                'cPais', 'xPais', 'cEndPost', 'xCidade', 'xEstProvReg', 'xLgr', 'nro', 'xBairro'
            ],
            'servico_mandatory' => [
                'cTribNac', 'xTribNac', 'cLocIncid', 'cServico', 'xServico', 'cCnae',
                'xDisc', 'vServ', 'vBC', 'aliq', 'vISS', 'vLiq', 'vTotTrib',
                'cMunPrest', 'cPaisPrest', 'indIncFisc', 'indISSRet', 'cNatOp', 'indRegEsp'
            ],
            'field_validations' => [
                'xDisc' => ['min_length' => 15, 'max_length' => 2000],
                'xNome' => ['max_length' => 115],
                'xFant' => ['max_length' => 60],
                'xLgr' => ['max_length' => 255],
                'nro' => ['max_length' => 60],
                'xCpl' => ['max_length' => 156],
                'xBairro' => ['max_length' => 60],
                'xMun' => ['max_length' => 60],
                'email' => ['max_length' => 80, 'format' => 'email'],
                'fone' => ['format' => 'phone'],
                'CEP' => ['length' => 8, 'format' => 'numeric'],
                'cMun' => ['length' => 7, 'format' => 'numeric'],
                'cPais' => ['length' => 4, 'format' => 'numeric'],
                'CNAE' => ['length' => 7, 'format' => 'numeric'],
                'IM' => ['max_length' => 15],
                'IE' => ['max_length' => 14]
            ],
            'value_validations' => [
                'vServ' => ['format' => 'decimal', 'min' => 0],
                'vBC' => ['format' => 'decimal', 'min' => 0],
                'aliq' => ['format' => 'decimal', 'min' => 0, 'max' => 100],
                'vISS' => ['format' => 'decimal', 'min' => 0],
                'vLiq' => ['format' => 'decimal', 'min' => 0],
                'vTotTrib' => ['format' => 'decimal', 'min' => 0],
                'vDeducao' => ['format' => 'decimal', 'min' => 0],
                'vDescIncond' => ['format' => 'decimal', 'min' => 0],
                'vDescCond' => ['format' => 'decimal', 'min' => 0],
                'vISSRet' => ['format' => 'decimal', 'min' => 0],
                'vOutrasRet' => ['format' => 'decimal', 'min' => 0]
            ],
            'indicator_validations' => [
                'tpInsc' => ['values' => [1, 2]],
                'cRegTrib' => ['values' => [1, 2, 3]],
                'indIncFisc' => ['values' => [1, 2]],
                'indISSRet' => ['values' => [1, 2]],
                'cNatOp' => ['values' => ['1', '2', '3', '4', '5', '6']],
                'indRegEsp' => ['values' => ['1', '2', '3', '4', '5', '6', '99']],
                'respTrib' => ['values' => ['1', '2', '3']],
                'exigISS' => ['values' => ['1', '2', '3', '4', '5', '6', '7']],
                'optSN' => ['values' => [1, 2]],
                'indCredSN' => ['values' => [1, 2]]
            ]
        ];
    }

    /**
     * Validate DPS XML - Complete validation method
     */
    public function validateDpsXmlComplete(string $xml): array
    {
        $validationResult = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [],
            'coverage' => []
        ];

        try {
            // Load XML
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);

            if (!$dom->loadXML($xml)) {
                $xmlErrors = libxml_get_errors();
                foreach ($xmlErrors as $error) {
                    $validationResult['errors'][] = 'XML Parse Error: ' . trim($error->message);
                }
                $validationResult['valid'] = false;
                return $validationResult;
            }

            $xpath = new DOMXPath($dom);

            // Run base validations
            $baseResult = parent::validateDpsXml($xml);
            $validationResult = $this->mergeValidationResults($validationResult, $baseResult);

            // Run complete validations
            $completeValidations = [
                'prestador_complete' => $this->validatePrestadorComplete($xpath),
                'tomador_complete' => $this->validateTomadorComplete($xpath),
                'servico_complete' => $this->validateServicoComplete($xpath),
                'field_formats_complete' => $this->validateFieldFormatsComplete($xpath),
                'value_consistency' => $this->validateValueConsistency($xpath),
                'business_rules_complete' => $this->validateBusinessRulesComplete($xpath)
            ];

            foreach ($completeValidations as $validationName => $result) {
                $validationResult = $this->mergeValidationResults($validationResult, $result);
                $validationResult['details'][$validationName] = $result;
            }

            // Calculate coverage
            $validationResult['coverage'] = $this->calculateFieldCoverage($xpath);

        } catch (Exception $e) {
            $validationResult['valid'] = false;
            $validationResult['errors'][] = 'Complete Validation Exception: ' . $e->getMessage();
        }

        // Log complete validation result
        if ($validationResult['valid']) {
            $this->logger->info('DPS XML completamente válido conforme RTC', [
                'coverage' => $validationResult['coverage']['percentage'],
                'warnings_count' => count($validationResult['warnings'])
            ]);
        } else {
            $this->logger->error('DPS XML não conforme RTC - validação completa', [
                'errors_count' => count($validationResult['errors']),
                'coverage' => $validationResult['coverage']['percentage']
            ]);
        }

        return $validationResult;
    }

    /**
     * Validate prestador complete
     */
    private function validatePrestadorComplete(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        $prestadorPath = '/NFSe/infNFSe/DPS/infDPS/prest';

        // Check mandatory fields
        foreach ($this->completeRtcRules['prestador_mandatory'] as $field) {
            $fieldNodes = $xpath->query("$prestadorPath/$field");

            if ($fieldNodes->length === 0) {
                $result['valid'] = false;
                $result['errors'][] = "Campo obrigatório ausente no prestador: $field";
            } else {
                $value = trim($fieldNodes->item(0)->textContent);
                if (empty($value) && $field !== 'xFant') {
                    $result['warnings'][] = "Campo obrigatório vazio no prestador: $field";
                }
            }
        }

        // Validate endNac if present
        $endnacNodes = $xpath->query("$prestadorPath/endNac");
        if ($endnacNodes->length > 0) {
            $endnacResult = $this->validateEnderecoNacional($xpath, "$prestadorPath/endNac", 'prestador');
            $result = $this->mergeValidationResults($result, $endnacResult);
        } else {
            $result['valid'] = false;
            $result['errors'][] = 'Endereço nacional obrigatório ausente no prestador';
        }

        // Validate CNPJ format
        $cnpjNodes = $xpath->query("$prestadorPath/nInsc");
        if ($cnpjNodes->length > 0) {
            $cnpj = $cnpjNodes->item(0)->textContent;
            if (!$this->validateCnpj($cnpj)) {
                $result['warnings'][] = 'CNPJ do prestador pode estar inválido: ' . $cnpj;
            }
        }

        return $result;
    }

    /**
     * Validate tomador complete
     */
    private function validateTomadorComplete(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        $tomadorPath = '/NFSe/infNFSe/DPS/infDPS/tom';

        // Check mandatory fields
        foreach ($this->completeRtcRules['tomador_mandatory'] as $field) {
            $fieldNodes = $xpath->query("$tomadorPath/$field");

            if ($fieldNodes->length === 0) {
                $result['valid'] = false;
                $result['errors'][] = "Campo obrigatório ausente no tomador: $field";
            } else {
                $value = trim($fieldNodes->item(0)->textContent);
                if (empty($value)) {
                    $result['warnings'][] = "Campo obrigatório vazio no tomador: $field";
                }
            }
        }

        // Validate address (endNac or endExt)
        $endnacNodes = $xpath->query("$tomadorPath/endNac");
        $endextNodes = $xpath->query("$tomadorPath/endExt");

        if ($endnacNodes->length > 0) {
            $endnacResult = $this->validateEnderecoNacional($xpath, "$tomadorPath/endNac", 'tomador');
            $result = $this->mergeValidationResults($result, $endnacResult);
        } elseif ($endextNodes->length > 0) {
            $endextResult = $this->validateEnderecoExterior($xpath, "$tomadorPath/endExt", 'tomador');
            $result = $this->mergeValidationResults($result, $endextResult);
        } else {
            $result['warnings'][] = 'Endereço do tomador não informado (recomendado)';
        }

        // Validate CPF/CNPJ
        $tipoInscNodes = $xpath->query("$tomadorPath/tpInsc");
        $inscNodes = $xpath->query("$tomadorPath/nInsc");

        if ($tipoInscNodes->length > 0 && $inscNodes->length > 0) {
            $tipo = $tipoInscNodes->item(0)->textContent;
            $inscricao = $inscNodes->item(0)->textContent;

            if ($tipo === '1' && !$this->validateCpf($inscricao)) {
                $result['warnings'][] = 'CPF do tomador pode estar inválido: ' . $inscricao;
            } elseif ($tipo === '2' && !$this->validateCnpj($inscricao)) {
                $result['warnings'][] = 'CNPJ do tomador pode estar inválido: ' . $inscricao;
            }
        }

        return $result;
    }

    /**
     * Validate servico complete
     */
    private function validateServicoComplete(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        $servicoPath = '/NFSe/infNFSe/DPS/infDPS/serv';

        // Check mandatory fields
        foreach ($this->completeRtcRules['servico_mandatory'] as $field) {
            $fieldNodes = $xpath->query("$servicoPath/$field");

            if ($fieldNodes->length === 0) {
                $result['valid'] = false;
                $result['errors'][] = "Campo obrigatório ausente no serviço: $field";
            } else {
                $value = trim($fieldNodes->item(0)->textContent);
                if (empty($value)) {
                    $result['warnings'][] = "Campo obrigatório vazio no serviço: $field";
                }
            }
        }

        // Validate xDisc minimum length
        $xdiscNodes = $xpath->query("$servicoPath/xDisc");
        if ($xdiscNodes->length > 0) {
            $xdisc = $xdiscNodes->item(0)->textContent;
            if (strlen($xdisc) < 15) {
                $result['valid'] = false;
                $result['errors'][] = "Discriminação do serviço deve ter pelo menos 15 caracteres. Encontrado: " . strlen($xdisc);
            }
        }

        // Validate service codes
        $ctribnacNodes = $xpath->query("$servicoPath/cTribNac");
        if ($ctribnacNodes->length > 0) {
            $ctribnac = $ctribnacNodes->item(0)->textContent;
            if (!preg_match('/^\d{6}$/', $ctribnac)) {
                $result['warnings'][] = "Código tributação nacional deve ter 6 dígitos: $ctribnac";
            }
        }

        return $result;
    }

    /**
     * Validate endereco nacional
     */
    private function validateEnderecoNacional(DOMXPath $xpath, string $enderecoPath, string $context): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        foreach ($this->completeRtcRules['tomador_endnac_mandatory'] as $field) {
            $fieldNodes = $xpath->query("$enderecoPath/$field");

            if ($fieldNodes->length === 0) {
                $result['valid'] = false;
                $result['errors'][] = "Campo obrigatório ausente no endereço nacional ($context): $field";
            } else {
                $value = trim($fieldNodes->item(0)->textContent);
                if (empty($value)) {
                    $result['warnings'][] = "Campo obrigatório vazio no endereço nacional ($context): $field";
                }
            }
        }

        // Validate CEP format
        $cepNodes = $xpath->query("$enderecoPath/CEP");
        if ($cepNodes->length > 0) {
            $cep = $cepNodes->item(0)->textContent;
            if (!preg_match('/^\d{8}$/', $cep)) {
                $result['warnings'][] = "CEP deve ter 8 dígitos numéricos ($context): $cep";
            }
        }

        // Validate municipality code
        $cmunNodes = $xpath->query("$enderecoPath/cMun");
        if ($cmunNodes->length > 0) {
            $cmun = $cmunNodes->item(0)->textContent;
            if (!preg_match('/^\d{7}$/', $cmun)) {
                $result['warnings'][] = "Código município deve ter 7 dígitos ($context): $cmun";
            }
        }

        return $result;
    }

    /**
     * Validate endereco exterior
     */
    private function validateEnderecoExterior(DOMXPath $xpath, string $enderecoPath, string $context): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        foreach ($this->completeRtcRules['tomador_endext_mandatory'] as $field) {
            $fieldNodes = $xpath->query("$enderecoPath/$field");

            if ($fieldNodes->length === 0) {
                $result['valid'] = false;
                $result['errors'][] = "Campo obrigatório ausente no endereço exterior ($context): $field";
            } else {
                $value = trim($fieldNodes->item(0)->textContent);
                if (empty($value)) {
                    $result['warnings'][] = "Campo obrigatório vazio no endereço exterior ($context): $field";
                }
            }
        }

        // Validate country code
        $cpaisNodes = $xpath->query("$enderecoPath/cPais");
        if ($cpaisNodes->length > 0) {
            $cpais = $cpaisNodes->item(0)->textContent;
            if (!preg_match('/^\d{4}$/', $cpais)) {
                $result['warnings'][] = "Código país deve ter 4 dígitos ($context): $cpais";
            }
        }

        return $result;
    }

    /**
     * Validate field formats complete
     */
    private function validateFieldFormatsComplete(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        foreach ($this->completeRtcRules['field_validations'] as $field => $rules) {
            $fieldResult = $this->validateFieldFormatComplete($xpath, $field, $rules);
            $result = $this->mergeValidationResults($result, $fieldResult);
        }

        return $result;
    }

    /**
     * Validate field format complete
     */
    private function validateFieldFormatComplete(DOMXPath $xpath, string $field, array $rules): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        $fieldNodes = $xpath->query("//*[local-name()='$field']");

        foreach ($fieldNodes as $node) {
            $value = trim($node->textContent);

            if (empty($value)) {
                continue;
            }

            // Length validations
            if (isset($rules['length']) && strlen($value) !== $rules['length']) {
                $result['valid'] = false;
                $result['errors'][] = sprintf('Campo %s deve ter %d caracteres. Encontrado: %d (%s)',
                    $field, $rules['length'], strlen($value), $value);
            }

            if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                $result['valid'] = false;
                $result['errors'][] = sprintf('Campo %s deve ter pelo menos %d caracteres. Encontrado: %d',
                    $field, $rules['min_length'], strlen($value));
            }

            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $result['valid'] = false;
                $result['errors'][] = sprintf('Campo %s excede tamanho máximo de %d caracteres. Encontrado: %d',
                    $field, $rules['max_length'], strlen($value));
            }

            // Format validations
            if (isset($rules['format'])) {
                switch ($rules['format']) {
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $result['valid'] = false;
                            $result['errors'][] = sprintf('Campo %s deve ser numérico: %s', $field, $value);
                        }
                        break;

                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $result['warnings'][] = sprintf('Campo %s pode ter formato de email inválido: %s', $field, $value);
                        }
                        break;

                    case 'phone':
                        if (!preg_match('/^\d{10,11}$/', $value)) {
                            $result['warnings'][] = sprintf('Campo %s deve ter formato de telefone válido (10-11 dígitos): %s', $field, $value);
                        }
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * Validate value consistency
     */
    private function validateValueConsistency(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        // Get service values
        $values = [];
        foreach (['vServ', 'vBC', 'aliq', 'vISS', 'vLiq', 'vTotTrib', 'vDeducao', 'vDescIncond', 'vDescCond', 'vISSRet', 'vOutrasRet'] as $field) {
            $fieldNodes = $xpath->query("/NFSe/infNFSe/DPS/infDPS/serv/$field");
            if ($fieldNodes->length > 0) {
                $values[$field] = (float) $fieldNodes->item(0)->textContent;
            } else {
                $values[$field] = 0.0;
            }
        }

        // Validate value formats
        foreach ($this->completeRtcRules['value_validations'] as $field => $rules) {
            if (isset($values[$field])) {
                $value = $values[$field];

                if (isset($rules['min']) && $value < $rules['min']) {
                    $result['valid'] = false;
                    $result['errors'][] = sprintf('Campo %s deve ser maior ou igual a %.2f. Encontrado: %.2f',
                        $field, $rules['min'], $value);
                }

                if (isset($rules['max']) && $value > $rules['max']) {
                    $result['valid'] = false;
                    $result['errors'][] = sprintf('Campo %s deve ser menor ou igual a %.2f. Encontrado: %.2f',
                        $field, $rules['max'], $value);
                }
            }
        }

        // Validate mathematical consistency
        $vserv = $values['vServ'];
        $vdeducao = $values['vDeducao'];
        $vdescincond = $values['vDescIncond'];
        $vbc = $values['vBC'];
        $aliq = $values['aliq'];
        $viss = $values['vISS'];
        $vliq = $values['vLiq'];

        // Base calculation: vBC = vServ - vDeducao - vDescIncond
        $calculatedVbc = $vserv - $vdeducao - $vdescincond;
        if (abs($vbc - $calculatedVbc) > 0.01) {
            $result['warnings'][] = sprintf('Inconsistência na base de cálculo: vBC (%.2f) ≠ vServ (%.2f) - vDeducao (%.2f) - vDescIncond (%.2f) = %.2f',
                $vbc, $vserv, $vdeducao, $vdescincond, $calculatedVbc);
        }

        // ISS calculation: vISS = vBC * (aliq / 100)
        $calculatedViss = $vbc * ($aliq / 100);
        if (abs($viss - $calculatedViss) > 0.01) {
            $result['warnings'][] = sprintf('Inconsistência no cálculo do ISS: vISS (%.2f) ≠ vBC (%.2f) * aliq (%.4f%%) = %.2f',
                $viss, $vbc, $aliq, $calculatedViss);
        }

        // Liquid value: vLiq = vServ - vISS
        $calculatedVliq = $vserv - $viss;
        if (abs($vliq - $calculatedVliq) > 0.01) {
            $result['warnings'][] = sprintf('Inconsistência no valor líquido: vLiq (%.2f) ≠ vServ (%.2f) - vISS (%.2f) = %.2f',
                $vliq, $vserv, $viss, $calculatedVliq);
        }

        return $result;
    }

    /**
     * Validate business rules complete
     */
    public function validateBusinessRulesComplete(DOMXPath $xpath): array
    {
        $result = ['valid' => true, 'errors' => [], 'warnings' => []];

        // Run base business rules
        $baseResult = parent::validateBusinessRules($xpath);
        $result = $this->mergeValidationResults($result, $baseResult);

        // Additional business rules

        // Rule: Validate indicator values
        foreach ($this->completeRtcRules['indicator_validations'] as $field => $rules) {
            $fieldNodes = $xpath->query("//*[local-name()='$field']");

            foreach ($fieldNodes as $node) {
                $value = trim($node->textContent);

                if (!empty($value) && isset($rules['values'])) {
                    $validValues = $rules['values'];
                    $numericValue = is_numeric($value) ? (int)$value : $value;

                    if (!in_array($numericValue, $validValues) && !in_array($value, $validValues)) {
                        $result['valid'] = false;
                        $result['errors'][] = sprintf('Campo %s deve ter um dos valores válidos: %s. Encontrado: %s',
                            $field, implode(', ', $validValues), $value);
                    }
                }
            }
        }

        // Rule: Validate CNAE format
        $cnaeNodes = $xpath->query('//*[local-name()="CNAE"]');
        foreach ($cnaeNodes as $node) {
            $cnae = trim($node->textContent);
            if (!empty($cnae) && !preg_match('/^\d{7}$/', $cnae)) {
                $result['warnings'][] = 'CNAE deve ter 7 dígitos numéricos: ' . $cnae;
            }
        }

        // Rule: Validate municipality codes
        $cmunNodes = $xpath->query('//*[local-name()="cMun"]');
        foreach ($cmunNodes as $node) {
            $cmun = trim($node->textContent);
            if (!empty($cmun) && !preg_match('/^\d{7}$/', $cmun)) {
                $result['warnings'][] = 'Código município deve ter 7 dígitos: ' . $cmun;
            }
        }

        return $result;
    }

    /**
     * Calculate field coverage
     */
    private function calculateFieldCoverage(DOMXPath $xpath): array
    {
        $totalMandatoryFields = 0;
        $implementedFields = 0;

        $sections = [
            'prestador' => $this->completeRtcRules['prestador_mandatory'],
            'tomador' => $this->completeRtcRules['tomador_mandatory'],
            'servico' => $this->completeRtcRules['servico_mandatory']
        ];

        $coverageDetails = [];

        foreach ($sections as $section => $fields) {
            $sectionTotal = count($fields);
            $sectionImplemented = 0;

            $basePath = $this->getSectionBasePath($section);

            foreach ($fields as $field) {
                $totalMandatoryFields++;

                $fieldNodes = $xpath->query("$basePath/$field");
                if ($fieldNodes->length > 0) {
                    $implementedFields++;
                    $sectionImplemented++;
                }
            }

            $coverageDetails[$section] = [
                'total' => $sectionTotal,
                'implemented' => $sectionImplemented,
                'percentage' => round(($sectionImplemented / $sectionTotal) * 100, 2)
            ];
        }

        $overallPercentage = $totalMandatoryFields > 0 ?
            round(($implementedFields / $totalMandatoryFields) * 100, 2) : 0;

        return [
            'total_mandatory' => $totalMandatoryFields,
            'implemented' => $implementedFields,
            'percentage' => $overallPercentage,
            'sections' => $coverageDetails
        ];
    }

    /**
     * Generate complete validation report
     */
    public function generateCompleteValidationReport(array $validationResult): array
    {
        $baseReport = parent::generateValidationReport($validationResult);

        $completeReport = array_merge($baseReport, [
            'coverage' => $validationResult['coverage'],
            'details' => $validationResult['details'] ?? [],
            'compliance_level' => $this->getComplianceLevel($validationResult),
            'next_steps' => $this->getNextSteps($validationResult)
        ]);

        return $completeReport;
    }

    /**
     * Get compliance level
     */
    private function getComplianceLevel(array $validationResult): string
    {
        $percentage = $validationResult['coverage']['percentage'] ?? 0;
        $hasErrors = count($validationResult['errors']) > 0;

        if ($hasErrors) {
            return 'NÃO CONFORME';
        } elseif ($percentage >= 95) {
            return 'TOTALMENTE CONFORME';
        } elseif ($percentage >= 80) {
            return 'MAJORITARIAMENTE CONFORME';
        } elseif ($percentage >= 60) {
            return 'PARCIALMENTE CONFORME';
        } else {
            return 'MINIMAMENTE CONFORME';
        }
    }

    /**
     * Get next steps
     */
    private function getNextSteps(array $validationResult): array
    {
        $steps = [];
        $percentage = $validationResult['coverage']['percentage'] ?? 0;
        $errorCount = count($validationResult['errors']);
        $warningCount = count($validationResult['warnings']);

        if ($errorCount > 0) {
            $steps[] = "Corrigir $errorCount erro(s) crítico(s) antes de prosseguir";
        }

        if ($percentage < 100) {
            $missing = 100 - $percentage;
            $steps[] = "Implementar $missing% dos campos obrigatórios restantes";
        }

        if ($warningCount > 0) {
            $steps[] = "Revisar $warningCount aviso(s) para melhorar qualidade dos dados";
        }

        if ($percentage >= 95 && $errorCount === 0) {
            $steps[] = "DPS pronta para teste em ambiente de homologação";
        }

        if (empty($steps)) {
            $steps[] = "DPS totalmente conforme - pode ser enviada para produção";
        }

        return $steps;
    }

    /**
     * Test complete validator
     */
    public function testCompleteValidator(): array
    {
        $testResults = [];

        // Test coverage calculation
        $sampleXml = '<?xml version="1.0" encoding="UTF-8"?>
        <DPS xmlns="http://www.nfse.gov.br/schema/dps_v1.xsd">
            <infDPS id="DPS000000000000001">
                <tpAmb>2</tpAmb>
                <dhEmi>2025-01-09T10:30:00Z</dhEmi>
                <tpEmi>1</tpEmi>
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
                    <xDisc>Prestação de serviços de desenvolvimento de software personalizado</xDisc>
                    <vServ>1000.00</vServ>
                    <vISS>50.00</vISS>
                    <vLiq>950.00</vLiq>
                </serv>
            </infDPS>
        </DPS>';

        $dom = new DOMDocument();
        $dom->loadXML($sampleXml);
        $xpath = new DOMXPath($dom);

        $coverage = $this->calculateFieldCoverage($xpath);
        $testResults['coverage_calculation'] = [
            'success' => isset($coverage['percentage']),
            'coverage' => $coverage
        ];

        // Test complete validation
        $validationResult = $this->validateDpsXmlComplete($sampleXml);
        $testResults['complete_validation'] = [
            'success' => isset($validationResult['valid']),
            'valid' => $validationResult['valid'],
            'error_count' => count($validationResult['errors']),
            'warning_count' => count($validationResult['warnings'])
        ];

        return $testResults;
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
}