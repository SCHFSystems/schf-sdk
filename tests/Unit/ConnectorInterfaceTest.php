<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Connector\ConnectorInterface;

class ConnectorInterfaceTest extends TestCase
{
    public function test_interface_exists(): void
    {
        $this->assertTrue(interface_exists(ConnectorInterface::class));
    }

    public function test_interface_defines_expected_methods(): void
    {
        $reflection = new \ReflectionClass(ConnectorInterface::class);

        $expectedMethods = [
            'connect',
            'disconnect',
            'getDriverName',
            'getSchema',
            'query',
            'fetchAll',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Interface must define method: {$method}"
            );
        }
    }

    public function test_connect_accepts_config_array(): void
    {
        $reflection = new \ReflectionMethod(ConnectorInterface::class, 'connect');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('params', $params[0]->getName());
        $this->assertSame('array', $params[0]->getType()->getName());
    }

    public function test_getDriverName_returns_string(): void
    {
        $reflection = new \ReflectionMethod(ConnectorInterface::class, 'getDriverName');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('string', $returnType->getName());
    }

    public function test_getSchema_returns_array(): void
    {
        $reflection = new \ReflectionMethod(ConnectorInterface::class, 'getSchema');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType->getName());
    }

    public function test_query_returns_iterator(): void
    {
        $reflection = new \ReflectionMethod(ConnectorInterface::class, 'query');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame(\Iterator::class, $returnType->getName());
    }

    public function test_fetchAll_returns_array(): void
    {
        $reflection = new \ReflectionMethod(ConnectorInterface::class, 'fetchAll');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType->getName());
    }

    public function test_can_be_implemented(): void
    {
        $mock = $this->createMock(ConnectorInterface::class);
        $this->assertInstanceOf(ConnectorInterface::class, $mock);
    }
}
