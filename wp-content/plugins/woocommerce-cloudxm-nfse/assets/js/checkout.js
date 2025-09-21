/**
 * Checkout JavaScript for WooCommerce CloudXM NFS-e plugin
 * Modern PSR-4 compatible asset management
 *
 * @version 2.0.0
 */

(function($) {
    'use strict';

    /**
     * NFSe Checkout Integration
     */
    const NFSeCheckout = {

        /**
         * Initialize checkout functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeCheckoutEnhancements();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // WooCommerce checkout events
            $(document.body).on('checkout_error', this.handleCheckoutError);
            $(document.body).on('updated_checkout', this.handleCheckoutUpdate);
            $(document).on('change', 'input[name="billing_cpf"], input[name="billing_cnpj"]', this.handleDocumentChange);

            // NFSe specific events
            $(document).on('click', '.wc-nfse-toggle-more-info', this.handleToggleMoreInfo);
            $(document).on('click', '.wc-nfse-validate-document', this.handleDocumentValidation);

            // Form validation
            $(document.body).on('checkout_place_order', this.handlePlaceOrderValidation);
        },

        /**
         * Initialize checkout enhancements
         */
        initializeCheckoutEnhancements: function() {
            this.addDocumentFieldsValidation();
            this.addCheckoutInformationSection();
            this.enhanceBillingFields();
        },

        /**
         * Add document fields validation
         */
        addDocumentFieldsValidation: function() {
            // Add validation for CPF/CNPJ fields
            $('input[name="billing_cpf"]').on('blur', function() {
                const cpf = $(this).val();
                if (cpf && !NFSeCheckout.validateCPF(cpf)) {
                    NFSeCheckout.showFieldError($(this), 'CPF inválido');
                } else {
                    NFSeCheckout.clearFieldError($(this));
                }
            });

            $('input[name="billing_cnpj"]').on('blur', function() {
                const cnpj = $(this).val();
                if (cnpj && !NFSeCheckout.validateCNPJ(cnpj)) {
                    NFSeCheckout.showFieldError($(this), 'CNPJ inválido');
                } else {
                    NFSeCheckout.clearFieldError($(this));
                }
            });
        },

        /**
         * Add NFSe information section to checkout
         */
        addCheckoutInformationSection: function() {
            const informationSection = `
                <div class="wc-nfse-checkout-integration" style="display: none;">
                    <h4>Informações NFS-e</h4>
                    <p class="small text-muted">
                        Para emissão da Nota Fiscal de Serviços Eletrônica (NFS-e),
                        precisamos de algumas informações adicionais conforme legislação fiscal.
                    </p>
                    <div class="wc-nfse-info-toggle">
                        <a href="#" class="wc-nfse-toggle-more-info">
                            Ver mais informações sobre NFS-e
                        </a>
                    </div>
                    <div class="wc-nfse-more-info" style="display: none;">
                        <p>A NFS-e é obrigatória para prestação de serviços e será emitida automaticamente após a confirmação do pedido.</p>
                        <p>Certifique-se de que os dados de endereço e documento estão corretos.</p>
                    </div>
                </div>
            `;

            // Add after payment methods
            $('.woocommerce-checkout-payment').after(informationSection);
        },

        /**
         * Enhance billing fields
         */
        enhanceBillingFields: function() {
            // Add document type toggle
            this.addDocumentTypeToggle();

            // Add address validation for NFSe requirements
            this.addAddressValidation();
        },

        /**
         * Add document type toggle (CPF/CNPJ)
         */
        addDocumentTypeToggle: function() {
            const documentSection = $('.woocommerce-billing-fields');
            if (documentSection.length === 0) return;

            // Add after company field
            const toggleHtml = `
                <p class="form-row wc-nfse-document-toggle" id="document_type_field">
                    <label for="document_type">Tipo de Documento</label>
                    <select name="document_type" id="document_type" class="woocommerce-select">
                        <option value="cpf">Pessoa Física (CPF)</option>
                        <option value="cnpj">Pessoa Jurídica (CNPJ)</option>
                    </select>
                </p>
                <p class="form-row wc-nfse-cpf-field">
                    <label for="billing_cpf">CPF <span class="required">*</span></label>
                    <input type="text" class="input-text" name="billing_cpf" id="billing_cpf"
                           placeholder="000.000.000-00" maxlength="14">
                </p>
                <p class="form-row wc-nfse-cnpj-field" style="display: none;">
                    <label for="billing_cnpj">CNPJ <span class="required">*</span></label>
                    <input type="text" class="input-text" name="billing_cnpj" id="billing_cnpj"
                           placeholder="00.000.000/0000-00" maxlength="18">
                </p>
            `;

            $('#billing_company_field').after(toggleHtml);

            // Handle toggle
            $(document).on('change', '#document_type', function() {
                const selectedType = $(this).val();
                if (selectedType === 'cnpj') {
                    $('.wc-nfse-cpf-field').hide();
                    $('.wc-nfse-cnpj-field').show();
                    $('#billing_cpf').val('').removeClass('woocommerce-invalid');
                    $('#billing_company_field').show();
                } else {
                    $('.wc-nfse-cnpj-field').hide();
                    $('.wc-nfse-cpf-field').show();
                    $('#billing_cnpj').val('').removeClass('woocommerce-invalid');
                    $('#billing_company_field').hide();
                }
            });

            // Format document fields
            this.applyDocumentMask();
        },

        /**
         * Apply document masks
         */
        applyDocumentMask: function() {
            $('#billing_cpf').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                    $(this).val(value);
                }
            });

            $('#billing_cnpj').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length <= 14) {
                    value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                    $(this).val(value);
                }
            });
        },

        /**
         * Add address validation for NFSe requirements
         */
        addAddressValidation: function() {
            // Make address fields required for companies (PJ)
            $(document).on('change', '#document_type', function() {
                const isCompany = $(this).val() === 'cnpj';
                const requiredFields = [
                    'billing_address_1',
                    'billing_number',
                    'billing_neighborhood',
                    'billing_city',
                    'billing_state',
                    'billing_postcode'
                ];

                // For companies, billing address becomes required
                if (isCompany) {
                    requiredFields.forEach(function(field) {
                        $('#' + field).closest('.form-row').addClass('validate-required');
                        $('#' + field).closest('.form-row').find('label').append('<span class="required">*</span>');
                    });
                }
            });
        },

        /**
         * Handle document change
         */
        handleDocumentChange: function(e) {
            const $field = $(this);
            const value = $field.val();

            // Remove validation message
            $field.removeClass('woocommerce-invalid');

            // Format document
            if ($field.attr('name') === 'billing_cpf') {
                $field.val(NFSeCheckout.formatCPF(value));
            } else if ($field.attr('name') === 'billing_cnpj') {
                $field.val(NFSeCheckout.formatCNPJ(value));
            }
        },

        /**
         * Handle toggle more info
         */
        handleToggleMoreInfo: function(e) {
            e.preventDefault();
            $('.wc-nfse-more-info').slideToggle();
            $(this).text(function(i, text) {
                return text === 'Ver mais informações sobre NFS-e' ?
                    'Ocultar informações' :
                    'Ver mais informações sobre NFS-e';
            });
        },

        /**
         * Handle document validation
         */
        handleDocumentValidation: function(e) {
            e.preventDefault();
            // Implement AJAX document validation if needed
            console.log('Document validation requested');
        },

        /**
         * Handle place order validation
         */
        handlePlaceOrderValidation: function() {
            const documentType = $('#document_type').val();
            let isValid = true;

            if (documentType === 'cpf') {
                const cpf = $('input[name="billing_cpf"]').val();
                if (!cpf || !NFSeCheckout.validateCPF(cpf)) {
                    isValid = false;
                    $('input[name="billing_cpf"]').addClass('woocommerce-invalid');
                }
            } else if (documentType === 'cnpj') {
                const cnpj = $('input[name="billing_cnpj"]').val();
                if (!cnpj || !NFSeCheckout.validateCNPJ(cnpj)) {
                    isValid = false;
                    $('input[name="billing_cnpj"]').addClass('woocommerce-invalid');
                }

                // For companies, validate required address fields
                const requiredAddressFields = [
                    'billing_address_1',
                    'billing_city',
                    'billing_state',
                    'billing_postcode'
                ];

                requiredAddressFields.forEach(function(field) {
                    const $field = $('#' + field);
                    if (!$field.val()) {
                        isValid = false;
                        $field.addClass('woocommerce-invalid');
                    }
                });
            }

            return isValid;
        },

        /**
         * Handle checkout error
         */
        handleCheckoutError: function() {
            // Handle NFSe related checkout errors
            console.log('Checkout error occurred');
        },

        /**
         * Handle checkout update
         */
        handleCheckoutUpdate: function() {
            // Reinitialize NFSe enhancements after checkout update
            NFSeCheckout.initializeCheckoutEnhancements();
        },

        /**
         * Validate CPF
         */
        validateCPF: function(cpf) {
            cpf = cpf.replace(/\D/g, '');
            if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
                return false;
            }

            let sum = 0;
            let remainder;

            for (let i = 1; i <= 9; i++) {
                sum = sum + parseInt(cpf.substring(i - 1, i)) * (11 - i);
            }

            remainder = (sum * 10) % 11;
            if (remainder === 10 || remainder === 11) remainder = 0;
            if (remainder !== parseInt(cpf.substring(9, 10))) return false;

            sum = 0;
            for (let i = 1; i <= 10; i++) {
                sum = sum + parseInt(cpf.substring(i - 1, i)) * (12 - i);
            }

            remainder = (sum * 10) % 11;
            if (remainder === 10 || remainder === 11) remainder = 0;

            return remainder === parseInt(cpf.substring(10, 11));
        },

        /**
         * Validate CNPJ
         */
        validateCNPJ: function(cnpj) {
            cnpj = cnpj.replace(/\D/g, '');
            if (cnpj.length !== 14) return false;

            // Calculate first verification digit
            let size = cnpj.length - 2;
            let numbers = cnpj.substring(0, size);
            const digits = cnpj.substring(size);
            let sum = 0;
            let pos = size - 7;

            for (let i = size; i >= 1; i--) {
                sum += numbers.charAt(size - i) * pos--;
                if (pos < 2) pos = 9;
            }

            let result = sum % 11 < 2 ? 0 : 11 - sum % 11;
            if (result !== parseInt(digits.charAt(0))) return false;

            // Calculate second verification digit
            size = size + 1;
            numbers = cnpj.substring(0, size);
            sum = 0;
            pos = size - 7;

            for (let i = size; i >= 1; i--) {
                sum += numbers.charAt(size - i) * pos--;
                if (pos < 2) pos = 9;
            }

            result = sum % 11 < 2 ? 0 : 11 - sum % 11;
            return result === parseInt(digits.charAt(1));
        },

        /**
         * Format CPF
         */
        formatCPF: function(cpf) {
            cpf = cpf.replace(/\D/g, '');
            cpf = cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            return cpf;
        },

        /**
         * Format CNPJ
         */
        formatCNPJ: function(cnpj) {
            cnpj = cnpj.replace(/\D/g, '');
            cnpj = cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            return cnpj;
        },

        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            $field.addClass('woocommerce-invalid');
            let $error = $field.closest('.form-row').find('.woocommerce-error');
            if ($error.length === 0) {
                $error = $('<span class="woocommerce-error">' + message + '</span>');
                $field.after($error);
            } else {
                $error.text(message);
            }
        },

        /**
         * Clear field error
         */
        clearFieldError: function($field) {
            $field.removeClass('woocommerce-invalid');
            $field.closest('.form-row').find('.woocommerce-error').remove();
        }

    };

    // Initialize when document is ready
    $(document).ready(function() {
        NFSeCheckout.init();
    });

    // Export for potential use by other scripts
    window.WCNfseCheckout = NFSeCheckout;

})(jQuery);