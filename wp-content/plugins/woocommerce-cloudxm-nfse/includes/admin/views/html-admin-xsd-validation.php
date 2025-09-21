<?php
/**
 * Admin page for XSD Validation - Phase 3
 */

if (!defined('ABSPATH')) {
    exit;
}

$xsd_validator = new \CloudXM\NFSe\Services\NfSeXsdValidator();
$xsd_generator = \CloudXM\NFSe\Bootstrap\Factories::nfSeDpsGenerator();

// Handle test actions
$test_results = array();
$validation_report = array();
$schema_availability = array();

if (isset($_POST['check_schemas'])) {
    $schema_availability = $xsd_validator->checkSchemasAvailability();
}

if (isset($_POST['test_xsd_validator'])) {
    $test_results['xsd_validator'] = $xsd_validator->testXsdValidator();
}

if (isset($_POST['test_xsd_generator'])) {
    $test_results['xsd_generator'] = $xsd_generator->testXsdCompliantGenerator();
}

if (isset($_POST['validate_xml_sample'])) {
    $sample_xml = $_POST['sample_xml'] ?? '';
    
    if (!empty($sample_xml)) {
        $validation_result = $xsd_validator->validateDpsXml($sample_xml);
        $comprehensive_report = $xsd_validator->generateComprehensiveValidationReport($sample_xml, array('dps'));
        
        $test_results['xml_validation'] = array(
            'validation_result' => $validation_result,
            'comprehensive_report' => $comprehensive_report
        );
    }
}

