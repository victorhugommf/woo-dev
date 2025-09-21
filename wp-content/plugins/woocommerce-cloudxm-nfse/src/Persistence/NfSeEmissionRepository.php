<?php

namespace CloudXM\NFSe\Persistence;

use CloudXM\NFSe\Utilities\Logger;

/**
 * NFSe Emission Repository
 *
 * Handles database operations for NFSe emission records
 */
class NfSeEmissionRepository implements RepositoryInterface
{
    private Logger $logger;
    private string $tableName;

    public function __construct(Logger $logger, string $tablePrefix = 'cloudxm_nfse')
    {
        global $wpdb;
        $this->logger = $logger;
        $this->tableName = $wpdb->prefix . $tablePrefix . '_emissions';
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        // Parse JSON data
        if (!empty($row['xml_data'])) {
            $row['xml_data'] = json_decode($row['xml_data'], true) ?? [];
        }

        if (!empty($row['response_data'])) {
            $row['response_data'] = json_decode($row['response_data'], true) ?? [];
        }

        return $row;
    }

    /**
     * @inheritDoc
     */
    public function findAll(array $conditions = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $whereClause = '';
        $whereParams = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = $wpdb->prepare("{$field} = %s", $value);
            }
            $whereClause = ' WHERE ' . implode(' AND ', $whereParts);
            $whereParams = array_values($conditions);
        }

        if (!empty($whereParams)) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->tableName}{$whereClause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge($whereParams, [$limit, $offset])
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->tableName} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ), ARRAY_A);
        }

        if (!$rows) {
            return [];
        }

        // Parse JSON data for each row
        foreach ($rows as &$row) {
            if (!empty($row['xml_data'])) {
                $row['xml_data'] = json_decode($row['xml_data'], true) ?? [];
            }
            if (!empty($row['response_data'])) {
                $row['response_data'] = json_decode($row['response_data'], true) ?? [];
            }
        }

        return $rows;
    }

    /**
     * @inheritDoc
     */
    public function findOneBy(array $conditions): ?array
    {
        $results = $this->findAll($conditions, 1);
        return $results[0] ?? null;
    }

    /**
     * Find by order ID
     */
    public function findByOrderId(int $orderId): ?array
    {
        return $this->findOneBy(['order_id' => $orderId]);
    }

    /**
     * Find by access key
     */
    public function findByAccessKey(string $accessKey): ?array
    {
        return $this->findOneBy(['access_key' => $accessKey]);
    }

    /**
     * @inheritDoc
     */
    public function save(array $data): int
    {
        global $wpdb;

        $insertData = $this->prepareInsertData($data);
        $result = $wpdb->insert(
            $this->tableName,
            $insertData,
            $this->getFormatArray($insertData)
        );

        if ($result === false) {
            $this->logger->error('Failed to save NFSe emission', [
                'error' => $wpdb->last_error,
                'table' => $this->tableName
            ]);
            throw new \Exception('Failed to save NFSe emission: ' . $wpdb->last_error);
        }

        $newId = $wpdb->insert_id;

        $this->logger->info('NFSe emission saved successfully', [
            'id' => $newId,
            'order_id' => $data['order_id'] ?? null
        ]);

        return $newId;
    }

    /**
     * @inheritDoc
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $updateData = $this->prepareUpdateData($data);
        $updateData['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->tableName,
            $updateData,
            ['id' => $id],
            $this->getFormatArray($updateData),
            ['%d']
        );

        if ($result === false) {
            $this->logger->error('Failed to update NFSe emission', [
                'id' => $id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }

        $this->logger->info('NFSe emission updated successfully', [
            'id' => $id,
            'affected_rows' => $result
        ]);

        return true;
    }

    /**
     * Update by order ID
     */
    public function updateByOrderId(int $orderId, array $data): bool
    {
        global $wpdb;

        $updateData = $this->prepareUpdateData($data);
        $updateData['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->tableName,
            $updateData,
            ['order_id' => $orderId],
            $this->getFormatArray($updateData),
            ['%d']
        );

        if ($result === false) {
            $this->logger->error('Failed to update NFSe emission by order ID', [
                'order_id' => $orderId,
                'error' => $wpdb->last_error
            ]);
            return false;
        }

        return $result > 0;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->tableName,
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error('Failed to delete NFSe emission', [
                'id' => $id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }

        $this->logger->info('NFSe emission deleted successfully', [
            'id' => $id,
            'affected_rows' => $result
        ]);

        return $result > 0;
    }

    /**
     * @inheritDoc
     */
    public function count(array $conditions = []): int
    {
        global $wpdb;

        $whereClause = '';
        $whereParams = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = $wpdb->prepare("{$field} = %s", $value);
            }
            $whereClause = ' WHERE ' . implode(' AND ', $whereParts);
            $whereParams = array_values($conditions);
        }

        if (!empty($whereParams)) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableName}{$whereClause}",
                ...$whereParams
            ));
        } else {
            $result = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName}");
        }

        return (int) $result;
    }

    /**
     * Get emission statistics
     */
    public function getStatistics(int $periodInDays = 30): array
    {
        global $wpdb;

        $dateCondition = $wpdb->prepare(
            "AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $periodInDays
        );

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_emissions,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_emissions,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_emissions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_emissions,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_emissions,
                AVG(CASE WHEN status IN ('success', 'error', 'cancelled')
                         THEN TIMESTAMPDIFF(SECOND, created_at, updated_at)
                         ELSE NULL END) as avg_processing_time
            FROM {$this->tableName}
            WHERE 1=1 {$dateCondition}
        ");

        if (!$stats) {
            return [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'pending' => 0,
                'cancelled' => 0,
                'success_rate' => 0,
                'avg_processing_time' => null,
            ];
        }

        $total = (int) $stats->total_emissions;
        $successful = (int) $stats->successful_emissions;

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => (int) $stats->failed_emissions,
            'pending' => (int) $stats->pending_emissions,
            'cancelled' => (int) $stats->cancelled_emissions,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'avg_processing_time' => $stats->avg_processing_time ? round((float) $stats->avg_processing_time, 2) : null,
        ];
    }

    /**
     * Prepare insert data - convert complex types to JSON
     */
    private function prepareInsertData(array $data): array
    {
        $insertData = $data;

        // Convert arrays/objects to JSON for storage
        if (isset($insertData['xml_data']) && is_array($insertData['xml_data'])) {
            $insertData['xml_data'] = json_encode($insertData['xml_data']);
        }

        if (isset($insertData['response_data']) && is_array($insertData['response_data'])) {
            $insertData['response_data'] = json_encode($insertData['response_data']);
        }

        // Set timestamps
        $currentTime = current_time('mysql');
        $insertData['created_at'] = $insertData['created_at'] ?? $currentTime;
        $insertData['updated_at'] = $insertData['updated_at'] ?? $currentTime;

        return $insertData;
    }

    /**
     * Prepare update data
     */
    private function prepareUpdateData(array $data): array
    {
        $updateData = $data;

        if (isset($updateData['xml_data']) && is_array($updateData['xml_data'])) {
            $updateData['xml_data'] = json_encode($updateData['xml_data']);
        }

        if (isset($updateData['response_data']) && is_array($updateData['response_data'])) {
            $updateData['response_data'] = json_encode($updateData['response_data']);
        }

        return $updateData;
    }

    /**
     * Get format array for wpdb prepare
     */
    private function getFormatArray(array $data): array
    {
        $formats = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['xml_data', 'response_data', 'error_message'])) {
                $formats[] = '%s';
            } elseif (in_array($key, ['id', 'order_id'])) {
                $formats[] = '%d';
            } elseif (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
