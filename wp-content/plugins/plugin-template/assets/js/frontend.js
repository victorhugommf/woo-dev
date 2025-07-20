/* Plugin Template - Frontend JavaScript */

(function($) {
    'use strict';
    
    // Objeto principal do plugin frontend
    var PluginTemplateFrontend = {
        
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        bindEvents: function() {
            // Formulários AJAX
            $(document).on('submit', '.plugin-template-form', this.handleFormSubmit);
            
            // Botões de ação
            $(document).on('click', '.plugin-template-action', this.handleAction);
            
            // Filtros e pesquisa
            $(document).on('input', '.plugin-template-search', this.utils.debounce(this.handleSearch, 300));
            $(document).on('change', '.plugin-template-filter', this.handleFilter);
            
            // Lazy loading
            $(window).on('scroll', this.utils.throttle(this.handleScroll, 100));
        },
        
        initComponents: function() {
            // Inicializar componentes interativos
            this.initTooltips();
            this.initModals();
            this.initCarousels();
            this.initLazyLoad();
        },
        
        initTooltips: function() {
            $('.plugin-template-tooltip').each(function() {
                var $element = $(this);
                var title = $element.attr('title') || $element.data('tooltip');
                
                if (title) {
                    $element.attr('title', '').data('original-title', title);
                    
                    $element.on('mouseenter', function() {
                        PluginTemplateFrontend.showTooltip($(this), title);
                    }).on('mouseleave', function() {
                        PluginTemplateFrontend.hideTooltip();
                    });
                }
            });
        },
        
        initModals: function() {
            $(document).on('click', '[data-modal]', function(e) {
                e.preventDefault();
                var modalId = $(this).data('modal');
                PluginTemplateFrontend.openModal(modalId);
            });
            
            $(document).on('click', '.plugin-template-modal-close, .plugin-template-modal-overlay', function() {
                PluginTemplateFrontend.closeModal();
            });
            
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // ESC key
                    PluginTemplateFrontend.closeModal();
                }
            });
        },
        
        initCarousels: function() {
            $('.plugin-template-carousel').each(function() {
                var $carousel = $(this);
                var autoplay = $carousel.data('autoplay') || false;
                var interval = $carousel.data('interval') || 5000;
                
                if (autoplay) {
                    setInterval(function() {
                        PluginTemplateFrontend.nextSlide($carousel);
                    }, interval);
                }
            });
        },
        
        initLazyLoad: function() {
            $('.plugin-template-lazy').each(function() {
                var $img = $(this);
                if (PluginTemplateFrontend.isInViewport($img[0])) {
                    PluginTemplateFrontend.loadImage($img);
                }
            });
        },
        
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"]');
            var $messages = $form.find('.form-messages');
            var originalText = $submitButton.text();
            
            // Validar formulário
            if (!PluginTemplateFrontend.validateForm($form)) {
                return false;
            }
            
            // Mostrar loading
            $submitButton.text('Enviando...').prop('disabled', true);
            $form.addClass('plugin-template-loading');
            $messages.find('.success-message, .error-message').hide();
            
            // Preparar dados
            var formData = $form.serialize();
            var action = $form.data('action') || 'plugin_template_action';
            
            $.ajax({
                url: pluginTemplate.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData + '&action=' + action,
                success: function(response) {
                    if (response.success) {
                        $messages.find('.success-message').show();
                        $messages.find('.error-message').hide();
                        
                        // Limpar formulário se especificado
                        if ($form.data('clear-on-success') !== false) {
                            $form[0].reset();
                        }
                        
                        // Callback personalizado
                        var callback = $form.data('success-callback');
                        if (callback && typeof window[callback] === 'function') {
                            window[callback](response, $form);
                        }
                    } else {
                        $messages.find('.error-message').text(response.data.message || 'Erro ao enviar formulário').show();
                        $messages.find('.success-message').hide();
                    }
                },
                error: function() {
                    $messages.find('.error-message').text('Erro de conexão. Tente novamente.').show();
                    $messages.find('.success-message').hide();
                },
                complete: function() {
                    $submitButton.text(originalText).prop('disabled', false);
                    $form.removeClass('plugin-template-loading');
                }
            });
        },
        
        handleAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            var confirm_message = $button.data('confirm');
            
            if (confirm_message && !confirm(confirm_message)) {
                return false;
            }
            
            var originalText = $button.text();
            $button.text('Processando...').prop('disabled', true);
            
            $.ajax({
                url: pluginTemplate.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'plugin_template_action',
                    action_type: action,
                    data: $button.data(),
                    nonce: pluginTemplate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Callback personalizado
                        var callback = $button.data('success-callback');
                        if (callback && typeof window[callback] === 'function') {
                            window[callback](response, $button);
                        } else {
                            PluginTemplateFrontend.showNotice('success', response.data.message || 'Ação realizada com sucesso!');
                        }
                    } else {
                        PluginTemplateFrontend.showNotice('error', response.data.message || 'Erro ao processar ação.');
                    }
                },
                error: function() {
                    PluginTemplateFrontend.showNotice('error', 'Erro de conexão. Tente novamente.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        handleSearch: function() {
            var query = $(this).val();
            var target = $(this).data('target');
            
            PluginTemplateFrontend.filterItems(target, query, 'search');
        },
        
        handleFilter: function() {
            var filter = $(this).val();
            var target = $(this).data('target');
            
            PluginTemplateFrontend.filterItems(target, filter, 'filter');
        },
        
        handleScroll: function() {
            // Lazy loading de imagens
            $('.plugin-template-lazy').each(function() {
                var $img = $(this);
                if (PluginTemplateFrontend.isInViewport($img[0])) {
                    PluginTemplateFrontend.loadImage($img);
                }
            });
            
            // Infinite scroll se habilitado
            var $container = $('.plugin-template-infinite-scroll');
            if ($container.length && PluginTemplateFrontend.isInViewport($container[0])) {
                PluginTemplateFrontend.loadMoreItems($container);
            }
        },
        
        validateForm: function($form) {
            var isValid = true;
            
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    PluginTemplateFrontend.showFieldError($field, 'Este campo é obrigatório');
                    isValid = false;
                } else {
                    PluginTemplateFrontend.clearFieldError($field);
                }
                
                // Validação de email
                if ($field.attr('type') === 'email' && value) {
                    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        PluginTemplateFrontend.showFieldError($field, 'Email inválido');
                        isValid = false;
                    }
                }
            });
            
            return isValid;
        },
        
        showFieldError: function($field, message) {
            $field.addClass('error');
            var $error = $field.siblings('.field-error');
            if (!$error.length) {
                $error = $('<span class="field-error"></span>');
                $field.after($error);
            }
            $error.text(message);
        },
        
        clearFieldError: function($field) {
            $field.removeClass('error');
            $field.siblings('.field-error').remove();
        },
        
        filterItems: function(target, query, type) {
            var $container = $(target);
            var $items = $container.find('.filterable-item');
            
            $items.each(function() {
                var $item = $(this);
                var text = $item.text().toLowerCase();
                var show = false;
                
                if (type === 'search') {
                    show = text.indexOf(query.toLowerCase()) !== -1;
                } else if (type === 'filter') {
                    var category = $item.data('category');
                    show = !query || category === query;
                }
                
                $item.toggle(show);
            });
        },
        
        showTooltip: function($element, text) {
            var $tooltip = $('<div class="plugin-template-tooltip-popup">' + text + '</div>');
            $('body').append($tooltip);
            
            var offset = $element.offset();
            $tooltip.css({
                top: offset.top - $tooltip.outerHeight() - 10,
                left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
            });
        },
        
        hideTooltip: function() {
            $('.plugin-template-tooltip-popup').remove();
        },
        
        openModal: function(modalId) {
            var $modal = $('#' + modalId);
            if ($modal.length) {
                $modal.addClass('active');
                $('body').addClass('modal-open');
            }
        },
        
        closeModal: function() {
            $('.plugin-template-modal').removeClass('active');
            $('body').removeClass('modal-open');
        },
        
        nextSlide: function($carousel) {
            var $slides = $carousel.find('.slide');
            var $current = $slides.filter('.active');
            var $next = $current.next('.slide');
            
            if (!$next.length) {
                $next = $slides.first();
            }
            
            $current.removeClass('active');
            $next.addClass('active');
        },
        
        isInViewport: function(element) {
            var rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },
        
        loadImage: function($img) {
            var src = $img.data('src');
            if (src) {
                $img.attr('src', src).removeClass('plugin-template-lazy');
            }
        },
        
        loadMoreItems: function($container) {
            if ($container.data('loading')) return;
            
            $container.data('loading', true);
            
            var page = $container.data('page') || 1;
            var action = $container.data('action') || 'load_more_items';
            
            $.ajax({
                url: pluginTemplate.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: action,
                    page: page + 1,
                    nonce: pluginTemplate.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $container.append(response.data.html);
                        $container.data('page', page + 1);
                        
                        if (!response.data.has_more) {
                            $container.removeClass('plugin-template-infinite-scroll');
                        }
                    }
                },
                complete: function() {
                    $container.data('loading', false);
                }
            });
        },
        
        showNotice: function(type, message, duration) {
            duration = duration || 5000;
            
            var $notice = $('<div class="plugin-template-notice plugin-template-notice-' + type + '">' + message + '</div>');
            
            $('body').append($notice);
            
            setTimeout(function() {
                $notice.addClass('show');
            }, 100);
            
            setTimeout(function() {
                $notice.removeClass('show');
                setTimeout(function() {
                    $notice.remove();
                }, 300);
            }, duration);
        },
        
        // Utilitários
        utils: {
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
            },
            
            throttle: function(func, limit) {
                var inThrottle;
                return function() {
                    var args = arguments;
                    var context = this;
                    if (!inThrottle) {
                        func.apply(context, args);
                        inThrottle = true;
                        setTimeout(function() {
                            inThrottle = false;
                        }, limit);
                    }
                };
            }
        }
    };
    
    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        PluginTemplateFrontend.init();
    });
    
    // Expor objeto globalmente
    window.PluginTemplateFrontend = PluginTemplateFrontend;
    
})(jQuery);

