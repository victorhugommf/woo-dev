/**
 * Admin JavaScript for WooCommerce CloudXM NFS-e plugin
 * Modern PSR-4 compatible asset management
 *
 * @version 2.0.0
 */

(function($) {
    'use strict';

    /**
     * NFSe Admin JavaScript
     */
    const NFSeAdmin = {

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeTabs();
            this.initializeAjaxHandlers();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Certificate management
            $(document).on('click', '.wc-nfse-upload-btn', this.handleCertificateUpload);
            $(document).on('click', '.wc-nfse-delete-cert', this.handleCertificateDelete);
            $(document).on('click', '.wc-nfse-test-cert', this.handleCertificateTest);

            // Test buttons
            $(document).on('click', '.wc-nfse-test-connection', this.handleConnectionTest);
            $(document).on('click', '.wc-nfse-test-dps', this.handleDpsTest);
            $(document).on('click', '.wc-nfse-test-validation', this.handleValidationTest);

            // Form submissions
            $(document).on('submit', '.wc-nfse-form', this.handleFormSubmission);

            // File inputs
            $(document).on('change', '.wc-nfse-file-input', this.handleFileSelection);

            // Dynamic content updates
            $(document).on('change', '.wc-nfse-dynamic-update', this.handleDynamicUpdate);
        },

        /**
         * Initialize tab functionality
         */
        initializeTabs: function() {
            $('.wc-nfse-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();

                const targetTab = $(this).attr('href');

                // Update active tab
                $('.wc-nfse-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Show target content
                $('.wc-nfse-tab-content').hide();
                $(targetTab).show();
            });
        },

        /**
         * Initialize AJAX handlers
         */
        initializeAjaxHandlers: function() {
            // Add loading indicators for AJAX requests
            $(document).ajaxStart(function() {
                $('#wpbody-content').addClass('wc-nfse-loading');
            });

            $(document).ajaxStop(function() {
                $('#wpbody-content').removeClass('wc-nfse-loading');
            });

            // Handle AJAX errors globally
            $(document).ajaxError(function(event, xhr, settings, error) {
                console.error('NFSe AJAX Error:', error);
                NFSeAdmin.showAdminNotice(
                    wc_nfse_admin.strings.error + ': ' + error,
                    'error'
                );
            });
        },

        /**
         * Handle certificate upload
         */
        handleCertificateUpload: function(e) {
            e.preventDefault();

            const formData = new FormData();
            const fileInput = $('.wc-nfse-certificate-file')[0];

            if (!fileInput.files[0]) {
                NFSeAdmin.showAdminNotice('Selecione um arquivo de certificado', 'error');
                return;
            }

            formData.append('certificate_file', fileInput.files[0]);
            formData.append('password', $('.wc-nfse-certificate-password').val());
            formData.append('action', 'wc_nfse_upload_certificate');
            formData.append('nonce', wc_nfse_admin.nonce);

            NFSeAdmin.showLoadingSpinner($(this));

            $.ajax({
                url: wc_nfse_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        NFSeAdmin.showAdminNotice(response.data.message, 'success');
                        // Reload page to show updated certificate status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        NFSeAdmin.showAdminNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr) {
                    NFSeAdmin.showAdminNotice('Erro no upload: ' + xhr.responseJSON?.data?.message, 'error');
                },
                complete: function() {
                    NFSeAdmin.hideLoadingSpinner();
                }
            });
        },

        /**
         * Handle certificate deletion
         */
        handleCertificateDelete: function(e) {
            e.preventDefault();

            if (!confirm(wc_nfse_admin.strings.confirm_delete)) {
                return;
            }

            const certificateId = $(this).data('certificate-id');

            $.post(wc_nfse_admin.ajax_url, {
                action: 'wc_nfse_delete_certificate',
                certificate_id: certificateId,
                nonce: wc_nfse_admin.nonce
            }, function(response) {
                if (response.success) {
                    NFSeAdmin.showAdminNotice(response.data.message, 'success');
                    // Reload page to show updated status
                    window.location.reload();
                } else {
                    NFSeAdmin.showAdminNotice(response.data.message, 'error');
                }
            });
        },

        /**
         * Handle certificate test
         */
        handleCertificateTest: function(e) {
            e.preventDefault();

            NFSeAdmin.showLoadingSpinner($(this));

            $.post(wc_nfse_admin.ajax_url, {
                action: 'wc_nfse_test_certificate',
                certificate_id: $(this).data('certificate-id'),
                nonce: wc_nfse_admin.nonce
            }, function(response) {
                if (response.success) {
                    NFSeAdmin.showAdminNotice('Certificate test successful', 'success');
                } else {
                    NFSeAdmin.showAdminNotice(response.data.message, 'error');
                }
            }).always(function() {
                NFSeAdmin.hideLoadingSpinner();
            });
        },

        /**
         * Handle connection test
         */
        handleConnectionTest: function(e) {
            e.preventDefault();

            NFSeAdmin.showLoadingSpinner($(this));

            $.post(wc_nfse_admin.ajax_url, {
                action: 'wc_nfse_test_connection',
                nonce: wc_nfse_admin.nonce
            }, function(response) {
                if (response.success) {
                    NFSeAdmin.showAdminNotice(response.data.message, 'success');
                    console.log('Connection test details:', response.data.details);
                } else {
                    NFSeAdmin.showAdminNotice(response.data.message, 'error');
                }
            }).always(function() {
                NFSeAdmin.hideLoadingSpinner();
            });
        },

        /**
         * Handle DPS test
         */
        handleDpsTest: function(e) {
            e.preventDefault();

            const orderId = $('#wc_nfse_test_order_id').val();
            if (!orderId) {
                NFSeAdmin.showAdminNotice('Por favor, informe o ID do pedido', 'error');
                return;
            }

            NFSeAdmin.showLoadingSpinner($(this));

            // Implementation depends on your backend AJAX handler
            NFSeAdmin.hideLoadingSpinner();
        },

        /**
         * Handle validation test
         */
        handleValidationTest: function(e) {
            e.preventDefault();

            NFSeAdmin.showLoadingSpinner($(this));

            // Implementation depends on your validation logic
            NFSeAdmin.hideLoadingSpinner();
        },

        /**
         * Handle form submissions
         */
        handleFormSubmission: function(e) {
            // Add form validation or preprocessing here if needed
        },

        /**
         * Handle file selection
         */
        handleFileSelection: function(e) {
            const file = e.target.files[0];
            const fileName = file ? file.name : 'Nenhum arquivo selecionado';

            $(this).siblings('.file-name-display').text(fileName);
        },

        /**
         * Handle dynamic updates
         */
        handleDynamicUpdate: function(e) {
            const fieldName = $(this).attr('name');
            const fieldValue = $(this).val();

            // Implement dynamic field updates based on selection
        },

        /**
         * Show admin notice
         */
        showAdminNotice: function(message, type = 'info') {
            const noticeClass = `notice notice-${type} is-dismissible`;

            const noticeHtml = `
                <div class="${noticeClass}">
                    <p><strong>NFSe:</strong> ${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;

            // Remove existing NFSe notices
            $('.wc-nfse-notice').remove();

            // Add new notice
            $('#wpbody-content .wrap h1').after(noticeHtml);

            // Handle dismiss
            $('.notice-dismiss').on('click', function() {
                $(this).closest('.notice').fadeOut();
            });

            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    $('.notice-success').fadeOut();
                }, 3000);
            }
        },

        /**
         * Show loading spinner
         */
        showLoadingSpinner: function($element) {
            $element.prop('disabled', true).append(`
                <span class="wc-nfse-spinner" style="margin-left: 10px;"></span>
            `);
        },

        /**
         * Hide loading spinner
         */
        hideLoadingSpinner: function() {
            $('.wc-nfse-spinner').parent().prop('disabled', false);
            $('.wc-nfse-spinner').remove();
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Validate certificate form
         */
        validateCertificateForm: function() {
            const password = $('.wc-nfse-certificate-password').val();
            const file = $('.wc-nfse-certificate-file')[0].files[0];

            if (!file) {
                this.showAdminNotice('Selecione um arquivo de certificado', 'error');
                return false;
            }

            if (!password) {
                this.showAdminNotice('Informe a senha do certificado', 'error');
                return false;
            }

            return true;
        }

    };

    // Initialize when document is ready
    $(document).ready(function() {
        NFSeAdmin.init();
    });

    // Export for potential use by other scripts
    window.WCNfseAdmin = NFSeAdmin;

})(jQuery);