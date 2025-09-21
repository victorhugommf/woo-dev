<?php
/**
 * WC_NFSe_Admin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_NFSe_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // Initialize sub-classes
        new WC_NFSe_Admin_Settings();
        new WC_NFSe_Admin_Orders();
    }

    /**
      * Add admin menu
      */
     public function admin_menu() {
         add_menu_page(
            __('NFS-e', 'wc-nfse'),
            __('NFS-e', 'wc-nfse'),
            'manage_woocommerce',
            'wc-nfse',
            array($this, 'admin_page'),
            'dashicons-media-document',
            56
        );

        add_submenu_page(
            'wc-nfse',
            __('Configurações', 'wc-nfse'),
            __('Configurações', 'wc-nfse'),
            'manage_woocommerce',
            'wc-nfse-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'wc-nfse',
            __('Certificados', 'wc-nfse'),
            __('Certificados', 'wc-nfse'),
            'manage_woocommerce',
            'wc-nfse-certificates',
            array($this, 'certificates_page')
        );

        add_submenu_page(
            'wc-nfse',
            __('Conformidade RTC', 'wc-nfse'),
            __('Conformidade RTC', 'wc-nfse'),
            'manage_woocommerce',
            'wc-nfse-rtc-compliance',
            array($this, 'rtc_compliance_page')
        );

        add_submenu_page(
            'wc-nfse',
            __('Validação XSD', 'wc-nfse'),
            __('Validação XSD', 'wc-nfse'),
            'manage_woocommerce',
            'wc-nfse-xsd-validation',
            array($this, 'xsd_validation_page')
        );

        add_submenu_page(
            'wc-nfse',
            __('Emissão Manual', 'wc-nfse'),
            __('Emissão Manual', 'wc-nfse'),
            'manage_woocommerce',
            'wc-nfse-manual-emission',
            array($this, 'manual_emission_page')
        );

        add_submenu_page(
            'wc-nfse',
            __('Logs', 'wc-nfse'),
            __('Logs', 'wc-nfse'),
            'manage_woocommerce',
            'wc-nfse-logs',
            array($this, 'logs_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'wc-nfse') === false) {
            return;
        }

        wp_enqueue_style(
            'wc-nfse-admin',
            WC_NFSE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_NFSE_VERSION
        );

        wp_enqueue_script(
            'wc-nfse-admin',
            WC_NFSE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WC_NFSE_VERSION,
            true
        );

        wp_localize_script('wc-nfse-admin', 'wc_nfse_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_nfse_admin'),
            'strings' => array(
                'confirm_delete' => __('Tem certeza que deseja excluir?', 'wc-nfse'),
                'processing' => __('Processando...', 'wc-nfse'),
                'error' => __('Erro ao processar solicitação', 'wc-nfse'),
                'success' => __('Operação realizada com sucesso', 'wc-nfse')
            )
        ));
    }

    /**
     * Show admin notices
     */
    public function admin_notices() {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, 'wc-nfse') === false) {
            return;
        }

        $settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
        $status = $settings->get_configuration_status();

        if (!$status['complete']) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('NFS-e:', 'wc-nfse') . '</strong> ';
            
            if (!$status['prestador_data']) {
                echo __('Configure os dados do prestador para começar a usar o plugin.', 'wc-nfse');
                echo ' <a href="' . admin_url('admin.php?page=wc-nfse-settings') . '">' . __('Configurar agora', 'wc-nfse') . '</a>';
            } elseif (!$status['certificate']) {
                echo __('Faça upload do certificado digital para emitir NFS-e.', 'wc-nfse');
                echo ' <a href="' . admin_url('admin.php?page=wc-nfse-certificates') . '">' . __('Configurar certificado', 'wc-nfse') . '</a>';
            }
            
            echo '</p>';
            echo '</div>';
        }
    }

    /**
     * Main admin page
     */
    public function admin_page() {
        $settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
        $status = $settings->get_configuration_status();
        
        include WC_NFSE_PLUGIN_PATH . 'includes/admin/views/html-admin-page.php';
    }

    /**
     * Settings page
     */
    public function settings_page() {
        $admin_settings = new WC_NFSe_Admin_Settings();
        $admin_settings->output();
    }

    /**
     * Certificates page
     */
    public function certificates_page() {
        include WC_NFSE_PLUGIN_PATH . 'includes/admin/views/html-admin-certificates.php';
    }

    /**
     * RTC Compliance page
     */
    public function rtc_compliance_page() {
        include WC_NFSE_PLUGIN_PATH . 'includes/admin/views/html-admin-rtc-compliance-complete.php';
    }

    /**
     * XSD Validation page
     */
    public function xsd_validation_page() {
        include WC_NFSE_PLUGIN_PATH . 'includes/admin/views/html-admin-xsd-validation.php';
    }

    /**
     * Manual emission page
     */
    public function manual_emission_page() {
        include WC_NFSE_PLUGIN_PATH . 'includes/admin/views/html-admin-manual-emission.php';
    }

    /**
     * Logs page
     */
    public function logs_page() {
        $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
        $logs = $logger->get_recent_logs(200);
        $log_size = $logger->get_log_size();
        
        include WC_NFSE_PLUGIN_PATH . 'includes/admin/views/html-admin-logs.php';
    }
}

