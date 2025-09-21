<?php

/**
 * Admin logs page template
 */

if (!defined('ABSPATH')) {
  exit;
}
?>

<div class="wrap wc-nfse-admin">
  <h1><?php _e('NFS-e - Logs do Sistema', 'wc-nfse'); ?></h1>

  <div class="wc-nfse-logs-container">

    <!-- Log Info Widget -->
    <div class="wc-nfse-widget">
      <h2><?php _e('Informações dos Logs', 'wc-nfse'); ?></h2>
      <div class="wc-nfse-log-info">
        <div class="wc-nfse-info-item">
          <strong><?php _e('Tamanho do arquivo de log:', 'wc-nfse'); ?></strong>
          <span><?php echo size_format($log_size ?? 0); ?></span>
        </div>
        <div class="wc-nfse-info-item">
          <strong><?php _e('Total de entradas exibidas:', 'wc-nfse'); ?></strong>
          <span><?php echo count($logs ?? []); ?></span>
        </div>
      </div>

      <div class="wc-nfse-log-actions">
        <button type="button" class="button" id="wc-nfse-refresh-logs">
          <?php _e('Atualizar Logs', 'wc-nfse'); ?>
        </button>
        <button type="button" class="button" id="wc-nfse-clear-logs">
          <?php _e('Limpar Logs', 'wc-nfse'); ?>
        </button>
      </div>
    </div>

    <!-- Logs Display -->
    <div class="wc-nfse-widget">
      <h2><?php _e('Logs Recentes', 'wc-nfse'); ?></h2>

      <div class="wc-nfse-log-filters">
        <select id="wc-nfse-log-level-filter">
          <option value=""><?php _e('Todos os níveis', 'wc-nfse'); ?></option>
          <option value="debug"><?php _e('Debug', 'wc-nfse'); ?></option>
          <option value="info"><?php _e('Info', 'wc-nfse'); ?></option>
          <option value="warning"><?php _e('Warning', 'wc-nfse'); ?></option>
          <option value="error"><?php _e('Error', 'wc-nfse'); ?></option>
        </select>

        <input type="text" id="wc-nfse-log-search" placeholder="<?php _e('Buscar nos logs...', 'wc-nfse'); ?>">
      </div>

      <div class="wc-nfse-logs-display" id="wc-nfse-logs-container">
        <?php if (!empty($logs) && is_array($logs)): ?>
          <?php foreach ($logs as $log_entry): ?>
            <?php
            $log_level = 'info';
            $log_message = $log_entry;

            // Parse log level from message
            if (is_string($log_entry)) {
              if (strpos($log_entry, '[error]') !== false || strpos($log_entry, 'PHP Warning') !== false || strpos($log_entry, 'PHP Fatal') !== false) {
                $log_level = 'error';
              } elseif (strpos($log_entry, '[warning]') !== false || strpos($log_entry, 'PHP Notice') !== false) {
                $log_level = 'warning';
              } elseif (strpos($log_entry, '[debug]') !== false || strpos($log_entry, 'DEBUG') !== false) {
                $log_level = 'debug';
              } else {
                $log_level = 'info';
              }
            }
            ?>
            <div class="wc-nfse-log-entry <?php echo $log_level; ?>" data-level="<?php echo $log_level; ?>">
              <div class="wc-nfse-log-level">
                <span class="wc-nfse-log-badge <?php echo $log_level; ?>">
                  <?php echo strtoupper($log_level); ?>
                </span>
              </div>
              <div class="wc-nfse-log-message">
                <pre><?php echo esc_html($log_message); ?></pre>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="wc-nfse-no-logs">
            <p><?php _e('Nenhum log encontrado.', 'wc-nfse'); ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
  .wc-nfse-admin {
    margin: 20px 0;
  }

  .wc-nfse-logs-container {
    margin-top: 20px;
  }

  .wc-nfse-widget {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    margin-bottom: 20px;
  }

  .wc-nfse-widget h2 {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
  }

  .wc-nfse-log-info {
    display: grid;
    gap: 10px;
    margin-bottom: 15px;
  }

  .wc-nfse-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
  }

  .wc-nfse-info-item:last-child {
    border-bottom: none;
  }

  .wc-nfse-log-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
  }

  .wc-nfse-log-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    align-items: center;
  }

  .wc-nfse-log-filters select,
  .wc-nfse-log-filters input {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
  }

  .wc-nfse-log-filters input {
    min-width: 250px;
  }

  .wc-nfse-logs-display {
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
  }

  .wc-nfse-log-entry {
    display: flex;
    border-bottom: 1px solid #eee;
    padding: 10px;
    transition: background-color 0.2s;
  }

  .wc-nfse-log-entry:hover {
    background-color: #f8f9fa;
  }

  .wc-nfse-log-entry:last-child {
    border-bottom: none;
  }

  .wc-nfse-log-level {
    flex-shrink: 0;
    margin-right: 15px;
    padding-top: 2px;
  }

  .wc-nfse-log-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    color: white;
    min-width: 60px;
    text-align: center;
  }

  .wc-nfse-log-badge.debug {
    background-color: #6c757d;
  }

  .wc-nfse-log-badge.info {
    background-color: #0073aa;
  }

  .wc-nfse-log-badge.warning {
    background-color: #ffb900;
  }

  .wc-nfse-log-badge.error {
    background-color: #dc3232;
  }

  .wc-nfse-log-message {
    flex: 1;
  }

  .wc-nfse-log-message pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
    font-family: Consolas, Monaco, 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    background: transparent;
    border: none;
    padding: 0;
  }

  .wc-nfse-log-entry.error {
    background-color: #fdf0f0;
    border-left: 4px solid #dc3232;
  }

  .wc-nfse-log-entry.warning {
    background-color: #fffbf0;
    border-left: 4px solid #ffb900;
  }

  .wc-nfse-log-entry.debug {
    background-color: #f8f9fa;
    border-left: 4px solid #6c757d;
  }

  .wc-nfse-log-entry.info {
    border-left: 4px solid #0073aa;
  }

  .wc-nfse-no-logs {
    text-align: center;
    padding: 40px;
    color: #666;
  }

  /* Hidden class for filtering */
  .wc-nfse-log-entry.hidden {
    display: none;
  }
