<?php
/**
 * Integração com WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class PluginTemplate_WC_Integration {
    
    /**
     * Construtor
     */
    public function __construct() {
        // Hooks do WooCommerce
        add_action('woocommerce_init', array($this, 'init'));
        
        // Hooks de produto
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
        
        // Hooks de pedido
        add_action('woocommerce_checkout_order_processed', array($this, 'process_order'), 10, 3);
        add_action('woocommerce_order_status_completed', array($this, 'order_completed'));
        
        // Hooks de carrinho
        add_action('woocommerce_before_add_to_cart_button', array($this, 'before_add_to_cart'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        
        // Hooks de email
        add_action('woocommerce_email_before_order_table', array($this, 'email_before_order_table'), 10, 4);
        
        // Shortcodes específicos do WooCommerce
        add_shortcode('plugin_template_wc_products', array($this, 'products_shortcode'));
    }
    
    /**
     * Inicializar integração
     */
    public function init() {
        // Verificar se WooCommerce está ativo
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Adicionar tab personalizada na conta do usuário
        add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_item'));
        add_action('woocommerce_account_plugin-template_endpoint', array($this, 'account_endpoint_content'));
        add_action('init', array($this, 'add_account_endpoint'));
    }
    
    /**
     * Adicionar campos personalizados ao produto
     */
    public function add_product_fields() {
        global $post;
        
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id' => '_plugin_template_enabled',
            'label' => __('Habilitar Plugin Template', 'plugin-template'),
            'description' => __('Habilitar funcionalidades do plugin para este produto', 'plugin-template')
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_plugin_template_custom_field',
            'label' => __('Campo Personalizado', 'plugin-template'),
            'description' => __('Digite um valor personalizado para este produto', 'plugin-template'),
            'type' => 'text'
        ));
        
        woocommerce_wp_select(array(
            'id' => '_plugin_template_category',
            'label' => __('Categoria Especial', 'plugin-template'),
            'options' => array(
                '' => __('Selecione...', 'plugin-template'),
                'premium' => __('Premium', 'plugin-template'),
                'standard' => __('Padrão', 'plugin-template'),
                'basic' => __('Básico', 'plugin-template')
            )
        ));
        
        echo '</div>';
    }
    
    /**
     * Salvar campos personalizados do produto
     */
    public function save_product_fields($post_id) {
        $enabled = isset($_POST['_plugin_template_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_plugin_template_enabled', $enabled);
        
        if (isset($_POST['_plugin_template_custom_field'])) {
            update_post_meta($post_id, '_plugin_template_custom_field', sanitize_text_field($_POST['_plugin_template_custom_field']));
        }
        
        if (isset($_POST['_plugin_template_category'])) {
            update_post_meta($post_id, '_plugin_template_category', sanitize_text_field($_POST['_plugin_template_category']));
        }
    }
    
    /**
     * Processar pedido
     */
    public function process_order($order_id, $posted_data, $order) {
        // Lógica personalizada ao processar pedido
        $custom_data = array(
            'order_id' => $order_id,
            'processed_at' => current_time('mysql'),
            'custom_field' => 'valor_personalizado'
        );
        
        // Salvar dados personalizados
        update_post_meta($order_id, '_plugin_template_data', $custom_data);
        
        // Log da ação
        $order->add_order_note(__('Plugin Template: Pedido processado com dados personalizados', 'plugin-template'));
    }
    
    /**
     * Pedido completado
     */
    public function order_completed($order_id) {
        $order = wc_get_order($order_id);
        
        // Lógica quando pedido é completado
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $enabled = get_post_meta($product->get_id(), '_plugin_template_enabled', true);
            
            if ($enabled === 'yes') {
                // Executar ação específica para produtos habilitados
                $this->handle_completed_product($product, $item, $order);
            }
        }
    }
    
    /**
     * Antes do botão adicionar ao carrinho
     */
    public function before_add_to_cart() {
        global $product;
        
        $enabled = get_post_meta($product->get_id(), '_plugin_template_enabled', true);
        
        if ($enabled === 'yes') {
            $custom_field = get_post_meta($product->get_id(), '_plugin_template_custom_field', true);
            
            if (!empty($custom_field)) {
                echo '<div class="plugin-template-product-info">';
                echo '<p><strong>' . __('Informação Especial:', 'plugin-template') . '</strong> ' . esc_html($custom_field) . '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Adicionar dados ao item do carrinho
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $enabled = get_post_meta($product_id, '_plugin_template_enabled', true);
        
        if ($enabled === 'yes') {
            $cart_item_data['plugin_template_data'] = array(
                'enabled' => true,
                'timestamp' => time()
            );
        }
        
        return $cart_item_data;
    }
    
    /**
     * Email antes da tabela do pedido
     */
    public function email_before_order_table($order, $sent_to_admin, $plain_text, $email) {
        $custom_data = get_post_meta($order->get_id(), '_plugin_template_data', true);
        
        if (!empty($custom_data)) {
            if ($plain_text) {
                echo __('Informações Personalizadas:', 'plugin-template') . "\n";
                echo __('Processado em:', 'plugin-template') . ' ' . $custom_data['processed_at'] . "\n\n";
            } else {
                echo '<h3>' . __('Informações Personalizadas', 'plugin-template') . '</h3>';
                echo '<p><strong>' . __('Processado em:', 'plugin-template') . '</strong> ' . $custom_data['processed_at'] . '</p>';
            }
        }
    }
    
    /**
     * Shortcode de produtos WooCommerce
     */
    public function products_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 4,
            'category' => '',
            'enabled_only' => 'no'
        ), $atts, 'plugin_template_wc_products');
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => intval($atts['limit']),
            'post_status' => 'publish'
        );
        
        if (!empty($atts['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $atts['category']
                )
            );
        }
        
        if ($atts['enabled_only'] === 'yes') {
            $args['meta_query'] = array(
                array(
                    'key' => '_plugin_template_enabled',
                    'value' => 'yes',
                    'compare' => '='
                )
            );
        }
        
        $products = new WP_Query($args);
        
        if (!$products->have_posts()) {
            return '<p>' . __('Nenhum produto encontrado.', 'plugin-template') . '</p>';
        }
        
        ob_start();
        echo '<div class="plugin-template-products">';
        
        while ($products->have_posts()) {
            $products->the_post();
            global $product;
            
            echo '<div class="product-item">';
            echo '<h4><a href="' . get_permalink() . '">' . get_the_title() . '</a></h4>';
            echo '<div class="price">' . $product->get_price_html() . '</div>';
            echo '<div class="excerpt">' . get_the_excerpt() . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        wp_reset_postdata();
        
        return ob_get_clean();
    }
    
    /**
     * Adicionar item ao menu da conta
     */
    public function add_account_menu_item($items) {
        $items['plugin-template'] = __('Plugin Template', 'plugin-template');
        return $items;
    }
    
    /**
     * Adicionar endpoint da conta
     */
    public function add_account_endpoint() {
        add_rewrite_endpoint('plugin-template', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Conteúdo do endpoint da conta
     */
    public function account_endpoint_content() {
        echo '<h3>' . __('Plugin Template', 'plugin-template') . '</h3>';
        echo '<p>' . __('Aqui você pode gerenciar suas configurações do Plugin Template.', 'plugin-template') . '</p>';
        
        // Adicionar conteúdo personalizado da conta
        $user_id = get_current_user_id();
        $user_data = get_user_meta($user_id, 'plugin_template_user_data', true);
        
        if (!empty($user_data)) {
            echo '<h4>' . __('Seus Dados', 'plugin-template') . '</h4>';
            echo '<pre>' . print_r($user_data, true) . '</pre>';
        }
    }
    
    /**
     * Manipular produto completado
     */
    private function handle_completed_product($product, $item, $order) {
        // Lógica específica para produtos habilitados quando pedido é completado
        $custom_field = get_post_meta($product->get_id(), '_plugin_template_custom_field', true);
        
        if (!empty($custom_field)) {
            // Executar ação baseada no campo personalizado
            do_action('plugin_template_product_completed', $product, $item, $order, $custom_field);
        }
    }
}

