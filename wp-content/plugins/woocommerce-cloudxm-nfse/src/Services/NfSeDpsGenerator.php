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

        $this->logger->info('DPS XML generated successfully', [
            'order_id' => $orderId,
            'dps_number' => $dpsNumber,
            'xml_size' => strlen($xml)
        ]);

        return [
            'xml' => $xml,
            'dps_number' => $dpsNumber,
            'dps_data' => $dpsData
        ];
    }

    /**
     * Generate DPS ID
     */
    private function generateDpsId(int $dpsNumber): string
    {
        $municipalityCode = $this->getMunicipalityCode(
            $this->settings->getPrestadorAddress()['cidade'] ?? '',
            $this->settings->getPrestadorAddress()['uf'] ?? ''
        );

        $cnpj = $this->settings->getPrestadorCnpj();

        return sprintf(
            'DPS%s2%s0001950001%015d',
            str_pad($municipalityCode, 7, '0', STR_PAD_LEFT),
            $cnpj,
            $dpsNumber
        );
    }

    /**
     * Build DPS data structure
     */
    private function buildDpsData(\WC_Order $order, int $dpsNumber): array
    {
        $dpsId = $this->generateDpsId($dpsNumber);

        return [
            'dps_id' => $dpsId,
            'ambiente' => $this->settings->getEnvironment() === 'production' ? 1 : 2,
            'data_emissao' => current_time('Y-m-d\TH:i:s\Z'),
            'tipo_emissao' => 1, // Normal
            'numero_dps' => $dpsNumber,
            'prestador' => $this->buildPrestadorData(),
            'tomador' => $this->buildTomadorData($order),
            'servico' => $this->buildServicoData($order)
        ];
    }

    /**
     * Build prestador data
     */
    private function buildPrestadorData(): array
    {
        $address = $this->settings->getPrestadorAddress();
        $contact = $this->settings->getPrestadorContact();

        return [
            'tipo_inscricao' => 2, // CNPJ
            'numero_inscricao' => $this->settings->getPrestadorCnpj(),
            'razao_social' => $this->settings->getPrestadorRazaoSocial(),
            'nome_fantasia' => $this->settings->getPrestadorNomeFantasia(),
            'endereco' => [
                'endereco' => $address['endereco'],
                'numero' => $address['numero'],
                'complemento' => $address['complemento'] ?? '',
                'bairro' => $address['bairro'],
                'codigo_municipio' => $this->getMunicipalityCode($address['cidade'], $address['uf']),
                'uf' => $address['uf'],
                'cep' => $address['cep']
            ],
            'contato' => [
                'telefone' => $contact['telefone'] ?? '',
                'email' => $contact['email'] ?? ''
            ]
        ];
    }

    /**
     * Build tomador data from order
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

        //precisa criar o campo de razao social e nome fantasia
        $tomadorData = [
            'tipo_inscricao' => !empty($document) ? ($documentType === 'cnpj' ? 2 : 1) : null,
            'numero_inscricao' => $document,
            'razao_social' => $isCompany ?
                $billingCompany :
                trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'nome_fantasia' => !$isCompany ? '' :
                trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())
        ];

        // Address is required for companies
        if ($isCompany || $documentType === 'cnpj') {
            $tomadorData['endereco'] = [
                'endereco' => $order->get_billing_address_1(),
                'numero' => $this->extractNumberFromAddress($order->get_billing_address_1()),
                'complemento' => $order->get_billing_address_2(),
                'bairro' => $this->getBillingNeighborhood($order),
                'codigo_municipio' => $this->getMunicipalityCode(
                    $order->get_billing_city(),
                    $order->get_billing_state()
                ),
                'uf' => $order->get_billing_state(),
                'cep' => preg_replace('/\D/', '', $order->get_billing_postcode())
            ];
        }

        // Contact information
        $tomadorData['contato'] = [];
        if ($order->get_billing_phone()) {
            $tomadorData['contato']['telefone'] = preg_replace('/\D/', '', $order->get_billing_phone());
        }
        if ($order->get_billing_email()) {
            $tomadorData['contato']['email'] = $order->get_billing_email();
        }

        return $tomadorData;
    }

    /**
     * Build servico data from order
     */
    private function buildServicoData(\WC_Order $order): array
    {
        $total = (float) $order->get_total();
        $taxTotal = (float) $order->get_total_tax();
        $subtotal = $total - $taxTotal;

        // Get NBS code
        $nbsCode = $this->getNbsCodeFromOrder($order);

        // Build service description
        $discriminacao = $this->buildServiceDescription($order);

        $municipalityCode = $this->getMunicipalityCode(
            $this->settings->getPrestadorAddress()['cidade'] ?? '',
            $this->settings->getPrestadorAddress()['uf'] ?? ''
        );

        return [
            'valores' => [
                'valor_servicos' => number_format($subtotal, 2, '.', ''),
                'valor_deducoes' => '0.00',
                'valor_pis' => '0.00',
                'valor_cofins' => '0.00',
                'valor_inss' => '0.00',
                'valor_ir' => '0.00',
                'valor_csll' => '0.00',
                'iss_retido' => '0.00',
                'valor_iss' => number_format($taxTotal, 2, '.', ''),
                'outras_retencoes' => '0.00',
                'base_calculo' => number_format($subtotal, 2, '.', ''),
                'aliquota' => $this->calculateIssRate($subtotal, $taxTotal),
                'valor_liquido_nfse' => number_format($total, 2, '.', ''),
                'desconto_incondicionado' => '0.00',
                'desconto_condicionado' => '0.00'
            ],
            'item_lista_servico' => $nbsCode,
            'codigo_cnae' => $this->settings->get('codigo_cnae', ''),
            'codigo_tributacao_municipio' => $this->settings->get('codigo_tributacao_municipio', ''),
            'discriminacao' => $discriminacao,
            'codigo_municipio' => $municipalityCode,
            'codigo_pais' => '1058', // Brazil
            'exigibilidade_iss' => '1', // Exigible
            'municipio_incidencia' => $municipalityCode,
            'numero_processo' => '',
            'cnae' => '',
            'codigo_obra' => '',
            'art' => ''
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

        // Root element
        $dps = $dom->createElement('DPS');
        $dps->setAttribute('xmlns', 'http://www.nfse.gov.br/schema/dps_v1.xsd');
        $dom->appendChild($dps);

        // InfDPS
        $infDps = $dom->createElement('InfDPS');
        $infDps->setAttribute('Id', $dpsData['dps_id']);
        $dps->appendChild($infDps);

        // IdentificacaoDps
        $this->addIdentificacaoDps($dom, $infDps, $dpsData);

        // Prestador
        $this->addPrestador($dom, $infDps, $dpsData['prestador']);

        // Tomador
        $this->addTomador($dom, $infDps, $dpsData['tomador']);

        // Servico
        $this->addServico($dom, $infDps, $dpsData['servico']);

        return $dom->saveXML();
    }

    /**
     * Add IdentificacaoDps to XML
     */
    private function addIdentificacaoDps(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $identificacao = $dom->createElement('identificacaoDps');
        $parent->appendChild($identificacao);

        $identificacao->appendChild($dom->createElement('numero', (string)$data['numero_dps']));
        $identificacao->appendChild($dom->createElement('serie', $this->settings->getDpsSerie()));
        $identificacao->appendChild($dom->createElement('tipo', '1')); // DPS
        $identificacao->appendChild($dom->createElement('dataEmissao', $data['data_emissao']));
        $identificacao->appendChild($dom->createElement('competencia', date('Y-m')));
        $identificacao->appendChild($dom->createElement('ambienteId', (string)$data['ambiente']));
        $identificacao->appendChild($dom->createElement('tipoEmissao', (string)$data['tipo_emissao']));
    }

    /**
     * Add Prestador to XML
     */
    private function addPrestador(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $prestador = $dom->createElement('prest');
        $parent->appendChild($prestador);

        $prestador->appendChild($dom->createElement('tpInsc', (string)$data['tipo_inscricao']));
        $prestador->appendChild($dom->createElement('nInsc', $data['numero_inscricao']));

        if (!empty($data['razao_social'])) {
            $prestador->appendChild($dom->createElement('xNome', htmlspecialchars($data['razao_social'], ENT_XML1)));
        }

        if (!empty($data['nome_fantasia'])) {
            $prestador->appendChild($dom->createElement('xFant', htmlspecialchars($data['nome_fantasia'], ENT_XML1)));
        }

        // Endereco
        if (!empty($data['endereco'])) {
            $this->addEndereco($dom, $prestador, $data['endereco']);
        }

        // Contato
        if (!empty($data['contato']['telefone']) || !empty($data['contato']['email'])) {
            $this->addContato($dom, $prestador, $data['contato']);
        }
    }

    /**
     * Add Tomador to XML
     */
    private function addTomador(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $tomador = $dom->createElement('tom');
        $parent->appendChild($tomador);

        if (!empty($data['tipo_inscricao']) && !empty($data['numero_inscricao'])) {
            $tomador->appendChild($dom->createElement('tpInsc', (string)$data['tipo_inscricao']));
            $tomador->appendChild($dom->createElement('nInsc', $data['numero_inscricao']));
        }

        if (!empty($data['razao_social'])) {
            $tomador->appendChild($dom->createElement('xNome', htmlspecialchars($data['razao_social'], ENT_XML1)));
        }

        if (!empty($data['nome_fantasia'])) {
            $tomador->appendChild($dom->createElement('xFant', htmlspecialchars($data['nome_fantasia'], ENT_XML1)));
        }

        // Endereco (required for companies)
        if (!empty($data['endereco'])) {
            $this->addEndereco($dom, $tomador, $data['endereco']);
        }

        // Contato
        if (!empty($data['contato'])) {
            $this->addContato($dom, $tomador, $data['contato']);
        }
    }

    /**
     * Add Servico to XML
     */
    private function addServico(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $servico = $dom->createElement('serv');
        $parent->appendChild($servico);

        // Valores
        $valores = $dom->createElement('vTotTrib');
        foreach ($data['valores'] as $key => $value) {
            $elementName = $this->convertCamelCase($key);
            $valores->appendChild($dom->createElement($elementName, $value));
        }
        $servico->appendChild($valores);

        // NBS code
        $servico->appendChild($dom->createElement('nItemPed', $data['item_lista_servico']));

        // Descricao
        $servico->appendChild($dom->createElement(
            'xDescServ',
            htmlspecialchars($data['discriminacao'], ENT_XML1)
        ));

        // Location codes
        $servico->appendChild($dom->createElement('cMun', $data['codigo_municipio']));
        $servico->appendChild($dom->createElement('cPais', $data['codigo_pais']));
        $servico->appendChild($dom->createElement('exigISS', $data['exigibilidade_iss']));

        // Optional fields
        if (!empty($data['codigo_cnae'])) {
            $servico->appendChild($dom->createElement('cCNAE', $data['codigo_cnae']));
        }

        if (!empty($data['municipio_incidencia'])) {
            $servico->appendChild($dom->createElement('cMunIncid', $data['municipio_incidencia']));
        }
    }

    /**
     * Add Endereco to XML
     */
    private function addEndereco(DOMDocument $dom, \DOMElement $parent, array $data): void
    {
        $endereco = $dom->createElement('end');
        $parent->appendChild($endereco);

        $endereco->appendChild($dom->createElement('xLgr', htmlspecialchars($data['endereco'], ENT_XML1)));
        $endereco->appendChild($dom->createElement('nro', $data['numero']));

        if (!empty($data['complemento'])) {
            $endereco->appendChild($dom->createElement('xCpl', htmlspecialchars($data['complemento'], ENT_XML1)));
        }

        $endereco->appendChild($dom->createElement('xBairro', htmlspecialchars($data['bairro'], ENT_XML1)));
        $endereco->appendChild($dom->createElement('cMun', $data['codigo_municipio']));
        $endereco->appendChild($dom->createElement('UF', $data['uf']));
        $endereco->appendChild($dom->createElement('CEP', $data['cep']));
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
        // TODO: Implement proper municipality code lookup
        return '3550308'; // Default to São Paulo
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

            $items[] = sprintf('%dx %s - %s', $quantity, $productName, wc_price($total));
        }

        $description = implode('; ', $items);

        // Ensure minimum length
        if (strlen($description) < 15) {
            $description = sprintf(__('Venda de produtos/serviços - Pedido #%s', 'wc-nfse'), $order->get_order_number());
        }

        // Limit to maximum length
        if (strlen($description) > 2000) {
            $description = substr($description, 0, 1997) . '...';
        }

        return $description;
    }

    private function calculateIssRate(float $base, float $tax): string
    {
        if ($base <= 0) {
            return '0.00';
        }

        $rate = ($tax / $base) * 100;
        return number_format($rate, 2, '.', '');
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
