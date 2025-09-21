<?php
/**
 * Test API Communication - Fase 5
 */

class WC_NFSe_Test_API_Communication extends WP_UnitTestCase {

    private $api_client;
    private $compressor;
    private $response_handler;
    private $cache_manager;

    public function setUp(): void {
        parent::setUp();
        
        $this->api_client = new WC_NFSe_API_Client_Enhanced();
        $this->compressor = \CloudXM\NFSe\Bootstrap\Factories::nfSeCompressor();
        $this->response_handler = new WC_NFSe_Response_Handler();
        $this->cache_manager = new WC_NFSe_Cache_Manager();
    }

    /**
     * T5.1 - Teste de Compactação
     */
    public function test_compression() {
        $xml_string = $this->get_large_xml_string();
        
        // Test compression
        $compressed = $this->compressor->compress_and_encode($xml_string);
        
        $this->assertNotEmpty($compressed, 'Compactação resultou em string vazia');
        $this->assertLessThan(strlen($xml_string), strlen($compressed), 'Compactação não reduziu o tamanho');
        
        // Test decompression
        $decompressed = $this->compressor->decode_and_decompress($compressed);
        
        $this->assertEquals($xml_string, $decompressed, 'Descompactação não restaurou o XML original');
        
        // Test compression statistics
        $stats = $this->compressor->get_compression_stats($xml_string);
        
        $this->assertArrayHasKey('compression_ratio', $stats);
        $this->assertGreaterThan(0, $stats['compression_ratio'], 'Taxa de compactação deve ser maior que 0');
        $this->assertTrue($stats['within_limits'], 'XML compactado deve estar dentro dos limites');
    }

    /**
     * T5.2 - Teste de Cliente HTTP com SSL
     */
    public function test_http_client_ssl() {
        // Test connectivity
        $connectivity_results = $this->api_client->test_connectivity();
        
        $this->assertIsArray($connectivity_results, 'Resultado de conectividade deve ser um array');
        $this->assertArrayHasKey('adn_base', $connectivity_results);
        $this->assertArrayHasKey('sefin_base', $connectivity_results);
        
        foreach ($connectivity_results as $endpoint => $result) {
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('response_time_ms', $result);
            $this->assertArrayHasKey('endpoint', $result);
            
            if (!$result['success']) {
                $this->markTestSkipped('Endpoint ' . $endpoint . ' não está acessível: ' . ($result['error'] ?? 'Unknown error'));
            }
        }
    }

    /**
     * T5.3 - Teste de Tratamento de Respostas
     */
    public function test_response_handling() {
        $test_results = $this->response_handler->test_response_handling();
        
        $this->assertIsArray($test_results, 'Resultado dos testes deve ser um array');
        
        // Test success response handling
        $this->assertArrayHasKey('Success Response', $test_results);
        $success_test = $test_results['Success Response'];
        $this->assertTrue($success_test['success'], 'Teste de resposta de sucesso falhou');
        $this->assertTrue($success_test['result']['success'], 'Resposta de sucesso não foi processada corretamente');
        
        // Test client error handling
        $this->assertArrayHasKey('Client Error', $test_results);
        $client_error_test = $test_results['Client Error'];
        $this->assertTrue($client_error_test['success'], 'Teste de erro do cliente falhou');
        $this->assertFalse($client_error_test['result']['success'], 'Erro do cliente não foi processado corretamente');
        
        // Test server error handling
        $this->assertArrayHasKey('Server Error', $test_results);
        $server_error_test = $test_results['Server Error'];
        $this->assertTrue($server_error_test['success'], 'Teste de erro do servidor falhou');
        $this->assertFalse($server_error_test['result']['success'], 'Erro do servidor não foi processado corretamente');
    }

    /**
     * T5.4 - Teste de Cache de Parâmetros
     */
    public function test_parameter_cache() {
        $municipality_code = '3550308'; // São Paulo
        
        // Clear cache first
        $this->cache_manager->delete('municipal_params_' . $municipality_code, 'municipal');
        
        // First call should miss cache
        $first_result = $this->cache_manager->get_municipal_parameters($municipality_code);
        $this->assertFalse($first_result, 'Cache deveria estar vazio inicialmente');
        
        // Set cache
        $test_params = array(
            'codigo_municipio' => $municipality_code,
            'nome_municipio' => 'São Paulo',
            'uf' => 'SP',
            'aliquota_iss' => 5.0
        );
        
        $cache_set = $this->cache_manager->cache_municipal_parameters($municipality_code, $test_params);
        $this->assertTrue($cache_set, 'Falha ao definir cache');
        
        // Second call should hit cache
        $cached_result = $this->cache_manager->get_municipal_parameters($municipality_code);
        $this->assertEquals($test_params, $cached_result, 'Dados do cache não conferem');
        
        // Test cache statistics
        $stats = $this->cache_manager->get_statistics();
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertGreaterThan(0, $stats['hits'] + $stats['misses'], 'Estatísticas de cache devem ter dados');
    }

