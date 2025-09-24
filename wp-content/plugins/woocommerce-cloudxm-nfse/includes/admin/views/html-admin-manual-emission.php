<?php

/**
 * Admin page for Manual NFS-e Emission
 */

if (!defined('ABSPATH')) {
    exit;
}

// Initialize services
$logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
$emission_service = \CloudXM\NFSe\Bootstrap\Factories::nfSeEmissionService();

// Handle form submissions
$emission_result = array();
$query_status = array();

if (isset($_POST['check_emission_status']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);

    try {
        $order = wc_get_order($order_id);
        if ($order) {
            $dps_number = get_post_meta($order_id, '_nfse_dps_number', true);
            $nfse_number = get_post_meta($order_id, '_nfse_number', true);
            $nfse_status = get_post_meta($order_id, '_nfse_status', true);
            $emitido_em = get_post_meta($order_id, '_nfse_emitido_em', true);

            $query_status = array(
                'found' => true,
                'order_id' => $order_id,
                'order_status' => $order->get_status(),
                'dps_number' => $dps_number,
                'nfse_number' => $nfse_number,
                'nfse_status' => $nfse_status,
                'emitido_em' => $emitido_em,
                'customer_name' => $order->get_formatted_billing_full_name(),
                'total' => $order->get_total()
            );
        } else {
            $query_status = array(
                'found' => false,
                'error' => __('Pedido não encontrado', 'wc-nfse')
            );
        }
    } catch (Exception $e) {
        $query_status = array(
            'found' => false,
            'error' => $e->getMessage()
        );
    }
}

if (isset($_POST['emit_nfse_manually']) && isset($_POST['order_id']) && isset($_POST['emission_type'])) {
    $order_id = intval($_POST['order_id']);
    $emission_type = sanitize_text_field($_POST['emission_type']);

    try {
        $logger->info('Manual NFS-e emission started', array(
            'order_id' => $order_id,
            'emission_type' => $emission_type,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ));

        // Emit NFS-e
        $result = $emission_service->processEmission($order_id, true);

        // processEmission() returns success data or throws exception
        $emission_result = array(
            'success' => true,
            'message' => $result['message'] ?? __('NFS-e emitida com sucesso!', 'wc-nfse'),
            'nfse_number' => $result['access_key'] ?? '',
            'nfse_data' => $result
        );

        $logger->info('Manual NFS-e emission completed successfully', array(
            'order_id' => $order_id,
            'emission_id' => $result['emission_id'] ?? '',
            'access_key' => $result['access_key'] ?? ''
        ));
    } catch (Exception $e) {
        $emission_result = array(
            'success' => false,
            'error' => $e->getMessage()
        );

        $logger->error('Manual NFS-e emission exception', array(
            'order_id' => $order_id,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ));
    }
}

if (isset($_POST['generate_dps_xml']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);

    try {
        $dps_generator = \CloudXM\NFSe\Bootstrap\Factories::nfSeDpsGenerator();
        $xml_result = $dps_generator->generateDpsXml($order_id);

        $xml_content = $xml_result['xml'] ?? '';
        $signed_xml = '';
        $signature_info = array();

        // Sign the XML if generation was successful and XML content exists
        if (!empty($xml_content)) {
            try {
                $digital_signer = new \CloudXM\NFSe\Services\NfSeDigitalSigner();
                $signed_xml = $digital_signer->signXml($xml_content);

                $signature_info = array(
                    'signed' => true,
                    'signature_timestamp' => $digital_signer->getSignatureTimestamp($signed_xml),
                    'certificate_info' => $digital_signer->extractCertificateInfo($signed_xml)
                );
            } catch (Exception $sign_e) {
                // If signing fails, keep the unsigned XML but log the error
                $signed_xml = $xml_content;
                $signature_info = array(
                    'signed' => false,
                    'error' => $sign_e->getMessage()
                );
            }
        }

        // Get comprehensive XSD validation results
        $xsd_validation = $xml_result['xsd_validation'] ?? array();

        // Generate comprehensive validation report if XSD validation exists
        $comprehensive_validation = array();
        if (!empty($xsd_validation)) {
            $xsd_validator = new \CloudXM\NFSe\Services\NfSeXsdValidator();
            $comprehensive_validation = $xsd_validator->generateComprehensiveValidationReport(
                $signed_xml ?: $xml_content,
                ['dps']
            );
        }

        $emission_result = array(
            'xml_generated' => true,
            'xml_content' => $signed_xml ?: $xml_content,
            'xml_validation' => $xsd_validation,
            'comprehensive_validation' => $comprehensive_validation,
            'signature_info' => $signature_info
        );
    } catch (Exception $e) {
        $emission_result = array(
            'xml_generated' => false,
            'error' => $e->getMessage()
        );
    }
}

// Get recent orders for quick selection
$recent_orders = wc_get_orders(array(
    'limit' => 20,
    'orderby' => 'date',
    'order' => 'DESC',
    'status' => array('wc-processing', 'wc-completed')
));
?>

