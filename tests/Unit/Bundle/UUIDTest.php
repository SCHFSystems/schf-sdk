<?php

namespace Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Bundle\UUID;

class UUIDTest extends TestCase
{
    public function testV4GeneratesValidUuid()
    {
        $uuid = UUID::v4();
        $this->assertTrue(UUID::isValid($uuid), "Generated UUID '{$uuid}' is not valid");
    }

    public function testV4VersionBits()
    {
        $uuid = UUID::v4();
        $parts = explode('-', $uuid);
        $this->assertEquals('4', $parts[2][0], "UUID version nibble should be 4");
    }

    public function testV4VariantBits()
    {
        $uuid = UUID::v4();
        $parts = explode('-', $uuid);
        $variant = $parts[3][0];
        $this->assertContains($variant, ['8', '9', 'a', 'b'], "UUID variant should be 10xx");
    }

    public function testIsValid()
    {
        $this->assertTrue(UUID::isValid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertTrue(UUID::isValid('f47ac10b-58cc-4372-a567-0e02b2c3d479'));
        $this->assertFalse(UUID::isValid('not-a-uuid'));
        $this->assertFalse(UUID::isValid(''));
        $this->assertFalse(UUID::isValid('550e8400e29b41d4a716446655440000'));
    }

    public function testV4Uniqueness()
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = UUID::v4();
        }
        $this->assertEquals(100, count(array_unique($uuids)));
    }
}
