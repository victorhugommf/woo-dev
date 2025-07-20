<?php
/**
 * Template da página principal do admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('plugin_template_options', array());
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="plugin-template-admin-header">
        <p><?php _e('Bem-vindo ao Plugin Template! Use esta página para gerenciar as funcionalidades do plugin.', 'plugin-template'); ?></p>
    </div>
    
    <div class="plugin-template-admin-content">
        <div class="postbox-container" style="width: 70%;">
            <div class="postbox">
                <h2 class="hndle"><?php _e('Visão Geral', 'plugin-template'); ?></h2>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Status do Plugin', 'plugin-template'); ?></th>
                            <td>
                                <?php if (isset($options['enabled']) && $options['enabled']): ?>
                                    <span class="status-enabled"><?php _e('Habilitado', 'plugin-template'); ?></span>
                                <?php else: ?>
                                    <span class="status-disabled"><?php _e('Desabilitado', 'plugin-template'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Versão', 'plugin-template'); ?></th>
                            <td><?php echo PLUGIN_TEMPLATE_VERSION; ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('WooCommerce', 'plugin-template'); ?></th>
                            <td>
                                <?php if (class_exists('WooCommerce')): ?>
                                    <span class="status-enabled"><?php _e('Detectado', 'plugin-template'); ?></span>
                                    <small>(<?php echo WC()->version; ?>)</small>
                                <?php else: ?>
                                    <span class="status-disabled"><?php _e('Não detectado', 'plugin-template'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle"><?php _e('Estatísticas', 'plugin-template'); ?></h2>
                <div class="inside">
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'plugin_template_data';
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                    ?>
                    <p><?php printf(__('Total de registros: %d', 'plugin-template'), $count); ?></p>
                    
                    <div class="plugin-template-stats">
                        <div class="stat-box">
                            <h4><?php _e('Registros Hoje', 'plugin-template'); ?></h4>
                            <span class="stat-number">
                                <?php
                                $today_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
                                    current_time('Y-m-d')
                                ));
                                echo $today_count;
                                ?>
                            </span>
                        </div>
                        
                        <div class="stat-box">
                            <h4><?php _e('Registros Esta Semana', 'plugin-template'); ?></h4>
                            <span class="stat-number">
                                <?php
                                $week_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s",
                                    date('Y-m-d', strtotime('-7 days'))
                                ));
                                echo $week_count;
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle"><?php _e('Ações Rápidas', 'plugin-template'); ?></h2>
                <div class="inside">
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=plugin-template-settings'); ?>" class="button button-primary">
                            <?php _e('Configurações', 'plugin-template'); ?>
                        </a>
                        
                        <button type="button" class="button" id="clear-data-btn">
                            <?php _e('Limpar Dados', 'plugin-template'); ?>
                        </button>
                        
                        <button type="button" class="button" id="export-data-btn">
                            <?php _e('Exportar Dados', 'plugin-template'); ?>
                        </button>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="postbox-container" style="width: 28%; margin-left: 2%;">
            <div class="postbox">
                <h2 class="hndle"><?php _e('Informações', 'plugin-template'); ?></h2>
                <div class="inside">
                    <h4><?php _e('Documentação', 'plugin-template'); ?></h4>
                    <p><?php _e('Consulte a documentação para aprender como usar todas as funcionalidades do plugin.', 'plugin-template'); ?></p>
                    
                    <h4><?php _e('Suporte', 'plugin-template'); ?></h4>
                    <p><?php _e('Precisa de ajuda? Entre em contato conosco através do suporte.', 'plugin-template'); ?></p>
                    
                    <h4><?php _e('Shortcodes Disponíveis', 'plugin-template'); ?></h4>
                    <ul>
                        <li><code>[plugin_template_example]</code></li>
                        <li><code>[plugin_template_form]</code></li>
                        <li><code>[plugin_template_data]</code></li>
                        <?php if (class_exists('WooCommerce')): ?>
                            <li><code>[plugin_template_wc_products]</code></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle"><?php _e('Últimos Registros', 'plugin-template'); ?></h2>
                <div class="inside">
                    <?php
                    $recent_data = $wpdb->get_results(
                        "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5"
                    );
                    
                    if (!empty($recent_data)): ?>
                        <ul class="recent-data-list">
                            <?php foreach ($recent_data as $item): ?>
                                <li>
                                    <strong><?php echo esc_html($item->name); ?></strong><br>
                                    <small><?php echo esc_html($item->created_at); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php _e('Nenhum registro encontrado.', 'plugin-template'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#clear-data-btn').on('click', function() {
        if (confirm(pluginTemplate.strings.confirm)) {
            $.ajax({
                url: pluginTemplate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'plugin_template_admin_action',
                    action_type: 'clear_data',
                    nonce: pluginTemplate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(pluginTemplate.strings.error);
                    }
                }
            });
        }
    });
    
    $('#export-data-btn').on('click', function() {
        window.location.href = pluginTemplate.ajaxUrl + '?action=plugin_template_export_data&nonce=' + pluginTemplate.nonce;
    });
});
</script>

