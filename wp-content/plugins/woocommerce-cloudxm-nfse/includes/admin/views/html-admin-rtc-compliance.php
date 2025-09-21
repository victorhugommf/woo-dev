<?php
/**
 * Admin page for RTC Compliance
 */

if (!defined('ABSPATH')) {
    exit;
}

// Use modern PSR-4 classes
$rtc_generator = \CloudXM\NFSe\Bootstrap\Factories::nfSeDpsGenerator();
$rtc_validator = new \CloudXM\NFSe\Services\NfSeRtcValidator();

// Handle test actions
$test_results = array();
$validation_report = array();

if (isset($_POST['test_validator'])) {
    $validator_results = $rtc_validator->testValidator();
    $test_results['validator'] = $validator_results;
}

if (isset($_POST['test_dps_generation']) && isset($_POST['test_order_id'])) {
    $order_id = intval($_POST['test_order_id']);

    try {
        $dps_result = $rtc_generator->generateDpsXml($order_id);
        $validation_result = $rtc_validator->validateDpsXml($dps_result['xml']);
        $validation_report = $rtc_validator->generateValidationReport($validation_result);
        
        $test_results['dps_generation'] = array(
            'success' => true,
            'dps_number' => $dps_result['dps_number'],
            'xml_size' => strlen($dps_result['xml']),
            'validation' => $validation_result
        );
        
        // Save test XML for download
        file_put_contents(WP_CONTENT_DIR . '/uploads/test_dps_rtc.xml', $dps_result['xml']);
        
    } catch (Exception $e) {
        $test_results['dps_generation'] = array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
}
?>

<div class="wrap">
    <h1><?php _e('Conformidade RTC - Layout v1.01.01', 'wc-nfse'); ?></h1>
    
    <div class="notice notice-info">
        <p><strong><?php _e('Correções Críticas Implementadas:', 'wc-nfse'); ?></strong></p>
        <ul>
            <li>✅ Estrutura XML corrigida: NFSe/infNFSe/DPS/infDPS</li>
            <li>✅ Identificador DPS de 45 caracteres implementado</li>
            <li>✅ Campos obrigatórios básicos adicionados (tpAmb, dhEmi, tpEmi)</li>
            <li>✅ Validação completa contra especificações RTC</li>
        </ul>
    </div>

    <div class="nav-tab-wrapper">
        <a href="#rtc-tests" class="nav-tab nav-tab-active"><?php _e('Testes RTC', 'wc-nfse'); ?></a>
        <a href="#dps-generation" class="nav-tab"><?php _e('Geração DPS', 'wc-nfse'); ?></a>
        <a href="#validation-report" class="nav-tab"><?php _e('Relatório Validação', 'wc-nfse'); ?></a>
        <a href="#rtc-documentation" class="nav-tab"><?php _e('Documentação RTC', 'wc-nfse'); ?></a>
    </div>

    <!-- RTC Tests Tab -->
    <div id="rtc-tests" class="tab-content">
        <h2><?php _e('Testes de Conformidade RTC', 'wc-nfse'); ?></h2>
        
        <div class="rtc-test-section">
            <h3><?php _e('Teste de Componentes Básicos', 'wc-nfse'); ?></h3>
            
            <form method="post" style="margin-bottom: 20px;">
                <input type="submit" name="test_validator" class="button button-secondary"
                       value="<?php _e('Testar Validador', 'wc-nfse'); ?>">
            </form>

            <?php if (!empty($test_results)): ?>
                <div class="test-results">
                    <h4><?php _e('Resultados dos Testes:', 'wc-nfse'); ?></h4>
                    
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

    <!-- DPS Generation Tab -->
    <div id="dps-generation" class="tab-content" style="display: none;">
        <h2><?php _e('Teste de Geração DPS', 'wc-nfse'); ?></h2>
        
        <div class="dps-generation-section">
            <h3><?php _e('Gerar DPS de Teste', 'wc-nfse'); ?></h3>
            
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
                                <?php _e('Digite o ID de um pedido WooCommerce existente para gerar DPS de teste.', 'wc-nfse'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="test_dps_generation" class="button button-primary" 
                           value="<?php _e('Gerar DPS de Teste', 'wc-nfse'); ?>">
                </p>
            </form>

            <?php if (isset($test_results['dps_generation'])): ?>
                <div class="dps-generation-results">
                    <h4><?php _e('Resultado da Geração:', 'wc-nfse'); ?></h4>
                    
                    <?php if ($test_results['dps_generation']['success']): ?>
                        <div class="notice notice-success">
                            <p><strong><?php _e('DPS gerado com sucesso!', 'wc-nfse'); ?></strong></p>
                            <ul>
                                <li><strong><?php _e('Número DPS:', 'wc-nfse'); ?></strong> <?php echo esc_html($test_results['dps_generation']['dps_number']); ?></li>
                                <li><strong><?php _e('Tamanho XML:', 'wc-nfse'); ?></strong> <?php echo esc_html($test_results['dps_generation']['xml_size']); ?> bytes</li>
                                <li><strong><?php _e('Validação:', 'wc-nfse'); ?></strong> 
                                    <span class="status-<?php echo $test_results['dps_generation']['validation']['valid'] ? 'success' : 'error'; ?>">
                                        <?php echo $test_results['dps_generation']['validation']['valid'] ? '✅ Válido' : '❌ Inválido'; ?>
                                    </span>
                                </li>
                            </ul>
                            
                            <p>
                                <a href="<?php echo content_url('uploads/test_dps_rtc.xml'); ?>" 
                                   class="button button-secondary" target="_blank">
                                    <?php _e('Download XML Gerado', 'wc-nfse'); ?>
                                </a>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-error">
                            <p><strong><?php _e('Erro na geração:', 'wc-nfse'); ?></strong></p>
                            <p><?php echo esc_html($test_results['dps_generation']['error']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Validation Report Tab -->
    <div id="validation-report" class="tab-content" style="display: none;">
        <h2><?php _e('Relatório de Validação RTC', 'wc-nfse'); ?></h2>
        
        <?php if (!empty($validation_report)): ?>
            <div class="validation-report">
                <div class="validation-summary">
                    <h3><?php _e('Resumo da Validação', 'wc-nfse'); ?></h3>
                    
                    <div class="summary-box status-<?php echo $validation_report['summary']['valid'] ? 'success' : 'error'; ?>">
                        <h4><?php echo esc_html($validation_report['summary']['status']); ?></h4>
                        <ul>
                            <li><strong><?php _e('Erros:', 'wc-nfse'); ?></strong> <?php echo $validation_report['summary']['total_errors']; ?></li>
                            <li><strong><?php _e('Avisos:', 'wc-nfse'); ?></strong> <?php echo $validation_report['summary']['total_warnings']; ?></li>
                        </ul>
                    </div>
                </div>

                <?php if (!empty($validation_report['errors'])): ?>
                    <div class="validation-errors">
                        <h3><?php _e('Erros Encontrados', 'wc-nfse'); ?></h3>
                        <ul class="error-list">
                            <?php foreach ($validation_report['errors'] as $error): ?>
                                <li class="error-item">❌ <?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($validation_report['warnings'])): ?>
                    <div class="validation-warnings">
                        <h3><?php _e('Avisos', 'wc-nfse'); ?></h3>
                        <ul class="warning-list">
                            <?php foreach ($validation_report['warnings'] as $warning): ?>
                                <li class="warning-item">⚠️ <?php echo esc_html($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="validation-recommendations">
                    <h3><?php _e('Recomendações', 'wc-nfse'); ?></h3>
                    <ul class="recommendation-list">
                        <?php foreach ($validation_report['recommendations'] as $recommendation): ?>
                            <li class="recommendation-item">💡 <?php echo esc_html($recommendation); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p><?php _e('Execute um teste de geração DPS para ver o relatório de validação.', 'wc-nfse'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- RTC Documentation Tab -->
    <div id="rtc-documentation" class="tab-content" style="display: none;">
        <h2><?php _e('Documentação RTC v1.01.01', 'wc-nfse'); ?></h2>
        
        <div class="rtc-documentation">
            <h3><?php _e('Principais Mudanças Implementadas', 'wc-nfse'); ?></h3>
            
            <div class="documentation-section">
                <h4><?php _e('1. Estrutura XML Corrigida', 'wc-nfse'); ?></h4>
                <div class="code-comparison">
                    <div class="code-before">
                        <h5><?php _e('❌ Antes (Incorreto)', 'wc-nfse'); ?></h5>
                        <pre><code>&lt;DPS xmlns="..."&gt;
    &lt;InfDPS Id="DPS{numero}"&gt;
        &lt;IdentificacaoDPS&gt;...&lt;/IdentificacaoDPS&gt;
    &lt;/InfDPS&gt;
&lt;/DPS&gt;</code></pre>
                    </div>
                    <div class="code-after">
                        <h5><?php _e('✅ Agora (Correto)', 'wc-nfse'); ?></h5>
                        <pre><code>&lt;NFSe xmlns="..."&gt;
    &lt;infNFSe&gt;
        &lt;DPS versao="1.01"&gt;
            &lt;infDPS id="DPS{45_chars}"&gt;
                &lt;tpAmb&gt;1&lt;/tpAmb&gt;
                &lt;dhEmi&gt;2025-01-09T10:30:00Z&lt;/dhEmi&gt;
                &lt;tpEmi&gt;1&lt;/tpEmi&gt;
                ...
            &lt;/infDPS&gt;
        &lt;/DPS&gt;
    &lt;/infNFSe&gt;
&lt;/NFSe&gt;</code></pre>
                    </div>
                </div>
            </div>

            <div class="documentation-section">
                <h4><?php _e('2. Identificador DPS (45 caracteres)', 'wc-nfse'); ?></h4>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Componente', 'wc-nfse'); ?></th>
                            <th><?php _e('Tamanho', 'wc-nfse'); ?></th>
                            <th><?php _e('Descrição', 'wc-nfse'); ?></th>
                            <th><?php _e('Exemplo', 'wc-nfse'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>DPS</td>
                            <td>3</td>
                            <td><?php _e('Literal "DPS"', 'wc-nfse'); ?></td>
                            <td><code>DPS</code></td>
                        </tr>
                        <tr>
                            <td><?php _e('Código Município', 'wc-nfse'); ?></td>
                            <td>7</td>
                            <td><?php _e('Código IBGE do município', 'wc-nfse'); ?></td>
                            <td><code>3550308</code></td>
                        </tr>
                        <tr>
                            <td><?php _e('Tipo Inscrição', 'wc-nfse'); ?></td>
                            <td>1</td>
                            <td><?php _e('1=CPF, 2=CNPJ', 'wc-nfse'); ?></td>
                            <td><code>2</code></td>
                        </tr>
                        <tr>
                            <td><?php _e('Inscrição Federal', 'wc-nfse'); ?></td>
                            <td>14</td>
                            <td><?php _e('CPF/CNPJ (completar com zeros)', 'wc-nfse'); ?></td>
                            <td><code>12345678000195</code></td>
                        </tr>
                        <tr>
                            <td><?php _e('Série', 'wc-nfse'); ?></td>
                            <td>5</td>
                            <td><?php _e('Série da DPS', 'wc-nfse'); ?></td>
                            <td><code>00001</code></td>
                        </tr>
                        <tr>
                            <td><?php _e('Número', 'wc-nfse'); ?></td>
                            <td>15</td>
                            <td><?php _e('Número sequencial da DPS', 'wc-nfse'); ?></td>
                            <td><code>000000000000001</code></td>
                        </tr>
                    </tbody>
                </table>
                <p><strong><?php _e('Resultado:', 'wc-nfse'); ?></strong> <code>DPS3550308212345678000195000010000000000000001</code></p>
            </div>

            <div class="documentation-section">
                <h4><?php _e('3. Campos Obrigatórios Adicionados', 'wc-nfse'); ?></h4>
                <ul>
                    <li><strong>tpAmb:</strong> <?php _e('Tipo ambiente (1=Produção, 2=Homologação)', 'wc-nfse'); ?></li>
                    <li><strong>dhEmi:</strong> <?php _e('Data/hora emissão em UTC (AAAA-MM-DDTHH:MM:SSZ)', 'wc-nfse'); ?></li>
                    <li><strong>tpEmi:</strong> <?php _e('Tipo emissão (1=App contribuinte, 2=Web fisco, 3=App fisco)', 'wc-nfse'); ?></li>
                    <li><strong>cTribNac:</strong> <?php _e('Código tributação nacional', 'wc-nfse'); ?></li>
                    <li><strong>xTribNac:</strong> <?php _e('Descrição tributação nacional', 'wc-nfse'); ?></li>
                    <li><strong>cLocIncid:</strong> <?php _e('Local incidência ISS', 'wc-nfse'); ?></li>
                </ul>
            </div>

            <div class="documentation-section">
                <h4><?php _e('4. Validações Implementadas', 'wc-nfse'); ?></h4>
                <ul>
                    <li>✅ <?php _e('Estrutura XML conforme RTC', 'wc-nfse'); ?></li>
                    <li>✅ <?php _e('Identificador DPS de 45 caracteres', 'wc-nfse'); ?></li>
                    <li>✅ <?php _e('Campos obrigatórios presentes', 'wc-nfse'); ?></li>
                    <li>✅ <?php _e('Formatos de dados corretos', 'wc-nfse'); ?></li>
                    <li>✅ <?php _e('Tamanhos de campos respeitados', 'wc-nfse'); ?></li>
                    <li>✅ <?php _e('Regras de negócio validadas', 'wc-nfse'); ?></li>
                    <li>✅ <?php _e('Validação CPF/CNPJ', 'wc-nfse'); ?></li>
                    <li>✅ <?php _e('Consistência de valores', 'wc-nfse'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.tab-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
}

.test-result-item {
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.status-success {
    color: #46b450;
    font-weight: bold;
}

.status-error {
    color: #dc3232;
    font-weight: bold;
}

.summary-box {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.summary-box.status-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.summary-box.status-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

.error-list, .warning-list, .recommendation-list {
    list-style: none;
    padding: 0;
}

.error-item, .warning-item, .recommendation-item {
    padding: 8px;
    margin-bottom: 5px;
    border-radius: 3px;
}

.error-item {
    background: #f8d7da;
    border-left: 4px solid #dc3232;
}

.warning-item {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}

.recommendation-item {
    background: #d1ecf1;
    border-left: 4px solid #17a2b8;
}

.code-comparison {
    display: flex;
    gap: 20px;
    margin: 15px 0;
}

.code-before, .code-after {
    flex: 1;
}

.code-before pre, .code-after pre {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
}

.documentation-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
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

