<?php

declare(strict_types=1);

namespace Tests\Unit\Drivers;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Connector\Drivers\FirebirdDriver;
use SCHF\SDK\Connector\ConnectorInterface;

class FirebirdDriverTest extends TestCase
{
    private FirebirdDriver $connector;

    protected function setUp(): void
    {
        $this->connector = new FirebirdDriver();
    }

    public function test_implements_connector_interface(): void
    {
        $this->assertInstanceOf(ConnectorInterface::class, $this->connector);
    }

    public function test_get_driver_name_returns_firebird(): void
    {
        $this->assertSame('firebird', $this->connector->getDriverName());
    }

    public function test_disconnect_sets_pdo_to_null(): void
    {
        $pdoMock = $this->createMock(\PDO::class);
        $this->setPrivatePdo($pdoMock);
        $this->connector->disconnect();
        $this->assertNull($this->getPrivatePdo());
    }

    public function test_get_schema_parses_firebird_metadata(): void
    {
        $tablesStmt = $this->createMock(\PDOStatement::class);
        $tablesStmt->method('execute')->willReturn(true);
        $tablesStmt->method('fetchAll')->willReturn([
            ['TABLE_NAME' => 'CLIENTES        '],
            ['TABLE_NAME' => 'PRODUTOS        '],
        ]);

        $columnsForClientes = [
            [
                'COLUMN_NAME' => 'COD_CLIENTE    ',
                'TYPE_ID' => 8,
                'LENGTH' => 4,
                'SCALE' => 0,
                'PRECISION' => null,
                'NULL_FLAG' => null,
                'DEFAULT_VALUE' => null,
            ],
            [
                'COLUMN_NAME' => 'NOME           ',
                'TYPE_ID' => 37,
                'LENGTH' => 60,
                'SCALE' => 0,
                'PRECISION' => null,
                'NULL_FLAG' => 1,
                'DEFAULT_VALUE' => null,
            ],
        ];

        $callCount = 0;
        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->method('prepare')->willReturnCallback(function () use ($tablesStmt, $columnsForClientes, &$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return $tablesStmt;
            }
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->method('execute')->willReturn(true);
            if ($callCount === 2) {
                $stmt->method('fetchAll')->willReturn($columnsForClientes);
            } else {
                $stmt->method('fetchAll')->willReturn([]);
            }
            return $stmt;
        });

        $this->setPrivatePdo($pdoMock);

        $schema = $this->connector->getSchema();

        $this->assertCount(2, $schema);

        $this->assertSame('CLIENTES', $schema[0]['table_name']);
        $this->assertArrayHasKey('COD_CLIENTE', $schema[0]['columns']);
        $this->assertArrayHasKey('NOME', $schema[0]['columns']);

        $this->assertSame('integer', $schema[0]['columns']['COD_CLIENTE']['type']);
        $this->assertTrue($schema[0]['columns']['COD_CLIENTE']['nullable']);
        $this->assertSame('string', $schema[0]['columns']['NOME']['type']);
        $this->assertFalse($schema[0]['columns']['NOME']['nullable']);
    }

    public function test_get_schema_handles_empty_database(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->method('prepare')->willReturn($stmt);

        $this->setPrivatePdo($pdoMock);

        $schema = $this->connector->getSchema();

        $this->assertIsArray($schema);
        $this->assertCount(0, $schema);
    }

    public function test_fetch_all_executes_query(): void
    {
        $expectedRows = [['ID' => 1, 'NAME' => 'Test']];

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with([':id' => 1])->willReturn(true);
        $stmt->expects($this->once())->method('fetchAll')->willReturn($expectedRows);

        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->expects($this->once())->method('prepare')
            ->with('SELECT * FROM T WHERE ID = :id')
            ->willReturn($stmt);

        $this->setPrivatePdo($pdoMock);

        $result = $this->connector->fetchAll('SELECT * FROM T WHERE ID = :id', [':id' => 1]);

        $this->assertSame($expectedRows, $result);
    }

    private function setPrivatePdo(?\PDO $pdo): void
    {
        $reflection = new \ReflectionProperty($this->connector, 'pdo');
        $reflection->setAccessible(true);
        $reflection->setValue($this->connector, $pdo);
    }

    private function getPrivatePdo(): ?\PDO
    {
        $reflection = new \ReflectionProperty($this->connector, 'pdo');
        $reflection->setAccessible(true);
        return $reflection->getValue($this->connector);
    }
}
