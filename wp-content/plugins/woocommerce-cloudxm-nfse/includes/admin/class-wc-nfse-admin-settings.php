<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_NFSe_Admin_Settings - PSR-4 Implementation
 *
 * Admin settings handler using modern PSR-4 components
 */
class WC_NFSe_Admin_Settings {

    /**
     * Settings instance
     */
    private \CloudXM\NFSe\Compatibility\SettingsCompatibility $settings;

    /**
     * Constructor
      */
     public function __construct() {
         $this->settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();

         add_action('admin_init', array($this, 'init_settings'));
         add_action('wp_ajax_wc_nfse_save_settings', array($this, 'save_settings'));

         // Debug: Log when class is instantiated
         if (defined('WP_DEBUG') && WP_DEBUG) {
             $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
             $logger->debug('WC_NFSe_Admin_Settings instantiated and AJAX action registered');
         }
     }

    /**
     * Initialize settings
     */
    public function init_settings() {
        // Register settings sections and fields will be handled via AJAX
    }

    /**
     * Output settings page
     */
    public function output() {
        $settings = $this->settings->get_all();
        $states = $this->settings->get_brazilian_states();
        $tax_regimes = $this->settings->get_tax_regimes();
        
        include WC_NFSE_PLUGIN_PATH . 'includes/admin/views/html-admin-settings.php';
    }

