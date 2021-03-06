<?php

namespace Phoenix\Database\Adapter;

use PDOStatement;
use Phoenix\Database\Element\Structure;
use Phoenix\Database\QueryBuilder\QueryBuilderInterface;

interface AdapterInterface
{
    public function execute(PDOStatement $sql): bool;

    public function query(string $sql): PDOStatement;
    
    /**
     * @return mixed last inserted id
     */
    public function insert(string $table, array $data);

    public function buildInsertQuery(string $table, array $data): PDOStatement;

    public function update(string $table, array $data, array $conditions = [], string $where = ''): bool;

    public function buildUpdateQuery(string $table, array $data, array $conditions = [], string $where = ''): PDOStatement;

    public function delete(string $table, array $conditions = [], string $where = ''): bool;

    public function buildDeleteQuery(string $table, array $conditions = [], string $where = ''): PDOStatement;

    public function select(string $sql): array;

    public function fetch(string $table, array $fields = ['*'], array $conditions = [], array $orders = [], array $groups = []): ?array;

    public function fetchAll(string $table, array $fields = ['*'], array $conditions = [], ?string $limit = null, array $orders = [], array $groups = []): array;

    /**
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder();

    /**
     * Initiates a transaction
     * @return bool
     */
    public function startTransaction(): bool;

    /**
     * Commits a transaction
     * @return bool
     */
    public function commit(): bool;

    /**
     * Rolls back a transaction
     * @return bool
     */
    public function rollback(): bool;

    public function setCharset(string $charset): AdapterInterface;

    public function getCharset(): ?string;

    public function getStructure(): Structure;
}
