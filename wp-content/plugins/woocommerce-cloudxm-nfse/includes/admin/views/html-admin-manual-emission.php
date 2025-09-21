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

        $emission_result = array(
            'xml_generated' => true,
            'xml_content' => $signed_xml ?: $xml_content,
            'xml_validation' => $xml_result['validation'] ?? array(),
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
                            <a href="data:text/xml;charset=utf-8,<?php echo urlencode($emission_result['xml_content']); ?>"
                                download="dps_<?php echo isset($_POST['order_id']) ? intval($_POST['order_id']) : 'order'; ?>.xml"
                                class="button button-secondary">
                                <?php _e('Download XML', 'wc-nfse'); ?>
                            </a>
                        </div>
                    </div>

                    <?php if (!empty($emission_result['xml_validation'])): ?>
                        <div class="xml-validation-results">
                            <h4><?php _e('Validação do XML:', 'wc-nfse'); ?></h4>
                            <?php $validation = $emission_result['xml_validation']; ?>
                            <div class="validation-status status-<?php echo $validation['valid'] ? 'success' : 'error'; ?>">
                                <?php if ($validation['valid']): ?>
                                    ✅ <?php _e('XML válido!', 'wc-nfse'); ?>
                                <?php else: ?>
                                    ❌ <?php _e('XML inválido!', 'wc-nfse'); ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($validation['errors'])): ?>
                                <div class="validation-errors">
                                    <h5><?php _e('Erros:', 'wc-nfse'); ?></h5>
                                    <ul>
                                        <?php foreach ($validation['errors'] as $error): ?>
                                            <li><?php echo esc_html($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
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
    }

    .validation-status {
        padding: 10px;
        border-radius: 4px;
        font-weight: bold;
        margin-bottom: 10px;
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

    .validation-errors ul {
        background: #f8f9fa;
        padding: 15px;
        border-left: 4px solid #dc3545;
        margin-top: 10px;
    }

    .validation-errors li {
        margin-bottom: 5px;
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

        // Form submission with loading states
        $('form[method="post"]').on('submit', function() {
            var $spinner = $(this).find('.spinner');
            if ($spinner.length) {
                $spinner.addClass('is-active');
            }
        });
    });
</script>