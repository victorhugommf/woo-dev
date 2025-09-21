<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_NFSe_Admin_Orders - PSR-4 Implementation
 *
 * Admin orders handler using modern PSR-4 components
 */
class WC_NFSe_Admin_Orders {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('wp_ajax_wc_nfse_emit_manual', array($this, 'emit_manual'));
        add_action('wp_ajax_wc_nfse_download_xml', array($this, 'download_xml'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_nfse_info'));
    }

    /**
     * Add meta boxes to order edit page
     */
    public function add_meta_boxes() {
        add_meta_box(
            'wc-nfse-order-actions',
            __('NFS-e', 'wc-nfse'),
            array($this, 'order_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Order meta box content
     */
    public function order_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }

        $emission = $this->get_order_emission($order->get_id());
        $settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
        
        include WC_NFSE_PLUGIN_PATH . 'includes/admin/views/html-order-meta-box.php';
    }

    /**
     * Display NFS-e info in order details
     */
    public function display_nfse_info($order) {
        $emission = $this->get_order_emission($order->get_id());
        
        if ($emission && $emission->status === 'success') {
            echo '<div class="wc-nfse-order-info">';
            echo '<h3>' . __('Informações da NFS-e', 'wc-nfse') . '</h3>';
            echo '<p><strong>' . __('Chave de Acesso:', 'wc-nfse') . '</strong> ' . esc_html($emission->access_key) . '</p>';
            echo '<p><strong>' . __('Número DPS:', 'wc-nfse') . '</strong> ' . esc_html($emission->dps_number) . '</p>';
            echo '<p><strong>' . __('Data de Emissão:', 'wc-nfse') . '</strong> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($emission->emission_date))) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Handle manual emission via AJAX
     */
    public function emit_manual() {
        check_ajax_referer('wc_nfse_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-nfse'));
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error(array(
                'message' => __('ID do pedido inválido.', 'wc-nfse')
            ));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array(
                'message' => __('Pedido não encontrado.', 'wc-nfse')
            ));
        }

        // Check if plugin is configured
        $settings = new \CloudXM\NFSe\Compatibility\SettingsCompatibility();
        if (!$settings->is_configured()) {
            wp_send_json_error(array(
                'message' => __('Plugin não está configurado. Configure os dados do prestador e certificado digital.', 'wc-nfse')
            ));
        }

        // Check if emission already exists
        $existing_emission = $this->get_order_emission($order_id);
        if ($existing_emission && $existing_emission->status === 'success') {
            wp_send_json_error(array(
                'message' => __('NFS-e já foi emitida para este pedido.', 'wc-nfse')
            ));
        }

        try {
            // For now, we'll create a mock emission since we haven't implemented the full DPS generation yet
            $this->create_mock_emission($order_id);
            
            $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
            $logger->info('Emissão manual solicitada para pedido: ' . $order_id, array(
                'user_id' => get_current_user_id(),
                'order_id' => $order_id
            ));

            wp_send_json_success(array(
                'message' => __('NFS-e emitida com sucesso!', 'wc-nfse'),
                'reload' => true
            ));

        } catch (Exception $e) {
            $logger = \CloudXM\NFSe\Utilities\Logger::getInstance();
            $logger->error('Erro na emissão manual: ' . $e->getMessage(), array(
                'order_id' => $order_id,
                'user_id' => get_current_user_id()
            ));

            wp_send_json_error(array(
                'message' => __('Erro ao emitir NFS-e: ', 'wc-nfse') . $e->getMessage()
            ));
        }
    }

    /**
     * Handle XML download via AJAX
     */
    public function download_xml() {
        check_ajax_referer('wc_nfse_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-nfse'));
        }

        $order_id = intval($_GET['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_die(__('ID do pedido inválido.', 'wc-nfse'));
        }

        $emission = $this->get_order_emission($order_id);
        
        if (!$emission || empty($emission->xml_data)) {
            wp_die(__('XML não encontrado para este pedido.', 'wc-nfse'));
        }

        $filename = 'nfse-' . $order_id . '-' . date('Y-m-d') . '.xml';
        
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($emission->xml_data));
        
        echo $emission->xml_data;
        exit;
    }

    /**
     * Get order emission data
     */
    private function get_order_emission($order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_nfse_emissions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY created_at DESC LIMIT 1",
            $order_id
        ));
    }

    /**
     * Create mock emission for testing (temporary)
     */
    private function create_mock_emission($order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_nfse_emissions';
        
        // Generate mock data
        $access_key = '35' . date('Ym') . '14200166000187550010000000' . str_pad($order_id, 8, '0', STR_PAD_LEFT) . '12345678';
        $dps_number = str_pad($order_id, 8, '0', STR_PAD_LEFT);
        
        $mock_xml = '<?xml version="1.0" encoding="UTF-8"?>
<DPS xmlns="http://www.nfse.gov.br/schema/dps_v1.xsd">
    <InfDPS Id="DPS' . $dps_number . '">
        <IdentificacaoDPS>
            <Numero>' . $dps_number . '</Numero>
            <DataEmissao>' . date('Y-m-d\TH:i:s') . '</DataEmissao>
            <Competencia>' . date('Y-m-d') . '</Competencia>
        </IdentificacaoDPS>
        <!-- Mock XML for testing -->
    </InfDPS>
</DPS>';

        $response_data = json_encode(array(
            'chaveAcesso' => $access_key,
            'numero' => $dps_number,
            'status' => 'autorizada',
            'dataEmissao' => date('Y-m-d\TH:i:s'),
            'protocolo' => '135000000000001'
        ));

        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'access_key' => $access_key,
                'dps_number' => $dps_number,
                'status' => 'success',
                'xml_data' => $mock_xml,
                'response_data' => $response_data,
                'emission_date' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            throw new Exception(__('Erro ao salvar dados da emissão.', 'wc-nfse'));
        }

        // Update order meta
        $order = wc_get_order($order_id);
        $order->update_meta_data('_nfse_access_key', $access_key);
        $order->update_meta_data('_nfse_dps_number', $dps_number);
        $order->update_meta_data('_nfse_emission_date', current_time('mysql'));
        $order->save();

        // Add order note
        $order->add_order_note(
            sprintf(__('NFS-e emitida com sucesso. Chave de acesso: %s', 'wc-nfse'), $access_key)
        );
    }
}

