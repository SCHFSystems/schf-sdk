<?php

namespace Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Bundle\Version;

class VersionTest extends TestCase
{
    public function testCurrentVersionIsSemver()
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Version::current());
    }

    public function testIsValid()
    {
        $this->assertTrue(Version::isValid('1.0.0'));
        $this->assertTrue(Version::isValid('0.1.0'));
        $this->assertTrue(Version::isValid('99.99.99'));
        $this->assertFalse(Version::isValid('1.0'));
        $this->assertFalse(Version::isValid('1'));
        $this->assertFalse(Version::isValid('abc'));
        $this->assertFalse(Version::isValid('1.0.0-beta'));
    }

    public function testCompare()
    {
        $this->assertEquals(0, Version::compare('1.0.0', '1.0.0'));
        $this->assertEquals(-1, Version::compare('1.0.0', '2.0.0'));
        $this->assertEquals(1, Version::compare('2.0.0', '1.0.0'));
    }

    public function testIsCompatible()
    {
        $result = Version::isCompatible('1.0.0', '1.5.0');
        $this->assertTrue($result['compatible']);
        $this->assertEmpty($result['issues']);
    }

    public function testIsCompatibleWithMajorMismatch()
    {
        $result = Version::isCompatible('2.0.0', '1.5.0');
        $this->assertFalse($result['compatible']);
    }

    public function testIsCompatibleWithCoreTooOld()
    {
        $result = Version::isCompatible('1.0.0', '1.0.0');
        $this->assertFalse($result['compatible']);
    }

    public function testIsSdkCompatible()
    {
        $this->assertTrue(Version::isSdkCompatible('0.1.0'));
        $this->assertTrue(Version::isSdkCompatible('1.0.0'));
        $this->assertFalse(Version::isSdkCompatible('0.0.9'));
    }

    public function testBumpMajor()
    {
        $this->assertEquals('2.0.0', Version::bumpMajor('1.5.3'));
    }

    public function testBumpMinor()
    {
        $this->assertEquals('1.6.0', Version::bumpMinor('1.5.3'));
    }

    public function testBumpPatch()
    {
        $this->assertEquals('1.5.4', Version::bumpPatch('1.5.3'));
    }

    public function testParse()
    {
        $parsed = Version::parse('1.2.3');
        $this->assertNotNull($parsed);
        $this->assertEquals(1, $parsed['major']);
        $this->assertEquals(2, $parsed['minor']);
        $this->assertEquals(3, $parsed['patch']);
    }

    public function testParseInvalid()
    {
        $this->assertNull(Version::parse('not-a-version'));
    }
}
