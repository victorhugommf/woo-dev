<?php
/**
 * Admin page for RTC Complete Compliance - Phase 2
 */

if (!defined('ABSPATH')) {
    exit;
}

$rtc_generator = \CloudXM\NFSe\Bootstrap\Factories::nfSeDpsGenerator();
$rtc_validator = new \CloudXM\NFSe\Services\NfSeRtcValidatorComplete();

// Handle test actions
$test_results = array();
$validation_report = array();
$coverage_analysis = array();

if (isset($_POST['test_complete_implementation'])) {
    $test_results = $rtc_generator->testXsdCompliantGenerator();
}

if (isset($_POST['test_complete_validator'])) {
    $validator_results = $rtc_validator->testCompleteValidator();
    $test_results['validator'] = $validator_results;
}

if (isset($_POST['test_complete_dps_generation']) && isset($_POST['test_order_id'])) {
    $order_id = intval($_POST['test_order_id']);

    try {
        $dps_result = $rtc_generator->generateDpsXml($order_id);
        $validation_result = $rtc_validator->validateDpsXmlComplete($dps_result['xml']);
        $validation_report = $rtc_validator->generateCompleteValidationReport($validation_result);
        $coverage_analysis = $validation_result['coverage'];

        $test_results['complete_dps_generation'] = array(
            'success' => true,
            'dps_number' => $dps_result['dps_number'],
            'xml_size' => strlen($dps_result['xml']),
            'validation' => $validation_result
        );

        // Save test XML for download
        file_put_contents(WP_CONTENT_DIR . '/uploads/test_dps_rtc_complete.xml', $dps_result['xml']);

    } catch (Exception $e) {
        $test_results['complete_dps_generation'] = array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
}

if (isset($_POST['analyze_coverage'])) {
    // Analyze current implementation coverage
    try {
        $sample_xml = '<?xml version="1.0" encoding="UTF-8"?>
        <NFSe xmlns="http://www.nfse.gov.br/schema/nfse_v1.xsd">
            <infNFSe>
                <DPS versao="1.01">
                    <infDPS id="DPS3550308212345678000195000010000000000000001">
                        <tpAmb>2</tpAmb>
                        <dhEmi>2025-01-09T10:30:00Z</dhEmi>
                        <tpEmi>1</tpEmi>
                        <prest><tpInsc>2</tpInsc><nInsc>12345678000195</nInsc><IM>123456</IM><xNome>Teste</xNome></prest>
                        <tom><tpInsc>1</tpInsc><nInsc>12345678901</nInsc><xNome>João</xNome></tom>
                        <serv><cTribNac>010101</cTribNac><xTribNac>Teste</xTribNac><cLocIncid>3550308</cLocIncid><xDisc>Prestação de serviços de teste</xDisc><vServ>1000.00</vServ><vISS>50.00</vISS><vLiq>950.00</vLiq></serv>
                    </infDPS>
                </DPS>
            </infNFSe>
        </NFSe>';
        
        $validation_result = $rtc_validator->validateDpsXmlComplete($sample_xml);
        $coverage_analysis = $validation_result['coverage'];
        
    } catch (Exception $e) {
        $test_results['coverage_error'] = $e->getMessage();
    }
}
?>

<div class="wrap">
    <h1><?php _e('Validação Completa do Sistema', 'wc-nfse'); ?></h1>
    
    <div class="nav-tab-wrapper">
        <a href="#complete-tests" class="nav-tab nav-tab-active"><?php _e('Testes Completos', 'wc-nfse'); ?></a>
        <a href="#coverage-analysis" class="nav-tab"><?php _e('Análise de Cobertura', 'wc-nfse'); ?></a>
        <a href="#complete-generation" class="nav-tab"><?php _e('Geração Completa', 'wc-nfse'); ?></a>
        <a href="#validation-report-complete" class="nav-tab"><?php _e('Relatório Completo', 'wc-nfse'); ?></a>
        <a href="#implementation-details" class="nav-tab"><?php _e('Detalhes Implementação', 'wc-nfse'); ?></a>
    </div>

    <!-- Complete Tests Tab -->
    <div id="complete-tests" class="tab-content">
    
        <div class="complete-test-section">
            <h3><?php _e('Teste de Componentes', 'wc-nfse'); ?></h3>
            
            <form method="post" style="margin-bottom: 20px;">
                <input type="submit" name="test_complete_implementation" class="button button-primary" 
                       value="<?php _e('Testar Implementação Completa', 'wc-nfse'); ?>">
                <input type="submit" name="test_complete_validator" class="button button-secondary" 
                       value="<?php _e('Testar Validador Completo', 'wc-nfse'); ?>">
                <input type="submit" name="analyze_coverage" class="button button-secondary" 
                       value="<?php _e('Analisar Cobertura', 'wc-nfse'); ?>">
            </form>

            <?php if (!empty($test_results)): ?>
                <div class="test-results">
                    <h4><?php _e('Resultados dos Testes Completos:', 'wc-nfse'); ?></h4>
                    
                    <?php foreach ($test_results as $test_name => $result): ?>
                        <div class="test-result-item">
                            <h5><?php echo esc_html(ucfirst(str_replace('_', ' ', $test_name))); ?></h5>
                            
                            <?php if (is_array($result)): ?>
                                <table class="widefat">
                                    <tbody>
                                        <?php foreach ($result as $key => $value): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($key); ?></strong></td>
                                                <td>
                                                    <?php if (is_bool($value)): ?>
                                                        <span class="status-<?php echo $value ? 'success' : 'error'; ?>">
                                                            <?php echo $value ? '✅ Sucesso' : '❌ Falha'; ?>
                                                        </span>
                                                    <?php elseif (is_array($value)): ?>
                                                        <pre><?php echo esc_html(json_encode($value, JSON_PRETTY_PRINT)); ?></pre>
                                                    <?php else: ?>
                                                        <code><?php echo esc_html($value); ?></code>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p><?php echo esc_html($result); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Coverage Analysis Tab -->
    <div id="coverage-analysis" class="tab-content" style="display: none;">
        <h2><?php _e('Análise de Cobertura de Campos', 'wc-nfse'); ?></h2>
        
        <?php if (!empty($coverage_analysis)): ?>
            <div class="coverage-analysis">
                <div class="coverage-summary">
                    <h3><?php _e('Resumo da Cobertura', 'wc-nfse'); ?></h3>
                    
                    <div class="coverage-box">
                        <div class="coverage-percentage">
                            <span class="percentage-value"><?php echo esc_html($coverage_analysis['percentage']); ?>%</span>
                            <span class="percentage-label"><?php _e('Campos Implementados', 'wc-nfse'); ?></span>
                        </div>
                        <div class="coverage-details">
                            <p><strong><?php _e('Total de campos obrigatórios:', 'wc-nfse'); ?></strong> <?php echo esc_html($coverage_analysis['total_mandatory']); ?></p>
                            <p><strong><?php _e('Campos implementados:', 'wc-nfse'); ?></strong> <?php echo esc_html($coverage_analysis['implemented']); ?></p>
                            <p><strong><?php _e('Campos ausentes:', 'wc-nfse'); ?></strong> <?php echo esc_html($coverage_analysis['total_mandatory'] - $coverage_analysis['implemented']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="coverage-sections">
                    <h3><?php _e('Cobertura por Seção', 'wc-nfse'); ?></h3>
                    
                    <div class="sections-grid">
                        <?php foreach ($coverage_analysis['sections'] as $section => $data): ?>
                            <div class="section-coverage">
                                <h4><?php echo esc_html(ucfirst($section)); ?></h4>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo esc_attr($data['percentage']); ?>%"></div>
                                </div>
                                <div class="section-stats">
                                    <span class="percentage"><?php echo esc_html($data['percentage']); ?>%</span>
                                    <span class="fraction"><?php echo esc_html($data['implemented']); ?>/<?php echo esc_html($data['total']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p><?php _e('Execute "Analisar Cobertura" para ver a análise detalhada de campos implementados.', 'wc-nfse'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Complete Generation Tab -->
    <div id="complete-generation" class="tab-content" style="display: none;">
        <h2><?php _e('Teste de Geração de Documentos', 'wc-nfse'); ?></h2>
        
        <div class="complete-generation-section">
            <h3><?php _e('Gerar DPS Completa de Teste', 'wc-nfse'); ?></h3>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_order_id"><?php _e('ID do Pedido WooCommerce', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="test_order_id" name="test_order_id" 
                                   value="<?php echo isset($_POST['test_order_id']) ? intval($_POST['test_order_id']) : ''; ?>" 
                                   class="regular-text" required>
                            <p class="description">
                                <?php _e('Digite o ID de um pedido WooCommerce existente para gerar DPS completa.', 'wc-nfse'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="test_complete_dps_generation" class="button button-primary" 
                           value="<?php _e('Gerar DPS Completa', 'wc-nfse'); ?>">
                </p>
            </form>

            <?php if (isset($test_results['complete_dps_generation'])): ?>
                <div class="complete-generation-results">
                    <h4><?php _e('Resultado da Geração Completa:', 'wc-nfse'); ?></h4>
                    
                    <?php if ($test_results['complete_dps_generation']['success']): ?>
                        <div class="notice notice-success">
                            <p><strong><?php _e('DPS Completa gerada com sucesso!', 'wc-nfse'); ?></strong></p>
                            <ul>
                                <li><strong><?php _e('Número DPS:', 'wc-nfse'); ?></strong> <?php echo esc_html($test_results['complete_dps_generation']['dps_number']); ?></li>
                                <li><strong><?php _e('Tamanho XML:', 'wc-nfse'); ?></strong> <?php echo esc_html($test_results['complete_dps_generation']['xml_size']); ?> bytes</li>
                                <li><strong><?php _e('Validação:', 'wc-nfse'); ?></strong> 
                                    <span class="status-<?php echo $test_results['complete_dps_generation']['validation']['valid'] ? 'success' : 'error'; ?>">
                                        <?php echo $test_results['complete_dps_generation']['validation']['valid'] ? '✅ Válido' : '❌ Inválido'; ?>
                                    </span>
                                </li>
                                <?php if (isset($coverage_analysis['percentage'])): ?>
                                    <li><strong><?php _e('Cobertura:', 'wc-nfse'); ?></strong> 
                                        <span class="coverage-badge coverage-<?php echo $coverage_analysis['percentage'] >= 90 ? 'high' : ($coverage_analysis['percentage'] >= 70 ? 'medium' : 'low'); ?>">
                                            <?php echo esc_html($coverage_analysis['percentage']); ?>%
                                        </span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            
                            <p>
                                <a href="<?php echo content_url('uploads/test_dps_rtc_complete.xml'); ?>" 
                                   class="button button-secondary" target="_blank">
                                    <?php _e('Download XML Completo', 'wc-nfse'); ?>
                                </a>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-error">
                            <p><strong><?php _e('Erro na geração completa:', 'wc-nfse'); ?></strong></p>
                            <p><?php echo esc_html($test_results['complete_dps_generation']['error']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Validation Report Complete Tab -->
    <div id="validation-report-complete" class="tab-content" style="display: none;">
        <h2><?php _e('Relatório de Validação Completo', 'wc-nfse'); ?></h2>
        
        <?php if (!empty($validation_report)): ?>
            <div class="validation-report-complete">
                <div class="validation-summary-complete">
                    <h3><?php _e('Resumo da Validação Completa', 'wc-nfse'); ?></h3>
                    
                    <div class="summary-box-complete status-<?php echo $validation_report['summary']['valid'] ? 'success' : 'error'; ?>">
                        <h4><?php echo esc_html($validation_report['compliance_level']); ?></h4>
                        <div class="summary-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $validation_report['summary']['total_errors']; ?></span>
                                <span class="stat-label"><?php _e('Erros', 'wc-nfse'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $validation_report['summary']['total_warnings']; ?></span>
                                <span class="stat-label"><?php _e('Avisos', 'wc-nfse'); ?></span>
                            </div>
                            <?php if (isset($validation_report['coverage']['percentage'])): ?>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $validation_report['coverage']['percentage']; ?>%</span>
                                    <span class="stat-label"><?php _e('Cobertura', 'wc-nfse'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($validation_report['errors'])): ?>
                    <div class="validation-errors-complete">
                        <h3><?php _e('Erros Encontrados', 'wc-nfse'); ?></h3>
                        <ul class="error-list-complete">
                            <?php foreach ($validation_report['errors'] as $error): ?>
                                <li class="error-item-complete">❌ <?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($validation_report['warnings'])): ?>
                    <div class="validation-warnings-complete">
                        <h3><?php _e('Avisos', 'wc-nfse'); ?></h3>
                        <ul class="warning-list-complete">
                            <?php foreach ($validation_report['warnings'] as $warning): ?>
                                <li class="warning-item-complete">⚠️ <?php echo esc_html($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="validation-next-steps">
                    <h3><?php _e('Próximos Passos', 'wc-nfse'); ?></h3>
                    <ul class="next-steps-list">
                        <?php foreach ($validation_report['next_steps'] as $step): ?>
                            <li class="next-step-item">🎯 <?php echo esc_html($step); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p><?php _e('Execute um teste de geração DPS completa para ver o relatório de validação.', 'wc-nfse'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Implementation Details Tab -->
    <div id="implementation-details" class="tab-content" style="display: none;">
        <h2><?php _e('Recursos Implementados', 'wc-nfse'); ?></h2>

        <div class="implementation-details">
            <h3><?php _e('Funcionalidades do Sistema', 'wc-nfse'); ?></h3>
            
            <div class="implementation-section">
                <h4><?php _e('🏢 Prestador - Campos Adicionais', 'wc-nfse'); ?></h4>
                <ul class="field-list">
                    <li>✅ <strong>indCredSN:</strong> Indicador de crédito Simples Nacional</li>
                    <li>✅ <strong>endNac:</strong> Endereço nacional completo</li>
                    <li>✅ <strong>optSN:</strong> Opção pelo Simples Nacional</li>
                    <li>✅ <strong>dtIniSN:</strong> Data início Simples Nacional</li>
                    <li>✅ <strong>dtFimSN:</strong> Data fim Simples Nacional</li>
                    <li>✅ <strong>Validação CNPJ:</strong> Verificação de dígitos</li>
                </ul>
            </div>

            <div class="implementation-section">
                <h4><?php _e('👤 Tomador - Campos Adicionais', 'wc-nfse'); ?></h4>
                <ul class="field-list">
                    <li>✅ <strong>IE:</strong> Inscrição estadual (se CNPJ)</li>
                    <li>✅ <strong>endNac:</strong> Endereço nacional completo</li>
                    <li>✅ <strong>endExt:</strong> Endereço exterior (se aplicável)</li>
                    <li>✅ <strong>Detecção automática:</strong> CPF/CNPJ</li>
                    <li>✅ <strong>Validação CPF/CNPJ:</strong> Verificação de dígitos</li>
                    <li>✅ <strong>Códigos país:</strong> Mapeamento internacional</li>
                </ul>
            </div>

            <div class="implementation-section">
                <h4><?php _e('🛠️ Serviço - Campos Adicionais', 'wc-nfse'); ?></h4>
                <ul class="field-list">
                    <li>✅ <strong>vDeducao:</strong> Valor de deduções</li>
                    <li>✅ <strong>vDescIncond:</strong> Desconto incondicional</li>
                    <li>✅ <strong>vDescCond:</strong> Desconto condicional</li>
                    <li>✅ <strong>vISSRet:</strong> ISS retido</li>
                    <li>✅ <strong>vOutrasRet:</strong> Outras retenções</li>
                    <li>✅ <strong>respTrib:</strong> Responsabilidade tributária</li>
                    <li>✅ <strong>exigISS:</strong> Exigibilidade do ISS</li>
                    <li>✅ <strong>nProcesso:</strong> Número processo judicial</li>
                    <li>✅ <strong>xInfComp:</strong> Informações complementares</li>
                </ul>
            </div>

            <div class="implementation-section">
                <h4><?php _e('🧮 Cálculos Automáticos', 'wc-nfse'); ?></h4>
                <ul class="field-list">
                    <li>✅ <strong>Base de cálculo:</strong> vBC = vServ - vDeducao - vDescIncond</li>
                    <li>✅ <strong>ISS:</strong> vISS = vBC × (aliq ÷ 100)</li>
                    <li>✅ <strong>Valor líquido:</strong> vLiq = vServ - vISS</li>
                    <li>✅ <strong>Total tributos:</strong> vTotTrib = vISS + outras</li>
                    <li>✅ <strong>Validação matemática:</strong> Consistência de valores</li>
                </ul>
            </div>

            <div class="implementation-section">
                <h4><?php _e('🔍 Validações Implementadas', 'wc-nfse'); ?></h4>
                <ul class="field-list">
                    <li>✅ <strong>231 campos obrigatórios:</strong> Verificação completa</li>
                    <li>✅ <strong>Formatos de dados:</strong> Email, telefone, CEP, etc.</li>
                    <li>✅ <strong>Tamanhos de campos:</strong> Limites mínimos e máximos</li>
                    <li>✅ <strong>Indicadores válidos:</strong> Valores permitidos</li>
                    <li>✅ <strong>Códigos IBGE:</strong> Municípios e países</li>
                    <li>✅ <strong>Consistência matemática:</strong> Cálculos corretos</li>
                    <li>✅ <strong>Regras de negócio:</strong> Lógica tributária</li>
                </ul>
            </div>

            <div class="implementation-section">
                <h4><?php _e('📊 Análise de Cobertura', 'wc-nfse'); ?></h4>
                <ul class="field-list">
                    <li>✅ <strong>Cobertura por seção:</strong> Prestador, Tomador, Serviço</li>
                    <li>✅ <strong>Percentual geral:</strong> Campos implementados vs obrigatórios</li>
                    <li>✅ <strong>Campos ausentes:</strong> Identificação automática</li>
                    <li>✅ <strong>Nível de conformidade:</strong> Classificação automática</li>
                    <li>✅ <strong>Próximos passos:</strong> Recomendações específicas</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.coverage-box {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.coverage-percentage {
    text-align: center;
}

.percentage-value {
    display: block;
    font-size: 48px;
    font-weight: bold;
    color: #2271b1;
}

.percentage-label {
    display: block;
    font-size: 14px;
    color: #666;
}

.coverage-details p {
    margin: 5px 0;
}

.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.section-coverage {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #fff;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4caf50, #2196f3);
    transition: width 0.3s ease;
}

.section-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.percentage {
    font-weight: bold;
    color: #2271b1;
}

.fraction {
    color: #666;
    font-size: 14px;
}

.summary-box-complete {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.summary-box-complete.status-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.summary-box-complete.status-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

.summary-stats {
    display: flex;
    gap: 30px;
    margin-top: 15px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.coverage-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 12px;
}

.coverage-high {
    background: #d4edda;
    color: #155724;
}

.coverage-medium {
    background: #fff3cd;
    color: #856404;
}

.coverage-low {
    background: #f8d7da;
    color: #721c24;
}

.field-list {
    list-style: none;
    padding: 0;
}

.field-list li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.implementation-section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #fafafa;
}

.error-list-complete, .warning-list-complete, .next-steps-list {
    list-style: none;
    padding: 0;
}

.error-item-complete, .warning-item-complete, .next-step-item {
    padding: 10px;
    margin-bottom: 8px;
    border-radius: 4px;
}

.error-item-complete {
    background: #f8d7da;
    border-left: 4px solid #dc3232;
}

.warning-item-complete {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}

.next-step-item {
    background: #d1ecf1;
    border-left: 4px solid #17a2b8;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab functionality
    $('.nav-tab').click(function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide();
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        var target = $(this).attr('href');
        $(target).show();
    });
});
</script>

