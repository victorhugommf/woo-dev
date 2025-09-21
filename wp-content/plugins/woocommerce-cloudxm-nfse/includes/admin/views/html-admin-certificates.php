<?php
/**
 * Admin certificates page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$certificate_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateManager();
$certificates = $certificate_manager->getCertificates();
$validator = \CloudXM\NFSe\Bootstrap\Factories::nfSeCertificateValidator();
?>

<div class="wrap wc-nfse-certificates">
    <h1><?php _e('Gerenciamento de Certificados', 'wc-nfse'); ?></h1>

    <div class="wc-nfse-certificates-container">
        
        <!-- Upload Certificate Section -->
        <div class="wc-nfse-certificate-upload">
            <h2><?php _e('Adicionar Novo Certificado', 'wc-nfse'); ?></h2>
            
            <form id="wc-nfse-certificate-upload-form" enctype="multipart/form-data">
                <?php wp_nonce_field('wc_nfse_admin', 'nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="certificate_name"><?php _e('Nome do Certificado', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="certificate_name" name="certificate_name" class="regular-text" placeholder="<?php _e('Ex: Certificado Produção 2025', 'wc-nfse'); ?>">
                            <p class="description"><?php _e('Nome para identificar o certificado (opcional).', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="certificate_file"><?php _e('Arquivo do Certificado', 'wc-nfse'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="file" id="certificate_file" name="certificate_file" accept=".p12,.pfx" required>
                            <p class="description"><?php _e('Arquivo .p12 ou .pfx do certificado digital ICP-Brasil.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="certificate_password"><?php _e('Senha do Certificado', 'wc-nfse'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="password" id="certificate_password" name="certificate_password" class="regular-text" required>
                            <button type="button" class="button" id="toggle-password">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <p class="description"><?php _e('Senha para acessar o certificado digital.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button-primary" id="upload-certificate-btn">
                        <?php _e('Enviar Certificado', 'wc-nfse'); ?>
                    </button>
                    <span class="spinner"></span>
                </p>
            </form>
        </div>

        <!-- Certificates List -->
        <div class="wc-nfse-certificates-list">
            <h2><?php _e('Certificados Cadastrados', 'wc-nfse'); ?></h2>
            
            <?php if (empty($certificates)): ?>
                <div class="wc-nfse-no-certificates">
                    <div class="wc-nfse-empty-state">
                        <span class="dashicons dashicons-lock"></span>
                        <h3><?php _e('Nenhum certificado cadastrado', 'wc-nfse'); ?></h3>
                        <p><?php _e('Faça upload do seu certificado digital ICP-Brasil para começar a emitir NFS-e.', 'wc-nfse'); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="wc-nfse-certificates-grid">
                    <?php foreach ($certificates as $certificate):
                        $expiration_status = $validator->getExpirationStatus(strtotime($certificate->valid_to));
                        $is_active = $certificate->is_active;
                    ?>
                    <div class="wc-nfse-certificate-card <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="certificate-header">
                            <div class="certificate-info">
                                <h3><?php echo esc_html($certificate->name); ?></h3>
                                <p class="certificate-subject"><?php echo esc_html($certificate->subject_name); ?></p>
                            </div>
                            <div class="certificate-status">
                                <?php if ($is_active): ?>
                                    <span class="status-badge active"><?php _e('Ativo', 'wc-nfse'); ?></span>
                                <?php endif; ?>
                                <span class="status-badge expiration <?php echo $expiration_status['class']; ?>">
                                    <?php echo esc_html($expiration_status['message']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="certificate-details">
                            <div class="detail-row">
                                <strong><?php _e('Emissor:', 'wc-nfse'); ?></strong>
                                <span><?php echo esc_html($certificate->issuer_name); ?></span>
                            </div>
                            <div class="detail-row">
                                <strong><?php _e('Válido de:', 'wc-nfse'); ?></strong>
                                <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($certificate->valid_from))); ?></span>
                            </div>
                            <div class="detail-row">
                                <strong><?php _e('Válido até:', 'wc-nfse'); ?></strong>
                                <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($certificate->valid_to))); ?></span>
                            </div>
                            <div class="detail-row">
                                <strong><?php _e('Adicionado em:', 'wc-nfse'); ?></strong>
                                <span><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($certificate->created_at))); ?></span>
                            </div>
                        </div>

                        <div class="certificate-actions">
                            <?php if (!$is_active): ?>
                                <button type="button" class="button button-primary activate-certificate" data-certificate-id="<?php echo $certificate->id; ?>">
                                    <?php _e('Ativar', 'wc-nfse'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="button test-certificate" data-certificate-id="<?php echo $certificate->id; ?>">
                                <?php _e('Testar', 'wc-nfse'); ?>
                            </button>
                            
                            <button type="button" class="button validate-certificate" data-certificate-id="<?php echo $certificate->id; ?>">
                                <?php _e('Validar', 'wc-nfse'); ?>
                            </button>
                            
                            <button type="button" class="button button-link-delete delete-certificate" data-certificate-id="<?php echo $certificate->id; ?>">
                                <?php _e('Excluir', 'wc-nfse'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Certificate Validation Modal -->
<div id="certificate-validation-modal" class="wc-nfse-modal" style="display: none;">
    <div class="wc-nfse-modal-content">
        <div class="wc-nfse-modal-header">
            <h2><?php _e('Validação do Certificado', 'wc-nfse'); ?></h2>
            <button type="button" class="wc-nfse-modal-close">&times;</button>
        </div>
        <div class="wc-nfse-modal-body">
            <div id="validation-results"></div>
        </div>
    </div>
</div>

<!-- Certificate Test Modal -->
<div id="certificate-test-modal" class="wc-nfse-modal" style="display: none;">
    <div class="wc-nfse-modal-content">
        <div class="wc-nfse-modal-header">
            <h2><?php _e('Teste do Certificado', 'wc-nfse'); ?></h2>
            <button type="button" class="wc-nfse-modal-close">&times;</button>
        </div>
        <div class="wc-nfse-modal-body">
            <div id="test-results"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle password visibility
    $('#toggle-password').on('click', function() {
        var passwordField = $('#certificate_password');
        var icon = $(this).find('.dashicons');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            passwordField.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // Certificate upload form
    $('#wc-nfse-certificate-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'wc_nfse_upload_certificate');
        
        var $button = $('#upload-certificate-btn');
        var $spinner = $(this).find('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
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
                showNotice('error', '<?php _e("Erro ao enviar certificado. Tente novamente.", "wc-nfse"); ?>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Activate certificate
    $('.activate-certificate').on('click', function() {
        var certificateId = $(this).data('certificate-id');
        
        if (!confirm('<?php _e("Tem certeza que deseja ativar este certificado?", "wc-nfse"); ?>')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_activate_certificate',
                certificate_id: certificateId,
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data.message);
                }
            }
        });
    });

    // Delete certificate
    $('.delete-certificate').on('click', function() {
        var certificateId = $(this).data('certificate-id');
        
        if (!confirm('<?php _e("Tem certeza que deseja excluir este certificado? Esta ação não pode ser desfeita.", "wc-nfse"); ?>')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_delete_certificate',
                certificate_id: certificateId,
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data.message);
                }
            }
        });
    });

    // Validate certificate
    $('.validate-certificate').on('click', function() {
        var certificateId = $(this).data('certificate-id');
        
        $('#validation-results').html('<div class="wc-nfse-loading"><?php _e("Validando certificado...", "wc-nfse"); ?></div>');
        $('#certificate-validation-modal').show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_validate_certificate',
                certificate_id: certificateId,
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#validation-results').html(response.data.html);
                } else {
                    $('#validation-results').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#validation-results').html('<div class="notice notice-error"><p><?php _e("Erro na validação. Tente novamente.", "wc-nfse"); ?></p></div>');
            }
        });
    });

    // Test certificate
    $('.test-certificate').on('click', function() {
        var certificateId = $(this).data('certificate-id');
        
        $('#test-results').html('<div class="wc-nfse-loading"><?php _e("Testando certificado com API...", "wc-nfse"); ?></div>');
        $('#certificate-test-modal').show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_test_certificate',
                certificate_id: certificateId,
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#test-results').html(response.data.html);
                } else {
                    $('#test-results').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#test-results').html('<div class="notice notice-error"><p><?php _e("Erro no teste. Tente novamente.", "wc-nfse"); ?></p></div>');
            }
        });
    });

    // Modal close
    $('.wc-nfse-modal-close, .wc-nfse-modal').on('click', function(e) {
        if (e.target === this) {
            $('.wc-nfse-modal').hide();
        }
    });

    // Show notice function
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
.wc-nfse-certificates {
    margin: 20px 0;
}

.wc-nfse-certificates-container {
    display: grid;
    gap: 30px;
}

.wc-nfse-certificate-upload {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wc-nfse-certificate-upload h2 {
    margin: 0 0 20px 0;
    font-size: 18px;
}

.required {
    color: #dc3232;
}

#toggle-password {
    margin-left: 5px;
    padding: 0 8px;
    height: 30px;
    line-height: 28px;
}

.wc-nfse-certificates-list h2 {
    margin: 0 0 20px 0;
    font-size: 18px;
}

.wc-nfse-no-certificates {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 40px;
    text-align: center;
}

.wc-nfse-empty-state .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #ccc;
    margin-bottom: 20px;
}

.wc-nfse-empty-state h3 {
    margin: 0 0 10px 0;
    color: #666;
}

.wc-nfse-empty-state p {
    color: #999;
    margin: 0;
}

.wc-nfse-certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.wc-nfse-certificate-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    transition: all 0.2s;
}

.wc-nfse-certificate-card:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
}

.wc-nfse-certificate-card.active {
    border-color: #46b450;
    box-shadow: 0 0 0 1px #46b450;
}

.certificate-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.certificate-info h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
}

.certificate-subject {
    margin: 0;
    color: #666;
    font-size: 13px;
}

.certificate-status {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-badge.active {
    background: #46b450;
    color: white;
}

.status-badge.expiration.success {
    background: #46b450;
    color: white;
}

.status-badge.expiration.warning {
    background: #ffb900;
    color: white;
}

.status-badge.expiration.error {
    background: #dc3232;
    color: white;
}

.certificate-details {
    margin-bottom: 20px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.detail-row strong {
    color: #333;
}

.detail-row span {
    color: #666;
}

.certificate-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.certificate-actions .button {
    font-size: 12px;
    height: auto;
    padding: 6px 12px;
}

/* Modal Styles */
.wc-nfse-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wc-nfse-modal-content {
    background: #fff;
    border-radius: 4px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.wc-nfse-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.wc-nfse-modal-header h2 {
    margin: 0;
    font-size: 18px;
}

.wc-nfse-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wc-nfse-modal-body {
    padding: 20px;
}

.wc-nfse-loading {
    text-align: center;
    padding: 40px;
    color: #666;
}

.wc-nfse-loading:before {
    content: '';
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #ccc;
    border-top-color: #0073aa;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 10px;
    vertical-align: middle;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .wc-nfse-certificates-grid {
        grid-template-columns: 1fr;
    }
    
    .certificate-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .certificate-status {
        align-items: flex-start;
    }
}
</style>

