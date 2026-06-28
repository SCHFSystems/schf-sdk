<?php

namespace Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use SCHF\SDK\Bundle\Builder;
use SCHF\SDK\Bundle\Validator;

class ValidatorTest extends TestCase
{
    public function testValidateValidBundle()
    {
        $builder = new Builder();
        $builder->setOrganization('org-v', 'Validation Test');
        $builder->setSource('firebird');

        $path = $builder->build();
        $validator = new Validator();
        $result = $validator->validate($path);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['manifest']);

        unlink($path);
    }

    public function testValidateMissingFile()
    {
        $validator = new Validator();
        $result = $validator->validate('/nonexistent/file.schf');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateWithRecords()
    {
        $builder = new Builder();
        $builder->setOrganization('org-rec', 'Records Test');
        $builder->setSource('firebird');
        $builder->addRecords('suppliers.json', [
            ['external_id' => 'S1', 'name' => 'Supplier'],
        ]);

        $path = $builder->build();
        $validator = new Validator();
        $result = $validator->validate($path);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        unlink($path);
    }

    public function testManifestHasRequiredFields()
    {
        $builder = new Builder();
        $builder->setOrganization('org-fields', 'Fields Test');
        $builder->setSource('firebird');

        $path = $builder->build();
        $validator = new Validator();
        $result = $validator->validate($path);

        $this->assertTrue($result['valid']);
        $manifest = $result['manifest'];
        $this->assertArrayHasKey('bundle_version', $manifest);
        $this->assertArrayHasKey('sdk_version', $manifest);
        $this->assertArrayHasKey('core_min_version', $manifest);
        $this->assertArrayHasKey('generated_at', $manifest);
        $this->assertArrayHasKey('generator', $manifest);
        $this->assertArrayHasKey('organization', $manifest);
        $this->assertArrayHasKey('source', $manifest);
        $this->assertArrayHasKey('files', $manifest);

        unlink($path);
    }

    public function testValidatorCleanup()
    {
        $builder = new Builder();
        $builder->setOrganization('org-vclean', 'Cleanup Test');
        $builder->setSource('firebird');

        $path = $builder->build();
        $extractDir = null;

        $validator = new Validator();
        $result = $validator->validate($path);
        $this->assertTrue($result['valid']);

        unlink($path);
    }
}
