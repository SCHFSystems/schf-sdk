<?php

namespace Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Bundle\Builder;
use SCHF\SDK\Bundle\Doctor;

class DoctorTest extends TestCase
{
    private string $bundlePath;

    protected function setUp(): void
    {
        $builder = new Builder();
        $builder->setOrganization('org-doc', 'Doctor Test Hospital');
        $builder->setSource('firebird', 'SGH', '5.0', str_repeat('d', 64));
        $builder->addRecords('suppliers.json', [
            ['external_id' => 'S1', 'name' => 'Supplier One'],
            ['external_id' => 'S2', 'name' => 'Supplier Two'],
        ]);
        $builder->addRecords('categories.json', [
            ['external_id' => 'C1', 'name' => 'Category One'],
        ]);
        $this->bundlePath = $builder->build();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->bundlePath)) {
            unlink($this->bundlePath);
        }
    }

    public function testDiagnose()
    {
        $doctor = new Doctor();
        $report = $doctor->diagnose($this->bundlePath);

        $this->assertTrue($report['valid']);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('compatibility', $report);
        $this->assertArrayHasKey('integrity', $report);
    }

    public function testDiagnoseWithDeep()
    {
        $doctor = new Doctor();
        $report = $doctor->diagnose($this->bundlePath, true);

        $this->assertTrue($report['valid']);
        $this->assertArrayHasKey('quality', $report);
        $this->assertArrayHasKey('score', $report['quality']);
        $this->assertArrayHasKey('rating', $report['quality']);
    }

    public function testSummarize()
    {
        $doctor = new Doctor();
        $report = $doctor->diagnose($this->bundlePath);
        $summary = $report['summary'];

        $this->assertEquals('1.0.0', $summary['bundle_version']);
        $this->assertEquals('Doctor Test Hospital', $summary['organization']);
        $this->assertEquals('firebird', $summary['source_type']);
        $this->assertGreaterThan(0, $summary['total_records']);
        $this->assertNotNull($summary['uuid']);
    }

    public function testDiagnoseMissingFile()
    {
        $doctor = new Doctor();
        $report = $doctor->diagnose('/nonexistent/bundle.schf');

        $this->assertFalse($report['valid']);
        $this->assertFalse($report['ready_to_import']);
    }

    public function testCheckIntegrity()
    {
        $doctor = new Doctor();
        $report = $doctor->diagnose($this->bundlePath);

        $integrity = $report['integrity'];
        $this->assertTrue($integrity['valid']);
    }

    public function testQualityAssessment()
    {
        $doctor = new Doctor();
        $report = $doctor->diagnose($this->bundlePath, true);

        $quality = $report['quality'];
        $this->assertGreaterThanOrEqual(0, $quality['score']);
        $this->assertLessThanOrEqual(100, $quality['score']);
        $this->assertContains($quality['rating'], ['excellent', 'good', 'fair', 'poor']);
    }

    public function testReadyToImport()
    {
        $doctor = new Doctor();
        $report = $doctor->diagnose($this->bundlePath);

        $this->assertTrue($report['ready_to_import']);
    }
}