if (isset($_POST['generate_xsd_compliant_dps']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    
    try {
        $validation_report = $xsd_generator->generateXsdValidationReport($order_id);
        
        // Save generated XML for download
        if ($validation_report['generation_result']['success']) {
            file_put_contents(WP_CONTENT_DIR . '/uploads/dps_xsd_compliant.xml', $validation_report['generation_result']['xml']);
        }
        
    } catch (Exception $e) {
        $test_results['generation_error'] = $e->getMessage();
    }
}

// Auto-check schemas on page load
if (empty($schema_availability)) {
    $schema_availability = $xsd_validator->checkSchemasAvailability();
}
?>

<div class="wrap">
    
    <div class="nav-tab-wrapper">
        <a href="#schema-status" class="nav-tab nav-tab-active"><?php _e('Status dos Schemas', 'wc-nfse'); ?></a>
        <a href="#xsd-validation" class="nav-tab"><?php _e('Validação XSD', 'wc-nfse'); ?></a>
        <a href="#compliant-generation" class="nav-tab"><?php _e('Geração Conforme', 'wc-nfse'); ?></a>
        <a href="#validation-report" class="nav-tab"><?php _e('Relatório Completo', 'wc-nfse'); ?></a>
        <a href="#testing-tools" class="nav-tab"><?php _e('Ferramentas de Teste', 'wc-nfse'); ?></a>
    </div>

    <!-- Schema Status Tab -->
    <div id="schema-status" class="tab-content">
        <h2><?php _e('Status dos Schemas XSD Oficiais', 'wc-nfse'); ?></h2>
        
        <form method="post" style="margin-bottom: 20px;">
            <input type="submit" name="check_schemas" class="button button-primary" 
                   value="<?php _e('Verificar Schemas', 'wc-nfse'); ?>">
        </form>

        <?php if (!empty($schema_availability)): ?>
            <div class="schema-status-grid">
                <?php foreach ($schema_availability as $schema_type => $status): ?>
                    <div class="schema-card status-<?php echo $status['available'] ? 'available' : 'missing'; ?>">
                        <h3><?php echo esc_html(ucfirst($schema_type)); ?></h3>
                        
                        <div class="schema-info">
                            <div class="status-indicator">
                                <?php if ($status['available']): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <span class="status-text"><?php _e('Disponível', 'wc-nfse'); ?></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <span class="status-text"><?php _e('Ausente', 'wc-nfse'); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($status['available']): ?>
                                <div class="schema-details">
                                    <p><strong><?php _e('Tamanho:', 'wc-nfse'); ?></strong> <?php echo esc_html(size_format($status['size'])); ?></p>
                                    <p><strong><?php _e('Modificado:', 'wc-nfse'); ?></strong> <?php echo esc_html(date('d/m/Y H:i:s', $status['modified'])); ?></p>
                                    <p><strong><?php _e('Legível:', 'wc-nfse'); ?></strong> 
                                        <?php echo $status['readable'] ? '✅ Sim' : '❌ Não'; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="schema-summary">
                <?php 
                $available_count = count(array_filter($schema_availability, function($s) { return $s['available']; }));
                $total_count = count($schema_availability);
                ?>
                <h3><?php _e('Resumo dos Schemas', 'wc-nfse'); ?></h3>
                <p><strong><?php _e('Disponíveis:', 'wc-nfse'); ?></strong> <?php echo $available_count; ?>/<?php echo $total_count; ?></p>
                
                <?php if ($available_count === $total_count): ?>
                    <div class="notice notice-success inline">
                        <p><?php _e('Todos os schemas XSD estão disponíveis! Validação completa habilitada.', 'wc-nfse'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <p><?php _e('Alguns schemas estão ausentes. Funcionalidade de validação pode estar limitada.', 'wc-nfse'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- XSD Validation Tab -->
    <div id="xsd-validation" class="tab-content" style="display: none;">
        <h2><?php _e('Validação XSD de XML', 'wc-nfse'); ?></h2>
        
        <div class="xsd-validation-section">
            <h3><?php _e('Validar XML Personalizado', 'wc-nfse'); ?></h3>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sample_xml"><?php _e('XML para Validação', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <textarea id="sample_xml" name="sample_xml" rows="15" cols="80" class="large-text code"><?php 
                                echo isset($_POST['sample_xml']) ? esc_textarea($_POST['sample_xml']) : 
                                '<?xml version="1.0" encoding="UTF-8"?>
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
                            ?></textarea>
                            <p class="description">
                                <?php _e('Cole aqui o XML que deseja validar contra os schemas XSD oficiais.', 'wc-nfse'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="validate_xml_sample" class="button button-primary" 
                           value="<?php _e('Validar XML', 'wc-nfse'); ?>">
                </p>
            </form>

            <?php if (isset($test_results['xml_validation'])): ?>
                <div class="xml-validation-results">
                    <h4><?php _e('Resultado da Validação XSD:', 'wc-nfse'); ?></h4>
                    
                    <?php 
                    $validation = $test_results['xml_validation']['validation_result'];
                    $comprehensive = $test_results['xml_validation']['comprehensive_report'];
                    ?>
                    
                    <div class="validation-summary status-<?php echo $validation['valid'] ? 'success' : 'error'; ?>">
                        <h5>
                            <?php if ($validation['valid']): ?>
                                ✅ <?php _e('XML Válido contra Schema XSD', 'wc-nfse'); ?>
                            <?php else: ?>
                                ❌ <?php _e('XML Inválido contra Schema XSD', 'wc-nfse'); ?>
                            <?php endif; ?>
                        </h5>
                        
                        <div class="validation-stats">
                            <span class="stat-item">
                                <strong><?php _e('Erros:', 'wc-nfse'); ?></strong> <?php echo count($validation['errors']); ?>
                            </span>
                            <span class="stat-item">
                                <strong><?php _e('Avisos:', 'wc-nfse'); ?></strong> <?php echo count($validation['warnings']); ?>
                            </span>
                            <span class="stat-item">
                                <strong><?php _e('Tempo:', 'wc-nfse'); ?></strong> <?php echo $validation['performance']['total_time']; ?>ms
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($validation['errors'])): ?>
                        <div class="validation-errors">
                            <h5><?php _e('Erros de Validação XSD:', 'wc-nfse'); ?></h5>
                            <ul class="error-list">
                                <?php foreach ($validation['errors'] as $error): ?>
                                    <li class="error-item">❌ <?php echo esc_html($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($validation['warnings'])): ?>
                        <div class="validation-warnings">
                            <h5><?php _e('Avisos de Validação XSD:', 'wc-nfse'); ?></h5>
                            <ul class="warning-list">
                                <?php foreach ($validation['warnings'] as $warning): ?>
                                    <li class="warning-item">⚠️ <?php echo esc_html($warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="performance-info">
                        <h5><?php _e('Informações de Performance:', 'wc-nfse'); ?></h5>
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <td><strong><?php _e('Tempo Total:', 'wc-nfse'); ?></strong></td>
                                    <td><?php echo esc_html($validation['performance']['total_time']); ?>ms</td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Tempo Validação Schema:', 'wc-nfse'); ?></strong></td>
                                    <td><?php echo esc_html($validation['performance']['schema_validation_time']); ?>ms</td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Tamanho XML:', 'wc-nfse'); ?></strong></td>
                                    <td><?php echo esc_html(size_format($validation['performance']['xml_size'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Uso de Memória:', 'wc-nfse'); ?></strong></td>
                                    <td><?php echo esc_html(size_format($validation['performance']['memory_usage'])); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Compliant Generation Tab -->
    <div id="compliant-generation" class="tab-content" style="display: none;">
        <h2><?php _e('Geração de DPS Conforme XSD', 'wc-nfse'); ?></h2>
        
        <div class="compliant-generation-section">
            <h3><?php _e('Gerar DPS 100% Conforme XSD', 'wc-nfse'); ?></h3>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="order_id"><?php _e('ID do Pedido WooCommerce', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="order_id" name="order_id" 
                                   value="<?php echo isset($_POST['order_id']) ? intval($_POST['order_id']) : ''; ?>" 
                                   class="regular-text" required>
                            <p class="description">
                                <?php _e('Digite o ID de um pedido WooCommerce para gerar DPS conforme XSD.', 'wc-nfse'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="generate_xsd_compliant_dps" class="button button-primary" 
                           value="<?php _e('Gerar DPS Conforme XSD', 'wc-nfse'); ?>">
                </p>
            </form>
        </div>
    </div>

    <!-- Validation Report Tab -->
    <div id="validation-report" class="tab-content" style="display: none;">
        <h2><?php _e('Relatório de Validação Completo', 'wc-nfse'); ?></h2>
        
        <?php if (!empty($validation_report)): ?>
            <div class="comprehensive-validation-report">
                <div class="report-header">
                    <h3><?php _e('Relatório de Geração e Validação XSD', 'wc-nfse'); ?></h3>
                    <p><strong><?php _e('Gerado em:', 'wc-nfse'); ?></strong> <?php echo esc_html($validation_report['timestamp']); ?></p>
                    <p><strong><?php _e('Pedido:', 'wc-nfse'); ?></strong> #<?php echo esc_html($validation_report['order_id']); ?></p>
                </div>

                <div class="generation-results">
                    <h4><?php _e('Resultado da Geração', 'wc-nfse'); ?></h4>
                    
                    <?php if ($validation_report['generation_result']['success']): ?>
                        <div class="notice notice-success inline">
                            <p><strong><?php _e('DPS gerada com sucesso!', 'wc-nfse'); ?></strong></p>
                            <ul>
                                <li><strong><?php _e('Número DPS:', 'wc-nfse'); ?></strong> <?php echo esc_html($validation_report['generation_result']['dps_number']); ?></li>
                                <li><strong><?php _e('Tamanho XML:', 'wc-nfse'); ?></strong> <?php echo esc_html(strlen($validation_report['generation_result']['xml'])); ?> bytes</li>
                                <li><strong><?php _e('Validação XSD:', 'wc-nfse'); ?></strong> 
                                    <?php echo $validation_report['generation_result']['xsd_validation']['valid'] ? '✅ Válido' : '❌ Inválido'; ?>
                                </li>
                            </ul>
                            
                            <p>
                                <a href="<?php echo content_url('uploads/dps_xsd_compliant.xml'); ?>" 
                                   class="button button-secondary" target="_blank">
                                    <?php _e('Download XML Conforme XSD', 'wc-nfse'); ?>
                                </a>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-error inline">
                            <p><strong><?php _e('Erro na geração da DPS:', 'wc-nfse'); ?></strong></p>
                            <ul>
                                <?php foreach ($validation_report['generation_result']['errors'] as $error): ?>
                                    <li>❌ <?php echo esc_html($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (isset($validation_report['comprehensive_validation'])): ?>
                    <div class="comprehensive-validation">
                        <h4><?php _e('Validação Abrangente', 'wc-nfse'); ?></h4>
                        
                        <?php $comp_val = $validation_report['comprehensive_validation']; ?>
                        
                        <div class="validation-summary-box status-<?php echo $comp_val['summary']['overall_valid'] ? 'success' : 'error'; ?>">
                            <h5><?php echo $comp_val['summary']['overall_valid'] ? '✅ Totalmente Válido' : '❌ Contém Erros'; ?></h5>
                            
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-number"><?php echo $comp_val['summary']['schemas_valid']; ?>/<?php echo $comp_val['summary']['schemas_tested']; ?></span>
                                    <span class="summary-label"><?php _e('Schemas Válidos', 'wc-nfse'); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-number"><?php echo $comp_val['summary']['total_errors']; ?></span>
                                    <span class="summary-label"><?php _e('Erros', 'wc-nfse'); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-number"><?php echo $comp_val['summary']['total_warnings']; ?></span>
                                    <span class="summary-label"><?php _e('Avisos', 'wc-nfse'); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-number"><?php echo $comp_val['summary']['compliance_percentage']; ?>%</span>
                                    <span class="summary-label"><?php _e('Conformidade', 'wc-nfse'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="recommendations">
                    <h4><?php _e('Recomendações', 'wc-nfse'); ?></h4>
                    <ul class="recommendations-list">
                        <?php foreach ($validation_report['recommendations'] as $recommendation): ?>
                            <li class="recommendation-item">🎯 <?php echo esc_html($recommendation); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p><?php _e('Execute uma geração de DPS conforme XSD para ver o relatório completo.', 'wc-nfse'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Testing Tools Tab -->
    <div id="testing-tools" class="tab-content" style="display: none;">
        <h2><?php _e('Ferramentas de Teste XSD', 'wc-nfse'); ?></h2>
        
        <div class="testing-tools-section">
            <h3><?php _e('Testes Automatizados', 'wc-nfse'); ?></h3>
            
            <form method="post" style="margin-bottom: 20px;">
                <input type="submit" name="test_xsd_validator" class="button button-primary" 
                       value="<?php _e('Testar Validador XSD', 'wc-nfse'); ?>">
                <input type="submit" name="test_xsd_generator" class="button button-secondary" 
                       value="<?php _e('Testar Gerador Conforme XSD', 'wc-nfse'); ?>">
            </form>

            <?php if (!empty($test_results)): ?>
                <div class="test-results-xsd">
                    <h4><?php _e('Resultados dos Testes XSD:', 'wc-nfse'); ?></h4>
                    
                    <?php foreach ($test_results as $test_name => $result): ?>
                        <div class="test-result-section">
                            <h5><?php echo esc_html(ucfirst(str_replace('_', ' ', $test_name))); ?></h5>
                            
                            <?php if (is_array($result)): ?>
                                <div class="test-result-details">
                                    <?php render_test_result_recursive($result); ?>
                                </div>
                            <?php else: ?>
                                <p><?php echo esc_html($result); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.schema-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.schema-card {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    background: #fff;
}

.schema-card.status-available {
    border-color: #46b450;
    background: #f7fcf7;
}

.schema-card.status-missing {
    border-color: #dc3232;
    background: #fcf7f7;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
}

.status-indicator .dashicons {
    font-size: 20px;
}

.status-available .dashicons {
    color: #46b450;
}

.status-missing .dashicons {
    color: #dc3232;
}

.schema-details p {
    margin: 5px 0;
    font-size: 14px;
}

.validation-summary {
    padding: 15px;
    border-radius: 6px;
    margin: 15px 0;
}

.validation-summary.status-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.validation-summary.status-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

.validation-stats {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.stat-item {
    font-size: 14px;
}

.error-list, .warning-list {
    list-style: none;
    padding: 0;
    margin: 10px 0;
}

.error-item, .warning-item {
    padding: 8px 12px;
    margin-bottom: 5px;
    border-radius: 4px;
}

.error-item {
    background: #f8d7da;
    border-left: 4px solid #dc3232;
}

.warning-item {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}

.validation-summary-box {
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.summary-item {
    text-align: center;
}

.summary-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
}

.summary-label {
    display: block;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.recommendations-list {
    list-style: none;
    padding: 0;
}

.recommendation-item {
    padding: 10px;
    margin-bottom: 8px;
    background: #d1ecf1;
    border-left: 4px solid #17a2b8;
    border-radius: 4px;
}

.test-result-section {
    margin-bottom: 25px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #fafafa;
}

.test-result-details table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.test-result-details td {
    padding: 8px;
    border-bottom: 1px solid #eee;
}

.test-result-details td:first-child {
    font-weight: bold;
    width: 30%;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab functionality
    $('.nav-tab').click(function(e) {
        e.preventDefault();
        
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide();
        
        $(this).addClass('nav-tab-active');
        
        var target = $(this).attr('href');
        $(target).show();
    });
});
</script>

<?php
// Helper function to render test results recursively
function render_test_result_recursive($data, $level = 0) {
    if ($level > 3) return; // Prevent infinite recursion
    
    if (is_array($data)) {
        echo '<table class="widefat">';
        echo '<tbody>';
        foreach ($data as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($key) . '</strong></td>';
            echo '<td>';
            if (is_bool($value)) {
                echo '<span class="status-' . ($value ? 'success' : 'error') . '">';
                echo $value ? '✅ Sucesso' : '❌ Falha';
                echo '</span>';
            } elseif (is_array($value)) {
                render_test_result_recursive($value, $level + 1);
            } else {
                echo '<code>' . esc_html($value) . '</code>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<code>' . esc_html($data) . '</code>';
    }
}
?>

