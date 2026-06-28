<?php

declare(strict_types=1);

namespace SCHF\SDK\Connector\Drivers;

use PDO;
use PDOException;
use SCHF\SDK\Connector\ConnectorInterface;

class FirebirdDriver implements ConnectorInterface
{
    private ?PDO $pdo = null;

    public function connect(array $params): void
    {
        $host     = $params['host'] ?? 'localhost';
        $port     = $params['port'] ?? 3050;
        $dbname   = $params['dbname'];
        $username = $params['username'];
        $password = $params['password'];
        $charset  = $params['charset'] ?? 'UTF8';

        $dsn = "firebird:dbname={$host}/{$port}:{$dbname};charset={$charset}";

        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function getDriverName(): string
    {
        return 'firebird';
    }

    public function getSchema(): array
    {
        $tables = $this->fetchAll(
            "SELECT RDB\$RELATION_NAME AS TABLE_NAME
               FROM RDB\$RELATIONS
              WHERE RDB\$SYSTEM_FLAG = 0
                AND RDB\$VIEW_BLR IS NULL
              ORDER BY RDB\$RELATION_NAME"
        );

        $schema = [];

        foreach ($tables as $table) {
            $raw = $table['TABLE_NAME'];
            $tableName = trim($raw);

            $columns = $this->fetchAll(
                "SELECT RF.RDB\$FIELD_NAME AS COLUMN_NAME,
                        F.RDB\$FIELD_TYPE AS TYPE_ID,
                        F.RDB\$FIELD_LENGTH AS LENGTH,
                        F.RDB\$FIELD_SCALE AS SCALE,
                        F.RDB\$FIELD_PRECISION AS PRECISION,
                        RF.RDB\$NULL_FLAG AS NULL_FLAG,
                        RF.RDB\$DEFAULT_VALUE AS DEFAULT_VALUE
                   FROM RDB\$RELATION_FIELDS RF
                   JOIN RDB\$FIELDS F ON F.RDB\$FIELD_NAME = RF.RDB\$FIELD_SOURCE
                  WHERE RF.RDB\$RELATION_NAME = ?
                  ORDER BY RF.RDB\$FIELD_POSITION",
                [$raw]
            );

            $cols = [];

            foreach ($columns as $col) {
                $colName = trim($col['COLUMN_NAME']);
                $typeId  = (int) $col['TYPE_ID'];
                $length  = (int) $col['LENGTH'];
                $scale   = (int) $col['SCALE'];

                $cols[$colName] = [
                    'name'     => $colName,
                    'type'     => $this->mapType($typeId, $scale),
                    'length'   => $length,
                    'scale'    => $scale,
                    'nullable' => ($col['NULL_FLAG'] === null),
                    'default'  => $this->cleanDefault($col['DEFAULT_VALUE']),
                ];
            }

            $schema[] = [
                'table_name' => $tableName,
                'columns'    => $cols,
            ];
        }

        return $schema;
    }

    public function query(string $sql, array $params = []): \Iterator
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function mapType(int $typeId, int $scale): string
    {
        return match ($typeId) {
            7, 8, 16 => $scale === 0 ? 'integer' : 'float',
            10, 11, 14, 27 => 'float',
            12, 13, 35 => 'datetime',
            37, 38, 40 => 'string',
            23 => 'boolean',
            45, 261 => 'text',
            default => 'string',
        };
    }

    private function cleanDefault(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim($value);
        if (str_starts_with($v, "'") && str_ends_with($v, "'")) {
            return substr($v, 1, -1);
        }
        return $v;
    }
}
