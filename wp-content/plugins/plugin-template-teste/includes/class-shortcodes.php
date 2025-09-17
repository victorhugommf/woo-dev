<?php
/**
 * Classe de shortcodes do plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class PluginTemplate_Shortcodes {
    
    /**
     * Construtor
     */
    public function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
    }
    
    /**
     * Registrar shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('plugin_template_example', array($this, 'example_shortcode'));
        add_shortcode('plugin_template_form', array($this, 'form_shortcode'));
        add_shortcode('plugin_template_data', array($this, 'data_shortcode'));
    }
    
    /**
     * Shortcode de exemplo
     */
    public function example_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'title' => __('Título Padrão', 'plugin-template'),
            'color' => '#333',
            'size' => 'medium'
        ), $atts, 'plugin_template_example');
        
        $classes = array('plugin-template-example', 'size-' . $atts['size']);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="color: <?php echo esc_attr($atts['color']); ?>">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <?php if (!empty($content)): ?>
                <div class="content">
                    <?php echo do_shortcode($content); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode de formulário
     */
    public function form_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'action' => 'submit_form',
            'button_text' => __('Enviar', 'plugin-template'),
            'success_message' => __('Formulário enviado com sucesso!', 'plugin-template')
        ), $atts, 'plugin_template_form');
        
        $form_id = 'plugin-template-form-' . uniqid();
        
        ob_start();
        ?>
        <form id="<?php echo esc_attr($form_id); ?>" class="plugin-template-form" data-action="<?php echo esc_attr($atts['action']); ?>">
            <div class="form-group">
                <label for="<?php echo esc_attr($form_id); ?>_name"><?php _e('Nome:', 'plugin-template'); ?></label>
                <input type="text" id="<?php echo esc_attr($form_id); ?>_name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="<?php echo esc_attr($form_id); ?>_email"><?php _e('Email:', 'plugin-template'); ?></label>
                <input type="email" id="<?php echo esc_attr($form_id); ?>_email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="<?php echo esc_attr($form_id); ?>_message"><?php _e('Mensagem:', 'plugin-template'); ?></label>
                <textarea id="<?php echo esc_attr($form_id); ?>_message" name="message" rows="4"></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit"><?php echo esc_html($atts['button_text']); ?></button>
            </div>
            
            <div class="form-messages" style="display: none;">
                <div class="success-message"><?php echo esc_html($atts['success_message']); ?></div>
                <div class="error-message"><?php _e('Erro ao enviar formulário. Tente novamente.', 'plugin-template'); ?></div>
            </div>
            
            <?php wp_nonce_field('plugin_template_nonce', 'nonce'); ?>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_js($form_id); ?>').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var formData = form.serialize();
                
                $.ajax({
                    url: pluginTemplate.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'plugin_template_action',
                        action_type: form.data('action'),
                        form_data: formData,
                        nonce: pluginTemplate.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            form.find('.success-message').show();
                            form.find('.error-message').hide();
                            form[0].reset();
                        } else {
                            form.find('.error-message').show();
                            form.find('.success-message').hide();
                        }
                    },
                    error: function() {
                        form.find('.error-message').show();
                        form.find('.success-message').hide();
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode de dados
     */
    public function data_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'type' => 'recent',
            'limit' => 5,
            'template' => 'list'
        ), $atts, 'plugin_template_data');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'plugin_template_data';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
            intval($atts['limit'])
        );
        
        $results = $wpdb->get_results($sql);
        
        if (empty($results)) {
            return '<p>' . __('Nenhum dado encontrado.', 'plugin-template') . '</p>';
        }
        
        ob_start();
        
        if ($atts['template'] === 'list') {
            echo '<ul class="plugin-template-data-list">';
            foreach ($results as $item) {
                echo '<li>';
                echo '<strong>' . esc_html($item->name) . '</strong><br>';
                echo '<span class="data">' . esc_html($item->data) . '</span><br>';
                echo '<small class="date">' . esc_html($item->created_at) . '</small>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="plugin-template-data-grid">';
            foreach ($results as $item) {
                echo '<div class="data-item">';
                echo '<h4>' . esc_html($item->name) . '</h4>';
                echo '<p>' . esc_html($item->data) . '</p>';
                echo '<small>' . esc_html($item->created_at) . '</small>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        return ob_get_clean();
    }
}

