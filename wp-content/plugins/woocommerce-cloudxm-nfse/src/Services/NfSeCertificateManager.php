<?php
/**
 * NFSe Certificate Manager Service
 *
 * Service class for managing digital certificates used in NFS-e emission
 *
 * @package CloudXM\NFSe\Services
 */

declare(strict_types=1);

namespace CloudXM\NFSe\Services;

class NfSeCertificateManager
{

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Validator instance
     */
    private $validator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
        $this->validator = new \CloudXM\NFSe\Services\NfSeCertificateValidator();

        add_action('wp_ajax_wc_nfse_upload_certificate', array($this, 'uploadCertificate'));
        add_action('wp_ajax_wc_nfse_delete_certificate', array($this, 'deleteCertificate'));
        add_action('wp_ajax_wc_nfse_activate_certificate', array($this, 'activateCertificate'));
        add_action('wp_ajax_wc_nfse_test_certificate', array($this, 'testCertificate'));
        add_action('wp_ajax_wc_nfse_validate_certificate', array($this, 'validateCertificate'));

        // Schedule certificate expiration checks
        add_action('wc_nfse_check_certificate_expiration', array($this, 'checkCertificateExpiration'));

        // Schedule daily check if not already scheduled
        if (!wp_next_scheduled('wc_nfse_check_certificate_expiration')) {
            wp_schedule_event(time(), 'daily', 'wc_nfse_check_certificate_expiration');
        }
    }

    /**
     * Upload certificate via AJAX
     */
    public function uploadCertificate() {
        check_ajax_referer('wc_nfse_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-nfse'));
        }

        if (empty($_FILES['certificate_file'])) {
            wp_send_json_error(array(
                'message' => __('Nenhum arquivo foi enviado.', 'wc-nfse')
            ));
        }

        $file = $_FILES['certificate_file'];
        $password = sanitize_text_field($_POST['certificate_password'] ?? '');
        $name = sanitize_text_field($_POST['certificate_name'] ?? '');

        if (empty($password)) {
            wp_send_json_error(array(
                'message' => __('Senha do certificado é obrigatória.', 'wc-nfse')
            ));
        }

        if (empty($name)) {
            $name = pathinfo($file['name'], PATHINFO_FILENAME);
        }

        try {
            $certificate_id = $this->processCertificateUpload($file, $password, $name);

            wp_send_json_success(array(
                'message' => __('Certificado enviado com sucesso!', 'wc-nfse'),
                'certificate_id' => $certificate_id,
                'reload' => true
            ));

        } catch (Exception $e) {
            $this->logger->error('Erro no upload do certificado: ' . $e->getMessage());

            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Process certificate upload
     */
    private function processCertificateUpload($file, $password, $name) {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception(__('Erro no upload do arquivo.', 'wc-nfse'));
        }

        $allowed_extensions = array('p12', 'pfx');
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception(__('Apenas arquivos .p12 ou .pfx são permitidos.', 'wc-nfse'));
        }

        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception(__('Arquivo muito grande. Tamanho máximo: 5MB.', 'wc-nfse'));
        }

        // Read and validate certificate
        $certificate_content = file_get_contents($file['tmp_name']);
        if (!$certificate_content) {
            throw new Exception(__('Não foi possível ler o arquivo do certificado.', 'wc-nfse'));
        }

        // Test certificate with password
        $certificate_data = $this->extractCertificateData($certificate_content, $password);

        // Validate certificate
        $validation_result = $this->validator->validateCertificate($certificate_data['certificate'], $certificate_data['private_key']);

        if (!$validation_result['valid']) {
            $error_message = __('Certificado inválido: ', 'wc-nfse') . implode(', ', $validation_result['errors']);
            throw new Exception($error_message);
        }

        // Generate unique filename
        $filename = 'cert_' . uniqid() . '.' . $file_extension;
        $file_path = WC_NFSE_CERTIFICATES_DIR . $filename;

        // Save certificate file
        if (!file_put_contents($file_path, $certificate_content)) {
            throw new Exception(__('Erro ao salvar arquivo do certificado.', 'wc-nfse'));
        }

        // Encrypt password
        $encrypted_password = $this->encrypt_password($password);

        // Save to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudxm_nfse_certificates';

        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'file_path' => $file_path,
                'password_hash' => $encrypted_password,
                'subject_name' => $certificate_data['subject_name'],
                'issuer_name' => $certificate_data['issuer_name'],
                'valid_from' => $certificate_data['valid_from'],
                'valid_to' => $certificate_data['valid_to'],
                'is_active' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if (!$result) {
            // Clean up file if database insert failed
            unlink($file_path);
            throw new Exception(__('Erro ao salvar dados do certificado.', 'wc-nfse'));
        }

        $certificate_id = $wpdb->insert_id;

        $this->logger->info('Certificado enviado com sucesso', array(
            'certificate_id' => $certificate_id,
            'name' => $name,
            'user_id' => get_current_user_id(),
            'validation_warnings' => count($validation_result['warnings'])
        ));

        return $certificate_id;
    }

    /**
     * Extract certificate data
     */
    private function extractCertificateData($certificate_content, $password) {
        $certificates = array();

        if (!openssl_pkcs12_read($certificate_content, $certificates, $password)) {
            throw new Exception(__('Senha do certificado incorreta ou arquivo corrompido.', 'wc-nfse'));
        }

        if (empty($certificates['cert'])) {
            throw new Exception(__('Certificado inválido.', 'wc-nfse'));
        }

        $cert_data = openssl_x509_parse($certificates['cert']);
        if (!$cert_data) {
            throw new Exception(__('Não foi possível analisar o certificado.', 'wc-nfse'));
        }

        return array(
            'subject_name' => $cert_data['subject']['CN'] ?? 'Unknown',
            'issuer_name' => $cert_data['issuer']['CN'] ?? 'Unknown',
            'valid_from' => date('Y-m-d H:i:s', $cert_data['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $cert_data['validTo_time_t']),
            'certificate' => $certificates['cert'],
            'private_key' => $certificates['pkey']
        );
    }

    /**
     * Validate certificate via AJAX
     */
    public function validateCertificate() {
        check_ajax_referer('wc_nfse_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-nfse'));
        }

        $certificate_id = intval($_POST['certificate_id'] ?? 0);

        if (!$certificate_id) {
            wp_send_json_error(array(
                'message' => __('ID do certificado inválido.', 'wc-nfse')
            ));
        }

        try {
            $certificate_data = $this->loadCertificateData($certificate_id);
            $validation_result = $this->validator->validateCertificate($certificate_data['certificate'], $certificate_data['private_key']);

            $html = $this->generateValidationHtml($validation_result);

            wp_send_json_success(array(
                'html' => $html
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Generate validation HTML
     */
    private function generateValidationHtml($validation_result) {
        ob_start();
        ?>
        <div class="wc-nfse-validation-results">
            <?php if ($validation_result['valid']): ?>
                <div class="notice notice-success">
                    <p><strong><?php _e('Certificado válido!', 'wc-nfse'); ?></strong></p>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><strong><?php _e('Certificado inválido!', 'wc-nfse'); ?></strong></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($validation_result['errors'])): ?>
                <div class="wc-nfse-validation-section">
                    <h3><?php _e('Erros:', 'wc-nfse'); ?></h3>
                    <ul class="wc-nfse-validation-list error">
                        <?php foreach ($validation_result['errors'] as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($validation_result['warnings'])): ?>
                <div class="wc-nfse-validation-section">
                    <h3><?php _e('Avisos:', 'wc-nfse'); ?></h3>
                    <ul class="wc-nfse-validation-list warning">
                        <?php foreach ($validation_result['warnings'] as $warning): ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($validation_result['info'])): ?>
                <div class="wc-nfse-validation-section">
                    <h3><?php _e('Informações do Certificado:', 'wc-nfse'); ?></h3>
                    <div class="wc-nfse-cert-info">
                        <?php foreach ($validation_result['info'] as $key => $value): ?>
                            <?php if (is_bool($value)): ?>
                                <div class="info-row">
                                    <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong>
                                    <span class="<?php echo $value ? 'yes' : 'no'; ?>">
                                        <?php echo $value ? __('Sim', 'wc-nfse') : __('Não', 'wc-nfse'); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="info-row">
                                    <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong>
                                    <span><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .wc-nfse-validation-section {
            margin: 20px 0;
        }

        .wc-nfse-validation-section h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
        }

        .wc-nfse-validation-list {
            margin: 0;
            padding-left: 20px;
        }

        .wc-nfse-validation-list.error li {
            color: #dc3232;
        }

        .wc-nfse-validation-list.warning li {
            color: #ffb900;
        }

        .wc-nfse-cert-info {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-row .yes {
            color: #46b450;
            font-weight: bold;
        }

        .info-row .no {
            color: #dc3232;
            font-weight: bold;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Test certificate via AJAX
     */
    public function testCertificate() {
        // Debug logging: Log $_POST data before nonce validation
        $certificate_id_from_post = intval($_POST['certificate_id'] ?? 0);
        $is_debug_target = ($certificate_id_from_post == 1);

        $log_context = array(
            'action' => 'testCertificate',
            'user_id' => get_current_user_id(),
            'user_capabilities' => wp_get_current_user()->allcaps ?? [],
            'post_data' => $_POST,
            'certificate_id_from_post' => $certificate_id_from_post,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );

        $this->logger->debug('testCertificate: Starting validation checks', $log_context);
        if ($is_debug_target) {
            error_log('DEBUG [testCertificate][' . time() . ']: Detailed POST data - ' . json_encode($log_context));
        }

        check_ajax_referer('wc_nfse_admin', 'nonce');

        // Debug logging: Nonce check passed
        $this->logger->debug('testCertificate: Nonce validation passed');
        if ($is_debug_target) {
            error_log('DEBUG [testCertificate][' . time() . ']: Nonce validation successful');
        }

        // Debug logging: Before permission check
        $this->logger->debug('testCertificate: Checking user permissions', array('user_can_manage_woocommerce' => current_user_can('manage_woocommerce')));
        if ($is_debug_target) {
            error_log('DEBUG [testCertificate][' . time() . ']: Checking permissions - current_user_can: ' . (current_user_can('manage_woocommerce') ? 'yes' : 'no'));
        }

        if (!current_user_can('manage_woocommerce')) {
            // Debug logging: Permission failure
            $this->logger->error('testCertificate: Permission denied', array(
                'user_id' => get_current_user_id(),
                'required_capability' => 'manage_woocommerce',
                'failure_reason' => 'permission_denied'
            ));
            if ($is_debug_target) {
                error_log('DEBUG [testCertificate][' . time() . ']: Permission failure - wp_die triggered');
            }
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-nfse'));
        }

        // Debug logging: Before certificate_id validation
        $raw_cert_id = $_POST['certificate_id'] ?? null;
        $this->logger->debug('testCertificate: Validating certificate_id', array('raw_certificate_id' => $raw_cert_id));
        if ($is_debug_target) {
            error_log('DEBUG [testCertificate][' . time() . ']: Raw certificate_id from POST: ' . (is_null($raw_cert_id) ? 'null' : strval($raw_cert_id)));
        }

        $certificate_id = intval($_POST['certificate_id'] ?? 0);

        if (!$certificate_id) {
            // Debug logging: Certificate ID validation failure
            $this->logger->error('testCertificate: Invalid certificate ID', array(
                'provided_certificate_id' => $_POST['certificate_id'] ?? null,
                'parsed_certificate_id' => $certificate_id,
                'failure_reason' => 'certificate_id_invalid'
            ));
            if ($is_debug_target) {
                error_log('DEBUG [testCertificate][' . time() . ']: Certificate ID validation failed - provided: ' . ($_POST['certificate_id'] ?? 'null') . ', parsed: ' . $certificate_id);
            }
            wp_send_json_error(array(
                'message' => __('ID do certificado inválido.', 'wc-nfse')
            ));
        }

        // Debug logging: All validations passed, entering try block
        $this->logger->debug('testCertificate: All pre-checks passed, starting certificate test', array(
            'certificate_id' => $certificate_id,
            'next_step' => 'load_certificate_data'
        ));
        if ($is_debug_target) {
            error_log('DEBUG [testCertificate][' . time() . ']: All validations passed, entering try block with certificate_id: ' . $certificate_id);
        }

        try {
            // Debug logging: About to load certificate data
            $this->logger->debug('testCertificate: Loading certificate data', array(
                'certificate_id' => $certificate_id,
                'step' => 'load_certificate_data'
            ));
            if ($is_debug_target) {
                error_log('DEBUG [testCertificate][' . time() . ']: Loading certificate data for ID: ' . $certificate_id);
            }

            $certificate_data = $this->loadCertificateData($certificate_id);

            // Debug logging: Certificate data loaded, about to test with API
            $this->logger->debug('testCertificate: Certificate data loaded, testing with API', array(
                'certificate_id' => $certificate_id,
                'step' => 'test_certificate_with_api',
                'has_certificate' => isset($certificate_data['certificate']),
                'has_private_key' => isset($certificate_data['private_key']),
                'subject_name' => $certificate_data['subject_name'] ?? 'unknown'
            ));
            if ($is_debug_target) {
                error_log('DEBUG [testCertificate][' . time() . ']: Certificate data loaded successfully, proceeding to API test');
            }

            $test_result = $this->validator->testCertificateWithApi($certificate_data);

            // Debug logging: Test completed successfully
            $this->logger->debug('testCertificate: Test completed successfully', array(
                'certificate_id' => $certificate_id,
                'test_result' => $test_result
            ));
            if ($is_debug_target) {
                error_log('DEBUG [testCertificate][' . time() . ']: Test completed successfully for certificate ID: ' . $certificate_id);
            }

            $html = $this->generateTestHtml($test_result);

            wp_send_json_success(array(
                'html' => $html
            ));

        } catch (Exception $e) {
            // Debug logging: Exception caught
            $this->logger->error('testCertificate: Exception occurred', array(
                'certificate_id' => $certificate_id,
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'failure_reason' => 'exception_in_test'
            ));
            if ($is_debug_target) {
                error_log('DEBUG [testCertificate][' . time() . ']: Exception caught - Message: ' . $e->getMessage() . ', File: ' . $e->getFile() . ', Line: ' . $e->getLine());
            }

            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Generate test HTML
     */
    private function generateTestHtml($test_result) {
        ob_start();
        ?>
        <div class="wc-nfse-test-results">
            <?php if ($test_result['success']): ?>
                <div class="notice notice-success">
                    <p><strong><?php _e('Teste realizado com sucesso!', 'wc-nfse'); ?></strong></p>
                    <p><?php echo esc_html($test_result['message']); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><strong><?php _e('Falha no teste!', 'wc-nfse'); ?></strong></p>
                    <p><?php echo esc_html($test_result['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($test_result['details'])): ?>
                <div class="wc-nfse-test-details">
                    <h3><?php _e('Detalhes do Teste:', 'wc-nfse'); ?></h3>
                    <div class="wc-nfse-test-info">
                        <?php foreach ($test_result['details'] as $key => $value): ?>
                            <div class="detail-row">
                                <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong>
                                <span><?php echo esc_html($value); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .wc-nfse-test-details {
            margin: 20px 0;
        }

        .wc-nfse-test-details h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
        }

        .wc-nfse-test-info {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Check certificate expiration (scheduled task)
     */
    public function checkCertificateExpiration() {
        $certificates = $this->getCertificates();

        foreach ($certificates as $certificate) {
            $valid_to_timestamp = strtotime($certificate->valid_to);

            if ($this->validator->needsRenewalNotification($certificate->id, $valid_to_timestamp)) {
                $this->sendExpirationNotification($certificate);
            }
        }
    }

    /**
     * Send expiration notification
     */
    private function sendExpirationNotification($certificate) {
        $expiration_status = $this->validator->getExpirationStatus(strtotime($certificate->valid_to));

        // Get admin email
        $admin_email = get_option('admin_email');

        // Email subject
        $subject = sprintf(
            __('[%s] Certificado Digital NFS-e - %s', 'wc-nfse'),
            get_bloginfo('name'),
            $expiration_status['message']
        );

        // Email message
        $message = sprintf(
            __('O certificado digital "%s" %s.', 'wc-nfse'),
            $certificate->name,
            strtolower($expiration_status['message'])
        );

        $message .= "\n\n";
        $message .= __('Detalhes do certificado:', 'wc-nfse') . "\n";
        $message .= sprintf(__('Nome: %s', 'wc-nfse'), $certificate->name) . "\n";
        $message .= sprintf(__('Titular: %s', 'wc-nfse'), $certificate->subject_name) . "\n";
        $message .= sprintf(__('Emissor: %s', 'wc-nfse'), $certificate->issuer_name) . "\n";
        $message .= sprintf(__('Válido até: %s', 'wc-nfse'), date_i18n(get_option('date_format'), strtotime($certificate->valid_to))) . "\n";

        $message .= "\n";
        $message .= __('Acesse o painel administrativo para renovar o certificado:', 'wc-nfse') . "\n";
        $message .= admin_url('admin.php?page=wc-nfse-certificates');

        // Send email
        wp_mail($admin_email, $subject, $message);

        // Log notification
        $this->logger->warning('Notificação de expiração de certificado enviada', array(
            'certificate_id' => $certificate->id,
            'certificate_name' => $certificate->name,
            'days_until_expiry' => $expiration_status['days'],
            'admin_email' => $admin_email
        ));
    }

    /**
     * Encrypt password
     */
    private function encrypt_password($password) {
        $key = $this->get_encryption_key();
        $iv = substr($key, 0, 16);

        return openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Decrypt password
     */
    private function decrypt_password($encrypted_password) {
        $key = $this->get_encryption_key();
        $iv = substr($key, 0, 16);

        return openssl_decrypt($encrypted_password, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get encryption key
     */
    private function get_encryption_key() {
        return hash('sha256', SECURE_AUTH_KEY . NONCE_KEY . 'wc_nfse_cert');
    }

    /**
     * Delete certificate via AJAX
     */
    public function deleteCertificate() {
        check_ajax_referer('wc_nfse_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-nfse'));
        }

        $certificate_id = intval($_POST['certificate_id'] ?? 0);

        if (!$certificate_id) {
            wp_send_json_error(array(
                'message' => __('ID do certificado inválido.', 'wc-nfse')
            ));
        }

        try {
            $this->deleteCertificateById($certificate_id);

            wp_send_json_success(array(
                'message' => __('Certificado excluído com sucesso!', 'wc-nfse')
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Delete certificate by ID
     */
    private function deleteCertificateById($certificate_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudxm_nfse_certificates';

        // Get certificate data
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $certificate_id
        ));

        if (!$certificate) {
            throw new Exception(__('Certificado não encontrado.', 'wc-nfse'));
        }

        // Delete file
        if (file_exists($certificate->file_path)) {
            unlink($certificate->file_path);
        }

        // Delete from database
        $result = $wpdb->delete(
            $table_name,
            array('id' => $certificate_id),
            array('%d')
        );

        if (!$result) {
            throw new Exception(__('Erro ao excluir certificado do banco de dados.', 'wc-nfse'));
        }

        // If this was the active certificate, clear the setting
        $settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
        if ($settings->get_active_certificate_id() == $certificate_id) {
            $settings->set('active_certificate_id', 0);
            $settings->save();
        }

        $this->logger->info('Certificado excluído', array(
            'certificate_id' => $certificate_id,
            'name' => $certificate->name,
            'user_id' => get_current_user_id()
        ));
    }

    /**
     * Activate certificate via AJAX
     */
    public function activateCertificate() {
        check_ajax_referer('wc_nfse_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-nfse'));
        }

        $certificate_id = intval($_POST['certificate_id'] ?? 0);

        if (!$certificate_id) {
            wp_send_json_error(array(
                'message' => __('ID do certificado inválido.', 'wc-nfse')
            ));
        }

        try {
            $this->setActiveCertificate($certificate_id);

            wp_send_json_success(array(
                'message' => __('Certificado ativado com sucesso!', 'wc-nfse')
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Set active certificate
     */
    private function setActiveCertificate($certificate_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudxm_nfse_certificates';

        // Verify certificate exists and is valid
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $certificate_id
        ));

        if (!$certificate) {
            throw new Exception(__('Certificado não encontrado.', 'wc-nfse'));
        }

        // Check if certificate is expired
        if (strtotime($certificate->valid_to) < time()) {
            throw new Exception(__('Não é possível ativar um certificado expirado.', 'wc-nfse'));
        }

        // Deactivate all certificates
        $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array(),
            array('%d'),
            array()
        );

        // Activate selected certificate
        $wpdb->update(
            $table_name,
            array('is_active' => 1),
            array('id' => $certificate_id),
            array('%d'),
            array('%d')
        );

        // Update settings
        $settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
        $settings->set('active_certificate_id', $certificate_id);
        $settings->save();

        $this->logger->info('Certificado ativado', array(
            'certificate_id' => $certificate_id,
            'name' => $certificate->name,
            'user_id' => get_current_user_id()
        ));
    }

    /**
     * Get all certificates
     */
    public function getCertificates() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudxm_nfse_certificates';

        return $wpdb->get_results(
            "SELECT id, name, subject_name, issuer_name, valid_from, valid_to, is_active, created_at
             FROM {$table_name}
             ORDER BY is_active DESC, created_at DESC"
        );
    }

    /**
     * Get active certificate
     */
    public function getActiveCertificate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudxm_nfse_certificates';

        return $wpdb->get_row(
            "SELECT * FROM {$table_name} WHERE is_active = 1 LIMIT 1"
        );
    }

    /**
     * Load certificate data for use
     */
    public function loadCertificateData($certificate_id = null) {
        if (!$certificate_id) {
            $certificate = $this->getActiveCertificate();
        } else {
            global $wpdb;
            $table_name = $wpdb->prefix . 'cloudxm_nfse_certificates';
            $certificate = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $certificate_id
            ));
        }

        if (!$certificate) {
            throw new Exception(__('Nenhum certificado ativo encontrado.', 'wc-nfse'));
        }

        if (!file_exists($certificate->file_path)) {
            throw new Exception(__('Arquivo do certificado não encontrado.', 'wc-nfse'));
        }

        $certificate_content = file_get_contents($certificate->file_path);
        $password = $this->decrypt_password($certificate->password_hash);

        return $this->extractCertificateData($certificate_content, $password);
    }

    /**
     * Check if certificate is valid for use
     */
    public function is_certificate_valid($certificate_id = null) {
        try {
            $certificate_data = $this->loadCertificateData($certificate_id);
            $validation_result = $this->validator->validateCertificate($certificate_data['certificate'], $certificate_data['private_key']);

            return $validation_result['valid'];
        } catch (Exception $e) {
            return false;
        }
    }
}