<?php

/**
 * Admin main page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wc-nfse-admin">
    <h1><?php _e('NFS-e - Painel Principal', 'wc-nfse'); ?></h1>

    <div class="wc-nfse-dashboard">
        <div class="wc-nfse-dashboard-widgets">

            <!-- Configuration Status Widget -->
            <div class="wc-nfse-widget">
                <h2><?php _e('Status da Configuração', 'wc-nfse'); ?></h2>
                <div class="wc-nfse-status-grid">
                    <div class="wc-nfse-status-item <?php echo $status['prestador_data'] ? 'complete' : 'incomplete'; ?>">
                        <span class="dashicons <?php echo $status['prestador_data'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                        <div>
                            <strong><?php _e('Dados do Prestador', 'wc-nfse'); ?></strong>
                            <p><?php echo $status['prestador_data'] ? __('Configurado', 'wc-nfse') : __('Pendente', 'wc-nfse'); ?></p>
                        </div>
                        <?php if (!$status['prestador_data']): ?>
                            <a href="<?php echo admin_url('admin.php?page=wc-nfse-settings'); ?>" class="button button-primary">
                                <?php _e('Configurar', 'wc-nfse'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="wc-nfse-status-item <?php echo $status['certificate'] ? 'complete' : 'incomplete'; ?>">
                        <span class="dashicons <?php echo $status['certificate'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                        <div>
                            <strong><?php _e('Certificado Digital', 'wc-nfse'); ?></strong>
                            <p><?php echo $status['certificate'] ? __('Configurado', 'wc-nfse') : __('Pendente', 'wc-nfse'); ?></p>
                        </div>
                        <?php if (!$status['certificate']): ?>
                            <a href="<?php echo admin_url('admin.php?page=wc-nfse-certificates'); ?>" class="button button-primary">
                                <?php _e('Configurar', 'wc-nfse'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($status['complete']): ?>
                    <div class="wc-nfse-status-complete">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <strong><?php _e('Plugin configurado e pronto para uso!', 'wc-nfse'); ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions Widget -->
            <div class="wc-nfse-widget">
                <h2><?php _e('Ações Rápidas', 'wc-nfse'); ?></h2>
                <div class="wc-nfse-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=wc-nfse-manual-emission'); ?>" class="wc-nfse-action-button">
                        <span class="dashicons dashicons-media-document"></span>
                        <div>
                            <strong><?php _e('Emissão Manual', 'wc-nfse'); ?></strong>
                            <p><?php _e('Emitir NFS-e para pedidos específicos', 'wc-nfse'); ?></p>
                        </div>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=wc-nfse-settings'); ?>" class="wc-nfse-action-button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <div>
                            <strong><?php _e('Configurações', 'wc-nfse'); ?></strong>
                            <p><?php _e('Gerenciar configurações do plugin', 'wc-nfse'); ?></p>
                        </div>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=wc-nfse-certificates'); ?>" class="wc-nfse-action-button">
                        <span class="dashicons dashicons-lock"></span>
                        <div>
                            <strong><?php _e('Certificados', 'wc-nfse'); ?></strong>
                            <p><?php _e('Gerenciar certificados digitais', 'wc-nfse'); ?></p>
                        </div>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=wc-nfse-logs'); ?>" class="wc-nfse-action-button">
                        <span class="dashicons dashicons-list-view"></span>
                        <div>
                            <strong><?php _e('Logs', 'wc-nfse'); ?></strong>
                            <p><?php _e('Visualizar logs do sistema', 'wc-nfse'); ?></p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Statistics Widget -->
            <div class="wc-nfse-widget">
                <h2><?php _e('Estatísticas', 'wc-nfse'); ?></h2>
                <div class="wc-nfse-stats">
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'cloudxm_nfse_emissions';

                    $total_emissions = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                    $successful_emissions = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'success'");
                    $failed_emissions = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'error'");
                    $today_emissions = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = %s",
                        current_time('Y-m-d')
                    ));
                    ?>

                    <div class="wc-nfse-stat-item">
                        <div class="wc-nfse-stat-number"><?php echo number_format((float)$total_emissions); ?></div>
                        <div class="wc-nfse-stat-label"><?php _e('Total de Emissões', 'wc-nfse'); ?></div>
                    </div>

                    <div class="wc-nfse-stat-item success">
                        <div class="wc-nfse-stat-number"><?php echo number_format((float)$successful_emissions); ?></div>
                        <div class="wc-nfse-stat-label"><?php _e('Sucessos', 'wc-nfse'); ?></div>
                    </div>

                    <div class="wc-nfse-stat-item error">
                        <div class="wc-nfse-stat-number"><?php echo number_format((float)$failed_emissions); ?></div>
                        <div class="wc-nfse-stat-label"><?php _e('Erros', 'wc-nfse'); ?></div>
                    </div>

                    <div class="wc-nfse-stat-item">
                        <div class="wc-nfse-stat-number"><?php echo number_format((float)$today_emissions); ?></div>
                        <div class="wc-nfse-stat-label"><?php _e('Hoje', 'wc-nfse'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Environment Info Widget -->
            <div class="wc-nfse-widget">
                <h2><?php _e('Informações do Ambiente', 'wc-nfse'); ?></h2>
                <div class="wc-nfse-environment-info">
                    <div class="wc-nfse-info-item">
                        <strong><?php _e('Ambiente:', 'wc-nfse'); ?></strong>
                        <span class="wc-nfse-environment-badge <?php echo $settings->get_environment(); ?>">
                            <?php echo $settings->get_environment() === 'production' ? __('Produção', 'wc-nfse') : __('Homologação', 'wc-nfse'); ?>
                        </span>
                    </div>

                    <div class="wc-nfse-info-item">
                        <strong><?php _e('Emissão Automática:', 'wc-nfse'); ?></strong>
                        <span class="<?php echo $settings->is_auto_emit_enabled() ? 'enabled' : 'disabled'; ?>">
                            <?php echo $settings->is_auto_emit_enabled() ? __('Habilitada', 'wc-nfse') : __('Desabilitada', 'wc-nfse'); ?>
                        </span>
                    </div>

                    <div class="wc-nfse-info-item">
                        <strong><?php _e('Debug:', 'wc-nfse'); ?></strong>
                        <span class="<?php echo $settings->is_debug_enabled() ? 'enabled' : 'disabled'; ?>">
                            <?php echo $settings->is_debug_enabled() ? __('Habilitado', 'wc-nfse') : __('Desabilitado', 'wc-nfse'); ?>
                        </span>
                    </div>

                    <div class="wc-nfse-info-item">
                        <strong><?php _e('Versão do Plugin:', 'wc-nfse'); ?></strong>
                        <span><?php echo WC_NFSE_VERSION; ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .wc-nfse-admin {
        margin: 20px 0;
    }

    .wc-nfse-dashboard-widgets {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .wc-nfse-widget {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    }

    .wc-nfse-widget h2 {
        margin: 0 0 15px 0;
        font-size: 16px;
        font-weight: 600;
    }

    .wc-nfse-status-grid {
        display: grid;
        gap: 15px;
    }

    .wc-nfse-status-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #f9f9f9;
    }

    .wc-nfse-status-item.complete {
        border-color: #46b450;
        background: #f0f8f0;
    }

    .wc-nfse-status-item.incomplete {
        border-color: #ffb900;
        background: #fffbf0;
    }

    .wc-nfse-status-item .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
    }

    .wc-nfse-status-item.complete .dashicons {
        color: #46b450;
    }

    .wc-nfse-status-item.incomplete .dashicons {
        color: #ffb900;
    }

    .wc-nfse-status-item div {
        flex: 1;
    }

    .wc-nfse-status-item p {
        margin: 5px 0 0 0;
        color: #666;
        font-size: 13px;
    }

    .wc-nfse-status-complete {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 15px;
        padding: 15px;
        background: #f0f8f0;
        border: 1px solid #46b450;
        border-radius: 4px;
        color: #46b450;
    }

    .wc-nfse-quick-actions {
        display: grid;
        gap: 10px;
    }

    .wc-nfse-action-button {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        color: #333;
        transition: all 0.2s;
    }

    .wc-nfse-action-button:hover {
        border-color: #0073aa;
        background: #f8f9fa;
        color: #0073aa;
    }

    .wc-nfse-action-button .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
        color: #666;
    }

    .wc-nfse-action-button:hover .dashicons {
        color: #0073aa;
    }

    .wc-nfse-action-button div {
        flex: 1;
    }

    .wc-nfse-action-button p {
        margin: 5px 0 0 0;
        color: #666;
        font-size: 13px;
    }

    .wc-nfse-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .wc-nfse-stat-item {
        text-align: center;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #f9f9f9;
    }

    .wc-nfse-stat-item.success {
        border-color: #46b450;
        background: #f0f8f0;
    }

    .wc-nfse-stat-item.error {
        border-color: #dc3232;
        background: #fdf0f0;
    }

    .wc-nfse-stat-number {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .wc-nfse-stat-item.success .wc-nfse-stat-number {
        color: #46b450;
    }

    .wc-nfse-stat-item.error .wc-nfse-stat-number {
        color: #dc3232;
    }

    .wc-nfse-stat-label {
        font-size: 13px;
        color: #666;
    }

    .wc-nfse-environment-info {
        display: grid;
        gap: 10px;
    }

    .wc-nfse-info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .wc-nfse-info-item:last-child {
        border-bottom: none;
    }

    .wc-nfse-environment-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: bold;
    }

    .wc-nfse-environment-badge.production {
        background: #dc3232;
        color: white;
    }

    .wc-nfse-environment-badge.homologation {
        background: #ffb900;
        color: white;
    }

    .enabled {
        color: #46b450;
        font-weight: bold;
    }

    .disabled {
        color: #dc3232;
        font-weight: bold;
    }
</style>