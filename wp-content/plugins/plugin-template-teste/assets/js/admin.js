/* Plugin Template - Admin JavaScript */

(function($) {
    'use strict';
    
    // Objeto principal do plugin
    var PluginTemplateAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        bindEvents: function() {
            // Eventos gerais
            $(document).on('click', '.plugin-template-toggle', this.handleToggle);
            $(document).on('submit', '.plugin-template-ajax-form', this.handleAjaxForm);
            $(document).on('click', '.plugin-template-confirm', this.handleConfirm);
            
            // Eventos específicos
            $('#plugin-template-test-connection').on('click', this.testConnection);
            $('.plugin-template-color-picker').wpColorPicker();
        },
        
        initComponents: function() {
            // Inicializar tooltips
            $('.plugin-template-tooltip').tooltip();
            
            // Inicializar tabs
            this.initTabs();
            
            // Inicializar sortables
            this.initSortables();
        },
        
        initTabs: function() {
            $('.plugin-template-tabs').each(function() {
                var $tabs = $(this);
                var $tabButtons = $tabs.find('.tab-button');
                var $tabContents = $tabs.find('.tab-content');
                
                $tabButtons.on('click', function(e) {
                    e.preventDefault();
                    
                    var target = $(this).data('tab');
                    
                    $tabButtons.removeClass('active');
                    $(this).addClass('active');
                    
                    $tabContents.removeClass('active');
                    $('#' + target).addClass('active');
                });
            });
        },
        
        initSortables: function() {
            $('.plugin-template-sortable').sortable({
                handle: '.sort-handle',
                update: function(event, ui) {
                    var order = $(this).sortable('toArray', {attribute: 'data-id'});
                    PluginTemplateAdmin.saveOrder(order);
                }
            });
        },
        
        handleToggle: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var target = $button.data('target');
            var $target = $(target);
            
            if ($target.is(':visible')) {
                $target.slideUp();
                $button.text($button.data('show-text') || 'Mostrar');
            } else {
                $target.slideDown();
                $button.text($button.data('hide-text') || 'Ocultar');
            }
        },
        
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"]');
            var originalText = $submitButton.text();
            
            // Mostrar loading
            $submitButton.text('Processando...').prop('disabled', true);
            $form.addClass('plugin-template-loading');
            
            $.ajax({
                url: pluginTemplate.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=plugin_template_admin_action&nonce=' + pluginTemplate.nonce,
                success: function(response) {
                    if (response.success) {
                        PluginTemplateAdmin.showNotice('success', response.data.message || 'Operação realizada com sucesso!');
                        
                        // Callback personalizado se existir
                        if (typeof response.data.callback === 'function') {
                            response.data.callback(response);
                        }
                    } else {
                        PluginTemplateAdmin.showNotice('error', response.data.message || 'Erro ao processar solicitação.');
                    }
                },
                error: function() {
                    PluginTemplateAdmin.showNotice('error', 'Erro de conexão. Tente novamente.');
                },
                complete: function() {
                    $submitButton.text(originalText).prop('disabled', false);
                    $form.removeClass('plugin-template-loading');
                }
            });
        },
        
        handleConfirm: function(e) {
            var message = $(this).data('confirm') || pluginTemplate.strings.confirm;
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },
        
        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text('Testando...').prop('disabled', true);
            
            $.ajax({
                url: pluginTemplate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'plugin_template_admin_action',
                    action_type: 'test_connection',
                    nonce: pluginTemplate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        PluginTemplateAdmin.showNotice('success', 'Conexão testada com sucesso!');
                    } else {
                        PluginTemplateAdmin.showNotice('error', 'Falha na conexão: ' + response.data.message);
                    }
                },
                error: function() {
                    PluginTemplateAdmin.showNotice('error', 'Erro ao testar conexão.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        saveOrder: function(order) {
            $.ajax({
                url: pluginTemplate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'plugin_template_admin_action',
                    action_type: 'save_order',
                    order: order,
                    nonce: pluginTemplate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        PluginTemplateAdmin.showNotice('success', 'Ordem salva com sucesso!', 2000);
                    }
                }
            });
        },
        
        showNotice: function(type, message, duration) {
            duration = duration || 5000;
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            // Auto-remover após duração especificada
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, duration);
        },
        
        // Utilitários
        utils: {
            formatDate: function(date) {
                return new Date(date).toLocaleDateString('pt-BR');
            },
            
            formatCurrency: function(value) {
                return new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                }).format(value);
            },
            
            debounce: function(func, wait, immediate) {
                var timeout;
                return function() {
                    var context = this, args = arguments;
                    var later = function() {
                        timeout = null;
                        if (!immediate) func.apply(context, args);
                    };
                    var callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func.apply(context, args);
                };
            }
        }
    };
    
    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        PluginTemplateAdmin.init();
    });
    
    // Expor objeto globalmente para uso em outros scripts
    window.PluginTemplateAdmin = PluginTemplateAdmin;
    
})(jQuery);

