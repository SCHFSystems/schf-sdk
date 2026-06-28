<?php

namespace SCHF\SDK\Bundle;

class Builder
{
    private array $records = [];
    private array $generator = ['name' => 'schf-sdk', 'version' => Version::CURRENT];
    private array $organization;
    private array $source = ['type' => 'unknown', 'product' => null, 'version' => null, 'inventory_hash' => ''];
    private string $workDir;
    private bool $includeAttachments = false;
    private array $attachments = [];

    public function __construct()
    {
        $this->workDir = sys_get_temp_dir() . '/schf_build_' . bin2hex(random_bytes(8));
        $this->organization = ['external_id' => '', 'name' => ''];
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    public function setGenerator(string $name, string $version, ?string $plugin = null): self
    {
        $this->generator = ['name' => $name, 'version' => $version, 'plugin' => $plugin];
        return $this;
    }

    public function setOrganization(string $externalId, string $name, array $extra = []): self
    {
        $this->organization = array_merge(['external_id' => $externalId, 'name' => $name], $extra);
        return $this;
    }

    public function setSource(string $type, ?string $product = null, ?string $version = null, string $inventoryHash = ''): self
    {
        $this->source = [
            'type' => $type,
            'product' => $product,
            'version' => $version,
            'inventory_hash' => $inventoryHash ?: str_repeat('0', 64),
        ];
        return $this;
    }

    public function addRecords(string $file, array $records, bool $required = true): self
    {
        if (!isset(Contract::RECORD_FILES[$file])) {
            $known = implode(', ', array_keys(Contract::RECORD_FILES));
            throw new \InvalidArgumentException("Unknown record file: {$file}. Valid: {$known}");
        }
        $this->records[$file] = $records;
        return $this;
    }

    public function addAttachment(string $sourcePath, ?string $bundlePath = null): self
    {
        if (!file_exists($sourcePath)) {
            throw new \InvalidArgumentException("Attachment not found: {$sourcePath}");
        }
        $this->includeAttachments = true;
        $this->attachments[] = [
            'source' => $sourcePath,
            'target' => $bundlePath ?? 'attachments/' . basename($sourcePath),
        ];
        return $this;
    }

    public function build(): string
    {
        $this->ensureWorkDir();
        $this->writeRecordFiles();
        $this->writeOrganization();
        $reportPath = $this->writeReport();
        $manifest = $this->buildManifest($reportPath);
        $this->writeJson("{$this->workDir}/manifest.json", $manifest->toArray());
        $this->writeChecksums();

        if ($this->includeAttachments) {
            $this->writeAttachments();
        }

        return $this->package();
    }

    public function buildPreview(): array
    {
        $files = [];
        foreach ($this->getAllRecordDefinitions() as $path => $schema) {
            $files[] = [
                'path' => $path,
                'schema' => $schema,
                'required' => true,
                'records' => count($this->records[basename($path)] ?? []),
            ];
        }

        return [
            'bundle_version' => Version::CURRENT,
            'sdk_version' => Version::SDK_MIN,
            'core_min_version' => Version::CORE_MIN,
            'source' => $this->source,
            'files' => $files,
            'total_records' => array_sum(array_map(fn($r) => count($r), $this->records)),
            'warnings' => $this->buildWarnings(),
        ];
    }

    public function getWorkDir(): string
    {
        return $this->workDir;
    }

    private function ensureWorkDir(): void
    {
        if (!is_dir($this->workDir)) {
            mkdir($this->workDir, 0755, true);
        }
    }

    private function writeRecordFiles(): void
    {
        foreach (Contract::RECORD_FILES as $file => $schema) {
            $records = $this->records[$file] ?? [];
            $this->writeJson("{$this->workDir}/{$file}", array_values($records));
        }
    }

    private function writeOrganization(): void
    {
        $this->writeJson("{$this->workDir}/organization.json", $this->organization);
    }

    private function writeReport(): string
    {
        $summary = [];
        foreach (Contract::RECORD_FILES as $file => $schema) {
            $path = "{$this->workDir}/{$file}";
            if (file_exists($path)) {
                $payload = json_decode(file_get_contents($path), true);
                $summary[$file] = is_array($payload) && array_is_list($payload) ? count($payload) : 1;
            }
        }
        $summary['organization.json'] = 1;

        $report = [
            'status' => empty($this->buildWarnings()) ? 'ready' : 'ready_with_warnings',
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
            'summary' => $summary,
            'warnings' => $this->buildWarnings(),
            'errors' => [],
        ];

        $this->writeJson("{$this->workDir}/report.json", $report);
        return 'report.json';
    }

    private function buildManifest(string $reportPath): Manifest
    {
        $files = [];
        $allFiles = array_merge(
            ['organization.json' => 'schemas/records/organization.schema.json'],
            Contract::RECORD_FILES,
            [$reportPath => 'schemas/bundle/report.schema.json']
        );

        foreach ($allFiles as $path => $schema) {
            $fullPath = "{$this->workDir}/{$path}";
            $payload = file_exists($fullPath) ? json_decode(file_get_contents($fullPath), true) : [];
            $files[] = [
                'path' => $path,
                'schema' => $schema,
                'required' => $path !== 'report.json',
                'records' => is_array($payload) && array_is_list($payload) ? count($payload) : 1,
                'sha256' => $this->fileHash($fullPath),
            ];
        }

        $uuid = UUID::v4();

        return Manifest::create(
            Version::CURRENT,
            Version::SDK_MIN,
            Version::CORE_MIN,
            $this->generator,
            $this->organization,
            array_merge($this->source, ['bundle_uuid' => $uuid]),
            $files
        );
    }

    private function writeChecksums(): void
    {
        $lines = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->workDir) + 1));
            if ($relative === 'checksum.sha256') {
                continue;
            }
            $lines[] = strtoupper(hash_file('sha256', $file->getPathname())) . "  {$relative}";
        }

        sort($lines);
        file_put_contents("{$this->workDir}/checksum.sha256", implode("\n", $lines) . "\n");
    }

    private function writeAttachments(): void
    {
        $attachDir = "{$this->workDir}/attachments";
        if (!is_dir($attachDir)) {
            mkdir($attachDir, 0755, true);
        }
        foreach ($this->attachments as $attachment) {
            $target = "{$this->workDir}/{$attachment['target']}";
            $targetDir = dirname($target);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            copy($attachment['source'], $target);
        }
    }

    private function package(): string
    {
        $zipPath = $this->workDir . '.schf';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create bundle: {$zipPath}");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->workDir) + 1));
            $zip->addFile($file->getPathname(), $relative);
        }

        $zip->close();

        return $zipPath;
    }

    private function writeJson(string $path, array $payload): void
    {
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }

    private function fileHash(string $path): string
    {
        return file_exists($path) ? strtoupper(hash_file('sha256', $path)) : str_repeat('0', 64);
    }

    private function getAllRecordDefinitions(): array
    {
        return array_merge(
            ['organization.json' => 'schemas/records/organization.schema.json'],
            Contract::RECORD_FILES
        );
    }

    private function buildWarnings(): array
    {
        $warnings = [];
        $totalRecords = array_sum(array_map(fn($r) => count($r), $this->records));
        if ($totalRecords === 0) {
            $warnings[] = 'No normalized records found. Bundle will contain structure and metadata only.';
        }
        if (empty($this->organization['external_id']) || empty($this->organization['name'])) {
            $warnings[] = 'Organization data is incomplete.';
        }
        return $warnings;
    }

    private function cleanup(): void
    {
        if (is_dir($this->workDir)) {
            $this->removeDirectory($this->workDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