<div class="wrap">
    <h1><?php _e('Emissão Manual de NFS-e', 'wc-nfse'); ?></h1>

    <div class="notice notice-info">
        <p><strong><?php _e('Emissão Manual:', 'wc-nfse'); ?></strong></p>
        <ul>
            <li>🔍 <?php _e('Verifique o status de emissão de pedidos existentes', 'wc-nfse'); ?></li>
            <li>📄 <?php _e('Gere XML DPS para validação antes da emissão', 'wc-nfse'); ?></li>
            <li>🚀 <?php _e('Emita NFS-e manualmente para pedidos específicos', 'wc-nfse'); ?></li>
            <li>📊 <?php _e('Acompanhe o status e resultados da emissão', 'wc-nfse'); ?></li>
        </ul>
    </div>

    <div class="nav-tab-wrapper">
        <a href="#order-check" class="nav-tab nav-tab-active"><?php _e('Verificar Pedido', 'wc-nfse'); ?></a>
        <a href="#xml-generation" class="nav-tab"><?php _e('Gerar XML DPS', 'wc-nfse'); ?></a>
        <a href="#xsd-validation" class="nav-tab"><?php _e('Validação XSD', 'wc-nfse'); ?></a>
        <a href="#manual-emission" class="nav-tab"><?php _e('Emitir Manualmente', 'wc-nfse'); ?></a>
        <a href="#bulk-actions" class="nav-tab"><?php _e('Ações em Lote', 'wc-nfse'); ?></a>
    </div>

    <!-- Order Status Check Tab -->
    <div id="order-check" class="tab-content">
        <h2><?php _e('Verificar Status de Emissão', 'wc-nfse'); ?></h2>

        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="check_order_id"><?php _e('ID do Pedido', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="check_order_id" name="order_id"
                            value="<?php echo isset($_POST['order_id']) ? intval($_POST['order_id']) : ''; ?>"
                            class="regular-text" required>
                        <input type="submit" name="check_emission_status" class="button button-secondary"
                            value="<?php _e('Verificar Status', 'wc-nfse'); ?>">
                        <p class="description">
                            <?php _e('Digite o ID do pedido WooCommerce para verificar o status da NFS-e.', 'wc-nfse'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php if (!empty($query_status)): ?>
                <div class="order-status-results">
                    <h3><?php _e('Status do Pedido:', 'wc-nfse'); ?></h3>

                    <?php if ($query_status['found']): ?>
                        <div class="status-card status-found">
                            <div class="order-info">
                                <h4><?php echo sprintf(__('Pedido #%d', 'wc-nfse'), $query_status['order_id']); ?></h4>
                                <p><strong><?php _e('Cliente:', 'wc-nfse'); ?></strong> <?php echo esc_html($query_status['customer_name']); ?></p>
                                <p><strong><?php _e('Valor Total:', 'wc-nfse'); ?></strong> <?php echo wc_price($query_status['total']); ?></p>
                                <p><strong><?php _e('Status:', 'wc-nfse'); ?></strong> <?php echo wc_get_order_status_name($query_status['order_status']); ?></p>
                            </div>

                            <div class="nfse-info">
                                <h4><?php _e('Informações da NFS-e:', 'wc-nfse'); ?></h4>
                                <div class="nfse-details">
                                    <div class="detail-row">
                                        <span class="label"><?php _e('Número DPS:', 'wc-nfse'); ?></span>
                                        <span class="value"><?php echo $query_status['dps_number'] ?: '-'; ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label"><?php _e('Número NFS-e:', 'wc-nfse'); ?></span>
                                        <span class="value"><?php echo $query_status['nfse_number'] ?: '-'; ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label"><?php _e('Status NFS-e:', 'wc-nfse'); ?></span>
                                        <span class="value status-<?php echo $query_status['nfse_status'] ?: 'unknown'; ?>">
                                            <?php echo $query_status['nfse_status'] ? ucfirst($query_status['nfse_status']) : __('Não Emitido', 'wc-nfse'); ?>
                                        </span>
                                    </div>
                                    <?php if ($query_status['emitido_em']): ?>
                                        <div class="detail-row">
                                            <span class="label"><?php _e('Emitido em:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($query_status['emitido_em'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-warning">
                            <p><?php echo esc_html($query_status['error']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Recent orders list -->
            <div class="recent-orders">
                <h3><?php _e('Pedidos Recentes:', 'wc-nfse'); ?></h3>
                <div class="orders-list">
                    <?php foreach ($recent_orders as $order): ?>
                        <div class="order-item" data-order-id="<?php echo $order->get_id(); ?>">
                            <span class="order-number">#<?php echo $order->get_id(); ?></span>
                            <span class="customer-name"><?php echo esc_html($order->get_formatted_billing_full_name()); ?></span>
                            <span class="order-total"><?php echo wc_price($order->get_total()); ?></span>
                            <span class="order-date"><?php echo $order->get_date_created()->date_i18n(get_option('date_format')); ?></span>
                            <button type="button" class="button button-small select-order" data-order-id="<?php echo $order->get_id(); ?>">
                                <?php _e('Selecionar', 'wc-nfse'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- XML Generation Tab -->
    <div id="xml-generation" class="tab-content" style="display: none;">
        <h2><?php _e('Gerar XML DPS', 'wc-nfse'); ?></h2>

        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="xml_order_id"><?php _e('ID do Pedido', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="xml_order_id" name="order_id"
                            value="<?php echo isset($_POST['order_id']) ? intval($_POST['order_id']) : ''; ?>"
                            class="regular-text" required>
                        <input type="submit" name="generate_dps_xml" class="button button-primary"
                            value="<?php _e('Gerar XML DPS', 'wc-nfse'); ?>">
                        <p class="description">
                            <?php _e('Gera o XML DPS para validação antes da emissão da NFS-e.', 'wc-nfse'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </form>

        <?php if (isset($emission_result['xml_generated'])): ?>
            <div class="xml-results">
                <h3><?php _e('XML DPS Gerado:', 'wc-nfse'); ?></h3>

                <?php if ($emission_result['xml_generated'] && !empty($emission_result['xml_content'])): ?>
                    <div class="xml-display">
                        <textarea id="dps_xml" class="large-text code" rows="20" readonly><?php echo esc_textarea($emission_result['xml_content']); ?></textarea>
                        <div class="xml-actions">
                            <button type="button" class="button copy-xml"><?php _e('Copiar XML', 'wc-nfse'); ?></button>
                            <a href="data:text/xml;charset=utf-8,<?php echo rawurlencode($emission_result['xml_content']); ?>"
                                download="dps_<?php echo isset($_POST['order_id']) ? intval($_POST['order_id']) : 'order'; ?>.xml"
                                class="button button-secondary">
                                <?php _e('Download XML', 'wc-nfse'); ?>
                            </a>
                            <button type="button" class="button button-primary copy-compressed-xml"
                                data-xml="<?php echo esc_attr($emission_result['xml_content']); ?>">
                                <?php _e('Copiar XML Comprimido (Base64)', 'wc-nfse'); ?>
                            </button>
                        </div>

                        <!-- Compressed XML Display -->
                        <div class="compressed-xml-section" style="margin-top: 20px;">
                            <h4><?php _e('XML Comprimido para Testes (gzip + base64):', 'wc-nfse'); ?></h4>
                            <div class="compressed-xml-info">
                                <p class="description">
                                    <?php _e('Use esta string comprimida para testes no Postman ou outras ferramentas de API:', 'wc-nfse'); ?>
                                </p>
                            </div>
                            <textarea id="compressed_xml" class="large-text code" rows="8" readonly
                                placeholder="<?php _e('Clique em "Copiar XML Comprimido" para gerar...', 'wc-nfse'); ?>"></textarea>
                            <div class="compressed-xml-actions">
                                <button type="button" class="button copy-compressed-only" disabled>
                                    <?php _e('Copiar String Comprimida', 'wc-nfse'); ?>
                                </button>
                                <span class="compression-stats" style="margin-left: 15px; color: #666;"></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($emission_result['xml_validation']) || !empty($emission_result['comprehensive_validation'])): ?>
                        <div class="xml-validation-results">
                            <h4><?php _e('Validação XSD do XML:', 'wc-nfse'); ?></h4>

                            <?php
                            $validation = $emission_result['xml_validation'];
                            $comprehensive = $emission_result['comprehensive_validation'];
                            ?>

                            <!-- Basic Validation Status -->
                            <div class="validation-status status-<?php echo $validation['valid'] ? 'success' : 'error'; ?>">
                                <?php if ($validation['valid']): ?>
                                    ✅ <?php _e('XML válido contra schema XSD oficial!', 'wc-nfse'); ?>
                                <?php else: ?>
                                    ❌ <?php _e('XML inválido contra schema XSD oficial!', 'wc-nfse'); ?>
                                <?php endif; ?>
                            </div>

                            <!-- Comprehensive Validation Summary -->
                            <?php if (!empty($comprehensive['summary'])): ?>
                                <div class="validation-summary">
                                    <h5><?php _e('Resumo da Validação:', 'wc-nfse'); ?></h5>
                                    <div class="summary-grid">
                                        <div class="summary-item">
                                            <span class="label"><?php _e('Conformidade:', 'wc-nfse'); ?></span>
                                            <span class="value compliance-<?php echo $comprehensive['summary']['compliance_percentage'] >= 100 ? 'full' : 'partial'; ?>">
                                                <?php echo $comprehensive['summary']['compliance_percentage']; ?>%
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label"><?php _e('Schemas Testados:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo $comprehensive['summary']['schemas_tested']; ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label"><?php _e('Schemas Válidos:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo $comprehensive['summary']['schemas_valid']; ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label"><?php _e('Total de Erros:', 'wc-nfse'); ?></span>
                                            <span class="value error-count"><?php echo $comprehensive['summary']['total_errors']; ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label"><?php _e('Total de Avisos:', 'wc-nfse'); ?></span>
                                            <span class="value warning-count"><?php echo $comprehensive['summary']['total_warnings']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Schema Information -->
                            <?php if (!empty($validation['schema_info'])): ?>
                                <div class="schema-info">
                                    <h5><?php _e('Informações do Schema:', 'wc-nfse'); ?></h5>
                                    <div class="schema-details">
                                        <div class="detail-row">
                                            <span class="label"><?php _e('Schema:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo esc_html($validation['schema_info']['file']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label"><?php _e('Namespace:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo esc_html($validation['schema_info']['namespace']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label"><?php _e('Descrição:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo esc_html($validation['schema_info']['description']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Performance Metrics -->
                            <?php if (!empty($validation['performance'])): ?>
                                <div class="validation-performance">
                                    <h5><?php _e('Métricas de Performance:', 'wc-nfse'); ?></h5>
                                    <div class="performance-grid">
                                        <div class="performance-item">
                                            <span class="label"><?php _e('Tempo Total:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo $validation['performance']['total_time']; ?>ms</span>
                                        </div>
                                        <div class="performance-item">
                                            <span class="label"><?php _e('Validação XSD:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo $validation['performance']['schema_validation_time']; ?>ms</span>
                                        </div>
                                        <div class="performance-item">
                                            <span class="label"><?php _e('Tamanho XML:', 'wc-nfse'); ?></span>
                                            <span class="value"><?php echo number_format($validation['performance']['xml_size']); ?> bytes</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Validation Errors -->
                            <?php if (!empty($validation['errors'])): ?>
                                <div class="validation-errors">
                                    <h5><?php _e('Erros de Validação XSD:', 'wc-nfse'); ?></h5>
                                    <div class="errors-list">
                                        <?php foreach ($validation['errors'] as $error): ?>
                                            <div class="error-item">
                                                <span class="error-icon">❌</span>
                                                <span class="error-message"><?php echo esc_html($error); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Validation Warnings -->
                            <?php if (!empty($validation['warnings'])): ?>
                                <div class="validation-warnings">
                                    <h5><?php _e('Avisos de Validação:', 'wc-nfse'); ?></h5>
                                    <div class="warnings-list">
                                        <?php foreach ($validation['warnings'] as $warning): ?>
                                            <div class="warning-item">
                                                <span class="warning-icon">⚠️</span>
                                                <span class="warning-message"><?php echo esc_html($warning); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Recommendations -->
                            <?php if (!empty($comprehensive['recommendations'])): ?>
                                <div class="validation-recommendations">
                                    <h5><?php _e('Recomendações:', 'wc-nfse'); ?></h5>
                                    <div class="recommendations-list">
                                        <?php foreach ($comprehensive['recommendations'] as $recommendation): ?>
                                            <div class="recommendation-item">
                                                <span class="recommendation-icon">💡</span>
                                                <span class="recommendation-message"><?php echo esc_html($recommendation); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Structure Validation -->
                            <?php if (!empty($comprehensive['structure_validation'])): ?>
                                <div class="structure-validation">
                                    <h5><?php _e('Validação de Estrutura XML:', 'wc-nfse'); ?></h5>
                                    <?php $structure = $comprehensive['structure_validation']; ?>
                                    <div class="structure-status status-<?php echo $structure['valid'] ? 'success' : 'error'; ?>">
                                        <?php if ($structure['valid']): ?>
                                            ✅ <?php _e('Estrutura XML válida', 'wc-nfse'); ?>
                                        <?php else: ?>
                                            ❌ <?php _e('Estrutura XML inválida', 'wc-nfse'); ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($structure['structure_info'])): ?>
                                        <div class="structure-info">
                                            <div class="detail-row">
                                                <span class="label"><?php _e('Elemento Raiz:', 'wc-nfse'); ?></span>
                                                <span class="value"><?php echo esc_html($structure['structure_info']['root_element']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="label"><?php _e('Namespace:', 'wc-nfse'); ?></span>
                                                <span class="value"><?php echo esc_html($structure['structure_info']['namespace']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="label"><?php _e('Elementos:', 'wc-nfse'); ?></span>
                                                <span class="value"><?php echo $structure['structure_info']['element_count']; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="label"><?php _e('Assinatura Digital:', 'wc-nfse'); ?></span>
                                                <span class="value"><?php echo $structure['structure_info']['has_signature'] ? '✅ Presente' : '❌ Ausente'; ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Validation Actions -->
                            <div class="validation-actions">
                                <button type="button" class="button button-secondary toggle-validation-details">
                                    <?php _e('Mostrar/Ocultar Detalhes Técnicos', 'wc-nfse'); ?>
                                </button>
                                <?php if (!empty($comprehensive)): ?>
                                    <button type="button" class="button button-secondary download-validation-report"
                                        data-report="<?php echo esc_attr(json_encode($comprehensive)); ?>">
                                        <?php _e('Download Relatório de Validação', 'wc-nfse'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($emission_result['signature_info'])): ?>
                        <div class="xml-signature-info">
                            <?php $signature = $emission_result['signature_info']; ?>
                            <?php if ($signature['signed']): ?>
                                <p class="signature-success">✅ <?php _e('XML assinado digitalmente com sucesso!', 'wc-nfse'); ?></p>
                            <?php else: ?>
                                <p class="signature-error">❌ <?php printf(__('Falha na assinatura digital: %s', 'wc-nfse'), esc_html($signature['error'] ?? '')); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html($emission_result['error'] ?? __('Erro ao gerar XML DPS', 'wc-nfse')); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- XSD Validation Demonstration Tab -->
    <div id="xsd-validation" class="tab-content" style="display: none;">
        <h2><?php _e('Demonstração de Validação XSD', 'wc-nfse'); ?></h2>

        <div class="notice notice-info">
            <p><strong><?php _e('Validação XSD:', 'wc-nfse'); ?></strong></p>
            <ul>
                <li>📋 <?php _e('Cole um XML DPS para validar contra o schema oficial', 'wc-nfse'); ?></li>
                <li>🔍 <?php _e('Veja relatórios detalhados de conformidade XSD', 'wc-nfse'); ?></li>
                <li>⚡ <?php _e('Teste a validação em tempo real', 'wc-nfse'); ?></li>
                <li>📊 <?php _e('Obtenha métricas de performance da validação', 'wc-nfse'); ?></li>
            </ul>
        </div>

        <form method="post" id="xsd-validation-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="xml_content"><?php _e('XML DPS para Validação', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <textarea id="xml_content" name="xml_content" class="large-text code" rows="15"
                            placeholder="<?php _e('Cole aqui o XML DPS que deseja validar...', 'wc-nfse'); ?>"><?php echo isset($_POST['xml_content']) ? esc_textarea($_POST['xml_content']) : ''; ?></textarea>
                        <p class="description">
                            <?php _e('Cole o XML DPS completo que deseja validar contra o schema XSD oficial.', 'wc-nfse'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="validation_type"><?php _e('Tipo de Validação', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <select id="validation_type" name="validation_type">
                            <option value="comprehensive" <?php selected(isset($_POST['validation_type']) ? $_POST['validation_type'] : '', 'comprehensive'); ?>>
                                <?php _e('Validação Completa (Recomendado)', 'wc-nfse'); ?>
                            </option>
                            <option value="basic" <?php selected(isset($_POST['validation_type']) ? $_POST['validation_type'] : '', 'basic'); ?>>
                                <?php _e('Validação Básica XSD', 'wc-nfse'); ?>
                            </option>
                            <option value="structure_only" <?php selected(isset($_POST['validation_type']) ? $_POST['validation_type'] : '', 'structure_only'); ?>>
                                <?php _e('Apenas Estrutura XML', 'wc-nfse'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Escolha o tipo de validação a ser executada.', 'wc-nfse'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="validate_xml_xsd" class="button button-primary"
                    value="<?php _e('Validar XML', 'wc-nfse'); ?>">
                <button type="button" class="button button-secondary" id="clear-xml">
                    <?php _e('Limpar', 'wc-nfse'); ?>
                </button>
                <button type="button" class="button button-secondary" id="load-sample-xml">
                    <?php _e('Carregar XML de Exemplo', 'wc-nfse'); ?>
                </button>
                <span class="spinner"></span>
            </p>
        </form>

        <?php
        // Handle XSD validation demonstration
        if (isset($_POST['validate_xml_xsd']) && !empty($_POST['xml_content'])) {
            $xml_to_validate = stripslashes($_POST['xml_content']);
            $validation_type = sanitize_text_field($_POST['validation_type']);

            try {
                $xsd_validator = new \CloudXM\NFSe\Services\NfSeXsdValidator();
                $validation_demo_result = array();

                switch ($validation_type) {
                    case 'comprehensive':
                        $validation_demo_result = $xsd_validator->generateComprehensiveValidationReport($xml_to_validate, ['dps']);
                        break;
                    case 'basic':
                        $validation_demo_result = $xsd_validator->validateDpsXml($xml_to_validate);
                        break;
                    case 'structure_only':
                        $validation_demo_result = $xsd_validator->validateXmlStructure($xml_to_validate);
                        break;
                }

                echo '<div class="xsd-validation-demo-results">';
                echo '<h3>' . __('Resultado da Validação XSD:', 'wc-nfse') . '</h3>';

                if ($validation_type === 'comprehensive') {
                    // Display comprehensive validation results
                    $comprehensive = $validation_demo_result;
                    include 'xsd-validation-display.php'; // We'll create this partial
                } else {
                    // Display basic validation results
                    $validation = $validation_demo_result;
                    echo '<div class="validation-status status-' . ($validation['valid'] ? 'success' : 'error') . '">';
                    if ($validation['valid']) {
                        echo '✅ ' . __('XML válido!', 'wc-nfse');
                    } else {
                        echo '❌ ' . __('XML inválido!', 'wc-nfse');
                    }
                    echo '</div>';

                    if (!empty($validation['errors'])) {
                        echo '<div class="validation-errors">';
                        echo '<h5>' . __('Erros:', 'wc-nfse') . '</h5>';
                        echo '<div class="errors-list">';
                        foreach ($validation['errors'] as $error) {
                            echo '<div class="error-item">';
                            echo '<span class="error-icon">❌</span>';
                            echo '<span class="error-message">' . esc_html($error) . '</span>';
                            echo '</div>';
                        }
                        echo '</div></div>';
                    }

                    if (!empty($validation['warnings'])) {
                        echo '<div class="validation-warnings">';
                        echo '<h5>' . __('Avisos:', 'wc-nfse') . '</h5>';
                        echo '<div class="warnings-list">';
                        foreach ($validation['warnings'] as $warning) {
                            echo '<div class="warning-item">';
                            echo '<span class="warning-icon">⚠️</span>';
                            echo '<span class="warning-message">' . esc_html($warning) . '</span>';
                            echo '</div>';
                        }
                        echo '</div></div>';
                    }
                }

                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="notice notice-error">';
                echo '<p><strong>' . __('Erro na validação:', 'wc-nfse') . '</strong> ' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        ?>
    </div>

    <!-- Manual Emission Tab -->
    <div id="manual-emission" class="tab-content" style="display: none;">
        <h2><?php _e('Emissão Manual de NFS-e', 'wc-nfse'); ?></h2>

        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="emit_order_id"><?php _e('ID do Pedido', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="emit_order_id" name="order_id"
                            value="<?php echo isset($_POST['order_id']) ? intval($_POST['order_id']) : ''; ?>"
                            class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="emission_type"><?php _e('Tipo de Emissão', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <select id="emission_type" name="emission_type" required>
                            <option value=""><?php _e('Selecione o tipo', 'wc-nfse'); ?></option>
                            <option value="production" <?php selected(isset($_POST['emission_type']) ? $_POST['emission_type'] : '', 'production'); ?>>
                                <?php _e('Produção', 'wc-nfse'); ?>
                            </option>
                            <option value="homologation" <?php selected(isset($_POST['emission_type']) ? $_POST['emission_type'] : '', 'homologation'); ?>>
                                <?php _e('Homologação', 'wc-nfse'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="emit_nfse_manually" class="button button-primary"
                    value="<?php _e('Emitir NFS-e', 'wc-nfse'); ?>"
                    onclick="return confirm('<?php _e('Tem certeza que deseja emitir a NFS-e manualmente?', 'wc-nfse'); ?>');">
                <span class="spinner"></span>
            </p>
        </form>

        <?php if (!empty($emission_result) && !isset($emission_result['xml_generated'])): ?>
            <div class="emission-results">
                <h3><?php _e('Resultado da Emissão:', 'wc-nfse'); ?></h3>

                <?php if ($emission_result['success']): ?>
                    <div class="notice notice-success">
                        <p><strong>✅ <?php echo esc_html($emission_result['message']); ?></strong></p>
                        <?php if (!empty($emission_result['nfse_number'])): ?>
                            <p><strong><?php _e('Número da NFS-e:', 'wc-nfse'); ?></strong> <?php echo esc_html($emission_result['nfse_number']); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($emission_result['nfse_data'])): ?>
                            <div class="nfse-details">
                                <h4><?php _e('Detalhes da Emissão:', 'wc-nfse'); ?></h4>
                                <ul>
                                    <?php foreach ($emission_result['nfse_data'] as $key => $value): ?>
                                        <?php if (is_scalar($value) && !empty($value)): ?>
                                            <li><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> <?php echo esc_html($value); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="notice notice-error">
                        <p><strong>❌ <?php echo esc_html($emission_result['error'] ?? __('Erro na emissão', 'wc-nfse')); ?></strong></p>
                        <?php if (!empty($emission_result['details'])): ?>
                            <div class="error-details">
                                <h4><?php _e('Detalhes do Erro:', 'wc-nfse'); ?></h4>
                                <pre><?php print_r($emission_result['details']); ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bulk Actions Tab -->
    <div id="bulk-actions" class="tab-content" style="display: none;">
        <h2><?php _e('Ações em Lote', 'wc-nfse'); ?></h2>

        <div class="notice notice-info">
            <p><?php _e('Funcionalidade de ações em lote será implementada em breve.', 'wc-nfse'); ?></p>
        </div>
    </div>
</div>

<style>
    .tab-content {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-top: none;
        padding: 20px;
        margin-bottom: 20px;
    }

    .order-status-results {
        margin-top: 20px;
    }

    .status-card {
        background: #f9f9f9;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .order-info,
    .nfse-info {
        margin-bottom: 15px;
    }

    .order-info h4,
    .nfse-info h4 {
        margin-top: 0;
        color: #23282d;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .label {
        font-weight: bold;
        color: #666;
    }

    .value {
        text-align: right;
    }

    .value.status-pending {
        color: #f39c12;
    }

    .value.status-completed {
        color: #27ae60;
    }

    .value.status-error {
        color: #e74c3c;
    }

    .value.status-unknown {
        color: #95a5a6;
    }

    .recent-orders {
        margin-top: 30px;
    }

    .orders-list {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .order-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        background: #fff;
        transition: background-color 0.2s;
    }

    .order-item:hover {
        background: #f5f5f5;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    .order-number {
        font-weight: bold;
        min-width: 80px;
    }

    .customer-name {
        flex: 1;
    }

    .select-order {
        margin-left: auto;
    }

    .xml-display {
        margin-top: 20px;
    }

    .xml-actions {
        margin-top: 10px;
        display: flex;
        gap: 10px;
    }

    .xml-validation-results {
        margin-top: 20px;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        padding: 20px;
    }

    .validation-status {
        padding: 15px;
        border-radius: 6px;
        font-weight: bold;
        margin-bottom: 20px;
        font-size: 16px;
    }

    .validation-status.status-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .validation-status.status-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Validation Summary */
    .validation-summary {
        margin-bottom: 20px;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #007cba;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        background: #fff;
        border-radius: 4px;
        border: 1px solid #e5e5e5;
    }

    .summary-item .label {
        font-weight: 600;
        color: #555;
    }

    .summary-item .value {
        font-weight: bold;
    }

    .compliance-full {
        color: #28a745;
    }

    .compliance-partial {
        color: #ffc107;
    }

    .error-count {
        color: #dc3545;
    }

    .warning-count {
        color: #fd7e14;
    }

    /* Schema Information */
    .schema-info {
        margin-bottom: 20px;
        background: #e9ecef;
        padding: 15px;
        border-radius: 4px;
    }

    .schema-details .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #dee2e6;
    }

    .schema-details .detail-row:last-child {
        border-bottom: none;
    }

    .schema-details .label {
        font-weight: 600;
        color: #495057;
    }

    .schema-details .value {
        color: #6c757d;
        font-family: monospace;
        font-size: 12px;
    }

    /* Performance Metrics */
    .validation-performance {
        margin-bottom: 20px;
        background: #fff3cd;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #ffc107;
    }

    .performance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .performance-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 10px;
        background: #fff;
        border-radius: 4px;
        border: 1px solid #ffeaa7;
    }

    .performance-item .label {
        font-size: 12px;
        color: #856404;
    }

    .performance-item .value {
        font-weight: bold;
        color: #856404;
        font-family: monospace;
    }

    /* Validation Errors */
    .validation-errors {
        margin-bottom: 20px;
        background: #f8d7da;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #dc3545;
    }

    .errors-list {
        margin-top: 10px;
    }

    .error-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #f5c6cb;
    }

    .error-item:last-child {
        border-bottom: none;
    }

    .error-icon {
        flex-shrink: 0;
        font-size: 14px;
    }

    .error-message {
        color: #721c24;
        font-size: 13px;
        line-height: 1.4;
    }

    /* Validation Warnings */
    .validation-warnings {
        margin-bottom: 20px;
        background: #fff3cd;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #ffc107;
    }

    .warnings-list {
        margin-top: 10px;
    }

    .warning-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #ffeaa7;
    }

    .warning-item:last-child {
        border-bottom: none;
    }

    .warning-icon {
        flex-shrink: 0;
        font-size: 14px;
    }

    .warning-message {
        color: #856404;
        font-size: 13px;
        line-height: 1.4;
    }

    /* Recommendations */
    .validation-recommendations {
        margin-bottom: 20px;
        background: #d1ecf1;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #17a2b8;
    }

    .recommendations-list {
        margin-top: 10px;
    }

    .recommendation-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #bee5eb;
    }

    .recommendation-item:last-child {
        border-bottom: none;
    }

    .recommendation-icon {
        flex-shrink: 0;
        font-size: 14px;
    }

    .recommendation-message {
        color: #0c5460;
        font-size: 13px;
        line-height: 1.4;
        font-weight: 500;
    }

    /* Structure Validation */
    .structure-validation {
        margin-bottom: 20px;
        background: #e2e3e5;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #6c757d;
    }

    .structure-status {
        padding: 10px;
        border-radius: 4px;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .structure-info {
        margin-top: 10px;
        background: #fff;
        padding: 10px;
        border-radius: 4px;
    }

    /* Validation Actions */
    .validation-actions {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e5e5e5;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .validation-actions .button {
        font-size: 12px;
    }

    /* XSD Validation Demo */
    .xsd-validation-demo-results {
        margin-top: 30px;
        background: #fff;
        border: 2px solid #007cba;
        border-radius: 8px;
        padding: 25px;
    }

    .xsd-validation-demo-results h3 {
        margin-top: 0;
        color: #007cba;
        border-bottom: 2px solid #007cba;
        padding-bottom: 10px;
    }

    #xml_content {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        line-height: 1.4;
        background: #f8f9fa;
        border: 1px solid #ced4da;
    }

    #xml_content:focus {
        border-color: #007cba;
        box-shadow: 0 0 0 0.2rem rgba(0, 124, 186, 0.25);
    }

    .validation-demo-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    /* Responsive Design */
    @media (max-width: 768px) {

        .summary-grid,
        .performance-grid {
            grid-template-columns: 1fr;
        }

        .summary-item,
        .performance-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }

        .validation-actions,
        .validation-demo-actions {
            flex-direction: column;
        }

        .xsd-validation-demo-results {
            padding: 15px;
        }
    }

    .emission-results {
        margin-top: 20px;
    }

    .nfse-details {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
        margin-top: 10px;
    }

    .error-details {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
        margin-top: 10px;
        border-left: 4px solid #dc3545;
    }

    .error-details pre {
        background: #fff;
        padding: 10px;
        border-radius: 4px;
        overflow-x: auto;
        margin-top: 10px;
    }

    .spinner {
        float: none;
        margin-left: 10px;
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

        // Order selection from recent orders list
        $('.select-order').on('click', function() {
            var orderId = $(this).data('order-id');
            $('#check_order_id, #xml_order_id, #emit_order_id').val(orderId);
            $('#order-check').show();
            $('.nav-tab').removeClass('nav-tab-active');
            $('a[href="#order-check"]').addClass('nav-tab-active');
        });

        // Copy XML functionality
        $('.copy-xml').on('click', function() {
            var $textarea = $('#dps_xml');
            $textarea.select();
            document.execCommand('copy');

            var $button = $(this);
            var originalText = $button.text();
            $button.text('<?php _e('Copiado!', 'wc-nfse'); ?>');
            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        });

        // Copy compressed XML functionality
        $('.copy-compressed-xml').on('click', function() {
            var xmlContent = $(this).data('xml');
            var $button = $(this);
            var originalText = $button.text();

            $button.text('<?php _e('Comprimindo...', 'wc-nfse'); ?>').prop('disabled', true);

            // Compress XML using AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'compress_xml_for_testing',
                    xml_content: xmlContent,
                    nonce: '<?php echo wp_create_nonce('compress_xml_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var compressedXml = response.data.compressed_xml;
                        var stats = response.data.stats;

                        // Update compressed XML textarea
                        $('#compressed_xml').val(compressedXml);

                        // Update stats
                        $('.compression-stats').html(
                            '<?php _e('Original:', 'wc-nfse'); ?> ' + stats.original_size + ' bytes | ' +
                            '<?php _e('Comprimido:', 'wc-nfse'); ?> ' + stats.compressed_size + ' bytes | ' +
                            '<?php _e('Redução:', 'wc-nfse'); ?> ' + stats.compression_ratio + '%'
                        );

                        // Enable copy button
                        $('.copy-compressed-only').prop('disabled', false);

                        // Auto-copy to clipboard
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(compressedXml).then(function() {
                                $button.text('<?php _e('Copiado para Clipboard!', 'wc-nfse'); ?>');
                            });
                        } else {
                            // Fallback for older browsers
                            $('#compressed_xml').select();
                            document.execCommand('copy');
                            $button.text('<?php _e('Copiado!', 'wc-nfse'); ?>');
                        }

                        setTimeout(function() {
                            $button.text(originalText).prop('disabled', false);
                        }, 3000);

                    } else {
                        alert('<?php _e('Erro ao comprimir XML:', 'wc-nfse'); ?> ' + response.data);
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('<?php _e('Erro na requisição AJAX', 'wc-nfse'); ?>');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });

        // Copy compressed XML only
        $('.copy-compressed-only').on('click', function() {
            var $textarea = $('#compressed_xml');
            if ($textarea.val()) {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText($textarea.val()).then(function() {
                        var $button = $(this);
                        var originalText = $button.text();
                        $button.text('<?php _e('Copiado!', 'wc-nfse'); ?>');
                        setTimeout(function() {
                            $button.text(originalText);
                        }, 2000);
                    });
                } else {
                    $textarea.select();
                    document.execCommand('copy');
                    var $button = $(this);
                    var originalText = $button.text();
                    $button.text('<?php _e('Copiado!', 'wc-nfse'); ?>');
                    setTimeout(function() {
                        $button.text(originalText);
                    }, 2000);
                }
            }
        });

        // Toggle validation details
        $('.toggle-validation-details').on('click', function() {
            var $technicalSections = $('.validation-performance, .schema-info, .structure-validation');
            var isVisible = $technicalSections.first().is(':visible');

            if (isVisible) {
                $technicalSections.slideUp();
                $(this).text('<?php _e('Mostrar Detalhes Técnicos', 'wc-nfse'); ?>');
            } else {
                $technicalSections.slideDown();
                $(this).text('<?php _e('Ocultar Detalhes Técnicos', 'wc-nfse'); ?>');
            }
        });

        // Download validation report
        $('.download-validation-report').on('click', function() {
            var reportData = $(this).data('report');
            if (reportData) {
                var blob = new Blob([JSON.stringify(reportData, null, 2)], {
                    type: 'application/json'
                });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'validation-report-' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        });

        // Auto-hide technical details initially
        $('.validation-performance, .schema-info, .structure-validation').hide();

        // XSD Validation Demo functionality
        $('#clear-xml').on('click', function() {
            $('#xml_content').val('');
        });

        $('#load-sample-xml').on('click', function() {
            var sampleXml = '<?php echo "<?xml"; ?> version="1.0" encoding="UTF-8"?>\n' +
                '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.00">\n' +
                '    <infDPS Id="DPS3550308212345678000195000010000000000000001">\n' +
                '        <tpAmb>2</tpAmb>\n' +
                '        <dhEmi>2025-01-09T10:30:00Z</dhEmi>\n' +
                '        <tpEmi>1</tpEmi>\n' +
                '        <nDPS>000000000000001</nDPS>\n' +
                '        <cDPS>1</cDPS>\n' +
                '        <serie>00001</serie>\n' +
                '        <dCompet>2025-01-09</dCompet>\n' +
                '        <emit>\n' +
                '            <CNPJ>12345678000195</CNPJ>\n' +
                '            <IM>123456</IM>\n' +
                '            <xNome>Empresa Teste LTDA</xNome>\n' +
                '            <enderNac>\n' +
                '                <xLgr>Rua das Flores</xLgr>\n' +
                '                <nro>123</nro>\n' +
                '                <xBairro>Centro</xBairro>\n' +
                '                <cMun>3550308</cMun>\n' +
                '                <xMun>São Paulo</xMun>\n' +
                '                <CEP>01234567</CEP>\n' +
                '                <UF>SP</UF>\n' +
                '            </enderNac>\n' +
                '        </emit>\n' +
                '        <toma>\n' +
                '            <CPF>12345678901</CPF>\n' +
                '            <xNome>João Silva</xNome>\n' +
                '            <enderNac>\n' +
                '                <xLgr>Avenida Paulista</xLgr>\n' +
                '                <nro>1000</nro>\n' +
                '                <xBairro>Bela Vista</xBairro>\n' +
                '                <cMun>3550308</cMun>\n' +
                '                <xMun>São Paulo</xMun>\n' +
                '                <CEP>01310100</CEP>\n' +
                '                <UF>SP</UF>\n' +
                '            </enderNac>\n' +
                '        </toma>\n' +
                '        <serv>\n' +
                '            <cTribNac>010101</cTribNac>\n' +
                '            <xDescServ>Desenvolvimento de software</xDescServ>\n' +
                '            <cLocIncid>3550308</cLocIncid>\n' +
                '        </serv>\n' +
                '        <valores>\n' +
                '            <vServ>1000.00</vServ>\n' +
                '            <vLiq>950.00</vLiq>\n' +
                '            <grpISS>\n' +
                '                <vBC>1000.00</vBC>\n' +
                '                <pAliq>0.0500</pAliq>\n' +
                '                <vISS>50.00</vISS>\n' +
                '            </grpISS>\n' +
                '        </valores>\n' +
                '    </infDPS>\n' +
                '</DPS>';

            $('#xml_content').val(sampleXml);
        });

        // XSD validation form submission with loading state
        $('#xsd-validation-form').on('submit', function() {
            var $spinner = $(this).find('.spinner');
            $spinner.addClass('is-active');

            // Scroll to results area after a short delay
            setTimeout(function() {
                if ($('.xsd-validation-demo-results').length) {
                    $('html, body').animate({
                        scrollTop: $('.xsd-validation-demo-results').offset().top - 50
                    }, 500);
                }
            }, 100);
        });

        // Form submission with loading states
        $('form[method="post"]').on('submit', function() {
            var $spinner = $(this).find('.spinner');
            if ($spinner.length) {
                $spinner.addClass('is-active');
            }
        });
    });
</script>