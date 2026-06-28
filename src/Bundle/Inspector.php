<?php

namespace SCHF\SDK\Bundle;

class Inspector
{
    private ?Validator $validator = null;
    private ?string $extractDir = null;

    public function open(string $bundlePath): array
    {
        $this->validator = new Validator();
        $validation = $this->validator->validate($bundlePath);

        if (!$validation['valid']) {
            return [
                'valid' => false,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ];
        }

        $this->extractDir = $this->validator->getExtractDir();

        $manifest = $this->validator->getManifest();
        $data = $manifest ? $manifest->toArray() : [];

        return [
            'valid' => true,
            'manifest' => $data,
            'summary' => $this->summarize($this->extractDir),
            'organizations' => $this->readJson('organization.json'),
            'warnings' => $validation['warnings'],
        ];
    }

    public function getOrganizations(): array
    {
        return $this->readJson('organization.json') ?? [];
    }

    public function getSuppliers(): array
    {
        return $this->readJson('suppliers.json') ?? [];
    }

    public function getCategories(): array
    {
        return $this->readJson('categories.json') ?? [];
    }

    public function getAccounts(): array
    {
        return $this->readJson('accounts.json') ?? [];
    }

    public function getPayments(): array
    {
        return $this->readJson('payments.json') ?? [];
    }

    public function getUsers(): array
    {
        return $this->readJson('users.json') ?? [];
    }

    public function getRecordCount(): int
    {
        $total = 0;
        $recordFiles = array_keys(Contract::RECORD_FILES);
        foreach ($recordFiles as $file) {
            $data = $this->readJson($file);
            if (is_array($data)) {
                $total += count($data);
            }
        }
        return $total;
    }

    public function getHistory(): array
    {
        $manifest = $this->readJson('manifest.json');
        if (!$manifest) {
            return [];
        }
        return [
            'uuid' => $manifest['source']['bundle_uuid'] ?? null,
            'version' => $manifest['bundle_version'] ?? null,
            'generated_at' => $manifest['generated_at'] ?? null,
            'generator' => $manifest['generator'] ?? null,
            'source' => $manifest['source'] ?? null,
            'organization' => $manifest['organization'] ?? null,
            'sdk_version' => $manifest['sdk_version'] ?? null,
            'inventory_hash' => $manifest['source']['inventory_hash'] ?? null,
        ];
    }

    public function close(): void
    {
        $this->validator = null;
        $this->extractDir = null;
    }

    private function summarize(string $dir): array
    {
        $summary = [];
        $recordFiles = array_merge(
            ['organization.json'],
            array_keys(Contract::RECORD_FILES)
        );

        foreach ($recordFiles as $file) {
            $path = "{$dir}/{$file}";
            if (!file_exists($path)) {
                continue;
            }
            $payload = json_decode(file_get_contents($path), true);
            $summary[$file] = is_array($payload) && array_is_list($payload) ? count($payload) : 1;
        }

        return $summary;
    }

    private function readJson(string $file): ?array
    {
        if ($this->extractDir === null) {
            return null;
        }
        $path = "{$this->extractDir}/{$file}";
        if (!file_exists($path)) {
            return null;
        }
        return json_decode(file_get_contents($path), true);
    }
}