    /**
     * Save settings via AJAX
      */
     public function save_settings() {
         // Debug: Log that save_settings was called
         if (defined('WP_DEBUG') && WP_DEBUG) {
             $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
             $logger->debug('save_settings method called', [
                 'post_data' => $_POST,
                 'files' => $_FILES
             ]);
         }

         check_ajax_referer('wc_nfse_admin', 'nonce');

         if (!current_user_can('manage_woocommerce')) {
             wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-nfse'));
         }

         $settings_data = array();
        
        // General settings
        $settings_data['enabled'] = sanitize_text_field($_POST['enabled'] ?? 'no');
        $settings_data['environment'] = sanitize_text_field($_POST['environment'] ?? 'homologation');
        $settings_data['auto_emit'] = sanitize_text_field($_POST['auto_emit'] ?? 'no');
        $settings_data['debug_mode'] = sanitize_text_field($_POST['debug_mode'] ?? 'no');

        // Prestador data
        $settings_data['prestador_cnpj'] = sanitize_text_field($_POST['prestador_cnpj'] ?? '');
        $settings_data['prestador_inscricao_municipal'] = sanitize_text_field($_POST['prestador_inscricao_municipal'] ?? '');
        $settings_data['prestador_razao_social'] = sanitize_text_field($_POST['prestador_razao_social'] ?? '');
        $settings_data['prestador_nome_fantasia'] = sanitize_text_field($_POST['prestador_nome_fantasia'] ?? '');
        $settings_data['prestador_telefone'] = sanitize_text_field($_POST['prestador_telefone'] ?? '');
        $settings_data['prestador_email'] = sanitize_email($_POST['prestador_email'] ?? '');

        // Address data
        $settings_data['prestador_endereco'] = sanitize_text_field($_POST['prestador_endereco'] ?? '');
        $settings_data['prestador_numero'] = sanitize_text_field($_POST['prestador_numero'] ?? '');
        $settings_data['prestador_complemento'] = sanitize_text_field($_POST['prestador_complemento'] ?? '');
        $settings_data['prestador_bairro'] = sanitize_text_field($_POST['prestador_bairro'] ?? '');
        $settings_data['prestador_cidade'] = sanitize_text_field($_POST['prestador_cidade'] ?? '');
        $settings_data['prestador_uf'] = sanitize_text_field($_POST['prestador_uf'] ?? '');
        $settings_data['prestador_cep'] = sanitize_text_field($_POST['prestador_cep'] ?? '');

        // Tax settings
        $settings_data['regime_tributario'] = sanitize_text_field($_POST['regime_tributario'] ?? 'simples_nacional');
        $settings_data['default_nbs_code'] = sanitize_text_field($_POST['default_nbs_code'] ?? '01.01');
        $settings_data['dps_serie'] = sanitize_text_field($_POST['dps_serie'] ?? '');

        // Validate CNPJ
        if (!empty($settings_data['prestador_cnpj'])) {
            $cnpj = preg_replace('/\D/', '', $settings_data['prestador_cnpj']);
            if (!$this->settings->validate_cnpj($cnpj)) {
                wp_send_json_error(array(
                    'message' => __('CNPJ inválido. Verifique o número informado.', 'wc-nfse')
                ));
            }
            $settings_data['prestador_cnpj'] = $cnpj;
        }

        // Validate email
        if (!empty($settings_data['prestador_email']) && !is_email($settings_data['prestador_email'])) {
            wp_send_json_error(array(
                'message' => __('Email inválido. Verifique o endereço informado.', 'wc-nfse')
            ));
        }

        // Validate CEP
        if (!empty($settings_data['prestador_cep'])) {
            $cep = preg_replace('/\D/', '', $settings_data['prestador_cep']);
            if (strlen($cep) !== 8) {
                wp_send_json_error(array(
                    'message' => __('CEP inválido. Deve conter 8 dígitos.', 'wc-nfse')
                ));
            }
            $settings_data['prestador_cep'] = $cep;
        }

        // Validate required address fields
        $required_address_fields = [
            'prestador_endereco' => __('Logradouro', 'wc-nfse'),
            'prestador_numero' => __('Número', 'wc-nfse'),
            'prestador_bairro' => __('Bairro', 'wc-nfse'),
            'prestador_cidade' => __('Cidade', 'wc-nfse'),
            'prestador_uf' => __('Estado (UF)', 'wc-nfse'),
            'prestador_cep' => __('CEP', 'wc-nfse')
        ];

        foreach ($required_address_fields as $field => $label) {
            if (empty($settings_data[$field])) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Campo obrigatório: %s', 'wc-nfse'), $label)
                ));
            }
        }

        // Save settings
        if ($this->settings->update($settings_data)) {
            // Log the settings update
            $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
            $logger->info('Configurações atualizadas pelo usuário: ' . get_current_user_id());

            wp_send_json_success(array(
                'message' => __('Configurações salvas com sucesso!', 'wc-nfse')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Erro ao salvar configurações. Tente novamente.', 'wc-nfse')
            ));
        }
    }

    /**
     * Get settings fields configuration
     */
    public function get_settings_fields() {
        return array(
            'general' => array(
                'title' => __('Configurações Gerais', 'wc-nfse'),
                'fields' => array(
                    'enabled' => array(
                        'title' => __('Habilitar NFS-e', 'wc-nfse'),
                        'type' => 'checkbox',
                        'description' => __('Habilita a emissão de NFS-e para pedidos.', 'wc-nfse'),
                        'default' => 'no'
                    ),
                    'environment' => array(
                        'title' => __('Ambiente', 'wc-nfse'),
                        'type' => 'select',
                        'options' => array(
                            'homologation' => __('Homologação', 'wc-nfse'),
                            'production' => __('Produção', 'wc-nfse')
                        ),
                        'description' => __('Selecione o ambiente para emissão das NFS-e.', 'wc-nfse'),
                        'default' => 'homologation'
                    ),
                    'auto_emit' => array(
                        'title' => __('Emissão Automática', 'wc-nfse'),
                        'type' => 'checkbox',
                        'description' => __('Emite NFS-e automaticamente após confirmação do pagamento.', 'wc-nfse'),
                        'default' => 'no'
                    ),
                    'debug_mode' => array(
                        'title' => __('Modo Debug', 'wc-nfse'),
                        'type' => 'checkbox',
                        'description' => __('Habilita logs detalhados para debug.', 'wc-nfse'),
                        'default' => 'yes'
                    )
                )
            ),
            'prestador' => array(
                'title' => __('Dados do Prestador', 'wc-nfse'),
                'fields' => array(
                    'prestador_cnpj' => array(
                        'title' => __('CNPJ', 'wc-nfse'),
                        'type' => 'text',
                        'description' => __('CNPJ do prestador de serviços.', 'wc-nfse'),
                        'required' => true
                    ),
                    'prestador_inscricao_municipal' => array(
                        'title' => __('Inscrição Municipal', 'wc-nfse'),
                        'type' => 'text',
                        'description' => __('Inscrição municipal do prestador.', 'wc-nfse'),
                        'required' => true
                    ),
                    'prestador_razao_social' => array(
                        'title' => __('Razão Social', 'wc-nfse'),
                        'type' => 'text',
                        'description' => __('Razão social da empresa.', 'wc-nfse'),
                        'required' => true
                    ),
                    'prestador_nome_fantasia' => array(
                        'title' => __('Nome Fantasia', 'wc-nfse'),
                        'type' => 'text',
                        'description' => __('Nome fantasia da empresa (opcional).', 'wc-nfse')
                    )
                )
            )
        );
    }
}

