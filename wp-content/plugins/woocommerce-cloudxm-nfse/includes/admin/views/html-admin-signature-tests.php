<?php
/**
 * Admin signature tests page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$digital_signer = \CloudXM\NFSe\Bootstrap\Factories::nfSeDigitalSigner();
// Updated to use PSR-4 versions - removing legacy classes after migration is complete
$signature_validator = \CloudXM\NFSe\Bootstrap\Factories::nfSeSignatureValidator();
$xmlseclibs_integration = \CloudXM\NFSe\Bootstrap\Factories::nfSeXmlSecLibsIntegration();
$certificate_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();

$xmlseclibs_status = $xmlseclibs_integration->get_status();
$certificate_status = $certificate_manager->is_certificate_valid();
?>

<div class="wrap wc-nfse-signature-tests">
    <h1><?php _e('Testes de Assinatura Digital', 'wc-nfse'); ?></h1>

    <!-- Status Overview -->
    <div class="wc-nfse-status-overview">
        <div class="status-cards">
            <div class="status-card <?php echo $certificate_status ? 'enabled' : 'disabled'; ?>">
                <div class="status-icon">
                    <span class="dashicons <?php echo $certificate_status ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                </div>
                <div class="status-content">
                    <h3><?php _e('Certificado Digital', 'wc-nfse'); ?></h3>
                    <p><?php echo $certificate_status ? __('Válido e ativo', 'wc-nfse') : __('Inválido ou não configurado', 'wc-nfse'); ?></p>
                </div>
            </div>

            <div class="status-card <?php echo $xmlseclibs_status['available'] ? 'enabled' : 'disabled'; ?>">
                <div class="status-icon">
                    <span class="dashicons <?php echo $xmlseclibs_status['available'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                </div>
                <div class="status-content">
                    <h3><?php _e('XMLSecLibs', 'wc-nfse'); ?></h3>
                    <p><?php echo $xmlseclibs_status['available'] ? __('Disponível', 'wc-nfse') : __('Não instalado', 'wc-nfse'); ?></p>
                </div>
                <div class="status-actions">
                    <?php if (!$xmlseclibs_status['available']): ?>
                        <?php if ($xmlseclibs_status['can_install_composer']): ?>
                            <button type="button" class="button button-primary" id="install-xmlseclibs-composer">
                                <?php _e('Instalar via Composer', 'wc-nfse'); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($xmlseclibs_status['can_install_manual']): ?>
                            <button type="button" class="button" id="install-xmlseclibs-manual">
                                <?php _e('Instalar Manualmente', 'wc-nfse'); ?>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button type="button" class="button" id="uninstall-xmlseclibs">
                            <?php _e('Desinstalar', 'wc-nfse'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($xmlseclibs_status['available']): ?>
        <div class="xmlseclibs-info">
            <h3><?php _e('Informações XMLSecLibs', 'wc-nfse'); ?></h3>
            <table class="widefat">
                <tr>
                    <td><strong><?php _e('Versão:', 'wc-nfse'); ?></strong></td>
                    <td><?php echo esc_html($xmlseclibs_status['version'] ?? 'unknown'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Método de Instalação:', 'wc-nfse'); ?></strong></td>
                    <td><?php echo esc_html($xmlseclibs_status['installation_method'] ?? 'unknown'); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Test Sections -->
    <div class="wc-nfse-test-sections">
        <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
            <a href="#basic-tests" class="nav-tab nav-tab-active"><?php _e('Testes Básicos', 'wc-nfse'); ?></a>
            <a href="#advanced-tests" class="nav-tab"><?php _e('Testes Avançados', 'wc-nfse'); ?></a>
            <a href="#performance-tests" class="nav-tab"><?php _e('Performance', 'wc-nfse'); ?></a>
            <a href="#xmlseclibs-tests" class="nav-tab"><?php _e('XMLSecLibs', 'wc-nfse'); ?></a>
        </nav>

        <!-- Basic Tests -->
        <div id="basic-tests" class="wc-nfse-tab-content">
            <h2><?php _e('Testes Básicos de Assinatura', 'wc-nfse'); ?></h2>
            
            <div class="test-group">
                <h3><?php _e('T4.1 - Teste de Assinatura Digital', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se a assinatura XMLDSig é adicionada corretamente ao XML.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="digital_signature">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-digital_signature"></div>
            </div>

            <div class="test-group">
                <h3><?php _e('T4.2 - Teste de Validação de Assinatura', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se a validação de assinatura funciona corretamente.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="signature_validation">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-signature_validation"></div>
            </div>

            <div class="test-group">
                <h3><?php _e('T4.3 - Teste de Certificado na Assinatura', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se o certificado é incluído corretamente na assinatura.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="certificate_in_signature">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-certificate_in_signature"></div>
            </div>

            <div class="test-group">
                <h3><?php _e('T4.4 - Teste de Canonicalização XML', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se a canonicalização XML está funcionando corretamente.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="xml_canonicalization">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-xml_canonicalization"></div>
            </div>

            <div class="test-actions">
                <button type="button" class="button button-secondary" id="run-all-basic-tests">
                    <?php _e('Executar Todos os Testes Básicos', 'wc-nfse'); ?>
                </button>
            </div>
        </div>

        <!-- Advanced Tests -->
        <div id="advanced-tests" class="wc-nfse-tab-content" style="display: none;">
            <h2><?php _e('Testes Avançados', 'wc-nfse'); ?></h2>
            
            <div class="test-group">
                <h3><?php _e('T4.5 - Teste de Algoritmos de Assinatura', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se os algoritmos de assinatura estão corretos.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="signature_algorithms">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-signature_algorithms"></div>
            </div>

            <div class="test-group">
                <h3><?php _e('T4.6 - Teste de Informações do Certificado', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se as informações do certificado são extraídas corretamente.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="certificate_info_extraction">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-certificate_info_extraction"></div>
            </div>

            <div class="test-group">
                <h3><?php _e('T4.7 - Teste de Relatório de Validação', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se o relatório de validação é gerado corretamente.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="validation_report">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-validation_report"></div>
            </div>

            <div class="test-group">
                <h3><?php _e('T4.10 - Teste de Validação em Lote', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se a validação em lote funciona corretamente.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="batch_validation">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-batch_validation"></div>
            </div>

            <div class="test-actions">
                <button type="button" class="button button-secondary" id="run-all-advanced-tests">
                    <?php _e('Executar Todos os Testes Avançados', 'wc-nfse'); ?>
                </button>
            </div>
        </div>

        <!-- Performance Tests -->
        <div id="performance-tests" class="wc-nfse-tab-content" style="display: none;">
            <h2><?php _e('Testes de Performance', 'wc-nfse'); ?></h2>
            
            <div class="test-group">
                <h3><?php _e('T4.9 - Teste de Performance de Assinatura', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Mede o tempo de assinatura de múltiplos XMLs.', 'wc-nfse'); ?></p>
                <div class="performance-controls">
                    <label for="performance-iterations"><?php _e('Número de iterações:', 'wc-nfse'); ?></label>
                    <input type="number" id="performance-iterations" value="10" min="1" max="100" class="small-text">
                </div>
                <button type="button" class="button button-primary run-test" data-test="signature_performance">
                    <?php _e('Executar Teste de Performance', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-signature_performance"></div>
            </div>

            <div class="performance-chart">
                <h3><?php _e('Gráfico de Performance', 'wc-nfse'); ?></h3>
                <canvas id="performance-chart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- XMLSecLibs Tests -->
        <div id="xmlseclibs-tests" class="wc-nfse-tab-content" style="display: none;">
            <h2><?php _e('Testes XMLSecLibs', 'wc-nfse'); ?></h2>
            
            <?php if ($xmlseclibs_status['available']): ?>
            <div class="test-group">
                <h3><?php _e('T4.8 - Teste de Integração XMLSecLibs', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Verifica se a integração com XMLSecLibs está funcionando.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary run-test" data-test="xmlseclibs_integration">
                    <?php _e('Executar Teste', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-xmlseclibs_integration"></div>
            </div>

            <div class="test-group">
                <h3><?php _e('Teste de Funcionalidade XMLSecLibs', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Testa assinatura e verificação usando XMLSecLibs.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary" id="test-xmlseclibs-functionality">
                    <?php _e('Testar Funcionalidade', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-xmlseclibs-functionality"></div>
            </div>

            <div class="test-group">
                <h3><?php _e('Comparação de Performance', 'wc-nfse'); ?></h3>
                <p class="description"><?php _e('Compara performance entre implementação nativa e XMLSecLibs.', 'wc-nfse'); ?></p>
                <button type="button" class="button button-primary" id="compare-performance">
                    <?php _e('Comparar Performance', 'wc-nfse'); ?>
                </button>
                <div class="test-result" id="result-compare-performance"></div>
            </div>
            <?php else: ?>
            <div class="notice notice-warning">
                <p><?php _e('XMLSecLibs não está instalado. Instale a biblioteca para executar estes testes.', 'wc-nfse'); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Test Results Summary -->
    <div class="wc-nfse-test-summary" style="display: none;">
        <h2><?php _e('Resumo dos Testes', 'wc-nfse'); ?></h2>
        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-number" id="tests-passed">0</div>
                <div class="stat-label"><?php _e('Testes Aprovados', 'wc-nfse'); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="tests-failed">0</div>
                <div class="stat-label"><?php _e('Testes Falharam', 'wc-nfse'); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="tests-total">0</div>
                <div class="stat-label"><?php _e('Total de Testes', 'wc-nfse'); ?></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var testResults = {};
    var performanceData = [];

    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.wc-nfse-tab-content').hide();
        $(target).show();
    });

    // Run individual test
    $('.run-test').on('click', function() {
        var testName = $(this).data('test');
        var $button = $(this);
        var $result = $('#result-' + testName);
        
        $button.prop('disabled', true).text('<?php _e("Executando...", "wc-nfse"); ?>');
        $result.html('<div class="spinner is-active"></div>');
        
        var testData = {
            action: 'wc_nfse_run_signature_test',
            test_name: testName,
            nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
        };
        
        if (testName === 'signature_performance') {
            testData.iterations = $('#performance-iterations').val();
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: testData,
            success: function(response) {
                if (response.success) {
                    testResults[testName] = response.data;
                    displayTestResult($result, response.data, true);
                    
                    if (testName === 'signature_performance') {
                        updatePerformanceChart(response.data);
                    }
                } else {
                    testResults[testName] = response.data;
                    displayTestResult($result, response.data, false);
                }
                
                updateTestSummary();
            },
            error: function() {
                displayTestResult($result, {
                    message: '<?php _e("Erro na comunicação com o servidor", "wc-nfse"); ?>'
                }, false);
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e("Executar Teste", "wc-nfse"); ?>');
            }
        });
    });

    // Run all basic tests
    $('#run-all-basic-tests').on('click', function() {
        var tests = ['digital_signature', 'signature_validation', 'certificate_in_signature', 'xml_canonicalization'];
        runTestSequence(tests);
    });

    // Run all advanced tests
    $('#run-all-advanced-tests').on('click', function() {
        var tests = ['signature_algorithms', 'certificate_info_extraction', 'validation_report', 'batch_validation'];
        runTestSequence(tests);
    });

    // XMLSecLibs installation
    $('#install-xmlseclibs-composer').on('click', function() {
        installXMLSecLibs('composer');
    });

    $('#install-xmlseclibs-manual').on('click', function() {
        installXMLSecLibs('manual');
    });

    $('#uninstall-xmlseclibs').on('click', function() {
        if (confirm('<?php _e("Tem certeza que deseja desinstalar XMLSecLibs?", "wc-nfse"); ?>')) {
            uninstallXMLSecLibs();
        }
    });

    // XMLSecLibs functionality test
    $('#test-xmlseclibs-functionality').on('click', function() {
        var $button = $(this);
        var $result = $('#result-xmlseclibs-functionality');
        
        $button.prop('disabled', true).text('<?php _e("Testando...", "wc-nfse"); ?>');
        $result.html('<div class="spinner is-active"></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_test_xmlseclibs_functionality',
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                displayTestResult($result, response.data, response.success);
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e("Testar Funcionalidade", "wc-nfse"); ?>');
            }
        });
    });

    // Performance comparison
    $('#compare-performance').on('click', function() {
        var $button = $(this);
        var $result = $('#result-compare-performance');
        
        $button.prop('disabled', true).text('<?php _e("Comparando...", "wc-nfse"); ?>');
        $result.html('<div class="spinner is-active"></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_compare_signature_performance',
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                displayTestResult($result, response.data, response.success);
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e("Comparar Performance", "wc-nfse"); ?>');
            }
        });
    });

    function runTestSequence(tests) {
        var currentTest = 0;
        
        function runNextTest() {
            if (currentTest < tests.length) {
                var testName = tests[currentTest];
                $('.run-test[data-test="' + testName + '"]').trigger('click');
                
                // Wait for test to complete before running next
                setTimeout(function() {
                    currentTest++;
                    runNextTest();
                }, 2000);
            }
        }
        
        runNextTest();
    }

    function displayTestResult($container, data, success) {
        var statusClass = success ? 'success' : 'error';
        var statusIcon = success ? 'yes-alt' : 'dismiss';
        var statusText = success ? '<?php _e("PASSOU", "wc-nfse"); ?>' : '<?php _e("FALHOU", "wc-nfse"); ?>';
        
        var html = '<div class="test-result-content ' + statusClass + '">';
        html += '<div class="test-status">';
        html += '<span class="dashicons dashicons-' + statusIcon + '"></span>';
        html += '<strong>' + statusText + '</strong>';
        html += '</div>';
        
        if (data.message) {
            html += '<div class="test-message">' + data.message + '</div>';
        }
        
        if (data.details) {
            html += '<div class="test-details">';
            if (typeof data.details === 'object') {
                html += '<pre>' + JSON.stringify(data.details, null, 2) + '</pre>';
            } else {
                html += '<div>' + data.details + '</div>';
            }
            html += '</div>';
        }
        
        if (data.execution_time) {
            html += '<div class="test-timing">Tempo de execução: ' + data.execution_time + 'ms</div>';
        }
        
        html += '</div>';
        
        $container.html(html);
    }

    function updateTestSummary() {
        var passed = 0;
        var failed = 0;
        var total = Object.keys(testResults).length;
        
        for (var test in testResults) {
            if (testResults[test].success) {
                passed++;
            } else {
                failed++;
            }
        }
        
        $('#tests-passed').text(passed);
        $('#tests-failed').text(failed);
        $('#tests-total').text(total);
        
        if (total > 0) {
            $('.wc-nfse-test-summary').show();
        }
    }

    function updatePerformanceChart(data) {
        if (data.performance_data) {
            performanceData = data.performance_data;
            // Here you would update the chart with the performance data
            // This would require a charting library like Chart.js
        }
    }

    function installXMLSecLibs(method) {
        var action = method === 'composer' ? 'wc_nfse_install_xmlseclibs_composer' : 'wc_nfse_install_xmlseclibs_manual';
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: action,
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice('error', response.data.message);
                }
            }
        });
    }

    function uninstallXMLSecLibs() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_uninstall_xmlseclibs',
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice('error', response.data.message);
                }
            }
        });
    }

    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
});
</script>

<style>
.wc-nfse-signature-tests {
    margin: 20px 0;
}

.wc-nfse-status-overview {
    margin-bottom: 30px;
}

.status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.status-card.enabled {
    border-color: #46b450;
    background: #f0f8f0;
}

.status-card.disabled {
    border-color: #dc3232;
    background: #fdf0f0;
}

.status-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.status-card.enabled .dashicons {
    color: #46b450;
}

.status-card.disabled .dashicons {
    color: #dc3232;
}

.status-content {
    flex: 1;
}

.status-content h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
}

.status-content p {
    margin: 0;
    color: #666;
}

.status-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.xmlseclibs-info {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.xmlseclibs-info table {
    margin-top: 10px;
}

.wc-nfse-tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
    margin-bottom: 20px;
}

.test-group {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.test-group:last-child {
    border-bottom: none;
}

.test-group h3 {
    margin: 0 0 10px 0;
    color: #333;
}

.test-group .description {
    margin-bottom: 15px;
    color: #666;
}

.test-result {
    margin-top: 15px;
}

.test-result-content {
    padding: 15px;
    border-radius: 4px;
    border: 1px solid;
}

.test-result-content.success {
    background: #f0f8f0;
    border-color: #46b450;
}

.test-result-content.error {
    background: #fdf0f0;
    border-color: #dc3232;
}

.test-status {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.test-status .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.test-result-content.success .dashicons {
    color: #46b450;
}

.test-result-content.error .dashicons {
    color: #dc3232;
}

.test-message {
    margin-bottom: 10px;
}

.test-details {
    background: #f9f9f9;
    padding: 10px;
    border-radius: 3px;
    margin-bottom: 10px;
}

.test-details pre {
    margin: 0;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
}

.test-timing {
    font-size: 12px;
    color: #666;
}

.test-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.performance-controls {
    margin-bottom: 15px;
}

.performance-controls label {
    display: inline-block;
    width: 150px;
}

.performance-chart {
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

.wc-nfse-test-summary {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-top: 30px;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 15px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #666;
}

@media (max-width: 768px) {
    .status-cards {
        grid-template-columns: 1fr;
    }
    
    .summary-stats {
        grid-template-columns: 1fr;
    }
    
    .status-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

