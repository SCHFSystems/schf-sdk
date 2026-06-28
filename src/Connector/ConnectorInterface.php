<?php

declare(strict_types=1);

namespace SCHF\SDK\Connector;

/**
 * Interface that every database connector must implement.
 *
 * This is the official contract between schf-connectors and schf-migration.
 * Connectors only connect, query, and describe the schema.
 * All normalization/inventory/validation logic lives in schf-migration.
 */
interface ConnectorInterface
{
    /**
     * @param array{
     *     host?: string,
     *     port?: int,
     *     dbname: string,
     *     username: string,
     *     password: string,
     *     charset?: string,
     * } $params
     */
    public function connect(array $params): void;

    public function disconnect(): void;

    /**
     * @return string One of: 'firebird', 'mysql', 'postgresql', 'sqlserver', 'oracle', 'sqlite'
     */
    public function getDriverName(): string;

    /**
     * @return array{table_name: string, columns: array<array{name: string, type: string, nullable: bool, default: mixed}>}[]
     */
    public function getSchema(): array;

    /**
     * @param  string $sql
     * @param  array  $params
     * @return \Iterator<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): \Iterator;

    /**
     * @param  string $sql
     * @param  array  $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array;
}
