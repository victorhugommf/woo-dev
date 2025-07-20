# Guia de Desenvolvimento de Plugins WordPress/WooCommerce

Este guia detalha como desenvolver plugins WordPress e WooCommerce usando o ambiente Docker fornecido.

## 📋 Índice

- [Introdução](#introdução)
- [Template de Plugin](#template-de-plugin)
- [Estrutura Recomendada](#estrutura-recomendada)
- [Desenvolvimento Passo a Passo](#desenvolvimento-passo-a-passo)
- [Integração WooCommerce](#integração-woocommerce)
- [Debugging e Testes](#debugging-e-testes)
- [Melhores Práticas](#melhores-práticas)
- [Exemplos Práticos](#exemplos-práticos)

## 🚀 Introdução

O ambiente inclui um template completo de plugin que serve como base para desenvolvimento. Este template inclui:

- Estrutura MVC organizada
- Integração completa com WooCommerce
- Sistema de configurações
- AJAX para frontend e backend
- Shortcodes personalizados
- Internacionalização
- Assets organizados

## 🎯 Template de Plugin

### Estrutura do Template

```
wp-content/plugins/plugin-template/
├── plugin-template.php           # Arquivo principal
├── includes/                     # Classes PHP
│   ├── class-settings.php       # Configurações
│   ├── class-ajax.php           # Handlers AJAX
│   ├── class-shortcodes.php     # Shortcodes
│   └── woocommerce/             # Integração WooCommerce
│       └── class-wc-integration.php
├── templates/                    # Templates
│   ├── admin/                   # Templates admin
│   │   ├── main-page.php
│   │   └── settings-page.php
│   └── frontend/                # Templates frontend
├── assets/                      # Assets estáticos
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
└── languages/                   # Arquivos de tradução
```

### Características do Template

#### 1. Classe Principal Singleton
```php
class PluginTemplate {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

#### 2. Sistema de Hooks Organizado
```php
private function init_hooks() {
    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    add_action('plugins_loaded', array($this, 'init'));
    add_action('admin_menu', array($this, 'admin_menu'));
}
```

#### 3. Verificação de Dependências
```php
private function check_dependencies() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        return false;
    }
    if (!class_exists('WooCommerce')) {
        return false;
    }
    return true;
}
```

## 🏗️ Estrutura Recomendada

### Para Plugins Simples
```
meu-plugin/
├── meu-plugin.php              # Arquivo principal
├── includes/
│   ├── class-main.php         # Classe principal
│   └── functions.php          # Funções auxiliares
├── assets/
│   ├── css/style.css
│   └── js/script.js
└── languages/
```

### Para Plugins Complexos
```
meu-plugin/
├── meu-plugin.php              # Arquivo principal
├── includes/                   # Classes PHP
│   ├── class-main.php         # Classe principal
│   ├── class-admin.php        # Funcionalidades admin
│   ├── class-frontend.php     # Funcionalidades frontend
│   ├── class-ajax.php         # Handlers AJAX
│   ├── class-settings.php     # Configurações
│   ├── class-database.php     # Operações de banco
│   └── integrations/          # Integrações
│       ├── class-woocommerce.php
│       └── class-elementor.php
├── templates/                  # Templates
│   ├── admin/
│   └── frontend/
├── assets/                     # Assets
│   ├── css/
│   ├── js/
│   └── images/
├── languages/                  # Traduções
├── tests/                      # Testes unitários
└── docs/                       # Documentação
```

## 🛠️ Desenvolvimento Passo a Passo

### 1. Criar Novo Plugin

```bash
# Copiar template
cp -r wp-content/plugins/plugin-template wp-content/plugins/meu-plugin

# Entrar no diretório
cd wp-content/plugins/meu-plugin
```

### 2. Configurar Informações Básicas

Editar `meu-plugin.php`:

```php
<?php
/**
 * Plugin Name: Meu Plugin Incrível
 * Plugin URI: https://meusite.com/meu-plugin
 * Description: Descrição detalhada do que o plugin faz
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://meusite.com
 * License: GPL v2 or later
 * Text Domain: meu-plugin
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

// Definir constantes
define('MEU_PLUGIN_VERSION', '1.0.0');
define('MEU_PLUGIN_PLUGIN_FILE', __FILE__);
define('MEU_PLUGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MEU_PLUGIN_PLUGIN_URL', plugin_dir_url(__FILE__));
```

### 3. Criar Classe Principal

```php
class MeuPlugin {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function activate() {
        // Código de ativação
        $this->create_tables();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Código de desativação
        flush_rewrite_rules();
    }
    
    public function init() {
        // Verificar dependências
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Carregar classes
        $this->includes();
        $this->init_classes();
        
        // Carregar textdomain
        load_plugin_textdomain('meu-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Inicializar plugin
MeuPlugin::get_instance();
```

### 4. Implementar Funcionalidades

#### Sistema de Configurações
```php
class MeuPlugin_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    public function admin_menu() {
        add_options_page(
            'Meu Plugin',
            'Meu Plugin',
            'manage_options',
            'meu-plugin',
            array($this, 'settings_page')
        );
    }
    
    public function settings_page() {
        include MEU_PLUGIN_PLUGIN_DIR . 'templates/admin/settings.php';
    }
}
```

#### Handlers AJAX
```php
class MeuPlugin_Ajax {
    public function __construct() {
        add_action('wp_ajax_meu_plugin_action', array($this, 'handle_action'));
        add_action('wp_ajax_nopriv_meu_plugin_action', array($this, 'handle_action'));
    }
    
    public function handle_action() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'meu_plugin_nonce')) {
            wp_die('Erro de segurança');
        }
        
        // Processar ação
        $result = $this->process_action($_POST['data']);
        
        wp_send_json($result);
    }
}
```

### 5. Adicionar Assets

#### CSS (assets/css/frontend.css)
```css
.meu-plugin-container {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
}

.meu-plugin-button {
    background: #0073aa;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}
```

#### JavaScript (assets/js/frontend.js)
```javascript
(function($) {
    'use strict';
    
    var MeuPlugin = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $(document).on('click', '.meu-plugin-button', this.handleClick);
        },
        
        handleClick: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: meuPlugin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'meu_plugin_action',
                    nonce: meuPlugin.nonce,
                    data: 'exemplo'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Sucesso!');
                    }
                }
            });
        }
    };
    
    $(document).ready(function() {
        MeuPlugin.init();
    });
    
})(jQuery);
```

### 6. Carregar Assets

```php
public function enqueue_scripts() {
    wp_enqueue_style(
        'meu-plugin-frontend',
        MEU_PLUGIN_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        MEU_PLUGIN_VERSION
    );
    
    wp_enqueue_script(
        'meu-plugin-frontend',
        MEU_PLUGIN_PLUGIN_URL . 'assets/js/frontend.js',
        array('jquery'),
        MEU_PLUGIN_VERSION,
        true
    );
    
    wp_localize_script('meu-plugin-frontend', 'meuPlugin', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('meu_plugin_nonce')
    ));
}
```

## 🛒 Integração WooCommerce

### Hooks Essenciais do WooCommerce

#### Produtos
```php
// Adicionar campos personalizados ao produto
add_action('woocommerce_product_options_general_product_data', 'adicionar_campos_produto');

// Salvar campos personalizados
add_action('woocommerce_process_product_meta', 'salvar_campos_produto');

// Exibir informações no frontend
add_action('woocommerce_single_product_summary', 'exibir_info_produto', 25);
```

#### Carrinho
```php
// Modificar dados do item no carrinho
add_filter('woocommerce_add_cart_item_data', 'adicionar_dados_carrinho', 10, 3);

// Exibir dados no carrinho
add_filter('woocommerce_get_item_data', 'exibir_dados_carrinho', 10, 2);
```

#### Checkout
```php
// Adicionar campos no checkout
add_action('woocommerce_checkout_fields', 'adicionar_campos_checkout');

// Processar pedido
add_action('woocommerce_checkout_order_processed', 'processar_pedido', 10, 3);
```

#### Emails
```php
// Adicionar conteúdo aos emails
add_action('woocommerce_email_before_order_table', 'adicionar_conteudo_email', 10, 4);
```

### Exemplo Prático: Campo Personalizado no Produto

```php
// Adicionar campo
function adicionar_campo_personalizado() {
    woocommerce_wp_text_input(array(
        'id' => '_campo_personalizado',
        'label' => 'Campo Personalizado',
        'description' => 'Digite um valor personalizado',
        'type' => 'text'
    ));
}
add_action('woocommerce_product_options_general_product_data', 'adicionar_campo_personalizado');

// Salvar campo
function salvar_campo_personalizado($post_id) {
    if (isset($_POST['_campo_personalizado'])) {
        update_post_meta($post_id, '_campo_personalizado', sanitize_text_field($_POST['_campo_personalizado']));
    }
}
add_action('woocommerce_process_product_meta', 'salvar_campo_personalizado');

// Exibir no frontend
function exibir_campo_personalizado() {
    global $product;
    $valor = get_post_meta($product->get_id(), '_campo_personalizado', true);
    
    if (!empty($valor)) {
        echo '<div class="campo-personalizado">';
        echo '<strong>Campo Personalizado:</strong> ' . esc_html($valor);
        echo '</div>';
    }
}
add_action('woocommerce_single_product_summary', 'exibir_campo_personalizado', 25);
```

## 🐛 Debugging e Testes

### Ferramentas de Debug Disponíveis

1. **Query Monitor**: Análise de queries e performance
2. **Debug Bar**: Informações gerais de debug
3. **WP_DEBUG**: Logs de erro do WordPress
4. **Error Logs**: Logs do PHP e Apache

### Comandos de Debug

```bash
# Ver logs em tempo real
make logs

# Ver apenas logs do WordPress
make watch-wp-logs

# Executar WP-CLI para debug
make wp eval "var_dump(get_option('minha_opcao'));"

# Verificar plugins ativos
make wp plugin list --status=active
```

### Debug no Código

```php
// Log personalizado
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Meu Plugin: ' . print_r($dados, true));
}

// Debug condicional
if (current_user_can('manage_options')) {
    echo '<pre>' . print_r($debug_info, true) . '</pre>';
}

// Usar wp_die para debug
wp_die('<pre>' . print_r($dados, true) . '</pre>');
```

### Testes Manuais

1. **Ativação/Desativação**: Testar hooks de ativação
2. **Compatibilidade**: Testar com outros plugins
3. **Performance**: Verificar impacto na velocidade
4. **Responsividade**: Testar em diferentes dispositivos
5. **Browsers**: Testar em diferentes navegadores

## ✅ Melhores Práticas

### Segurança

```php
// Sempre verificar nonces
if (!wp_verify_nonce($_POST['nonce'], 'minha_acao')) {
    wp_die('Erro de segurança');
}

// Verificar permissões
if (!current_user_can('manage_options')) {
    wp_die('Permissão negada');
}

// Sanitizar dados
$valor = sanitize_text_field($_POST['campo']);
$email = sanitize_email($_POST['email']);
$url = esc_url_raw($_POST['url']);

// Escapar output
echo esc_html($texto);
echo esc_attr($atributo);
echo esc_url($url);
```

### Performance

```php
// Carregar assets apenas quando necessário
if (is_admin()) {
    add_action('admin_enqueue_scripts', 'carregar_assets_admin');
}

// Usar transients para cache
$dados = get_transient('meu_plugin_dados');
if (false === $dados) {
    $dados = buscar_dados_pesados();
    set_transient('meu_plugin_dados', $dados, HOUR_IN_SECONDS);
}

// Otimizar queries
$posts = get_posts(array(
    'posts_per_page' => 10,
    'meta_query' => array(
        array(
            'key' => 'minha_meta',
            'value' => 'valor',
            'compare' => '='
        )
    )
));
```

### Organização

```php
// Usar namespaces (PHP 5.3+)
namespace MeuPlugin\Admin;

// Prefixar funções
function meu_plugin_funcao() {
    // código
}

// Usar constantes para configurações
define('MEU_PLUGIN_VERSION', '1.0.0');
define('MEU_PLUGIN_DB_VERSION', '1.0');

// Organizar em classes
class MeuPlugin_Admin {
    // código da classe
}
```

## 💡 Exemplos Práticos

### 1. Plugin de Avaliações Personalizadas

```php
// Adicionar campo de avaliação personalizada
function adicionar_avaliacao_personalizada() {
    global $post;
    
    $avaliacao = get_post_meta($post->ID, '_avaliacao_personalizada', true);
    
    echo '<div class="avaliacao-personalizada">';
    echo '<label>Avaliação Personalizada (1-10):</label>';
    echo '<input type="number" name="_avaliacao_personalizada" value="' . esc_attr($avaliacao) . '" min="1" max="10">';
    echo '</div>';
}
add_action('woocommerce_product_options_general_product_data', 'adicionar_avaliacao_personalizada');

// Salvar avaliação
function salvar_avaliacao_personalizada($post_id) {
    if (isset($_POST['_avaliacao_personalizada'])) {
        update_post_meta($post_id, '_avaliacao_personalizada', intval($_POST['_avaliacao_personalizada']));
    }
}
add_action('woocommerce_process_product_meta', 'salvar_avaliacao_personalizada');

// Exibir avaliação no frontend
function exibir_avaliacao_personalizada() {
    global $product;
    $avaliacao = get_post_meta($product->get_id(), '_avaliacao_personalizada', true);
    
    if ($avaliacao) {
        echo '<div class="avaliacao-display">';
        echo '<strong>Nossa Avaliação:</strong> ';
        echo str_repeat('⭐', $avaliacao) . ' (' . $avaliacao . '/10)';
        echo '</div>';
    }
}
add_action('woocommerce_single_product_summary', 'exibir_avaliacao_personalizada', 15);
```

### 2. Plugin de Desconto por Quantidade

```php
// Aplicar desconto baseado na quantidade
function aplicar_desconto_quantidade($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $quantity = $cart_item['quantity'];
        $product = $cart_item['data'];
        
        if ($quantity >= 5) {
            $desconto = 0.1; // 10% de desconto
            $preco_original = $product->get_regular_price();
            $novo_preco = $preco_original * (1 - $desconto);
            $product->set_price($novo_preco);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'aplicar_desconto_quantidade');
```

### 3. Plugin de Campos Extras no Checkout

```php
// Adicionar campo extra no checkout
function adicionar_campo_empresa($fields) {
    $fields['billing']['billing_empresa'] = array(
        'label' => 'Nome da Empresa',
        'placeholder' => 'Digite o nome da empresa',
        'required' => false,
        'class' => array('form-row-wide'),
        'clear' => true
    );
    
    return $fields;
}
add_filter('woocommerce_checkout_fields', 'adicionar_campo_empresa');

// Salvar campo extra
function salvar_campo_empresa($order_id) {
    if (!empty($_POST['billing_empresa'])) {
        update_post_meta($order_id, '_billing_empresa', sanitize_text_field($_POST['billing_empresa']));
    }
}
add_action('woocommerce_checkout_update_order_meta', 'salvar_campo_empresa');

// Exibir no admin do pedido
function exibir_campo_empresa_admin($order) {
    $empresa = get_post_meta($order->get_id(), '_billing_empresa', true);
    if ($empresa) {
        echo '<p><strong>Empresa:</strong> ' . esc_html($empresa) . '</p>';
    }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'exibir_campo_empresa_admin');
```

---

Este guia fornece uma base sólida para desenvolvimento de plugins WordPress/WooCommerce. Use o template fornecido como ponto de partida e adapte conforme suas necessidades específicas.

