<?php

namespace Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Bundle\Builder;
use SCHF\SDK\Bundle\Validator;

class BuilderTest extends TestCase
{
    public function testBuildEmptyBundle()
    {
        $builder = new Builder();
        $builder->setOrganization('org-test', 'Test Organization');
        $builder->setSource('firebird', 'SGH', '1.0', str_repeat('f', 64));

        $path = $builder->build();

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.schf', $path);

        $validator = new Validator();
        $result = $validator->validate($path);

        $this->assertTrue($result['valid'], 'Empty bundle should be valid: ' . implode(', ', $result['errors']));

        unlink($path);
    }

    public function testBuildWithRecords()
    {
        $builder = new Builder();
        $builder->setOrganization('org-42', 'My Hospital');
        $builder->setSource('firebird', 'SGH', '2.0', str_repeat('0', 64));
        $builder->addRecords('suppliers.json', [
            ['external_id' => 'S001', 'name' => 'Fornecedor A', 'active' => true],
            ['external_id' => 'S002', 'name' => 'Fornecedor B', 'active' => true],
        ]);

        $path = $builder->build();

        $this->assertFileExists($path);

        $validator = new Validator();
        $result = $validator->validate($path);
        $this->assertTrue($result['valid'], 'Bundle with records should be valid');

        $manifest = $validator->getManifest();
        $this->assertNotNull($manifest);
        $this->assertEquals('My Hospital', $manifest->getOrganization()['name']);

        $file = $manifest->getFileByPath('suppliers.json');
        $this->assertNotNull($file);
        $this->assertEquals(2, $file['records']);

        unlink($path);
    }

    public function testBuildPreview()
    {
        $builder = new Builder();
        $builder->setOrganization('org-preview', 'Preview Org');
        $builder->setSource('mysql');
        $builder->addRecords('users.json', [
            ['external_id' => 'U1', 'name' => 'User One'],
        ]);

        $preview = $builder->buildPreview();

        $this->assertArrayHasKey('bundle_version', $preview);
        $this->assertArrayHasKey('files', $preview);
        $this->assertArrayHasKey('total_records', $preview);
        $this->assertSame('1.0.0', $preview['sdk_version']);
        $this->assertGreaterThan(0, $preview['total_records']);
        $this->assertEquals(1, $preview['files'][0]['records']);
    }

    public function testBuildWithMultipleRecordTypes()
    {
        $builder = new Builder();
        $builder->setOrganization('org-full', 'Full Bundle');
        $builder->setSource('firebird', 'SGH', '3.0');
        $builder->addRecords('suppliers.json', [
            ['external_id' => 'S1', 'name' => 'Sup 1'],
        ]);
        $builder->addRecords('categories.json', [
            ['external_id' => 'C1', 'name' => 'Cat 1'],
            ['external_id' => 'C2', 'name' => 'Cat 2'],
        ]);
        $builder->addRecords('accounts.json', [
            ['external_id' => 'A1', 'name' => 'Account 1', 'bank_external_id' => 'B1'],
        ]);

        $path = $builder->build();

        $validator = new Validator();
        $result = $validator->validate($path);
        $this->assertTrue($result['valid']);

        $manifest = $validator->getManifest();
        $this->assertEquals(1, $manifest->getFileByPath('suppliers.json')['records']);
        $this->assertEquals(2, $manifest->getFileByPath('categories.json')['records']);
        $this->assertEquals(1, $manifest->getFileByPath('accounts.json')['records']);

        unlink($path);
    }

    public function testBuildWithGenerator()
    {
        $builder = new Builder();
        $builder->setOrganization('org-gen', 'Generator Test');
        $builder->setSource('firebird');
        $builder->setGenerator('sdk-test', '2.0.0', 'custom-plugin');

        $path = $builder->build();
        $validator = new Validator();
        $result = $validator->validate($path);
        $this->assertTrue($result['valid']);

        $manifest = $validator->getManifest();
        $generator = $manifest->getGenerator();
        $this->assertEquals('sdk-test', $generator['name']);
        $this->assertEquals('2.0.0', $generator['version']);
        $this->assertEquals('custom-plugin', $generator['plugin']);

        unlink($path);
    }

    public function testAddRecordsThrowsOnUnknownFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        $builder = new Builder();
        $builder->addRecords('unknown.json', []);
    }

    public function testBuildCleanup()
    {
        $builder = new Builder();
        $builder->setOrganization('org-cleanup', 'Cleanup Test');
        $builder->setSource('firebird');
        $path = $builder->build();
        $this->assertFileExists($path);
        unlink($path);
    }
}
