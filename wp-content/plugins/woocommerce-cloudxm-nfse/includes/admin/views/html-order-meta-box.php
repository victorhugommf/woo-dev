<?php
/**
 * Order meta box template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wc-nfse-order-meta-box">
    <?php if ($emission && $emission->status === 'success'): ?>
        <!-- NFS-e Successfully Issued -->
        <div class="wc-nfse-emission-success">
            <div class="wc-nfse-status-header">
                <span class="dashicons dashicons-yes-alt"></span>
                <strong><?php _e('NFS-e Emitida', 'wc-nfse'); ?></strong>
            </div>
            
            <div class="wc-nfse-emission-details">
                <div class="detail-row">
                    <strong><?php _e('Chave de Acesso:', 'wc-nfse'); ?></strong>
                    <span class="access-key"><?php echo esc_html($emission->access_key); ?></span>
                    <button type="button" class="copy-access-key" data-key="<?php echo esc_attr($emission->access_key); ?>" title="<?php _e('Copiar chave', 'wc-nfse'); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                </div>
                
                <div class="detail-row">
                    <strong><?php _e('Número DPS:', 'wc-nfse'); ?></strong>
                    <span><?php echo esc_html($emission->dps_number); ?></span>
                </div>
                
                <div class="detail-row">
                    <strong><?php _e('Data de Emissão:', 'wc-nfse'); ?></strong>
                    <span><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($emission->emission_date))); ?></span>
                </div>
            </div>
            
            <div class="wc-nfse-actions">
                <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=wc_nfse_download_xml&order_id=' . $order->get_id()), 'wc_nfse_admin', 'nonce'); ?>" 
                   class="button button-secondary" target="_blank">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Download XML', 'wc-nfse'); ?>
                </a>
                
                <button type="button" class="button button-secondary reemit-nfse" data-order-id="<?php echo $order->get_id(); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Reemitir', 'wc-nfse'); ?>
                </button>
            </div>
        </div>
        
    <?php elseif ($emission && $emission->status === 'error'): ?>
        <!-- NFS-e Error -->
        <div class="wc-nfse-emission-error">
            <div class="wc-nfse-status-header">
                <span class="dashicons dashicons-warning"></span>
                <strong><?php _e('Erro na Emissão', 'wc-nfse'); ?></strong>
            </div>
            
            <div class="wc-nfse-error-message">
                <p><?php echo esc_html($emission->error_message); ?></p>
            </div>
            
            <div class="wc-nfse-actions">
                <button type="button" class="button button-primary emit-nfse" data-order-id="<?php echo $order->get_id(); ?>">
                    <span class="dashicons dashicons-media-document"></span>
                    <?php _e('Tentar Novamente', 'wc-nfse'); ?>
                </button>
            </div>
        </div>
        
    <?php elseif ($emission && $emission->status === 'pending'): ?>
        <!-- NFS-e Pending -->
        <div class="wc-nfse-emission-pending">
            <div class="wc-nfse-status-header">
                <span class="dashicons dashicons-clock"></span>
                <strong><?php _e('Emissão Pendente', 'wc-nfse'); ?></strong>
            </div>
            
            <div class="wc-nfse-pending-message">
                <p><?php _e('A emissão da NFS-e está sendo processada.', 'wc-nfse'); ?></p>
            </div>
            
            <div class="wc-nfse-actions">
                <button type="button" class="button button-secondary refresh-status" data-order-id="<?php echo $order->get_id(); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Atualizar Status', 'wc-nfse'); ?>
                </button>
            </div>
        </div>
        
    <?php else: ?>
        <!-- No NFS-e -->
        <div class="wc-nfse-no-emission">
            <?php if (!$settings->is_configured()): ?>
                <div class="wc-nfse-not-configured">
                    <div class="wc-nfse-status-header">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <strong><?php _e('Plugin Não Configurado', 'wc-nfse'); ?></strong>
                    </div>
                    
                    <p><?php _e('Configure os dados do prestador e certificado digital para emitir NFS-e.', 'wc-nfse'); ?></p>
                    
                    <div class="wc-nfse-actions">
                        <a href="<?php echo admin_url('admin.php?page=wc-nfse-settings'); ?>" class="button button-primary">
                            <?php _e('Configurar Plugin', 'wc-nfse'); ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="wc-nfse-ready-to-emit">
                    <div class="wc-nfse-status-header">
                        <span class="dashicons dashicons-media-document"></span>
                        <strong><?php _e('NFS-e Não Emitida', 'wc-nfse'); ?></strong>
                    </div>
                    
                    <p><?php _e('Este pedido ainda não possui NFS-e emitida.', 'wc-nfse'); ?></p>
                    
                    <div class="wc-nfse-actions">
                        <button type="button" class="button button-primary emit-nfse" data-order-id="<?php echo $order->get_id(); ?>">
                            <span class="dashicons dashicons-media-document"></span>
                            <?php _e('Emitir NFS-e', 'wc-nfse'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Order Info for NFS-e -->
    <div class="wc-nfse-order-info">
        <h4><?php _e('Informações do Pedido', 'wc-nfse'); ?></h4>
        
        <div class="order-info-grid">
            <div class="info-item">
                <strong><?php _e('Total:', 'wc-nfse'); ?></strong>
                <span><?php echo wc_price($order->get_total()); ?></span>
            </div>
            
            <div class="info-item">
                <strong><?php _e('Status:', 'wc-nfse'); ?></strong>
                <span><?php echo wc_get_order_status_name($order->get_status()); ?></span>
            </div>
            
            <div class="info-item">
                <strong><?php _e('Data:', 'wc-nfse'); ?></strong>
                <span><?php echo $order->get_date_created()->date_i18n(get_option('date_format')); ?></span>
            </div>
            
            <div class="info-item">
                <strong><?php _e('Cliente:', 'wc-nfse'); ?></strong>
                <span><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></span>
            </div>
            
            <?php if ($order->get_billing_company()): ?>
            <div class="info-item">
                <strong><?php _e('Empresa:', 'wc-nfse'); ?></strong>
                <span><?php echo esc_html($order->get_billing_company()); ?></span>
            </div>
            <?php endif; ?>
            
            <?php 
            $customer_document = $order->get_meta('_billing_cpf') ?: $order->get_meta('_billing_cnpj');
            if ($customer_document): 
            ?>
            <div class="info-item">
                <strong><?php _e('Documento:', 'wc-nfse'); ?></strong>
                <span><?php echo esc_html($customer_document); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Emit NFS-e
    $('.emit-nfse, .reemit-nfse').on('click', function() {
        var $button = $(this);
        var orderId = $button.data('order-id');
        var isReemit = $button.hasClass('reemit-nfse');
        
        var confirmMessage = isReemit ? 
            '<?php _e("Tem certeza que deseja reemitir a NFS-e? Isso criará uma nova emissão.", "wc-nfse"); ?>' :
            '<?php _e("Tem certeza que deseja emitir a NFS-e para este pedido?", "wc-nfse"); ?>';
            
        if (!confirm(confirmMessage)) {
            return;
        }
        
        $button.prop('disabled', true);
        $button.find('.dashicons').addClass('spin');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_emit_manual',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    if (response.data.reload) {
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', '<?php _e("Erro ao processar solicitação. Tente novamente.", "wc-nfse"); ?>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('spin');
            }
        });
    });
    
    // Copy access key
    $('.copy-access-key').on('click', function() {
        var key = $(this).data('key');
        
        // Create temporary input to copy text
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(key).select();
        document.execCommand('copy');
        tempInput.remove();
        
        // Show feedback
        var $button = $(this);
        var originalIcon = $button.find('.dashicons').attr('class');
        
        $button.find('.dashicons').removeClass().addClass('dashicons dashicons-yes');
        
        setTimeout(function() {
            $button.find('.dashicons').removeClass().addClass(originalIcon);
        }, 2000);
        
        showNotice('success', '<?php _e("Chave de acesso copiada!", "wc-nfse"); ?>');
    });
    
    // Refresh status
    $('.refresh-status').on('click', function() {
        location.reload();
    });
    
    // Show notice function
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('#wpbody-content .wrap h1').first().after(notice);
        
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
});
</script>

<style>
.wc-nfse-order-meta-box {
    font-size: 13px;
}

.wc-nfse-status-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.wc-nfse-emission-success .wc-nfse-status-header .dashicons {
    color: #46b450;
}

.wc-nfse-emission-error .wc-nfse-status-header .dashicons {
    color: #dc3232;
}

.wc-nfse-emission-pending .wc-nfse-status-header .dashicons {
    color: #ffb900;
}

.wc-nfse-emission-details {
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.detail-row strong {
    min-width: 100px;
    color: #333;
}

.access-key {
    font-family: monospace;
    font-size: 11px;
    background: #f1f1f1;
    padding: 2px 6px;
    border-radius: 3px;
    flex: 1;
}

.copy-access-key {
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px;
    color: #666;
}

.copy-access-key:hover {
    color: #0073aa;
}

.wc-nfse-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.wc-nfse-actions .button {
    font-size: 12px;
    height: auto;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.wc-nfse-error-message {
    background: #fdf0f0;
    border: 1px solid #dc3232;
    border-radius: 3px;
    padding: 10px;
    margin-bottom: 15px;
}

.wc-nfse-error-message p {
    margin: 0;
    color: #dc3232;
    font-size: 12px;
}

.wc-nfse-pending-message {
    margin-bottom: 15px;
}

.wc-nfse-pending-message p {
    margin: 0;
    color: #666;
}

.wc-nfse-not-configured {
    background: #fffbf0;
    border: 1px solid #ffb900;
    border-radius: 3px;
    padding: 15px;
}

.wc-nfse-order-info {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.wc-nfse-order-info h4 {
    margin: 0 0 10px 0;
    font-size: 13px;
    font-weight: 600;
}

.order-info-grid {
    display: grid;
    gap: 8px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.info-item strong {
    color: #333;
}

.info-item span {
    color: #666;
}

/* Spinning animation for loading */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}
</style>

