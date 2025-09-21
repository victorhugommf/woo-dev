<?php
/**
 * Test Signature System - Fase 4
 */

class WC_NFSe_Test_Signature_System extends WP_UnitTestCase {

    private $digital_signer;
    private $signature_validator;
    private $xmlseclibs_integration;
    private $certificate_manager;
    private $sample_xml;
    private $sample_certificate_data;

    public function setUp(): void {
        parent::setUp();
        
        $this->digital_signer = \CloudXM\NFSe\Bootstrap\Factories::nfSeDigitalSigner();
        $this->signature_validator = new WC_NFSe_Signature_Validator();
        $this->xmlseclibs_integration = new WC_NFSe_XMLSecLibs_Integration();
        $this->certificate_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
        
        $this->sample_xml = $this->get_sample_dps_xml();
        $this->sample_certificate_data = $this->get_sample_certificate_data();
    }

    /**
     * T4.1 - Teste de Assinatura Digital
     */
    public function test_digital_signature() {
        // Assinar XML
        $signed_xml = $this->digital_signer->sign_xml($this->sample_xml);
        
        // Verificar se assinatura foi adicionada
        $dom = new DOMDocument();
        $dom->loadXML($signed_xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        
        $signatures = $xpath->query('//ds:Signature');
        
        $this->assertEquals(1, $signatures->length, 'Assinatura não foi adicionada ao XML');
        
        // Verificar elementos obrigatórios da assinatura
        $this->assertEquals(1, $xpath->query('//ds:SignedInfo')->length, 'SignedInfo não encontrado');
        $this->assertEquals(1, $xpath->query('//ds:SignatureValue')->length, 'SignatureValue não encontrado');
        $this->assertEquals(1, $xpath->query('//ds:KeyInfo')->length, 'KeyInfo não encontrado');
        $this->assertEquals(1, $xpath->query('//ds:X509Certificate')->length, 'Certificado não encontrado');
    }

    /**
     * T4.2 - Teste de Validação de Assinatura
     */
    public function test_signature_validation() {
        // Assinar XML
        $signed_xml = $this->digital_signer->sign_xml($this->sample_xml);
        
        // Validar assinatura
        $this->assertTrue(
            $this->signature_validator->validate_signature($signed_xml),
            'Assinatura válida não foi reconhecida como válida'
        );
        
        // Testar XML modificado (deve falhar)
        $modified_xml = $this->modify_xml_content($signed_xml);
        $this->assertFalse(
            $this->signature_validator->validate_signature($modified_xml),
            'Assinatura inválida foi reconhecida como válida'
        );
    }

    /**
     * T4.3 - Teste de Certificado na Assinatura
     */
    public function test_certificate_in_signature() {
        // Assinar XML
        $signed_xml = $this->digital_signer->sign_xml($this->sample_xml);
        
        // Verificar se certificado está incluído
        $dom = new DOMDocument();
        $dom->loadXML($signed_xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        
        $cert_nodes = $xpath->query('//ds:X509Certificate');
        
        $this->assertEquals(1, $cert_nodes->length, 'Certificado não encontrado na assinatura');
        $this->assertNotEmpty($cert_nodes->item(0)->nodeValue, 'Conteúdo do certificado está vazio');
        
        // Verificar se o certificado é válido
        $cert_content = $cert_nodes->item(0)->nodeValue;
        $certificate_pem = "-----BEGIN CERTIFICATE-----\n" . 
                          chunk_split($cert_content, 64, "\n") . 
                          "-----END CERTIFICATE-----\n";
        
        $cert_resource = openssl_x509_read($certificate_pem);
        $this->assertNotFalse($cert_resource, 'Certificado na assinatura é inválido');
        
        if ($cert_resource) {
            openssl_x509_free($cert_resource);
        }
    }

    /**
     * T4.4 - Teste de Canonicalização XML
     */
    public function test_xml_canonicalization() {
        $dom = new DOMDocument();
        $dom->loadXML($this->sample_xml);
        
        $inf_dps = $dom->getElementsByTagName('InfDPS')->item(0);
        $this->assertNotNull($inf_dps, 'Elemento InfDPS não encontrado');
        
        // Testar canonicalização
        $canonical = $inf_dps->C14N(false, false);
        
        $this->assertNotEmpty($canonical, 'Canonicalização resultou em string vazia');
        $this->assertStringNotContainsString('<?xml', $canonical, 'Canonicalização contém declaração XML');
        
        // Testar consistência da canonicalização
        $canonical2 = $inf_dps->C14N(false, false);
        $this->assertEquals($canonical, $canonical2, 'Canonicalização não é consistente');
    }

    /**
     * T4.5 - Teste de Algoritmos de Assinatura
     */
    public function test_signature_algorithms() {
        $signed_xml = $this->digital_signer->sign_xml($this->sample_xml);
        
        $algorithms = $this->signature_validator->get_signature_algorithms($signed_xml);
        
        // Verificar algoritmos recomendados
        $this->assertEquals(
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            $algorithms['canonicalization'],
            'Algoritmo de canonicalização incorreto'
        );
        
        $this->assertEquals(
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
            $algorithms['signature'],
            'Algoritmo de assinatura incorreto'
        );
        
        $this->assertEquals(
            'http://www.w3.org/2001/04/xmlenc#sha256',
            $algorithms['digest'],
            'Algoritmo de digest incorreto'
        );
    }

    /**
     * T4.6 - Teste de Informações do Certificado
     */
    public function test_certificate_info_extraction() {
        $signed_xml = $this->digital_signer->sign_xml($this->sample_xml);
        
        $cert_info = $this->signature_validator->get_certificate_info($signed_xml);
        
        $this->assertNotNull($cert_info, 'Informações do certificado não foram extraídas');
        $this->assertArrayHasKey('subject_name', $cert_info);
        $this->assertArrayHasKey('issuer_name', $cert_info);
        $this->assertArrayHasKey('serial_number', $cert_info);
        $this->assertArrayHasKey('valid_from', $cert_info);
        $this->assertArrayHasKey('valid_to', $cert_info);
        $this->assertArrayHasKey('is_expired', $cert_info);
        
        $this->assertNotEmpty($cert_info['subject_name'], 'Nome do titular não encontrado');
        $this->assertNotEmpty($cert_info['issuer_name'], 'Nome do emissor não encontrado');
    }

    /**
     * T4.7 - Teste de Relatório de Validação
     */
    public function test_validation_report() {
        $signed_xml = $this->digital_signer->sign_xml($this->sample_xml);
        
        $report = $this->signature_validator->get_validation_report($signed_xml);
        
        $this->assertArrayHasKey('valid', $report);
        $this->assertArrayHasKey('errors', $report);
        $this->assertArrayHasKey('warnings', $report);
        $this->assertArrayHasKey('certificate_info', $report);
        $this->assertArrayHasKey('algorithms', $report);
        $this->assertArrayHasKey('validation_time', $report);
        
        $this->assertTrue($report['valid'], 'Relatório indica assinatura inválida');
        $this->assertIsArray($report['errors'], 'Erros não é um array');
        $this->assertIsArray($report['warnings'], 'Avisos não é um array');
    }

    /**
     * T4.8 - Teste de XMLSecLibs Integration
     */
    public function test_xmlseclibs_integration() {
        $status = $this->xmlseclibs_integration->get_status();
        
        $this->assertArrayHasKey('available', $status);
        $this->assertArrayHasKey('can_install_composer', $status);
        $this->assertArrayHasKey('can_install_manual', $status);
        
        if ($status['available']) {
            // Se xmlseclibs está disponível, testar funcionalidade
            $test_result = $this->xmlseclibs_integration->test_xmlseclibs();
            
            $this->assertArrayHasKey('success', $test_result);
            $this->assertArrayHasKey('message', $test_result);
            
            if ($test_result['success']) {
                // Testar assinatura com xmlseclibs
                $signed_xml = $this->xmlseclibs_integration->sign_xml_with_xmlseclibs(
                    $this->sample_xml,
                    $this->sample_certificate_data
                );
                
                $this->assertNotEmpty($signed_xml, 'XML assinado com xmlseclibs está vazio');
                
                // Testar verificação
                $verification = $this->xmlseclibs_integration->verify_xml_with_xmlseclibs($signed_xml);
                $this->assertTrue($verification, 'Verificação com xmlseclibs falhou');
            }
        }
    }

    /**
     * T4.9 - Teste de Performance de Assinatura
     */
    public function test_signature_performance() {
        $start_time = microtime(true);
        
        // Assinar 10 XMLs
        for ($i = 0; $i < 10; $i++) {
            $signed_xml = $this->digital_signer->sign_xml($this->sample_xml);
            $this->assertNotEmpty($signed_xml);
        }
        
        $end_time = microtime(true);
        $total_time = $end_time - $start_time;
        $avg_time = $total_time / 10;
        
        // Assinatura deve ser concluída em menos de 2 segundos por XML
        $this->assertLessThan(2.0, $avg_time, 'Assinatura muito lenta: ' . $avg_time . 's por XML');
        
        $this->addToAssertionCount(1); // Contabilizar teste de performance
    }

    /**
     * T4.10 - Teste de Validação em Lote
     */
    public function test_batch_validation() {
        // Criar múltiplos XMLs assinados
        $signed_xmls = array();
        for ($i = 0; $i < 5; $i++) {
            $signed_xmls[] = $this->digital_signer->sign_xml($this->sample_xml);
        }
        
        // Validar em lote
        $results = $this->signature_validator->batch_validate($signed_xmls);
        
        $this->assertCount(5, $results, 'Número incorreto de resultados');
        
        foreach ($results as $index => $result) {
            $this->assertArrayHasKey('valid', $result);
            $this->assertArrayHasKey('certificate_info', $result);
            $this->assertTrue($result['valid'], "Assinatura $index inválida");
        }
    }

    /**
     * T4.11 - Teste de Assinatura com Certificado Inválido
     */
    public function test_signature_with_invalid_certificate() {
        $invalid_cert_data = array(
            'certificate' => 'invalid_certificate_content',
            'private_key' => 'invalid_private_key_content'
        );
        
        $this->expectException(Exception::class);
        $this->digital_signer->sign_xml($this->sample_xml, null, $invalid_cert_data);
    }

    /**
     * T4.12 - Teste de Validação de XML Sem Assinatura
     */
    public function test_validation_unsigned_xml() {
        $result = $this->signature_validator->validate_signature($this->sample_xml);
        $this->assertFalse($result, 'XML sem assinatura foi considerado válido');
    }

    // Helper methods

    private function get_sample_dps_xml() {
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
                    <Discriminacao>Desenvolvimento de software</Discriminacao>
                    <CodigoMunicipio>3550308</CodigoMunicipio>
                </Servico>
            </InfDPS>
        </DPS>';
    }

    private function get_sample_certificate_data() {
        // Generate a test certificate for testing purposes
        $private_key = openssl_pkey_new(array(
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ));

        $csr = openssl_csr_new(array(
            'CN' => 'Test Certificate',
            'O' => 'Test Organization',
            'C' => 'BR'
        ), $private_key);

        $cert = openssl_csr_sign($csr, null, $private_key, 365);

        openssl_x509_export($cert, $cert_string);
        openssl_pkey_export($private_key, $private_key_string);

        return array(
            'certificate' => $cert_string,
            'private_key' => $private_key_string
        );
    }

    private function modify_xml_content($xml) {
        // Modify XML content to make signature invalid
        return str_replace('Desenvolvimento de software', 'Desenvolvimento de software modificado', $xml);
    }

    public function tearDown(): void {
        parent::tearDown();
    }
}

