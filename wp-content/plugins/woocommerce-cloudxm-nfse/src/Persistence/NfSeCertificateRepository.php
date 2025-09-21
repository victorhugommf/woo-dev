<?php

namespace CloudXM\NFSe\Persistence;

use CloudXM\NFSe\Utilities\Logger;

/**
 * NFSe Certificate Repository
 *
 * Handles database operations for digital certificates
 */
class NfSeCertificateRepository implements RepositoryInterface
{
    private Logger $logger;
    private string $tableName;

    public function __construct(Logger $logger, string $tablePrefix = 'cloudxm_nfse')
    {
        global $wpdb;
        $this->logger = $logger;
        $this->tableName = $wpdb->prefix . $tablePrefix . '_certificates';
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

        return $row ?: null;
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

        $params = array_merge($whereParams, [$limit, $offset]);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tableName}{$whereClause} ORDER BY is_active DESC, created_at DESC LIMIT %d OFFSET %d",
            $params
        ), ARRAY_A);

        return $rows ?: [];
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
     * Find active certificate
     */
    public function findActive(): ?array
    {
        return $this->findOneBy(['is_active' => 1]);
    }

    /**
     * Find certificate by name
     */
    public function findByName(string $name): ?array
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Find expired certificates
     */
    public function findExpired(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->tableName}
             WHERE valid_to < CURDATE()
             ORDER BY valid_to DESC",
            ARRAY_A
        );

        return $rows ?: [];
    }

    /**
     * Find certificates expiring soon
     */
    public function findExpiringSoon(int $days = 30): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL %d DAY)
             ORDER BY valid_to ASC",
            $days
        ), ARRAY_A);

        return $rows ?: [];
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
            $this->logger->error('Failed to save certificate', [
                'error' => $wpdb->last_error,
                'table' => $this->tableName,
                'name' => $data['name'] ?? null
            ]);
            throw new \Exception('Failed to save certificate: ' . $wpdb->last_error);
        }

        $newId = $wpdb->insert_id;

        $this->logger->info('Certificate saved successfully', [
            'id' => $newId,
            'name' => $data['name'] ?? null,
            'subject_name' => $data['subject_name'] ?? null
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
            $this->logger->error('Failed to update certificate', [
                'id' => $id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }

        $this->logger->info('Certificate updated successfully', [
            'id' => $id,
            'affected_rows' => $result,
            'is_active' => $data['is_active'] ?? null
        ]);

        return true;
    }

    /**
     * Set active certificate
     */
    public function setActive(int $certificateId): bool
    {
        global $wpdb;

        // First, deactivate all certificates
        $wpdb->update(
            $this->tableName,
            ['is_active' => 0],
            ['is_active' => 1],
            ['%d'],
            ['%d']
        );

        // Then activate the specified certificate
        return $this->update($certificateId, ['is_active' => 1]);
    }

    /**
     * Validate certificate data
     */
    public function validateCertificateData(array $data): array
    {
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Certificate name is required';
        }

        if (empty($data['file_path'])) {
            $errors[] = 'Certificate file path is required';
        } else {
            // Check if file exists
            if (!file_exists($data['file_path'])) {
                $errors[] = 'Certificate file does not exist';
            }
        }

        // Check file extension
        if (!empty($data['file_path'])) {
            $extension = strtolower(pathinfo($data['file_path'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['p12', 'pfx', 'pem'])) {
                $errors[] = 'Invalid certificate file format. Only .p12, .pfx, and .pem files are supported.';
            }
        }

        // Password validation
        if (empty($data['password_hash'])) {
            $errors[] = 'Certificate password is required';
        }

        // Dates validation
        if (!empty($data['valid_from']) && !empty($data['valid_to'])) {
            $validFrom = strtotime($data['valid_from']);
            $validTo = strtotime($data['valid_to']);

            if ($validTo <= $validFrom) {
                $errors[] = 'Certificate expiry date must be later than valid from date';
            }

            if ($validTo < time()) {
                $errors[] = 'Certificate has already expired';
            }
        }

        return $errors;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        // Get certificate info for cleanup
        $certificate = $this->find($id);
        if (!$certificate) {
            return false;
        }

        // If deleting active certificate, log warning
        if ($certificate['is_active'] == 1) {
            $this->logger->warning('Deleting active certificate', [
                'id' => $id,
                'name' => $certificate['name']
            ]);
        }

        // Delete from database
        $result = $wpdb->delete(
            $this->tableName,
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error('Failed to delete certificate', [
                'id' => $id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }

        // Try to cleanup certificate file
        if (!empty($certificate['file_path']) && file_exists($certificate['file_path'])) {
            if (unlink($certificate['file_path'])) {
                $this->logger->info('Certificate file cleaned up', [
                    'file_path' => $certificate['file_path']
                ]);
            } else {
                $this->logger->warning('Failed to delete certificate file', [
                    'file_path' => $certificate['file_path']
                ]);
            }
        }

        $this->logger->info('Certificate deleted successfully', [
            'id' => $id,
            'affected_rows' => $result,
            'name' => $certificate['name']
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
     * Get certificate statistics
     */
    public function getStatistics(): array
    {
        global $wpdb;

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_certificates,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_certificates,
                SUM(CASE WHEN valid_to < CURDATE() THEN 1 ELSE 0 END) as expired_certificates,
                SUM(CASE WHEN valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon
            FROM {$this->tableName}
        ");

        if (!$stats) {
            return [
                'total_certificates' => 0,
                'active_certificates' => 0,
                'expired_certificates' => 0,
                'expiring_soon' => 0,
            ];
        }

        return [
            'total_certificates' => (int) $stats->total_certificates,
            'active_certificates' => (int) $stats->active_certificates,
            'expired_certificates' => (int) $stats->expired_certificates,
            'expiring_soon' => (int) $stats->expiring_soon,
        ];
    }

    /**
     * Check if there are certificates expiring soon
     */
    public function getExpirationAlerts(int $daysAhead = 30): array
    {
        $expiringSoon = $this->findExpiringSoon($daysAhead);
        $expired = $this->findExpired();

        $alerts = [];

        foreach ($expiringSoon as $cert) {
            $alerts[] = [
                'level' => 'warning',
                'certificate_id' => $cert['id'],
                'certificate_name' => $cert['name'],
                'expires_on' => $cert['valid_to'],
                'days_remaining' => floor((strtotime($cert['valid_to']) - time()) / (60 * 60 * 24)),
                'message' => sprintf(
                    'Certificate "%s" expires on %s',
                    $cert['name'],
                    date('Y-m-d', strtotime($cert['valid_to']))
                ),
            ];
        }

        foreach ($expired as $cert) {
            $alerts[] = [
                'level' => 'critical',
                'certificate_id' => $cert['id'],
                'certificate_name' => $cert['name'],
                'expires_on' => $cert['valid_to'],
                'days_expired' => floor((time() - strtotime($cert['valid_to'])) / (60 * 60 * 24)),
                'message' => sprintf(
                    'Certificate "%s" has expired on %s',
                    $cert['name'],
                    date('Y-m-d', strtotime($cert['valid_to']))
                ),
            ];
        }

        // Sort by severity then by expiry date
        usort($alerts, function ($a, $b) {
            $levelOrder = ['critical' => 3, 'warning' => 2, 'info' => 1];
            if ($levelOrder[$a['level']] !== $levelOrder[$b['level']]) {
                return $levelOrder[$b['level']] - $levelOrder[$a['level']];
            }
            return strcmp($a['expires_on'] ?? '', $b['expires_on'] ?? '');
        });

        return $alerts;
    }

    /**
     * Prepare insert data
     */
    private function prepareInsertData(array $data): array
    {
        $insertData = $data;

        // Set timestamps
        $currentTime = current_time('mysql');
        $insertData['created_at'] = $insertData['created_at'] ?? $currentTime;
        $insertData['updated_at'] = $insertData['updated_at'] ?? $currentTime;

        // Ensure is_active has default value
        $insertData['is_active'] = $insertData['is_active'] ?? 0;

        return $insertData;
    }

    /**
     * Prepare update data
     */
    private function prepareUpdateData(array $data): array
    {
        // No special processing needed
        return $data;
    }

    /**
     * Get format array for wpdb prepare
     */
    private function getFormatArray(array $data): array
    {
        $formats = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'is_active'])) {
                $formats[] = '%d';
            } elseif (in_array($key, ['valid_from', 'valid_to', 'created_at', 'updated_at'])) {
                $formats[] = '%s';
            } elseif (is_int($value)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
