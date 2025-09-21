<?php
/**
 * Admin settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wc-nfse-settings">
    <h1><?php _e('Configurações NFS-e', 'wc-nfse'); ?></h1>

    <form id="wc-nfse-settings-form" method="post">
        <?php wp_nonce_field('wc_nfse_admin', 'nonce'); ?>

        <!-- General Settings -->
        <div class="wc-nfse-settings-section">
            <h2><?php _e('Configurações Gerais', 'wc-nfse'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enabled"><?php _e('Habilitar NFS-e', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="enabled" name="enabled" value="yes" <?php checked($settings['enabled'] ?? 'no', 'yes'); ?>>
                        <p class="description"><?php _e('Habilita a emissão de NFS-e para pedidos do WooCommerce.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="environment"><?php _e('Ambiente', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <select id="environment" name="environment">
                            <option value="homologation" <?php selected($settings['environment'] ?? 'homologation', 'homologation'); ?>><?php _e('Homologação', 'wc-nfse'); ?></option>
                            <option value="production" <?php selected($settings['environment'] ?? 'homologation', 'production'); ?>><?php _e('Produção', 'wc-nfse'); ?></option>
                        </select>
                        <p class="description"><?php _e('Selecione o ambiente para emissão das NFS-e. Use homologação para testes.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="auto_emit"><?php _e('Emissão Automática', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="auto_emit" name="auto_emit" value="yes" <?php checked($settings['auto_emit'] ?? 'no', 'yes'); ?>>
                        <p class="description"><?php _e('Emite NFS-e automaticamente após confirmação do pagamento.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="debug_mode"><?php _e('Modo Debug', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="debug_mode" name="debug_mode" value="yes" <?php checked($settings['debug_mode'] ?? 'yes', 'yes'); ?>>
                        <p class="description"><?php _e('Habilita logs detalhados para debug e desenvolvimento.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Prestador Data Section -->
        <div class="wc-nfse-settings-section">
            <h2><?php _e('Dados do Prestador', 'wc-nfse'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="prestador_cnpj"><?php _e('CNPJ', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_cnpj" name="prestador_cnpj" value="<?php echo esc_attr($settings['prestador_cnpj'] ?? ''); ?>" class="regular-text" maxlength="18" required>
                        <p class="description"><?php _e('CNPJ do prestador de serviços (apenas números ou com formatação).', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_inscricao_municipal"><?php _e('Inscrição Municipal', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_inscricao_municipal" name="prestador_inscricao_municipal" value="<?php echo esc_attr($settings['prestador_inscricao_municipal'] ?? ''); ?>" class="regular-text" required>
                        <p class="description"><?php _e('Inscrição municipal do prestador no município de prestação do serviço.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_razao_social"><?php _e('Razão Social', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_razao_social" name="prestador_razao_social" value="<?php echo esc_attr($settings['prestador_razao_social'] ?? ''); ?>" class="regular-text" maxlength="255" required>
                        <p class="description"><?php _e('Razão social da empresa conforme registro na Receita Federal.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_nome_fantasia"><?php _e('Nome Fantasia', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_nome_fantasia" name="prestador_nome_fantasia" value="<?php echo esc_attr($settings['prestador_nome_fantasia'] ?? ''); ?>" class="regular-text" maxlength="255">
                        <p class="description"><?php _e('Nome fantasia da empresa (opcional).', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_telefone"><?php _e('Telefone', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_telefone" name="prestador_telefone" value="<?php echo esc_attr($settings['prestador_telefone'] ?? ''); ?>" class="regular-text" maxlength="15">
                        <p class="description"><?php _e('Telefone de contato (apenas números).', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_email"><?php _e('Email', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="prestador_email" name="prestador_email" value="<?php echo esc_attr($settings['prestador_email'] ?? ''); ?>" class="regular-text" maxlength="255">
                        <p class="description"><?php _e('Email de contato da empresa.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Address Section -->
        <div class="wc-nfse-settings-section">
            <h2><?php _e('Endereço do Prestador', 'wc-nfse'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="prestador_endereco"><?php _e('Logradouro', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_endereco" name="prestador_endereco" value="<?php echo esc_attr($settings['prestador_endereco'] ?? ''); ?>" class="regular-text" maxlength="255" required>
                        <p class="description"><?php _e('Rua, avenida, etc.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_numero"><?php _e('Número', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_numero" name="prestador_numero" value="<?php echo esc_attr($settings['prestador_numero'] ?? ''); ?>" class="regular-text" maxlength="10" required>
                        <p class="description"><?php _e('Número do endereço.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_complemento"><?php _e('Complemento', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_complemento" name="prestador_complemento" value="<?php echo esc_attr($settings['prestador_complemento'] ?? ''); ?>" class="regular-text" maxlength="100">
                        <p class="description"><?php _e('Apartamento, sala, etc. (opcional).', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_bairro"><?php _e('Bairro', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_bairro" name="prestador_bairro" value="<?php echo esc_attr($settings['prestador_bairro'] ?? ''); ?>" class="regular-text" maxlength="100" required>
                        <p class="description"><?php _e('Bairro do endereço.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_cidade"><?php _e('Cidade', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_cidade" name="prestador_cidade" value="<?php echo esc_attr($settings['prestador_cidade'] ?? ''); ?>" class="regular-text" maxlength="100" required>
                        <p class="description"><?php _e('Nome da cidade.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_uf"><?php _e('Estado (UF)', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select id="prestador_uf" name="prestador_uf" required>
                            <option value=""><?php _e('Selecione o estado', 'wc-nfse'); ?></option>
                            <?php foreach ($states as $uf => $name): ?>
                            <option value="<?php echo esc_attr($uf); ?>" <?php selected($settings['prestador_uf'] ?? '', $uf); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Estado onde está localizada a empresa.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="prestador_cep"><?php _e('CEP', 'wc-nfse'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="prestador_cep" name="prestador_cep" value="<?php echo esc_attr($settings['prestador_cep'] ?? ''); ?>" class="regular-text" maxlength="9" required>
                        <p class="description"><?php _e('CEP do endereço (apenas números ou com formatação).', 'wc-nfse'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tax Settings Section -->
        <div class="wc-nfse-settings-section">
            <h2><?php _e('Configurações Tributárias', 'wc-nfse'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="regime_tributario"><?php _e('Regime Tributário', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <select id="regime_tributario" name="regime_tributario">
                            <?php foreach ($tax_regimes as $regime => $name): ?>
                            <option value="<?php echo esc_attr($regime); ?>" <?php selected($settings['regime_tributario'] ?? 'simples_nacional', $regime); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Regime tributário da empresa para cálculo de impostos.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_nbs_code"><?php _e('Código NBS Padrão', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="default_nbs_code" name="default_nbs_code" value="<?php echo esc_attr($settings['default_nbs_code'] ?? '01.01'); ?>" class="regular-text" maxlength="5" pattern="\d{2}\.\d{2}">
                        <p class="description"><?php _e('Código NBS padrão para serviços (formato: XX.XX). Ex: 01.01 para desenvolvimento de software.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dps_serie"><?php _e('Série da DPS', 'wc-nfse'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="dps_serie" name="dps_serie" value="<?php echo esc_attr($settings['dps_serie'] ?? ''); ?>" class="regular-text" maxlength="5">
                        <p class="description"><?php _e('Série da DPS (opcional). Deixe em branco se não usar série.', 'wc-nfse'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button-primary" id="wc-nfse-save-settings">
                <?php _e('Salvar Configurações', 'wc-nfse'); ?>
            </button>
            <span class="spinner"></span>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {

    // CNPJ formatting
    $('#prestador_cnpj').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length <= 14) {
            value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            $(this).val(value);
        }
    });

    // CEP formatting
    $('#prestador_cep').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length <= 8) {
            value = value.replace(/^(\d{5})(\d{3})$/, '$1-$2');
            $(this).val(value);
        }
    });

    // Phone formatting
    $('#prestador_telefone').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        if (value.length <= 11) {
            if (value.length === 11) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (value.length === 10) {
                value = value.replace(/^(\d{2})(\d{4})(\d{4})$/, '($1) $2-$3');
            }
            $(this).val(value);
        }
    });

    // Form submission
    $('#wc-nfse-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $('#wc-nfse-save-settings');
        var $spinner = $form.find('.spinner');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');

        var formData = $form.serialize() + '&action=wc_nfse_save_settings';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                        .insertAfter('.wrap h1')
                        .delay(3000)
                        .fadeOut();
                } else {
                    // Show error message
                    $('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>')
                        .insertAfter('.wrap h1');
                }
            },
            error: function() {
                $('<div class="notice notice-error is-dismissible"><p><?php _e("Erro ao salvar configurações. Tente novamente.", "wc-nfse"); ?></p></div>')
                    .insertAfter('.wrap h1');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>

<style>
.wc-nfse-settings .form-table th {
    width: 200px;
}

.required {
    color: #dc3232;
}

.wc-nfse-settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
    margin-bottom: 20px;
}

.spinner {
    float: none;
    margin-left: 10px;
}
</style>