</style>

<script>
  jQuery(document).ready(function($) {
    // Log level filter
    $('#wc-nfse-log-level-filter').on('change', function() {
      var selectedLevel = $(this).val();
      filterLogs();
    });

    // Search filter
    $('#wc-nfse-log-search').on('input', function() {
      filterLogs();
    });

    function filterLogs() {
      var selectedLevel = $('#wc-nfse-log-level-filter').val();
      var searchTerm = $('#wc-nfse-log-search').val().toLowerCase();

      $('.wc-nfse-log-entry').each(function() {
        var $entry = $(this);
        var level = $entry.data('level');
        var message = $entry.find('.wc-nfse-log-message').text().toLowerCase();

        var levelMatch = !selectedLevel || level === selectedLevel;
        var textMatch = !searchTerm || message.includes(searchTerm);

        if (levelMatch && textMatch) {
          $entry.removeClass('hidden');
        } else {
          $entry.addClass('hidden');
        }
      });
    }

    // Refresh logs
    $('#wc-nfse-refresh-logs').on('click', function() {
      location.reload();
    });

    // Clear logs (you would need to implement the AJAX handler)
    $('#wc-nfse-clear-logs').on('click', function() {
      if (confirm('<?php _e('Tem certeza que deseja limpar todos os logs?', 'wc-nfse'); ?>')) {
        // Implement AJAX call to clear logs
        alert('<?php _e('Funcionalidade de limpeza de logs precisa ser implementada.', 'wc-nfse'); ?>');
      }
    });
  });
</script>