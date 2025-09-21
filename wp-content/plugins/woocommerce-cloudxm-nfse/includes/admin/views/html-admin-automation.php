<?php
/**
 * Admin automation page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$automation = \CloudXM\NFSe\Bootstrap\Factories::nfSeAutomationService();
$queue_manager = \CloudXM\NFSe\Bootstrap\Factories::nfSeQueueService();
$automation_stats = $automation->getAutomationStatistics();
$queue_health = $queue_manager->getQueueHealth();
?>

<div class="wrap wc-nfse-automation">
    <h1><?php _e('Automação NFS-e', 'wc-nfse'); ?></h1>

    <!-- Status Overview -->
    <div class="wc-nfse-automation-overview">
        <div class="wc-nfse-status-cards">
            <div class="status-card <?php echo $automation_stats['automation_enabled'] ? 'enabled' : 'disabled'; ?>">
                <div class="status-icon">
                    <span class="dashicons <?php echo $automation_stats['automation_enabled'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                </div>
                <div class="status-content">
                    <h3><?php _e('Automação', 'wc-nfse'); ?></h3>
                    <p><?php echo $automation_stats['automation_enabled'] ? __('Habilitada', 'wc-nfse') : __('Desabilitada', 'wc-nfse'); ?></p>
                </div>
                <div class="status-actions">
                    <?php if ($automation_stats['automation_enabled']): ?>
                        <button type="button" class="button" id="disable-automation">
                            <?php _e('Desabilitar', 'wc-nfse'); ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="button button-primary" id="enable-automation">
                            <?php _e('Habilitar', 'wc-nfse'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="status-card">
                <div class="status-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="status-content">
                    <h3><?php _e('Fila de Processamento', 'wc-nfse'); ?></h3>
                    <p><?php echo sprintf(__('%d itens pendentes', 'wc-nfse'), $automation_stats['pending_emissions']); ?></p>
                </div>
                <div class="status-actions">
                    <button type="button" class="button" id="process-queue-now">
                        <?php _e('Processar Agora', 'wc-nfse'); ?>
                    </button>
                </div>
            </div>

            <div class="status-card">
                <div class="status-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="status-content">
                    <h3><?php _e('Taxa de Sucesso', 'wc-nfse'); ?></h3>
                    <p><?php echo sprintf('%.1f%%', $automation_stats['queue_stats']['success_rate']); ?></p>
                </div>
            </div>

            <div class="status-card <?php echo $automation_stats['business_hours']['current_status'] === 'within' ? 'enabled' : 'disabled'; ?>">
                <div class="status-icon">
                    <span class="dashicons dashicons-businessperson"></span>
                </div>
                <div class="status-content">
                    <h3><?php _e('Horário Comercial', 'wc-nfse'); ?></h3>
                    <p><?php echo $automation_stats['business_hours']['current_status'] === 'within' ? __('Dentro do horário', 'wc-nfse') : __('Fora do horário', 'wc-nfse'); ?></p>
                </div>
            </div>
        </div>

        <!-- Queue Health -->
        <?php if ($queue_health['status'] !== 'healthy'): ?>
        <div class="wc-nfse-queue-health <?php echo $queue_health['status']; ?>">
            <div class="health-header">
                <span class="dashicons dashicons-warning"></span>
                <strong><?php _e('Status da Fila:', 'wc-nfse'); ?> <?php echo ucfirst($queue_health['status']); ?></strong>
            </div>
            
            <?php if (!empty($queue_health['issues'])): ?>
            <div class="health-issues">
                <h4><?php _e('Problemas Identificados:', 'wc-nfse'); ?></h4>
                <ul>
                    <?php foreach ($queue_health['issues'] as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($queue_health['recommendations'])): ?>
            <div class="health-recommendations">
                <h4><?php _e('Recomendações:', 'wc-nfse'); ?></h4>
                <ul>
                    <?php foreach ($queue_health['recommendations'] as $recommendation): ?>
                        <li><?php echo esc_html($recommendation); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="health-actions">
                <button type="button" class="button" id="reset-stuck-items">
                    <?php _e('Reiniciar Itens Presos', 'wc-nfse'); ?>
                </button>
                <button type="button" class="button" id="retry-failed-items">
                    <?php _e('Tentar Novamente Falhas', 'wc-nfse'); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Configuration Tabs -->
    <div class="wc-nfse-automation-config">
        <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
            <a href="#triggers" class="nav-tab nav-tab-active"><?php _e('Gatilhos', 'wc-nfse'); ?></a>
            <a href="#conditions" class="nav-tab"><?php _e('Condições', 'wc-nfse'); ?></a>
            <a href="#schedule" class="nav-tab"><?php _e('Agendamento', 'wc-nfse'); ?></a>
            <a href="#queue" class="nav-tab"><?php _e('Fila', 'wc-nfse'); ?></a>
        </nav>

        <form id="wc-nfse-automation-form" method="post">
            <?php wp_nonce_field('wc_nfse_admin', 'nonce'); ?>

            <!-- Triggers Tab -->
            <div id="triggers" class="wc-nfse-tab-content">
                <h2><?php _e('Gatilhos de Emissão', 'wc-nfse'); ?></h2>
                <p class="description"><?php _e('Configure quando a NFS-e deve ser emitida automaticamente.', 'wc-nfse'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Gatilhos Ativos', 'wc-nfse'); ?></th>
                        <td>
                            <?php
                            $triggers = $settings->get('auto_emit_triggers', array('payment_complete'));
                            $available_triggers = array(
                                'payment_complete' => __('Pagamento confirmado', 'wc-nfse'),
                                'order_processing' => __('Pedido em processamento', 'wc-nfse'),
                                'order_completed' => __('Pedido concluído', 'wc-nfse'),
                                'subscription_payment' => __('Pagamento de assinatura', 'wc-nfse')
                            );
                            ?>
                            
                            <?php foreach ($available_triggers as $trigger_key => $trigger_label): ?>
                            <label>
                                <input type="checkbox" name="auto_emit_triggers[]" value="<?php echo esc_attr($trigger_key); ?>" 
                                       <?php checked(in_array($trigger_key, $triggers)); ?>>
                                <?php echo esc_html($trigger_label); ?>
                            </label><br>
                            <?php endforeach; ?>
                            
                            <p class="description"><?php _e('Selecione os eventos que devem disparar a emissão automática de NFS-e.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_emit_delay"><?php _e('Atraso na Emissão', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <select id="auto_emit_delay" name="auto_emit_delay">
                                <option value="0" <?php selected($settings->get('auto_emit_delay', 300), 0); ?>><?php _e('Imediato', 'wc-nfse'); ?></option>
                                <option value="60" <?php selected($settings->get('auto_emit_delay', 300), 60); ?>><?php _e('1 minuto', 'wc-nfse'); ?></option>
                                <option value="300" <?php selected($settings->get('auto_emit_delay', 300), 300); ?>><?php _e('5 minutos', 'wc-nfse'); ?></option>
                                <option value="900" <?php selected($settings->get('auto_emit_delay', 300), 900); ?>><?php _e('15 minutos', 'wc-nfse'); ?></option>
                                <option value="1800" <?php selected($settings->get('auto_emit_delay', 300), 1800); ?>><?php _e('30 minutos', 'wc-nfse'); ?></option>
                                <option value="3600" <?php selected($settings->get('auto_emit_delay', 300), 3600); ?>><?php _e('1 hora', 'wc-nfse'); ?></option>
                            </select>
                            <p class="description"><?php _e('Tempo de espera antes de processar a emissão após o gatilho.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Conditions Tab -->
            <div id="conditions" class="wc-nfse-tab-content" style="display: none;">
                <h2><?php _e('Condições de Emissão', 'wc-nfse'); ?></h2>
                <p class="description"><?php _e('Configure as condições que devem ser atendidas para emissão automática.', 'wc-nfse'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Status de Pedidos', 'wc-nfse'); ?></th>
                        <td>
                            <?php
                            $allowed_statuses = $settings->get('auto_emit_order_statuses', array('processing', 'completed'));
                            $order_statuses = wc_get_order_statuses();
                            ?>
                            
                            <?php foreach ($order_statuses as $status_key => $status_label): ?>
                            <label>
                                <input type="checkbox" name="auto_emit_order_statuses[]" value="<?php echo esc_attr(str_replace('wc-', '', $status_key)); ?>" 
                                       <?php checked(in_array(str_replace('wc-', '', $status_key), $allowed_statuses)); ?>>
                                <?php echo esc_html($status_label); ?>
                            </label><br>
                            <?php endforeach; ?>
                            
                            <p class="description"><?php _e('Apenas pedidos com estes status serão processados.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_emit_min_total"><?php _e('Valor Mínimo', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="auto_emit_min_total" name="auto_emit_min_total" 
                                   value="<?php echo esc_attr($settings->get('auto_emit_min_total', 0)); ?>" 
                                   min="0" step="0.01" class="regular-text">
                            <p class="description"><?php _e('Valor mínimo do pedido para emissão automática (0 = sem limite).', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Tipos de Cliente', 'wc-nfse'); ?></th>
                        <td>
                            <?php
                            $customer_types = $settings->get('auto_emit_customer_types', array('all'));
                            ?>
                            
                            <label>
                                <input type="checkbox" name="auto_emit_customer_types[]" value="all" 
                                       <?php checked(in_array('all', $customer_types)); ?>>
                                <?php _e('Todos os clientes', 'wc-nfse'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="auto_emit_customer_types[]" value="individual" 
                                       <?php checked(in_array('individual', $customer_types)); ?>>
                                <?php _e('Pessoa física', 'wc-nfse'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="auto_emit_customer_types[]" value="business" 
                                       <?php checked(in_array('business', $customer_types)); ?>>
                                <?php _e('Pessoa jurídica', 'wc-nfse'); ?>
                            </label><br>
                            
                            <p class="description"><?php _e('Tipos de cliente para os quais emitir NFS-e automaticamente.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Métodos de Pagamento Excluídos', 'wc-nfse'); ?></th>
                        <td>
                            <?php
                            $excluded_methods = $settings->get('auto_emit_excluded_payment_methods', array());
                            $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
                            ?>
                            
                            <?php foreach ($payment_gateways as $gateway_id => $gateway): ?>
                            <label>
                                <input type="checkbox" name="auto_emit_excluded_payment_methods[]" value="<?php echo esc_attr($gateway_id); ?>" 
                                       <?php checked(in_array($gateway_id, $excluded_methods)); ?>>
                                <?php echo esc_html($gateway->get_title()); ?>
                            </label><br>
                            <?php endforeach; ?>
                            
                            <p class="description"><?php _e('Métodos de pagamento que NÃO devem gerar NFS-e automaticamente.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Schedule Tab -->
            <div id="schedule" class="wc-nfse-tab-content" style="display: none;">
                <h2><?php _e('Configurações de Agendamento', 'wc-nfse'); ?></h2>
                <p class="description"><?php _e('Configure horários e dias para processamento automático.', 'wc-nfse'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="auto_emit_business_hours_enabled"><?php _e('Horário Comercial', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="auto_emit_business_hours_enabled" name="auto_emit_business_hours_enabled" value="yes" 
                                   <?php checked($settings->get('auto_emit_business_hours_enabled', false), true); ?>>
                            <label for="auto_emit_business_hours_enabled"><?php _e('Processar apenas em horário comercial', 'wc-nfse'); ?></label>
                            <p class="description"><?php _e('Se habilitado, emissões serão processadas apenas nos horários configurados.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="business-hours-config">
                        <th scope="row"><?php _e('Horário de Funcionamento', 'wc-nfse'); ?></th>
                        <td>
                            <label for="auto_emit_business_hours_start"><?php _e('Início:', 'wc-nfse'); ?></label>
                            <input type="time" id="auto_emit_business_hours_start" name="auto_emit_business_hours_start" 
                                   value="<?php echo esc_attr($settings->get('auto_emit_business_hours_start', '08:00')); ?>">
                            
                            <label for="auto_emit_business_hours_end" style="margin-left: 20px;"><?php _e('Fim:', 'wc-nfse'); ?></label>
                            <input type="time" id="auto_emit_business_hours_end" name="auto_emit_business_hours_end" 
                                   value="<?php echo esc_attr($settings->get('auto_emit_business_hours_end', '18:00')); ?>">
                        </td>
                    </tr>
                    
                    <tr class="business-hours-config">
                        <th scope="row"><?php _e('Dias de Funcionamento', 'wc-nfse'); ?></th>
                        <td>
                            <?php
                            $business_days = $settings->get('auto_emit_business_days', array(1, 2, 3, 4, 5));
                            $days_of_week = array(
                                0 => __('Domingo', 'wc-nfse'),
                                1 => __('Segunda-feira', 'wc-nfse'),
                                2 => __('Terça-feira', 'wc-nfse'),
                                3 => __('Quarta-feira', 'wc-nfse'),
                                4 => __('Quinta-feira', 'wc-nfse'),
                                5 => __('Sexta-feira', 'wc-nfse'),
                                6 => __('Sábado', 'wc-nfse')
                            );
                            ?>
                            
                            <?php foreach ($days_of_week as $day_num => $day_name): ?>
                            <label>
                                <input type="checkbox" name="auto_emit_business_days[]" value="<?php echo $day_num; ?>" 
                                       <?php checked(in_array($day_num, $business_days)); ?>>
                                <?php echo esc_html($day_name); ?>
                            </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Queue Tab -->
            <div id="queue" class="wc-nfse-tab-content" style="display: none;">
                <h2><?php _e('Configurações da Fila', 'wc-nfse'); ?></h2>
                <p class="description"><?php _e('Configure o comportamento da fila de processamento.', 'wc-nfse'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="auto_emit_retry_limit"><?php _e('Limite de Tentativas', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="auto_emit_retry_limit" name="auto_emit_retry_limit" 
                                   value="<?php echo esc_attr($settings->get('auto_emit_retry_limit', 3)); ?>" 
                                   min="1" max="10" class="small-text">
                            <p class="description"><?php _e('Número máximo de tentativas para emissões que falharam.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="queue_batch_size"><?php _e('Tamanho do Lote', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="queue_batch_size" name="queue_batch_size" 
                                   value="<?php echo esc_attr($settings->get('queue_batch_size', 10)); ?>" 
                                   min="1" max="50" class="small-text">
                            <p class="description"><?php _e('Número de itens processados por vez na fila.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="queue_cleanup_days"><?php _e('Limpeza Automática', 'wc-nfse'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="queue_cleanup_days" name="queue_cleanup_days" 
                                   value="<?php echo esc_attr($settings->get('queue_cleanup_days', 7)); ?>" 
                                   min="1" max="90" class="small-text">
                            <label for="queue_cleanup_days"><?php _e('dias', 'wc-nfse'); ?></label>
                            <p class="description"><?php _e('Remover itens concluídos da fila após este período.', 'wc-nfse'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- Queue Actions -->
                <div class="wc-nfse-queue-actions">
                    <h3><?php _e('Ações da Fila', 'wc-nfse'); ?></h3>
                    
                    <button type="button" class="button" id="clear-completed-queue">
                        <?php _e('Limpar Itens Concluídos', 'wc-nfse'); ?>
                    </button>
                    
                    <button type="button" class="button" id="clear-entire-queue">
                        <?php _e('Limpar Fila Inteira', 'wc-nfse'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary" id="pause-queue">
                        <?php _e('Pausar Processamento', 'wc-nfse'); ?>
                    </button>
                </div>
            </div>

            <p class="submit">
                <button type="submit" class="button-primary" id="save-automation-settings">
                    <?php _e('Salvar Configurações', 'wc-nfse'); ?>
                </button>
                <span class="spinner"></span>
            </p>
        </form>
    </div>

    <!-- Queue Monitor -->
    <div class="wc-nfse-queue-monitor">
        <h2><?php _e('Monitor da Fila', 'wc-nfse'); ?></h2>
        
        <div class="queue-stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo $automation_stats['queue_stats']['pending_items']; ?></div>
                <div class="stat-label"><?php _e('Pendentes', 'wc-nfse'); ?></div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number"><?php echo $automation_stats['queue_stats']['processing_items']; ?></div>
                <div class="stat-label"><?php _e('Processando', 'wc-nfse'); ?></div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number"><?php echo $automation_stats['queue_stats']['completed_items']; ?></div>
                <div class="stat-label"><?php _e('Concluídos', 'wc-nfse'); ?></div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number"><?php echo $automation_stats['queue_stats']['failed_items']; ?></div>
                <div class="stat-label"><?php _e('Falharam', 'wc-nfse'); ?></div>
            </div>
        </div>
        
        <div id="queue-items-table">
            <!-- Queue items will be loaded via AJAX -->
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.wc-nfse-tab-content').hide();
        $(target).show();
    });

    // Business hours toggle
    $('#auto_emit_business_hours_enabled').on('change', function() {
        $('.business-hours-config').toggle($(this).is(':checked'));
    }).trigger('change');

    // Enable/Disable automation
    $('#enable-automation, #disable-automation').on('click', function() {
        var enable = $(this).attr('id') === 'enable-automation';
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: enable ? 'wc_nfse_enable_automation' : 'wc_nfse_disable_automation',
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Process queue now
    $('#process-queue-now').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e("Processando...", "wc-nfse"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_process_queue_now',
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e("Processar Agora", "wc-nfse"); ?>');
            }
        });
    });

    // Queue management actions
    $('#reset-stuck-items, #retry-failed-items, #clear-completed-queue, #clear-entire-queue').on('click', function() {
        var action = $(this).attr('id').replace('-', '_');
        var confirmMessage = '';
        
        switch(action) {
            case 'clear_entire_queue':
                confirmMessage = '<?php _e("Tem certeza que deseja limpar toda a fila? Esta ação não pode ser desfeita.", "wc-nfse"); ?>';
                break;
            case 'clear_completed_queue':
                confirmMessage = '<?php _e("Tem certeza que deseja limpar os itens concluídos?", "wc-nfse"); ?>';
                break;
            default:
                confirmMessage = '<?php _e("Tem certeza que deseja executar esta ação?", "wc-nfse"); ?>';
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_' + action,
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotice('error', response.data.message);
                }
            }
        });
    });

    // Save automation settings
    $('#wc-nfse-automation-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#save-automation-settings');
        var $spinner = $form.find('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=wc_nfse_save_automation_settings',
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Load queue items
    loadQueueItems();

    function loadQueueItems() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_nfse_get_queue_items',
                nonce: '<?php echo wp_create_nonce("wc_nfse_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#queue-items-table').html(response.data.html);
                }
            }
        });
    }

    // Show notice function
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
});
</script>

<style>
.wc-nfse-automation {
    margin: 20px 0;
}

.wc-nfse-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.status-card.enabled {
    border-color: #46b450;
    background: #f0f8f0;
}

.status-card.disabled {
    border-color: #dc3232;
    background: #fdf0f0;
}

.status-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.status-card.enabled .dashicons {
    color: #46b450;
}

.status-card.disabled .dashicons {
    color: #dc3232;
}

.status-content {
    flex: 1;
}

.status-content h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
}

.status-content p {
    margin: 0;
    color: #666;
}

.wc-nfse-queue-health {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.wc-nfse-queue-health.warning {
    border-color: #ffb900;
    background: #fffbf0;
}

.wc-nfse-queue-health.critical {
    border-color: #dc3232;
    background: #fdf0f0;
}

.health-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.health-issues, .health-recommendations {
    margin-bottom: 15px;
}

.health-issues h4, .health-recommendations h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.health-actions {
    display: flex;
    gap: 10px;
}

.wc-nfse-tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
    margin-bottom: 20px;
}

.wc-nfse-queue-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.wc-nfse-queue-actions h3 {
    margin: 0 0 15px 0;
}

.wc-nfse-queue-actions .button {
    margin-right: 10px;
}

.queue-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #666;
}

@media (max-width: 768px) {
    .wc-nfse-status-cards {
        grid-template-columns: 1fr;
    }
    
    .queue-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .status-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

