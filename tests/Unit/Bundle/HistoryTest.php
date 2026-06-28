<?php

namespace Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Bundle\Builder;
use SCHF\SDK\Bundle\History;
use SCHF\SDK\Bundle\Validator;

class HistoryTest extends TestCase
{
    private string $storagePath;
    private string $bundlePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/schf_test_history_' . bin2hex(random_bytes(4));

        $builder = new Builder();
        $builder->setOrganization('org-hist', 'History Hospital');
        $builder->setSource('firebird', 'SGH', '2.0');
        $builder->addRecords('suppliers.json', [
            ['external_id' => 'S1', 'name' => 'Supplier'],
        ]);
        $this->bundlePath = $builder->build();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->bundlePath)) {
            unlink($this->bundlePath);
        }
        if (is_dir($this->storagePath)) {
            array_map('unlink', glob("{$this->storagePath}/*"));
            rmdir($this->storagePath);
        }
    }

    public function testRecord()
    {
        $history = new History($this->storagePath);
        $result = $history->record($this->bundlePath);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('entry', $result);
        $this->assertEquals('History Hospital', $result['entry']['client']);
        $this->assertEquals(1, $history->count());
    }

    public function testRecordInvalidBundle()
    {
        $history = new History($this->storagePath);
        $result = $history->record('/nonexistent/file.schf');

        $this->assertFalse($result['success']);
    }

    public function testFindByUuid()
    {
        $history = new History($this->storagePath);
        $result = $history->record($this->bundlePath);
        $uuid = $result['entry']['uuid'];

        $found = $history->findByUuid($uuid);
        $this->assertNotNull($found);
        $this->assertEquals($uuid, $found['uuid']);

        $this->assertNull($history->findByUuid('nonexistent-uuid'));
    }

    public function testFindByClient()
    {
        $history = new History($this->storagePath);
        $history->record($this->bundlePath);

        $records = $history->findByClient('History Hospital');
        $this->assertCount(1, $records);

        $records = $history->findByClient('Other Client');
        $this->assertCount(0, $records);
    }

    public function testGetLatest()
    {
        $history = new History($this->storagePath);
        $history->record($this->bundlePath);

        $latest = $history->getLatest();
        $this->assertNotNull($latest);
        $this->assertEquals('History Hospital', $latest['client']);

        $latestPerClient = $history->getLatest('History Hospital');
        $this->assertNotNull($latestPerClient);

        $this->assertNull($history->getLatest('Nonexistent'));
    }

    public function testFindAll()
    {
        $history = new History($this->storagePath);
        $history->record($this->bundlePath);

        $all = $history->findAll();
        $this->assertCount(1, $all);
    }

    public function testCount()
    {
        $history = new History($this->storagePath);
        $this->assertEquals(0, $history->count());
        $history->record($this->bundlePath);
        $this->assertEquals(1, $history->count());
    }

    public function testPersistenceAcrossInstances()
    {
        $history1 = new History($this->storagePath);
        $history1->record($this->bundlePath);
        $this->assertEquals(1, $history1->count());

        $history2 = new History($this->storagePath);
        $this->assertEquals(1, $history2->count());
    }
}
