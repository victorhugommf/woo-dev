<?php

/**
 * Plugin Name: Plugin Template 123
 * Plugin URI: https://example.com/plugin-template
 * Description: Template básico para desenvolvimento de plugins WordPress/WooCommerce
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: plugin-template
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes do plugin
define('PLUGIN_TEMPLATE_VERSION', '1.0.0');
define('PLUGIN_TEMPLATE_PLUGIN_FILE', __FILE__);
define('PLUGIN_TEMPLATE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PLUGIN_TEMPLATE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PLUGIN_TEMPLATE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principal do plugin
 */
class PluginTemplate
{

    /**
     * Instância única da classe
     */
    private static $instance = null;

    /**
     * Construtor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Obter instância única
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks()
    {
        // Hook de ativação
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Hook de desativação
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Inicializar plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Carregar textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Scripts e estilos admin
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Scripts e estilos frontend
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));

        // Menu admin
        add_action('admin_menu', array($this, 'admin_menu'));

        // Verificar se WooCommerce está ativo
        add_action('admin_init', array($this, 'check_woocommerce'));
    }

    /**
     * Ativação do plugin
     */
    public function activate()
    {
        // Criar tabelas se necessário
        $this->create_tables();

        // Definir versão
        update_option('plugin_template_version', PLUGIN_TEMPLATE_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Desativação do plugin
     */
    public function deactivate()
    {
        // Limpar cache se necessário
        wp_cache_flush();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Inicializar plugin
     */
    public function init()
    {
        // Verificar dependências
        if (!$this->check_dependencies()) {
            return;
        }

        // Incluir arquivos necessários
        $this->includes();

        // Inicializar classes
        $this->init_classes();

        // Hook personalizado após inicialização
        do_action('plugin_template_init');
    }

    /**
     * Carregar textdomain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'plugin-template',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Scripts e estilos admin
     */
    public function admin_scripts($hook)
    {
        // Carregar apenas nas páginas do plugin
        if (strpos($hook, 'plugin-template') === false) {
            return;
        }

        wp_enqueue_style(
            'plugin-template-admin',
            PLUGIN_TEMPLATE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PLUGIN_TEMPLATE_VERSION
        );

        wp_enqueue_script(
            'plugin-template-admin',
            PLUGIN_TEMPLATE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            PLUGIN_TEMPLATE_VERSION,
            true
        );

        // Localizar script
        wp_localize_script('plugin-template-admin', 'pluginTemplate', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('plugin_template_nonce'),
            'strings' => array(
                'confirm' => __('Tem certeza?', 'plugin-template'),
                'error' => __('Erro ao processar solicitação', 'plugin-template'),
            )
        ));
    }

    /**
     * Scripts e estilos frontend
     */
    public function frontend_scripts()
    {
        wp_enqueue_style(
            'plugin-template-frontend',
            PLUGIN_TEMPLATE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PLUGIN_TEMPLATE_VERSION
        );

        wp_enqueue_script(
            'plugin-template-frontend',
            PLUGIN_TEMPLATE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            PLUGIN_TEMPLATE_VERSION,
            true
        );
    }

    /**
     * Menu admin
     */
    public function admin_menu()
    {
        add_menu_page(
            __('Plugin Template', 'plugin-template'),
            __('Plugin Template', 'plugin-template'),
            'manage_options',
            'plugin-template',
            array($this, 'admin_page'),
            'dashicons-admin-plugins',
            30
        );

        add_submenu_page(
            'plugin-template',
            __('Configurações', 'plugin-template'),
            __('Configurações', 'plugin-template'),
            'manage_options',
            'plugin-template-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Página admin principal
     */
    public function admin_page()
    {
        include PLUGIN_TEMPLATE_PLUGIN_DIR . 'templates/admin/main-page.php';
    }

    /**
     * Página de configurações
     */
    public function settings_page()
    {
        include PLUGIN_TEMPLATE_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }

    /**
     * Verificar WooCommerce
     */
    public function check_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }

    /**
     * Aviso WooCommerce ausente
     */
    public function woocommerce_missing_notice()
    {
?>
        <div class="notice notice-error">
            <p><?php _e('Plugin Template requer o WooCommerce para funcionar corretamente.', 'plugin-template'); ?></p>
        </div>
    <?php
    }

    /**
     * Verificar dependências
     */
    private function check_dependencies()
    {
        // Verificar versão PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }

        // Verificar versão WordPress
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }

        return true;
    }

    /**
     * Incluir arquivos
     */
    private function includes()
    {
        // Incluir classes
        require_once PLUGIN_TEMPLATE_PLUGIN_DIR . 'includes/class-settings.php';
        require_once PLUGIN_TEMPLATE_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once PLUGIN_TEMPLATE_PLUGIN_DIR . 'includes/class-shortcodes.php';

        // Incluir funções
        require_once PLUGIN_TEMPLATE_PLUGIN_DIR . 'includes/functions.php';

        // Incluir integrações WooCommerce
        if (class_exists('WooCommerce')) {
            require_once PLUGIN_TEMPLATE_PLUGIN_DIR . 'includes/woocommerce/class-wc-integration.php';
        }
    }

    /**
     * Inicializar classes
     */
    private function init_classes()
    {
        new PluginTemplate_Settings();
        new PluginTemplate_Ajax();
        new PluginTemplate_Shortcodes();

        if (class_exists('WooCommerce')) {
            new PluginTemplate_WC_Integration();
        }
    }

    /**
     * Criar tabelas
     */
    private function create_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'plugin_template_data';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            data text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Avisos de versão
     */
    public function php_version_notice()
    {
    ?>
        <div class="notice notice-error">
            <p><?php printf(__('Plugin Template requer PHP 7.4 ou superior. Versão atual: %s', 'plugin-template'), PHP_VERSION); ?></p>
        </div>
    <?php
    }

    public function wp_version_notice()
    {
    ?>
        <div class="notice notice-error">
            <p><?php printf(__('Plugin Template requer WordPress 5.0 ou superior. Versão atual: %s', 'plugin-template'), get_bloginfo('version')); ?></p>
        </div>
<?php
    }
}

// Inicializar plugin
PluginTemplate::get_instance();

// Funções auxiliares globais
function plugin_template()
{
    return PluginTemplate::get_instance();
}

// Hook para outros plugins
do_action('plugin_template_loaded');
