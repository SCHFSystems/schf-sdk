<?php

namespace Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Bundle\Manifest;

class ManifestTest extends TestCase
{
    private array $validData;

    protected function setUp(): void
    {
        $this->validData = [
            'bundle_version' => '1.0.0',
            'sdk_version' => '0.1.0',
            'core_min_version' => '1.5.0',
            'core_max_version' => null,
            'generated_at' => '2026-06-28T00:00:00Z',
            'generator' => ['name' => 'test', 'version' => '1.0.0', 'plugin' => null],
            'organization' => ['external_id' => 'org-1', 'name' => 'Test Org'],
            'source' => ['type' => 'firebird', 'inventory_hash' => str_repeat('a', 64)],
            'files' => [
                ['path' => 'organization.json', 'schema' => 'org.schema.json', 'required' => true, 'records' => 1, 'sha256' => str_repeat('b', 64)],
            ],
        ];
    }

    public function testCreateFromJson()
    {
        $manifest = Manifest::fromJson(json_encode($this->validData));
        $this->assertInstanceOf(Manifest::class, $manifest);
    }

    public function testCreateStatic()
    {
        $manifest = Manifest::create(
            '1.0.0',
            '0.1.0',
            '1.5.0',
            ['name' => 'test', 'version' => '1.0.0', 'plugin' => null],
            ['external_id' => 'org-1', 'name' => 'Test'],
            ['type' => 'firebird', 'inventory_hash' => str_repeat('c', 64)],
            [['path' => 'test.json', 'schema' => 'test.schema.json', 'required' => true, 'records' => 0, 'sha256' => str_repeat('d', 64)]]
        );
        $this->assertEquals('1.0.0', $manifest->getBundleVersion());
        $this->assertEquals('Test', $manifest->getOrganization()['name']);
    }

    public function testGetters()
    {
        $manifest = Manifest::fromJson(json_encode($this->validData));
        $this->assertEquals('1.0.0', $manifest->getBundleVersion());
        $this->assertEquals('0.1.0', $manifest->getSdkVersion());
        $this->assertEquals('1.5.0', $manifest->getCoreMinVersion());
        $this->assertNull($manifest->getCoreMaxVersion());
        $this->assertEquals('Test Org', $manifest->getOrganization()['name']);
        $this->assertEquals('firebird', $manifest->getSource()['type']);
    }

    public function testGetRequiredFiles()
    {
        $manifest = Manifest::fromJson(json_encode($this->validData));
        $required = $manifest->getRequiredFiles();
        $this->assertCount(1, $required);
    }

    public function testGetFileByPath()
    {
        $manifest = Manifest::fromJson(json_encode($this->validData));
        $file = $manifest->getFileByPath('organization.json');
        $this->assertNotNull($file);
        $this->assertEquals(1, $file['records']);

        $this->assertNull($manifest->getFileByPath('nonexistent.json'));
    }

    public function testGetRecordCount()
    {
        $manifest = Manifest::fromJson(json_encode($this->validData));
        $this->assertEquals(1, $manifest->getRecordCount());
    }

    public function testToArray()
    {
        $manifest = Manifest::fromJson(json_encode($this->validData));
        $arr = $manifest->toArray();
        $this->assertEquals('1.0.0', $arr['bundle_version']);
    }

    public function testToJson()
    {
        $manifest = Manifest::fromJson(json_encode($this->validData));
        $json = $manifest->toJson();
        $decoded = json_decode($json, true);
        $this->assertEquals('1.0.0', $decoded['bundle_version']);
    }

    public function testFromFileThrowsOnMissing()
    {
        $this->expectException(\RuntimeException::class);
        Manifest::fromFile('/nonexistent/manifest.json');
    }
}
