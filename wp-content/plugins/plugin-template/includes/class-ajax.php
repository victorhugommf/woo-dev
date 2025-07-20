<?php
/**
 * Classe AJAX do plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class PluginTemplate_Ajax {
    
    /**
     * Construtor
     */
    public function __construct() {
        // AJAX para usuários logados
        add_action('wp_ajax_plugin_template_action', array($this, 'handle_ajax_action'));
        
        // AJAX para usuários não logados
        add_action('wp_ajax_nopriv_plugin_template_action', array($this, 'handle_ajax_action'));
        
        // Exemplo de AJAX específico para admin
        add_action('wp_ajax_plugin_template_admin_action', array($this, 'handle_admin_action'));
    }
    
    /**
     * Manipular ação AJAX geral
     */
    public function handle_ajax_action() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'plugin_template_nonce')) {
            wp_die(__('Erro de segurança', 'plugin-template'));
        }
        
        // Obter dados
        $action_type = sanitize_text_field($_POST['action_type']);
        $data = sanitize_text_field($_POST['data']);
        
        $response = array();
        
        switch ($action_type) {
            case 'get_data':
                $response = $this->get_data($data);
                break;
                
            case 'save_data':
                $response = $this->save_data($data);
                break;
                
            default:
                $response = array(
                    'success' => false,
                    'message' => __('Ação não reconhecida', 'plugin-template')
                );
        }
        
        wp_send_json($response);
    }
    
    /**
     * Manipular ação admin
     */
    public function handle_admin_action() {
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Permissão negada', 'plugin-template'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'plugin_template_nonce')) {
            wp_die(__('Erro de segurança', 'plugin-template'));
        }
        
        // Processar ação admin
        $result = $this->process_admin_action();
        
        wp_send_json($result);
    }
    
    /**
     * Obter dados
     */
    private function get_data($data) {
        // Implementar lógica para obter dados
        return array(
            'success' => true,
            'data' => array(
                'example' => 'Dados de exemplo',
                'timestamp' => current_time('mysql')
            )
        );
    }
    
    /**
     * Salvar dados
     */
    private function save_data($data) {
        // Implementar lógica para salvar dados
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'plugin_template_data';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => 'ajax_data',
                'data' => $data,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        
        if ($result !== false) {
            return array(
                'success' => true,
                'message' => __('Dados salvos com sucesso', 'plugin-template'),
                'id' => $wpdb->insert_id
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Erro ao salvar dados', 'plugin-template')
            );
        }
    }
    
    /**
     * Processar ação admin
     */
    private function process_admin_action() {
        // Implementar lógica específica do admin
        return array(
            'success' => true,
            'message' => __('Ação admin processada', 'plugin-template')
        );
    }
}

