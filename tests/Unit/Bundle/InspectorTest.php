<?php

namespace Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Bundle\Builder;
use SCHF\SDK\Bundle\Inspector;

class InspectorTest extends TestCase
{
    private string $bundlePath;

    protected function setUp(): void
    {
        $builder = new Builder();
        $builder->setOrganization('org-insp', 'Inspector Hospital');
        $builder->setSource('firebird', 'SGH', '4.0');
        $builder->addRecords('suppliers.json', [
            ['external_id' => 'S1', 'name' => 'Supplier A'],
            ['external_id' => 'S2', 'name' => 'Supplier B'],
            ['external_id' => 'S3', 'name' => 'Supplier C'],
        ]);
        $builder->addRecords('categories.json', [
            ['external_id' => 'C1', 'name' => 'Category 1'],
        ]);
        $builder->addRecords('accounts.json', [
            ['external_id' => 'A1', 'name' => 'Account 1', 'type' => 'checking'],
        ]);
        $this->bundlePath = $builder->build();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->bundlePath)) {
            unlink($this->bundlePath);
        }
    }

    public function testOpenValidBundle()
    {
        $inspector = new Inspector();
        $result = $inspector->open($this->bundlePath);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('manifest', $result);
        $this->assertArrayHasKey('summary', $result);
        $inspector->close();
    }

    public function testGetOrganizations()
    {
        $inspector = new Inspector();
        $inspector->open($this->bundlePath);

        $orgs = $inspector->getOrganizations();
        $this->assertNotEmpty($orgs);
        $this->assertEquals('Inspector Hospital', $orgs['name']);

        $inspector->close();
    }

    public function testGetSuppliers()
    {
        $inspector = new Inspector();
        $inspector->open($this->bundlePath);

        $suppliers = $inspector->getSuppliers();
        $this->assertCount(3, $suppliers);

        $inspector->close();
    }

    public function testGetCategories()
    {
        $inspector = new Inspector();
        $inspector->open($this->bundlePath);

        $categories = $inspector->getCategories();
        $this->assertCount(1, $categories);

        $inspector->close();
    }

    public function testGetAccounts()
    {
        $inspector = new Inspector();
        $inspector->open($this->bundlePath);

        $accounts = $inspector->getAccounts();
        $this->assertCount(1, $accounts);

        $inspector->close();
    }

    public function testGetRecordCount()
    {
        $inspector = new Inspector();
        $inspector->open($this->bundlePath);

        $count = $inspector->getRecordCount();
        $this->assertEquals(5, $count);

        $inspector->close();
    }

    public function testGetHistory()
    {
        $inspector = new Inspector();
        $inspector->open($this->bundlePath);

        $history = $inspector->getHistory();
        $this->assertNotNull($history['uuid']);
        $this->assertEquals('1.0.0', $history['version']);
        $this->assertEquals('Inspector Hospital', $history['organization']['name']);

        $inspector->close();
    }

    public function testOpenInvalidFile()
    {
        $inspector = new Inspector();
        $result = $inspector->open('/nonexistent/file.schf');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $inspector->close();
    }

    public function testOpenCloseReopen()
    {
        $inspector = new Inspector();
        $result = $inspector->open($this->bundlePath);
        $this->assertTrue($result['valid']);
        $inspector->close();

        $result2 = $inspector->open($this->bundlePath);
        $this->assertTrue($result2['valid']);
        $inspector->close();
    }
}