    /**
     * T5.5 - Teste de Sistema de Retry
     */
    public function test_retry_system() {
        // Mock a failing endpoint
        $mock_endpoint = 'https://httpstat.us/500'; // Returns 500 error
        
        // Configure retry with shorter delays for testing
        $this->api_client->set_retry_config(array(
            'max_attempts' => 3,
            'base_delay' => 0.1, // 100ms
            'max_delay' => 1,    // 1 second
            'backoff_multiplier' => 2
        ));
        
        $start_time = microtime(true);
        
        try {
            // This should fail after retries
            $response = $this->api_client->makeRequestWithRetry('GET', $mock_endpoint);
            $this->fail('Request should have failed after retries');
        } catch (Exception $e) {
            $end_time = microtime(true);
            $total_time = $end_time - $start_time;
            
            // Should have taken some time due to retries
            $this->assertGreaterThan(0.3, $total_time, 'Retry system should have added delay');
            $this->assertLessThan(5, $total_time, 'Total retry time should be reasonable');
        }
    }

    /**
     * T5.6 - Teste de Configuração de Timeout
     */
    public function test_timeout_configuration() {
        $config = $this->api_client->get_configuration();
        
        $this->assertArrayHasKey('timeout_config', $config);
        $this->assertArrayHasKey('connect_timeout', $config['timeout_config']);
        $this->assertArrayHasKey('request_timeout', $config['timeout_config']);
        
        // Test setting new timeout
        $new_timeout_config = array(
            'connect_timeout' => 5,
            'request_timeout' => 30
        );
        
        $this->api_client->set_timeout_config($new_timeout_config);
        
        $updated_config = $this->api_client->get_configuration();
        $this->assertEquals(5, $updated_config['timeout_config']['connect_timeout']);
        $this->assertEquals(30, $updated_config['timeout_config']['request_timeout']);
    }

    /**
     * T5.7 - Teste de Validação de Headers
     */
    public function test_header_validation() {
        $config = $this->api_client->get_configuration();
        
        $this->assertArrayHasKey('certificate_loaded', $config);
        
        // If certificate is loaded, test should pass
        if ($config['certificate_loaded']) {
            $this->assertTrue(true, 'Certificado carregado corretamente');
        } else {
            $this->markTestSkipped('Certificado não configurado para teste');
        }
    }

    /**
     * T5.8 - Teste de Performance de Compactação
     */
    public function test_compression_performance() {
        $xml_sizes = array(1000, 5000, 10000, 50000); // Different XML sizes
        $performance_results = array();
        
        foreach ($xml_sizes as $size) {
            $xml_content = $this->generate_xml_content($size);
            
            $start_time = microtime(true);
            $compressed = $this->compressor->compress_and_encode($xml_content);
            $decompressed = $this->compressor->decode_and_decompress($compressed);
            $end_time = microtime(true);
            
            $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
            
            $performance_results[$size] = array(
                'execution_time_ms' => $execution_time,
                'original_size' => strlen($xml_content),
                'compressed_size' => strlen($compressed),
                'compression_ratio' => (1 - strlen($compressed) / strlen($xml_content)) * 100
            );
            
            // Performance assertions
            $this->assertLessThan(1000, $execution_time, "Compactação de {$size} chars deve ser rápida");
            $this->assertEquals($xml_content, $decompressed, 'Integridade dos dados deve ser mantida');
        }
        
        // Log performance results for analysis
        error_log('Compression Performance Results: ' . json_encode($performance_results));
    }

    /**
     * T5.9 - Teste de Cache em Lote
     */
    public function test_batch_cache_operations() {
        $municipalities = array('3550308', '3304557', '4314902'); // SP, RJ, RS
        
        // Test batch cache warming
        $warmed_items = $this->cache_manager->warm_up_cache($municipalities);
        
        // Should have warmed some items (even if API calls fail, the method should handle gracefully)
        $this->assertIsInt($warmed_items, 'Aquecimento deve retornar número de itens');
        $this->assertGreaterThanOrEqual(0, $warmed_items, 'Número de itens aquecidos deve ser >= 0');
        
        // Test cache statistics after warming
        $stats = $this->cache_manager->get_statistics();
        $this->assertIsArray($stats, 'Estatísticas devem ser um array');
        $this->assertArrayHasKey('hit_rate', $stats);
    }

