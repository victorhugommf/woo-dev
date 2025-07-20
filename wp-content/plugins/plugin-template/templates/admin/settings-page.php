<?php
/**
 * Template da página de configurações
 */

if (!defined('ABSPATH')) {
    exit;
}

// Processar formulário
if (isset($_POST['submit'])) {
    check_admin_referer('plugin_template_settings');
    
    $options = array();
    if (isset($_POST['plugin_template_options'])) {
        $options = $_POST['plugin_template_options'];
    }
    
    // Sanitizar e salvar opções
    $sanitized_options = array();
    if (isset($options['enabled'])) {
        $sanitized_options['enabled'] = (bool) $options['enabled'];
    }
    if (isset($options['example_field'])) {
        $sanitized_options['example_field'] = sanitize_text_field($options['example_field']);
    }
    
    update_option('plugin_template_options', $sanitized_options);
    
    echo '<div class="notice notice-success"><p>' . __('Configurações salvas com sucesso!', 'plugin-template') . '</p></div>';
}

$options = get_option('plugin_template_options', array());
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('plugin_template_settings'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="plugin_template_enabled"><?php _e('Habilitar Plugin', 'plugin-template'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="plugin_template_enabled" name="plugin_template_options[enabled]" value="1" <?php checked(isset($options['enabled']) && $options['enabled']); ?> />
                        <p class="description"><?php _e('Habilitar todas as funcionalidades do plugin.', 'plugin-template'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="plugin_template_example_field"><?php _e('Campo de Exemplo', 'plugin-template'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="plugin_template_example_field" name="plugin_template_options[example_field]" value="<?php echo esc_attr(isset($options['example_field']) ? $options['example_field'] : ''); ?>" class="regular-text" />
                        <p class="description"><?php _e('Digite um valor de exemplo para demonstração.', 'plugin-template'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2><?php _e('Configurações Avançadas', 'plugin-template'); ?></h2>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Limpar Dados', 'plugin-template'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="clear-all-data">
                            <?php _e('Limpar Todos os Dados', 'plugin-template'); ?>
                        </button>
                        <p class="description"><?php _e('ATENÇÃO: Esta ação irá remover todos os dados do plugin permanentemente.', 'plugin-template'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Exportar/Importar', 'plugin-template'); ?></th>
                    <td>
                        <p>
                            <button type="button" class="button" id="export-settings">
                                <?php _e('Exportar Configurações', 'plugin-template'); ?>
                            </button>
                            
                            <input type="file" id="import-settings" accept=".json" style="display: none;" />
                            <button type="button" class="button" id="import-settings-btn">
                                <?php _e('Importar Configurações', 'plugin-template'); ?>
                            </button>
                        </p>
                        <p class="description"><?php _e('Exporte suas configurações para backup ou importe de outro site.', 'plugin-template'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php if (class_exists('WooCommerce')): ?>
        <h2><?php _e('Configurações WooCommerce', 'plugin-template'); ?></h2>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Integração WooCommerce', 'plugin-template'); ?></th>
                    <td>
                        <p><?php _e('WooCommerce detectado e integração ativa.', 'plugin-template'); ?></p>
                        <p><strong><?php _e('Versão:', 'plugin-template'); ?></strong> <?php echo WC()->version; ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Produtos Habilitados', 'plugin-template'); ?></th>
                    <td>
                        <?php
                        global $wpdb;
                        $enabled_products = $wpdb->get_var(
                            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_plugin_template_enabled' AND meta_value = 'yes'"
                        );
                        ?>
                        <p><?php printf(__('%d produtos têm o Plugin Template habilitado.', 'plugin-template'), $enabled_products); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>
        
        <h2><?php _e('Informações do Sistema', 'plugin-template'); ?></h2>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Versão do Plugin', 'plugin-template'); ?></th>
                    <td><?php echo PLUGIN_TEMPLATE_VERSION; ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Versão do WordPress', 'plugin-template'); ?></th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Versão do PHP', 'plugin-template'); ?></th>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Banco de Dados', 'plugin-template'); ?></th>
                    <td>
                        <?php
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'plugin_template_data';
                        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                        ?>
                        <?php if ($table_exists): ?>
                            <span style="color: green;"><?php _e('Tabela criada com sucesso', 'plugin-template'); ?></span>
                        <?php else: ?>
                            <span style="color: red;"><?php _e('Tabela não encontrada', 'plugin-template'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#clear-all-data').on('click', function() {
        if (confirm('<?php _e('Tem certeza que deseja limpar todos os dados? Esta ação não pode ser desfeita.', 'plugin-template'); ?>')) {
            $.ajax({
                url: pluginTemplate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'plugin_template_admin_action',
                    action_type: 'clear_all_data',
                    nonce: pluginTemplate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Dados limpos com sucesso!', 'plugin-template'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Erro ao limpar dados.', 'plugin-template'); ?>');
                    }
                }
            });
        }
    });
    
    $('#export-settings').on('click', function() {
        var settings = <?php echo json_encode($options); ?>;
        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(settings, null, 2));
        var downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", "plugin-template-settings.json");
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    });
    
    $('#import-settings-btn').on('click', function() {
        $('#import-settings').click();
    });
    
    $('#import-settings').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);
                    // Preencher campos com as configurações importadas
                    if (settings.enabled) {
                        $('#plugin_template_enabled').prop('checked', true);
                    }
                    if (settings.example_field) {
                        $('#plugin_template_example_field').val(settings.example_field);
                    }
                    alert('<?php _e('Configurações importadas! Clique em "Salvar alterações" para aplicar.', 'plugin-template'); ?>');
                } catch (error) {
                    alert('<?php _e('Erro ao importar configurações. Verifique o arquivo.', 'plugin-template'); ?>');
                }
            };
            reader.readAsText(file);
        }
    });
});
</script>

