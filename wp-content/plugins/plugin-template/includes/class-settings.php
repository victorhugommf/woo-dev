<?php
/**
 * Classe de configurações do plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class PluginTemplate_Settings {
    
    /**
     * Construtor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    /**
     * Inicializar configurações
     */
    public function init_settings() {
        // Registrar configurações
        register_setting('plugin_template_settings', 'plugin_template_options', array($this, 'sanitize_options'));
        
        // Seção principal
        add_settings_section(
            'plugin_template_main',
            __('Configurações Principais', 'plugin-template'),
            array($this, 'main_section_callback'),
            'plugin_template_settings'
        );
        
        // Campo de exemplo
        add_settings_field(
            'example_field',
            __('Campo de Exemplo', 'plugin-template'),
            array($this, 'example_field_callback'),
            'plugin_template_settings',
            'plugin_template_main'
        );
        
        // Campo habilitado
        add_settings_field(
            'enabled',
            __('Habilitar Plugin', 'plugin-template'),
            array($this, 'enabled_field_callback'),
            'plugin_template_settings',
            'plugin_template_main'
        );
    }
    
    /**
     * Callback da seção principal
     */
    public function main_section_callback() {
        echo '<p>' . __('Configure as opções principais do plugin.', 'plugin-template') . '</p>';
    }
    
    /**
     * Callback do campo de exemplo
     */
    public function example_field_callback() {
        $options = get_option('plugin_template_options', array());
        $value = isset($options['example_field']) ? $options['example_field'] : '';
        
        echo '<input type="text" name="plugin_template_options[example_field]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Digite um valor de exemplo.', 'plugin-template') . '</p>';
    }
    
    /**
     * Callback do campo habilitado
     */
    public function enabled_field_callback() {
        $options = get_option('plugin_template_options', array());
        $checked = isset($options['enabled']) && $options['enabled'] ? 'checked' : '';
        
        echo '<input type="checkbox" name="plugin_template_options[enabled]" value="1" ' . $checked . ' />';
        echo '<label for="plugin_template_options[enabled]">' . __('Habilitar funcionalidades do plugin', 'plugin-template') . '</label>';
    }
    
    /**
     * Sanitizar opções
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        if (isset($input['example_field'])) {
            $sanitized['example_field'] = sanitize_text_field($input['example_field']);
        }
        
        if (isset($input['enabled'])) {
            $sanitized['enabled'] = (bool) $input['enabled'];
        }
        
        return $sanitized;
    }
    
    /**
     * Obter opção
     */
    public static function get_option($key, $default = '') {
        $options = get_option('plugin_template_options', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Atualizar opção
     */
    public static function update_option($key, $value) {
        $options = get_option('plugin_template_options', array());
        $options[$key] = $value;
        update_option('plugin_template_options', $options);
    }
}