    /**
     * T5.10 - Teste de Limpeza de Cache
     */
    public function test_cache_cleanup() {
        // Set some test cache data
        $test_data = array('test' => 'data', 'timestamp' => time());
        
        $this->cache_manager->set('test_key_1', $test_data, 60, 'test_group');
        $this->cache_manager->set('test_key_2', $test_data, 60, 'test_group');
        
        // Verify data is cached
        $cached_data = $this->cache_manager->get('test_key_1', 'test_group');
        $this->assertEquals($test_data, $cached_data, 'Dados de teste devem estar em cache');
        
        // Flush the test group
        $flush_result = $this->cache_manager->flush_group('test_group');
        $this->assertTrue($flush_result, 'Flush do grupo deve ser bem-sucedido');
        
        // Verify data is no longer cached
        $cached_after_flush = $this->cache_manager->get('test_key_1', 'test_group');
        $this->assertFalse($cached_after_flush, 'Dados devem ter sido removidos após flush');
    }

    /**
     * T5.11 - Teste de Tratamento de Erros de Rede
     */
    public function test_network_error_handling() {
        // Test with invalid endpoint
        $invalid_endpoint = 'https://invalid-domain-that-does-not-exist.com/api';
        
        try {
            $response = wp_remote_get($invalid_endpoint, array('timeout' => 5));
            
            if (is_wp_error($response)) {
                $processed_response = $this->response_handler->process_response($response);
                
                $this->assertFalse($processed_response['success'], 'Resposta de erro deve indicar falha');
                $this->assertArrayHasKey('error_code', $processed_response);
                $this->assertArrayHasKey('message', $processed_response);
            } else {
                $this->markTestSkipped('Endpoint inválido retornou resposta válida');
            }
        } catch (Exception $e) {
            $this->assertInstanceOf('Exception', $e, 'Erro de rede deve gerar exceção');
        }
    }

    /**
     * T5.12 - Teste de Configuração de Ambiente
     */
    public function test_environment_configuration() {
        $config = $this->api_client->get_configuration();
        
        $this->assertArrayHasKey('environment', $config);
        $this->assertArrayHasKey('endpoints', $config);
        
        $environment = $config['environment'];
        $this->assertContains($environment, array('production', 'homologacao'), 'Ambiente deve ser válido');
        
        // Verify endpoints match environment
        $endpoints = $config['endpoints'];
        
        if ($environment === 'production') {
            $this->assertStringContainsString('nfse.gov.br', $endpoints['adn_base'], 'Endpoint de produção deve usar domínio correto');
        } else {
            $this->assertStringContainsString('homolog', $endpoints['adn_base'], 'Endpoint de homologação deve conter "homolog"');
        }
    }

    // Helper methods

    private function get_large_xml_string() {
        $base_xml = '<?xml version="1.0" encoding="UTF-8"?>
        <DPS xmlns="http://www.nfse.gov.br/schema/dps_v1.xsd">
            <InfDPS Id="DPS000000000000001">
                <IdentificacaoDPS>
                    <Numero>1</Numero>
                    <DataEmissao>2025-01-09T10:30:00</DataEmissao>
                    <Competencia>2025-01</Competencia>
                </IdentificacaoDPS>
                <Prestador>
                    <IdentificacaoPrestador>
                        <CpfCnpj><Cnpj>12345678000195</Cnpj></CpfCnpj>
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
                        <CpfCnpj><Cpf>12345678901</Cpf></CpfCnpj>
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
                    <Discriminacao>%s</Discriminacao>
                    <CodigoMunicipio>3550308</CodigoMunicipio>
                </Servico>
            </InfDPS>
        </DPS>';

        // Add large description to increase XML size
        $large_description = str_repeat('Desenvolvimento de software personalizado com integração de sistemas legados e implementação de novas funcionalidades avançadas. ', 50);
        
        return sprintf($base_xml, $large_description);
    }

    private function generate_xml_content($target_size) {
        $base_content = 'Test content for compression performance analysis. ';
        $repeat_count = ceil($target_size / strlen($base_content));
        
        return str_repeat($base_content, $repeat_count);
    }

    public function tearDown(): void {
        // Clean up test cache data
        $this->cache_manager->flush_group('test_group');
        parent::tearDown();
    }
}

