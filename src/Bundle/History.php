<?php

namespace SCHF\SDK\Bundle;

class History
{
    private array $records = [];
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/schf_history';
    }

    public function record(string $bundlePath, ?Manifest $manifest = null): array
    {
        if ($manifest === null) {
            $validator = new Validator();
            $validation = $validator->validate($bundlePath);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => 'Cannot record history for invalid bundle'];
            }
            $manifest = $validator->getManifest();
        }

        if ($manifest === null) {
            return ['success' => false, 'error' => 'Cannot record history without manifest'];
        }

        $data = $manifest->toArray();
        $entry = [
            'uuid' => $data['source']['bundle_uuid'] ?? UUID::v4(),
            'client' => $data['organization']['name'] ?? 'unknown',
            'bundle_version' => $data['bundle_version'],
            'sdk_version' => $data['sdk_version'],
            'generated_at' => $data['generated_at'],
            'generator' => $data['generator']['name'] ?? 'unknown',
            'generator_version' => $data['generator']['version'] ?? 'unknown',
            'source_type' => $data['source']['type'] ?? 'unknown',
            'inventory_hash' => $data['source']['inventory_hash'] ?? '',
            'file_count' => count($data['files'] ?? []),
            'record_count' => array_sum(array_map(fn($f) => $f['records'] ?? 0, $data['files'] ?? [])),
            'bundle_path' => $bundlePath,
            'bundle_hash' => file_exists($bundlePath) ? strtoupper(hash_file('sha256', $bundlePath)) : '',
            'recorded_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ];

        $this->load();
        $this->records[] = $entry;
        $this->save();

        return ['success' => true, 'entry' => $entry];
    }

    public function findByUuid(string $uuid): ?array
    {
        $this->load();
        foreach ($this->records as $entry) {
            if ($entry['uuid'] === $uuid) {
                return $entry;
            }
        }
        return null;
    }

    public function findByClient(string $client): array
    {
        $this->load();
        return array_values(array_filter($this->records, fn($e) => $e['client'] === $client));
    }

    public function findAll(): array
    {
        $this->load();
        return $this->records;
    }

    public function getLatest(?string $client = null): ?array
    {
        $this->load();
        $records = $client ? $this->findByClient($client) : $this->records;
        if (empty($records)) {
            return null;
        }
        usort($records, fn($a, $b) => strcmp($b['recorded_at'], $a['recorded_at']));
        return $records[0];
    }

    public function count(): int
    {
        $this->load();
        return count($this->records);
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    private function load(): void
    {
        $file = "{$this->storagePath}/history.json";
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $this->records = is_array($data) ? $data : [];
        }
    }

    private function save(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
        file_put_contents(
            "{$this->storagePath}/history.json",
            json_encode($this->records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
