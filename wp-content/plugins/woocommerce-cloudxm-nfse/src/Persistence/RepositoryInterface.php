<?php

namespace CloudXM\NFSe\Persistence;

/**
 * Base Repository Interface
 */
interface RepositoryInterface
{
    /**
     * Find entity by ID
     *
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array;

    /**
     * Find all entities
     *
     * @param array $conditions
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll(array $conditions = [], int $limit = 50, int $offset = 0): array;

    /**
     * Find by conditions
     *
     * @param array $conditions
     * @return array|null
     */
    public function findOneBy(array $conditions): ?array;

    /**
     * Save entity
     *
     * @param array $data
     * @return int New ID
     */
    public function save(array $data): int;

    /**
     * Update entity
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete entity
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Count entities
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int;
}