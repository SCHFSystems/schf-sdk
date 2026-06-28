<?php

namespace SCHF\SDK\Bundle;

class Manifest
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return new self($data);
    }

    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Manifest file not found: {$path}");
        }
        return self::fromJson(file_get_contents($path));
    }

    public static function create(
        string $bundleVersion,
        string $sdkVersion,
        string $coreMinVersion,
        array $generator,
        array $organization,
        array $source,
        array $files
    ): self {
        return new self([
            'bundle_version' => $bundleVersion,
            'sdk_version' => $sdkVersion,
            'core_min_version' => $coreMinVersion,
            'core_max_version' => null,
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
            'generator' => $generator,
            'organization' => $organization,
            'source' => $source,
            'files' => $files,
        ]);
    }

    public function getBundleVersion(): string
    {
        return $this->data['bundle_version'];
    }

    public function getSdkVersion(): string
    {
        return $this->data['sdk_version'];
    }

    public function getCoreMinVersion(): string
    {
        return $this->data['core_min_version'];
    }

    public function getCoreMaxVersion(): ?string
    {
        return $this->data['core_max_version'] ?? null;
    }

    public function getGeneratedAt(): string
    {
        return $this->data['generated_at'];
    }

    public function getGenerator(): array
    {
        return $this->data['generator'];
    }

    public function getOrganization(): array
    {
        return $this->data['organization'];
    }

    public function getSource(): array
    {
        return $this->data['source'];
    }

    public function getFiles(): array
    {
        return $this->data['files'];
    }

    public function getRequiredFiles(): array
    {
        return array_filter($this->data['files'], fn($f) => ($f['required'] ?? false) === true);
    }

    public function getFileByPath(string $path): ?array
    {
        foreach ($this->data['files'] as $file) {
            if ($file['path'] === $path) {
                return $file;
            }
        }
        return null;
    }

    public function getRecordCount(): int
    {
        return array_sum(array_map(fn($f) => $f['records'] ?? 0, $this->data['files']));
    }

    public function getInventoryHash(): string
    {
        return $this->data['source']['inventory_hash'] ?? '';
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function toArrayWithHashes(): array
    {
        $result = $this->data;
        foreach ($result['files'] as &$file) {
            unset($file['sha256']);
        }
        return $result;
    }
}
