<?php

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

use CloudXM\NFSe\Utilities\Logger;
use CloudXM\NFSe\Services\NfSeSettings;
use Exception;
use DOMDocument;

/**
 * NFSe DPS Generator Service
 *
 * Modern DPS XML generation service following PSR-4 architecture
 */
class NfSeDpsGenerator
{
    private Logger $logger;
    private NfSeSettings $settings;

    public function __construct(Logger $logger, NfSeSettings $settings)
    {
        $this->logger = $logger;
        $this->settings = $settings;
    }

    /**
     * Generate DPS XML from order ID
     */
    public function generateDpsXml(int $orderId): array
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            throw new Exception(__('Pedido não encontrado.', 'wc-nfse'));
        }

        // Get next DPS number
        $dpsNumber = $this->getNextDpsNumber();

        // Build DPS data structure
        $dpsData = $this->buildDpsData($order, $dpsNumber);

        // Generate XML
        $xml = $this->generateXmlFromData($dpsData);

        // Validate XML structure
        $this->validateXml($xml);

        // XSD Validation using existing NfSeXsdValidator
        $xsdValidator = new NfSeXsdValidator();
        $xsdValidationResult = $xsdValidator->validateDpsXml($xml);

        $this->logger->info('DPS XML generated successfully', [
            'order_id' => $orderId,
            'dps_number' => $dpsNumber,
            'xml_size' => strlen($xml),
            'xsd_valid' => $xsdValidationResult['valid'],
            'xsd_errors_count' => count($xsdValidationResult['errors']),
            'xsd_warnings_count' => count($xsdValidationResult['warnings'])
        ]);

        return [
            'xml' => $xml,
            'dps_number' => $dpsNumber,
            'dps_data' => $dpsData,
            'xsd_validation' => $xsdValidationResult
        ];
    }

    /**
     * Generate DPS ID according to official specification
     * Format: "DPS" + Cód.Mun.(7) + Tipo Inscrição Federal(1) + Inscrição Federal(14) + Série DPS(5) + Núm. DPS(15)
     * Total: 45 characters (XSD maxLength compliance)
     */
    private function generateDpsId(int $dpsNumber): string
    {
        $municipalityCode = $this->getMunicipalityCode(
            $this->settings->getPrestadorAddress()['cidade'] ?? 'Adamantina',
            $this->settings->getPrestadorAddress()['uf'] ?? 'SP'
        );

        $documento = $this->settings->getPrestadorCnpj();
        $documentoLimpo = preg_replace('/\D/', '', $documento);

        // Determinar tipo de inscrição federal e formatar documento
        if (strlen($documentoLimpo) === 14) {
            // CNPJ
            $tipoInscricao = '2'; // 2 = CNPJ
            $inscricaoFederal = $documentoLimpo; // CNPJ já tem 14 dígitos
        } else {
            // CPF - completar com zeros à esquerda para 14 posições
            $tipoInscricao = '1'; // 1 = CPF
            $inscricaoFederal = str_pad($documentoLimpo, 14, '0', STR_PAD_LEFT);
        }

        // Série DPS (5 dígitos) - usar configuração ou padrão
        $serieDps = str_pad($this->settings->getDpsSerie(), 5, '0', STR_PAD_LEFT);

        return sprintf(
            'DPS%s%s%s%s%015d',
            str_pad($municipalityCode, 7, '0', STR_PAD_LEFT), // Código município (7)
            $tipoInscricao,                                    // Tipo inscrição (1)
            $inscricaoFederal,                                 // Inscrição federal (14)
            $serieDps,                                         // Série DPS (5)
            $dpsNumber                                         // Número DPS (15)
        );
    }

    /**
     * Build DPS data structure
     */
    private function buildDpsData(\WC_Order $order, int $dpsNumber): array
    {
        $dpsId = $this->generateDpsId($dpsNumber);

        // Get municipality code for emission location
        $prestadorAddress = $this->settings->getPrestadorAddress();
        $municipalityCode = $this->getMunicipalityCode(
            $prestadorAddress['cidade'] ?? 'Adamantina',
            $prestadorAddress['uf'] ?? 'SP'
        );

        return [
            'dps_id' => $dpsId,
            'tpAmb' => $this->settings->getEnvironment() === 'production' ? 1 : 2,
            'dhEmi' => current_time('c'), // ISO8601 format (TSDateTimeUTC)
            'verAplic' => '1.0.0', // Application version
            'serie' => $this->settings->getDpsSerie(),
            'nDPS' => $dpsNumber,
            'dCompet' => current_time('Y-m-d'), // Date format (YYYY-MM-DD) as per specification
            'tpEmit' => 1, // 1=Prestador, 2=Tomador, 3=Intermediário
            'cLocEmi' => $municipalityCode, // 7-digit IBGE code for emission location
            'prestador' => $this->buildPrestadorData(),
            'tomador' => $this->buildTomadorData($order),
            'servico' => $this->buildServicoData($order)
        ];
    }

    /**
     * Build prestador data compatible with emit element structure
     */
    private function buildPrestadorData(): array
    {
        $address = $this->settings->getPrestadorAddress();
        $contact = $this->settings->getPrestadorContact();
        $cnpj = $this->settings->getPrestadorCnpj();

        // Determine if it's CNPJ or CPF based on document length
        $documentType = strlen(preg_replace('/\D/', '', $cnpj)) === 14 ? 'cnpj' : 'cpf';

        $data = [
            'razao_social' => $this->settings->getPrestadorRazaoSocial(),
            'endereco' => [
                'endereco' => $address['endereco'],
                'numero' => $address['numero'],
                'complemento' => $address['complemento'] ?? '',
                'bairro' => $address['bairro'],
                'codigo_municipio' => $this->getMunicipalityCode($address['cidade'], $address['uf']),
                'uf' => $address['uf'],
                'cep' => preg_replace('/\D/', '', $address['cep']) // Digits only
            ],
            'telefone' => !empty($contact['telefone']) ? $this->sanitizePhone($contact['telefone']) : '',
            'email' => $contact['email'] ?? '',
            'regime_tributario' => [
                'op_simples_nacional' => $this->settings->get('op_simples_nacional', '1'), // 1=Não Optante, 2=MEI, 3=ME/EPP
                'reg_esp_trib' => $this->settings->get('reg_esp_trib', '0') // 0=Nenhum, 1=Ato Cooperado, etc.
            ]
        ];

        // Add CNPJ or CPF field
        if ($documentType === 'cnpj') {
            $data['cnpj'] = preg_replace('/\D/', '', $cnpj);
        } else {
            $data['cpf'] = preg_replace('/\D/', '', $cnpj);
        }

        // Add municipal registration if available
        $inscricaoMunicipal = $this->settings->get('inscricao_municipal', '');
        if (!empty($inscricaoMunicipal)) {
            $data['inscricao_municipal'] = $inscricaoMunicipal;
        }

        return $data;
    }

    /**
     * Build tomador data from order compatible with toma element structure
     */
    private function buildTomadorData(\WC_Order $order): array
    {
        $billingCompany = $order->get_billing_company();
        $isCompany = !empty($billingCompany);

        // Get document (CPF or CNPJ)
        $cpf = $order->get_meta('_billing_cpf');
        $cnpj = $order->get_meta('_billing_cnpj');

        $document = '';
        $documentType = '';

        if (!empty($cnpj)) {
            $document = preg_replace('/\D/', '', $cnpj);
            $documentType = 'cnpj';
        } elseif (!empty($cpf)) {
            $document = preg_replace('/\D/', '', $cpf);
            $documentType = 'cpf';
        }

        // Build tomador data compatible with TCInfoPessoa structure
        $tomadorData = [
            'razao_social' => $isCompany ?
                $billingCompany :
                trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())
        ];

        // Add CNPJ or CPF field directly (XSD requires choice between CNPJ, CPF, NIF, or cNaoNIF)
        if ($documentType === 'cnpj') {
            $tomadorData['cnpj'] = $document;
        } elseif ($documentType === 'cpf') {
            $tomadorData['cpf'] = $document;
        }

        // Municipal registration (optional)
        $inscricaoMunicipal = $order->get_meta('_billing_inscricao_municipal');
        if (!empty($inscricaoMunicipal)) {
            $tomadorData['inscricao_municipal'] = $inscricaoMunicipal;
        }

        // Address is required for companies using enderNac structure
        if ($isCompany || $documentType === 'cnpj') {
            $municipalityCode = $this->getMunicipalityCode(
                $order->get_billing_city(),
                $order->get_billing_state()
            );

            $tomadorData['endereco'] = [
                'endereco' => $order->get_billing_address_1(),
                'numero' => $this->extractNumberFromAddress($order->get_billing_address_1()),
                'complemento' => $order->get_billing_address_2(),
                'bairro' => $this->getBillingNeighborhood($order),
                'codigo_municipio' => $municipalityCode,
                'uf' => $order->get_billing_state(),
                'cep' => preg_replace('/\D/', '', $order->get_billing_postcode())
            ];
        }

        // Contact information (digits only for phone)
        if ($order->get_billing_phone()) {
            $tomadorData['telefone'] = $this->sanitizePhone($order->get_billing_phone());
        }
        if ($order->get_billing_email()) {
            $tomadorData['email'] = $order->get_billing_email();
        }

        return $tomadorData;
    }

    /**
     * Build servico data from order according to XSD structure
     */
    private function buildServicoData(\WC_Order $order): array
    {
        $total = (float) $order->get_total();
        $taxTotal = (float) $order->get_total_tax();
        $subtotal = $total - $taxTotal;

        // Get NBS code
        $nbsCode = $this->getNbsCodeFromOrder($order);

        // Build service description (sanitized)
        $discriminacao = $this->buildServiceDescription($order);

        $municipalityCode = $this->getMunicipalityCode(
            $this->settings->getPrestadorAddress()['cidade'] ?? 'Adamantina',
            $this->settings->getPrestadorAddress()['uf'] ?? 'SP'
        );

        return [
            // Service information (for serv element)
            'codigo_municipio' => $municipalityCode,
            'codigo_pais' => $this->getServiceProvisionCountryCode($order), // ISO Alpha-2 code as per XSD TSCodPaisISO
            'codigo_tributacao_nacional' => $this->settings->get('codigo_tributacao_nacional', '010101'), // Default: Item 01, Subitem 01, Desdobro 01
            'codigo_tributacao_municipio' => $this->settings->get('codigo_tributacao_municipio', ''),
            'discriminacao' => $discriminacao,
            'item_lista_servico' => $nbsCode,
            'codigo_interno_contribuinte' => $this->settings->get('codigo_interno_contribuinte', ''),
            'informacoes_complementares' => [
                'documento_referencia' => sprintf('Pedido WooCommerce #%s', $order->get_order_number()),
                'informacoes_adicionais' => $this->settings->get('informacoes_adicionais_dps', '')
            ],

            // Values information (for valores element)
            'valores' => [
                'valor_servicos' => $this->formatMonetaryValue($subtotal),
                'valor_recebido_intermediario' => $this->getIntermediaryValue($order), // Value received by intermediary (if any)
                'desconto_incondicionado' => $this->formatMonetaryValue(0),
                'desconto_condicionado' => $this->formatMonetaryValue(0),
                'valor_deducoes' => $this->formatMonetaryValue(0),
                'valor_pis' => $this->formatMonetaryValue(0),
                'valor_cofins' => $this->formatMonetaryValue(0),
                'valor_inss' => $this->formatMonetaryValue(0),
                'valor_ir' => $this->formatMonetaryValue(0),
                'valor_csll' => $this->formatMonetaryValue(0),
                'iss_retido' => $this->getIssRetidoValue($order), // Check if ISS is retained
                'valor_iss' => $this->formatMonetaryValue($taxTotal),
                'outras_retencoes' => $this->formatMonetaryValue(0),
                'base_calculo' => $this->formatMonetaryValue($subtotal),
                'aliquota' => $this->calculateIssRate($subtotal, $taxTotal),
                'valor_liquido_nfse' => $this->formatMonetaryValue($total)
            ]
        ];
    }

    /**
     * Generate XML from DPS data
     */
    private function generateXmlFromData(array $dpsData): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;

        // Root element with correct namespace and version
        $dps = $dom->createElement('DPS');
        $dps->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $dps->setAttribute('versao', '1.00');
        $dom->appendChild($dps);

        // infDPS (lowercase) with proper Id attribute
        $infDps = $dom->createElement('infDPS');
        $infDps->setAttribute('Id', $dpsData['dps_id']);
        $dps->appendChild($infDps);

        // Add identification fields directly to infDPS
        $infDps->appendChild($dom->createElement('tpAmb', (string)$dpsData['tpAmb']));
        $infDps->appendChild($dom->createElement('dhEmi', $dpsData['dhEmi']));
        $infDps->appendChild($dom->createElement('verAplic', $dpsData['verAplic']));
        $infDps->appendChild($dom->createElement('serie', $dpsData['serie']));
        $infDps->appendChild($dom->createElement('nDPS', (string)$dpsData['nDPS']));
        $infDps->appendChild($dom->createElement('dCompet', $dpsData['dCompet']));
        $infDps->appendChild($dom->createElement('tpEmit', (string)$dpsData['tpEmit']));
        $infDps->appendChild($dom->createElement('cLocEmi', $dpsData['cLocEmi']));

        // Prestador (Provider)
        $this->addPrestadorElement($dom, $infDps, $dpsData['prestador']);

        // Tomador
        $this->addTomaElement($dom, $infDps, $dpsData['tomador']);

        // Servico (Service information)
        $this->addServElement($dom, $infDps, $dpsData['servico']);

        // Valores (Values information)
        $this->addValoresElement($dom, $infDps, $dpsData['servico']);

        return $dom->saveXML();
    }



    /**
     * Add Prestador (Provider) element to XML for DPS
     */
    private function addPrestadorElement(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $prest = $dom->createElement('prest');
        $parent->appendChild($prest);

        // Use CNPJ or CPF directly, never tpInsc/nInsc combination
        if (!empty($data['cnpj'])) {
            $prest->appendChild($dom->createElement('CNPJ', $data['cnpj']));
        } elseif (!empty($data['cpf'])) {
            $prest->appendChild($dom->createElement('CPF', $data['cpf']));
        }

        // CAEPF (optional) - Cadastro de Atividade Econômica da Pessoa Física
        if (!empty($data['caepf'])) {
            $prest->appendChild($dom->createElement('CAEPF', $data['caepf']));
        }

        // Municipal registration (optional)
        if (!empty($data['inscricao_municipal'])) {
            $prest->appendChild($dom->createElement('IM', $data['inscricao_municipal']));
        }

        // Corporate name (optional in DPS)
        // if (!empty($data['razao_social'])) {
        //     $prest->appendChild($dom->createElement('xNome', htmlspecialchars($data['razao_social'], ENT_XML1)));
        // }

        // Address using TCEndereco structure with endNac sub-element for XSD compliance
        // if (!empty($data['endereco'])) {
        //     $this->addEndereco($dom, $prest, $data['endereco']);
        // }

        // Phone (digits only)
        if (!empty($data['telefone'])) {
            $prest->appendChild($dom->createElement('fone', $data['telefone']));
        }

        // Email
        if (!empty($data['email'])) {
            $prest->appendChild($dom->createElement('email', $data['email']));
        }

        // Tax regime information (regTrib group) - REQUIRED for prestador
        $this->addRegTrib($dom, $prest, $data['regime_tributario']);
    }

    /**
     * Add Toma (Customer) element to XML following TCInfoPessoa structure
     */
    private function addTomaElement(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $toma = $dom->createElement('toma');
        $parent->appendChild($toma);

        // Use CNPJ or CPF directly, never tpInsc/nInsc combination (XSD requirement)
        if (!empty($data['cnpj'])) {
            $toma->appendChild($dom->createElement('CNPJ', $data['cnpj']));
        } elseif (!empty($data['cpf'])) {
            $toma->appendChild($dom->createElement('CPF', $data['cpf']));
        }

        // CAEPF (optional) - Cadastro de Atividade Econômica da Pessoa Física
        if (!empty($data['caepf'])) {
            $toma->appendChild($dom->createElement('CAEPF', $data['caepf']));
        }

        // Municipal registration (optional)
        if (!empty($data['inscricao_municipal'])) {
            $toma->appendChild($dom->createElement('IM', $data['inscricao_municipal']));
        }

        // Name/Corporate name (required in TCInfoPessoa)
        if (!empty($data['razao_social'])) {
            $toma->appendChild($dom->createElement('xNome', htmlspecialchars($data['razao_social'], ENT_XML1)));
        }

        // Address using TCEndereco structure (optional)
        if (!empty($data['endereco'])) {
            $this->addEndereco($dom, $toma, $data['endereco']);
        }

        // Phone (digits only, direct child of toma)
        if (!empty($data['telefone'])) {
            $toma->appendChild($dom->createElement('fone', $data['telefone']));
        }

        // Email (direct child of toma)
        if (!empty($data['email'])) {
            $toma->appendChild($dom->createElement('email', $data['email']));
        }
    }

    /**
     * Add Servico (Service) element to XML according to XSD TCServ structure
     */
    private function addServElement(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $serv = $dom->createElement('serv');
        $parent->appendChild($serv);

        // locPrest - Local da prestação do serviço (required)
        $locPrest = $dom->createElement('locPrest');
        $serv->appendChild($locPrest);

        // cLocPrestacao - Código do município onde o serviço foi prestado
        $locPrest->appendChild($dom->createElement('cLocPrestacao', $data['codigo_municipio']));

        // cPaisPrestacao - Código do país onde o serviço foi prestado
        // $locPrest->appendChild($dom->createElement('cPaisPrestacao', $data['codigo_pais']));

        // cServ - Código do serviço prestado (required)
        $cServ = $dom->createElement('cServ');
        $serv->appendChild($cServ);

        // cTribNac - Código de tributação nacional do ISSQN (required)
        if (!empty($data['codigo_tributacao_nacional'])) {
            $cServ->appendChild($dom->createElement('cTribNac', $data['codigo_tributacao_nacional']));
        }

        // cTribMun - Código de tributação municipal do ISSQN (optional)
        if (!empty($data['codigo_tributacao_municipio'])) {
            $cServ->appendChild($dom->createElement('cTribMun', $data['codigo_tributacao_municipio']));
        }

        // xDescServ - Descrição completa do serviço prestado (required)
        $cServ->appendChild($dom->createElement(
            'xDescServ',
            htmlspecialchars($data['discriminacao'], ENT_XML1)
        ));

        // cNBS - Código NBS (optional)
        if (!empty($data['item_lista_servico'])) {
            $cServ->appendChild($dom->createElement('cNBS', $data['item_lista_servico']));
        }

        // cIntContrib - Código interno do contribuinte (optional)
        if (!empty($data['codigo_interno_contribuinte'])) {
            $cServ->appendChild($dom->createElement('cIntContrib', $data['codigo_interno_contribuinte']));
        }

        // infoCompl - Informações complementares (optional)
        if (!empty($data['informacoes_complementares'])) {
            $infoCompl = $dom->createElement('infoCompl');
            $serv->appendChild($infoCompl);

            if (!empty($data['informacoes_complementares']['documento_referencia'])) {
                $infoCompl->appendChild($dom->createElement('docRef', $data['informacoes_complementares']['documento_referencia']));
            }

            if (!empty($data['informacoes_complementares']['informacoes_adicionais'])) {
                $infoCompl->appendChild($dom->createElement('xInfComp', htmlspecialchars($data['informacoes_complementares']['informacoes_adicionais'], ENT_XML1)));
            }
        }
    }

    /**
     * Add Valores (Values) element to XML according to XSD TCInfoValores structure
     */
    private function addValoresElement(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $valores = $dom->createElement('valores');
        $parent->appendChild($valores);

        // vServPrest - Valores do serviço prestado (required)
        $vServPrest = $dom->createElement('vServPrest');
        $valores->appendChild($vServPrest);

        // vServ - Valor dos serviços (required)
        $vServPrest->appendChild($dom->createElement('vServ', $data['valores']['valor_servicos']));

        // vReceb - Valor recebido pelo intermediário (optional - omit if zero)
        if (!$this->isZeroOrEmpty($data['valores']['valor_recebido_intermediario'])) {
            $vServPrest->appendChild($dom->createElement('vReceb', $data['valores']['valor_recebido_intermediario']));
        }

        // vDescCondIncond - Descontos condicionados e incondicionados (optional - omit if both zero)
        if (!$this->isZeroOrEmpty($data['valores']['desconto_incondicionado']) || !$this->isZeroOrEmpty($data['valores']['desconto_condicionado'])) {
            $vDescCondIncond = $dom->createElement('vDescCondIncond');
            $valores->appendChild($vDescCondIncond);

            if (!$this->isZeroOrEmpty($data['valores']['desconto_incondicionado'])) {
                $vDescCondIncond->appendChild($dom->createElement('vDescIncond', $data['valores']['desconto_incondicionado']));
            }

            if (!$this->isZeroOrEmpty($data['valores']['desconto_condicionado'])) {
                $vDescCondIncond->appendChild($dom->createElement('vDescCond', $data['valores']['desconto_condicionado']));
            }
        }

        // vDedRed - Valores para dedução/redução (optional - omit if zero)
        if (!$this->isZeroOrEmpty($data['valores']['valor_deducoes'])) {
            $vDedRed = $dom->createElement('vDedRed');
            $valores->appendChild($vDedRed);

            // Use valor monetário para dedução/redução
            $vDedRed->appendChild($dom->createElement('vDR', $data['valores']['valor_deducoes']));
        }

        // trib - Informações de tributação (required) - apenas campos obrigatórios
        $this->addTribGroupMinimal($dom, $valores, $data);
    }

    /**
     * Add Tributacao (Taxation) element to XML - APENAS CAMPOS OBRIGATÓRIOS
     * Conforme XSD TCInfoTributacao - versão simplificada
     */
    private function addTribGroupMinimal(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $trib = $dom->createElement('trib');
        $parent->appendChild($trib);

        // tribMun - Tributação municipal (ISSQN) - OBRIGATÓRIO
        $tribMun = $dom->createElement('tribMun');
        $trib->appendChild($tribMun);

        // tribISSQN - Tributação do ISSQN - OBRIGATÓRIO
        $tribISSQN = $this->determineIssqnTaxationType($data);
        $tribMun->appendChild($dom->createElement('tribISSQN', (string)$tribISSQN));

        // tpRetISSQN - Tipo de retenção do ISSQN - OBRIGATÓRIO
        $tpRetISSQN = $this->determineIssqnRetentionType($data);
        $tribMun->appendChild($dom->createElement('tpRetISSQN', (string)$tpRetISSQN));

        // totTrib - Total aproximado dos tributos - OBRIGATÓRIO
        $totTrib = $dom->createElement('totTrib');
        $trib->appendChild($totTrib);

        // indTotTrib - Indicador para não informar valores estimados - OBRIGATÓRIO
        // Valor fixo 0 conforme Decreto 8.264/2014
        $totTrib->appendChild($dom->createElement('indTotTrib', '0'));
    }



    /**
     * Determine ISSQN taxation type based on service data
     */
    private function determineIssqnTaxationType(array $data): int
    {
        // Check if service is for export (international service)
        if (!empty($data['codigo_pais']) && $data['codigo_pais'] !== 'BR') { // BR = Brazil (ISO Alpha-2)
            return 2; // Exportação de serviço
        }

        // Default to taxable operation for now
        // Can be enhanced later to check for immunity and non-incidence cases
        return 1; // Operação tributável
    }



    /**
     * Determine ISSQN retention type
     */
    private function determineIssqnRetentionType(array $data): int
    {
        // Check if ISS is retained by customer
        if (!empty($data['valores']['iss_retido']) && $data['valores']['iss_retido'] !== '0.00') {
            return 2; // Retido pelo Tomador
        }

        return 1; // Não Retido (default)
    }

    /**
     * Format tax rate to proper decimal format for XSD compliance
     * Uses 2 decimal places for percentages as required by XSD (TSDec1V2 type)
     */
    private function formatTaxRate(string $rate): string
    {
        return $this->formatPercentageValue($rate);
    }

    /**
     * Get ISS retention value from order
     */
    private function getIssRetidoValue(\WC_Order $order): string
    {
        $issRetido = $order->get_meta('_iss_retido');
        if (!empty($issRetido) && is_numeric($issRetido)) {
            return $this->formatMonetaryValue($issRetido);
        }
        return $this->formatMonetaryValue(0);
    }



    /**
     * Add Endereco to XML following TCEndereco structure for XSD compliance
     */
    private function addEndereco(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        // Create main address element (TCEndereco)
        $endereco = $dom->createElement('end');
        $parent->appendChild($endereco);

        // Add endNac (national address) sub-element with only cMun and CEP as per XSD
        $endNac = $dom->createElement('endNac');
        $endereco->appendChild($endNac);

        $endNac->appendChild($dom->createElement('cMun', $data['codigo_municipio']));
        $endNac->appendChild($dom->createElement('CEP', $data['cep']));

        // Common address fields (direct children of end element)
        $endereco->appendChild($dom->createElement('xLgr', htmlspecialchars($data['endereco'], ENT_XML1)));
        $endereco->appendChild($dom->createElement('nro', $data['numero']));

        if (!empty($data['complemento'])) {
            $endereco->appendChild($dom->createElement('xCpl', htmlspecialchars($data['complemento'], ENT_XML1)));
        }

        $endereco->appendChild($dom->createElement('xBairro', htmlspecialchars($data['bairro'], ENT_XML1)));
    }



    /**
     * Add RegTrib (Tax Regime) to XML for prestador element
     */
    private function addRegTrib(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $regTrib = $dom->createElement('regTrib');
        $parent->appendChild($regTrib);

        // Simples Nacional option (required)
        $regTrib->appendChild($dom->createElement('opSimpNac', $data['op_simples_nacional']));

        // Regime de apuração (optional) - only for ME/EPP (opSimpNac = 3)
        if ($data['op_simples_nacional'] === '3' && !empty($data['reg_ap_trib_sn'])) {
            $regTrib->appendChild($dom->createElement('regApTribSN', $data['reg_ap_trib_sn']));
        }

        // Special tax regime (required)
        $regTrib->appendChild($dom->createElement('regEspTrib', $data['reg_esp_trib']));
    }

    /**
     * Add Contato to XML
     */
    private function addContato(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $contato = $dom->createElement('cont');
        $parent->appendChild($contato);

        if (!empty($data['telefone'])) {
            $contato->appendChild($dom->createElement('fone', $data['telefone']));
        }

        if (!empty($data['email'])) {
            $contato->appendChild($dom->createElement('email', $data['email']));
        }
    }

    /**
     * Data sanitization helper methods
     */

    /**
     * Sanitize phone number to extract digits only
     * 
     * @param string $phone Raw phone number with possible formatting
     * @return string Phone number with digits only
     */
    private function sanitizePhone(string $phone): string
    {
        // Remove all non-digit characters
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Sanitize service description to remove HTML tags and entities
     * Ensures compliance with XSD TSDesc2000 type requirements (plain text, 1-2000 chars)
     * 
     * @param string $description Service description that may contain HTML
     * @return string Plain text description without HTML tags, meeting XSD requirements
     */
    private function sanitizeServiceDescription(string $description): string
    {
        // Remove HTML tags first using strip_tags() - XSD requires plain text
        $sanitized = strip_tags($description);

        // Then decode HTML entities to plain text (e.g., &amp; -> &, &lt; -> <, &gt; -> >)
        $sanitized = html_entity_decode($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace while preserving line breaks (XSD base type allows line breaks)
        $sanitized = preg_replace('/[ \t]+/', ' ', $sanitized); // Normalize spaces and tabs
        $sanitized = preg_replace('/\n\s*\n/', "\n", $sanitized); // Remove empty lines

        // Trim leading/trailing whitespace
        $sanitized = trim($sanitized);

        // Ensure XSD pattern compliance: TSStringComQuebraDeLinha pattern allows most characters
        // Remove any control characters except line breaks and carriage returns
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);

        return $sanitized;
    }

    /**
     * Format datetime to ISO8601 standard
     * 
     * @param string $datetime Input datetime string
     * @return string Formatted datetime in ISO8601 format (YYYY-MM-DDTHH:mm:ssTZD)
     */
    private function formatDateTimeISO8601(string $datetime): string
    {
        try {
            // Create DateTime object from input
            $dateTime = new \DateTime($datetime);

            // Format to ISO8601 with timezone
            return $dateTime->format('c'); // ISO8601 format: YYYY-MM-DDTHH:mm:ssTZD
        } catch (\Exception $e) {
            // Fallback to current time if parsing fails
            return (new \DateTime())->format('c');
        }
    }

    /**
     * Format monetary value to proper decimal format for XSD compliance
     * Uses 2 decimal places for currency values as required by XSD
     * 
     * @param float|string $value The monetary value to format
     * @return string Formatted monetary value with 2 decimal places
     */
    private function formatMonetaryValue($value): string
    {
        $numericValue = (float)str_replace(',', '.', (string)$value);
        return number_format($numericValue, 2, '.', '');
    }

    /**
     * Format percentage value to proper decimal format for XSD compliance
     * Uses 2 decimal places for percentages as required by XSD (TSDec1V2 type)
     * 
     * @param float|string $value The percentage value to format
     * @return string Formatted percentage value with 2 decimal places
     */
    private function formatPercentageValue($value): string
    {
        $numericValue = (float)str_replace(',', '.', (string)$value);
        return number_format($numericValue, 2, '.', '');
    }

    /**
     * Get country code for service provision location
     * Returns ISO Alpha-2 country code as required by XSD TSCodPaisISO type
     * 
     * @param \WC_Order $order The WooCommerce order
     * @return string ISO Alpha-2 country code (2 uppercase letters)
     */
    private function getServiceProvisionCountryCode(\WC_Order $order): string
    {
        // Get country from order billing address
        $billingCountry = $order->get_billing_country();

        // For now, default to Brazil as most services are domestic
        // This can be enhanced later to support international services
        if (empty($billingCountry) || $billingCountry === 'BR') {
            return 'BR'; // Brazil
        }

        // Validate that country code is ISO Alpha-2 format (2 uppercase letters)
        if (preg_match('/^[A-Z]{2}$/', $billingCountry)) {
            return $billingCountry;
        }

        // Convert common country codes to ISO Alpha-2 if needed
        $countryMapping = [
            'BRA' => 'BR', // Brazil
            'USA' => 'US', // United States
            'ARG' => 'AR', // Argentina
            'URY' => 'UY', // Uruguay
            'PRY' => 'PY', // Paraguay
            'CHL' => 'CL', // Chile
            'COL' => 'CO', // Colombia
            'PER' => 'PE', // Peru
            'BOL' => 'BO', // Bolivia
            'VEN' => 'VE', // Venezuela
        ];

        if (isset($countryMapping[$billingCountry])) {
            return $countryMapping[$billingCountry];
        }

        // Default to Brazil if country cannot be determined
        return 'BR';
    }

    /**
     * Check if a monetary value is zero or should be omitted from XML
     * 
     * @param string|float|null $value The monetary value to check
     * @return bool True if value is zero or should be omitted, false otherwise
     */
    private function isZeroOrEmpty($value): bool
    {
        if (empty($value) || $value === null) {
            return true;
        }

        // Convert to float for comparison
        $numericValue = (float)str_replace(',', '.', (string)$value);

        // Consider zero or very small values as empty
        return abs($numericValue) < 0.01;
    }

    /**
     * Get intermediary value from order
     * Returns the monetary value received by an intermediary service provider
     * 
     * @param \WC_Order $order The WooCommerce order
     * @return string Formatted monetary value or zero if no intermediary
     */
    private function getIntermediaryValue(\WC_Order $order): string
    {
        // Check if there's an intermediary value in order meta
        $intermediaryValue = $order->get_meta('_intermediary_value');

        if (!empty($intermediaryValue) && is_numeric($intermediaryValue)) {
            return $this->formatMonetaryValue($intermediaryValue);
        }

        // For most direct services, there's no intermediary
        // Return zero which will be omitted from XML by isZeroOrEmpty check
        return $this->formatMonetaryValue(0);
    }

    /**
     * Helper methods
     */
    private function getNextDpsNumber(): int
    {
        $currentNumber = get_option('wc_nfse_last_dps_number', 0);
        $nextNumber = $currentNumber + 1;
        update_option('wc_nfse_last_dps_number', $nextNumber);
        return $nextNumber;
    }

    //pensar em implementar aqui
    private function getMunicipalityCode(string $city, string $state): string
    {
        // Normaliza parâmetros
        $city  = mb_strtolower(trim($city), 'UTF-8');
        $state = strtoupper(trim($state));

        // Endpoint da API IBGE para os municípios de um estado
        $url = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$state}/municipios";

        // Requisição
        $response = wp_remote_get($url, [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return ''; // Retorna vazio se a requisição falhar
        }

        $body = wp_remote_retrieve_body($response);
        $municipios = json_decode($body, true);

        if (!is_array($municipios)) {
            return '';
        }

        // Procura pelo município
        foreach ($municipios as $municipio) {
            if (mb_strtolower($municipio['nome'], 'UTF-8') === $city) {
                return (string) $municipio['id']; // Código IBGE
            }
        }

        return ''; // Retorna vazio se não encontrar
    }



    private function getNbsCodeFromOrder(\WC_Order $order): string
    {
        // Try to get NBS code from products or categories
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $nbsCode = $product->get_meta('_nbs_code');
                if (!empty($nbsCode)) {
                    return $nbsCode;
                }

                // Try categories
                $categories = $product->get_category_ids();
                foreach ($categories as $categoryId) {
                    $nbsCode = get_term_meta($categoryId, '_nbs_code', true);
                    if (!empty($nbsCode)) {
                        return $nbsCode;
                    }
                }
            }
        }

        return $this->settings->getDefaultNbsCode();
    }

    private function buildServiceDescription(\WC_Order $order): string
    {
        $items = [];

        foreach ($order->get_items() as $item) {
            $productName = $item->get_name();
            $quantity = $item->get_quantity();
            $total = $item->get_total();

            // Build item description without HTML formatting (wc_price returns HTML)
            $formattedPrice = number_format((float)$total, 2, ',', '.');
            $items[] = sprintf('%dx %s - R$ %s', $quantity, $productName, $formattedPrice);
        }

        $description = implode('; ', $items);

        // Ensure minimum length requirement (XSD requires minLength="1", but business logic needs more descriptive text)
        if (strlen($description) < 15) {
            $description = sprintf(__('Venda de produtos/serviços - Pedido #%s', 'wc-nfse'), $order->get_order_number());
        }

        // Sanitize HTML tags and entities from description to meet XSD plain text requirements
        $description = $this->sanitizeServiceDescription($description);

        // Ensure XSD length constraints: TSDesc2000 allows 1-2000 characters
        if (strlen($description) > 2000) {
            $description = substr($description, 0, 1997) . '...';
        }

        // Final validation: ensure minimum length after sanitization
        if (strlen($description) < 1) {
            $description = sprintf(__('Serviço prestado - Pedido #%s', 'wc-nfse'), $order->get_order_number());
        }

        return $description;
    }

    private function calculateIssRate(float $base, float $tax): string
    {
        if ($base <= 0) {
            return $this->formatPercentageValue(0);
        }

        $rate = ($tax / $base) * 100;
        return $this->formatPercentageValue($rate);
    }

    private function extractNumberFromAddress(string $address): string
    {
        if (preg_match('/(\d+)/', $address, $matches)) {
            return $matches[1];
        }
        return 'S/N';
    }

    private function getBillingNeighborhood(\WC_Order $order): string
    {
        $neighborhood = $order->get_meta('_billing_neighborhood');
        if (!empty($neighborhood)) {
            return $neighborhood;
        }

        $address2 = $order->get_billing_address_2();
        if (!empty($address2)) {
            return $address2;
        }

        return 'Centro';
    }

    private function convertCamelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    private function validateXml(string $xml): void
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml)) {
            throw new Exception(__('XML generated is invalid.', 'wc-nfse'));
        }
    }

    /**
     * Test XSD compliant DPS generator functionality
     */
    public function testXsdCompliantGenerator(): array
    {
        $testResults = [];

        // Test basic XML generation structure
        try {
            //criar um campo no paineladm para inserir / selecionar o $sampleOrderId
            $sampleOrderId = 1; // Test with sample order ID
            $dpsResult = $this->generateDpsXml($sampleOrderId);
            $testResults['xml_generation'] = [
                'success' => isset($dpsResult['xml']),
                'xml_size' => isset($dpsResult['xml']) ? strlen($dpsResult['xml']) : 0,
                'dps_number' => $dpsResult['dps_number'] ?? null
            ];
        } catch (Exception $e) {
            $testResults['xml_generation'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        // Test schema compliance
        $xsdValidator = new NfSeXsdValidator();
        if ($xsdValidator->checkSchemasAvailability()['dps']['available']) {
            $testResults['xsd_compatibility'] = [
                'success' => true,
                'schema_file' => 'DPS_v1.00.xsd'
            ];
        } else {
            $testResults['xsd_compatibility'] = [
                'success' => false,
                'error' => 'DPS XSD schema not available'
            ];
        }

        // Test municipality code lookup
        $testResults['municipality_lookup'] = [
            'success' => !empty($this->getMunicipalityCode('São Paulo', 'SP')),
            'default_code' => $this->getMunicipalityCode('São Paulo', 'SP')
        ];

        return $testResults;
    }

    /**
     * Test data sanitization methods
     */
    public function testSanitizationMethods(): array
    {
        $testResults = [];

        // Test phone sanitization
        $testPhones = [
            '(11) 99999-9999' => '11999999999',
            '+55 11 9 9999-9999' => '5511999999999',
            '11.99999.9999' => '11999999999',
            '11 99999 9999' => '11999999999',
            'abc123def456' => '123456'
        ];

        foreach ($testPhones as $input => $expected) {
            $result = $this->sanitizePhone($input);
            $testResults['phone_sanitization'][] = [
                'input' => $input,
                'expected' => $expected,
                'result' => $result,
                'passed' => $result === $expected
            ];
        }

        // Test service description sanitization
        $testDescriptions = [
            '<p>Test description</p>' => 'Test description',
            'Description with <strong>HTML</strong> tags' => 'Description with HTML tags',
            'Text with &amp; entities &lt;test&gt;' => 'Text with & entities <test>',
            '  Multiple   spaces   ' => 'Multiple spaces',
            '<script>alert("test")</script>Clean text' => 'Clean text'
        ];

        foreach ($testDescriptions as $input => $expected) {
            $result = $this->sanitizeServiceDescription($input);
            $testResults['description_sanitization'][] = [
                'input' => $input,
                'expected' => $expected,
                'result' => $result,
                'passed' => $result === $expected
            ];
        }

        // Test ISO8601 datetime formatting
        $testDates = [
            '2024-01-15 14:30:00',
            '2024-12-31 23:59:59',
            'invalid-date'
        ];

        foreach ($testDates as $input) {
            $result = $this->formatDateTimeISO8601($input);
            $isValidISO8601 = (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $result);
            $testResults['datetime_formatting'][] = [
                'input' => $input,
                'result' => $result,
                'is_valid_iso8601' => $isValidISO8601,
                'passed' => $isValidISO8601
            ];
        }

        return $testResults;
    }

    /**
     * Generate XSD validation report for an order
     */
    public function generateXsdValidationReport(int $orderId): array
    {
        $report = [
            'timestamp' => current_time('mysql'),
            'order_id' => $orderId,
            'generation_result' => null,
            'xsd_validation' => null,
            'comprehensive_validation' => null,
            'recommendations' => []
        ];

        try {
            // Generate DPS XML
            $dpsResult = $this->generateDpsXml($orderId);
            $report['generation_result'] = [
                'success' => true,
                'xml' => $dpsResult['xml'],
                'dps_number' => $dpsResult['dps_number'],
                'xml_size' => strlen($dpsResult['xml'])
            ];

            // Validate against XSD
            $xsdValidator = new NfSeXsdValidator();
            $report['xsd_validation'] = $xsdValidator->validateDpsXml($dpsResult['xml']);

            // Run comprehensive validation
            $report['comprehensive_validation'] = $xsdValidator->generateComprehensiveValidationReport($dpsResult['xml']);

            // Generate recommendations
            if ($report['xsd_validation']['valid'] && $report['comprehensive_validation']['summary']['overall_valid']) {
                $report['recommendations'][] = __('DPS gerado com sucesso e validado contra schema XSD', 'wc-nfse');
                $report['recommendations'][] = __('Pronto para envio para a API gov.br', 'wc-nfse');
            } else {
                $report['recommendations'][] = __('Corrija os erros de validação antes de enviar', 'wc-nfse');
                if (!$report['xsd_validation']['valid']) {
                    $report['recommendations'][] = __('Erro na validação XSD - verifique campos obrigatórios', 'wc-nfse');
                }
            }
        } catch (Exception $e) {
            $report['generation_result'] = [
                'success' => false,
                'error' => $e->getMessage(),
                'xml' => null
            ];
            $report['recommendations'][] = __('Erro na geração da DPS: ', 'wc-nfse') . $e->getMessage();
        }

        return $report;
    }
}
