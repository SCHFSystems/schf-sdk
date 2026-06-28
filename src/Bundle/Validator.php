<?php

namespace SCHF\SDK\Bundle;

class Validator
{
    private array $errors = [];
    private array $warnings = [];
    private ?Manifest $manifest = null;
    private string $extractDir;

    public function __construct()
    {
        $this->extractDir = sys_get_temp_dir() . '/schf_validate_' . bin2hex(random_bytes(8));
    }

    public function __destruct()
    {
        if (is_dir($this->extractDir)) {
            $this->removeDirectory($this->extractDir);
        }
    }

    public function validate(string $bundlePath): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->manifest = null;

        if (!file_exists($bundlePath)) {
            return $this->result(false, ["Bundle file not found: {$bundlePath}"]);
        }

        if (!$this->isValidBundleExtension($bundlePath)) {
            $this->warnings[] = "Bundle file does not have .schf extension";
        }

        try {
            $this->extractSafely($bundlePath);
        } catch (\RuntimeException $e) {
            return $this->result(false, [$e->getMessage()]);
        }

        $this->validateRequiredFiles();
        if (!empty($this->errors)) {
            return $this->result(false);
        }

        $this->validateChecksums();
        $this->validateManifest();
        $this->validateJsonIntegrity();
        $this->validateSignatures();

        return $this->result(empty($this->errors));
    }

    public function getExtractDir(): string
    {
        return $this->extractDir;
    }

    public function getManifest(): ?Manifest
    {
        return $this->manifest;
    }

    private function isValidBundleExtension(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === Contract::EXTENSION;
    }

    private function extractSafely(string $bundlePath): void
    {
        if (!extension_loaded('zip')) {
            throw new \RuntimeException('PHP ZipArchive extension is required');
        }

        $zip = new \ZipArchive();
        $code = $zip->open($bundlePath);
        if ($code !== true) {
            throw new \RuntimeException("Invalid or unreadable bundle ZIP (error code: {$code})");
        }

        if (!is_dir($this->extractDir)) {
            mkdir($this->extractDir, 0755, true);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $safePath = $this->safePath($entryName);
            $targetPath = $this->extractDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safePath);

            if (str_ends_with($entryName, '/')) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
                continue;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $source = $zip->getStream($entryName);
            if (!$source) {
                throw new \RuntimeException("Unable to read ZIP entry: {$entryName}");
            }

            $destination = fopen($targetPath, 'wb');
            stream_copy_to_stream($source, $destination);
            fclose($source);
            fclose($destination);
        }

        $zip->close();
    }

    private function safePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..')) {
            throw new \RuntimeException("Unsafe bundle path: {$path}");
        }

        return $path;
    }

    private function validateRequiredFiles(): void
    {
        foreach (Contract::REQUIRED_FILES as $file) {
            if (!file_exists("{$this->extractDir}/{$file}")) {
                $this->errors[] = "Missing required file: {$file}";
            }
        }
    }

    private function validateChecksums(): void
    {
        $checksumPath = "{$this->extractDir}/checksum.sha256";
        if (!file_exists($checksumPath)) {
            return;
        }

        $content = file_get_contents($checksumPath);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (!preg_match('/^([A-Fa-f0-9]{64})\s+(.+)$/', trim($line), $matches)) {
                $this->warnings[] = "Invalid checksum line format: {$line}";
                continue;
            }

            $expected = strtoupper($matches[1]);
            $relative = trim($matches[2]);
            $path = "{$this->extractDir}/{$relative}";

            if (!file_exists($path)) {
                $this->errors[] = "Checksum references missing file: {$relative}";
                continue;
            }

            $actual = strtoupper(hash_file('sha256', $path));
            if ($actual !== $expected) {
                $this->errors[] = "Checksum mismatch: {$relative}";
            }
        }
    }

    private function validateManifest(): void
    {
        $manifestPath = "{$this->extractDir}/manifest.json";
        if (!file_exists($manifestPath)) {
            return;
        }

        try {
            $this->manifest = Manifest::fromFile($manifestPath);
        } catch (\Throwable $e) {
            $this->errors[] = "Invalid manifest.json: {$e->getMessage()}";
            return;
        }

        $data = $this->manifest->toArray();
        $requiredFields = ['bundle_version', 'sdk_version', 'core_min_version', 'generated_at', 'generator', 'organization', 'source', 'files'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                $this->errors[] = "Missing manifest field: {$field}";
            }
        }

        if (!Version::isValid($this->manifest->getBundleVersion())) {
            $this->errors[] = "Invalid bundle version format: {$this->manifest->getBundleVersion()}";
        }

        if (!Version::isValid($this->manifest->getSdkVersion())) {
            $this->warnings[] = "Invalid SDK version format in manifest";
        }

        if (!empty($data['source']['bundle_uuid']) && !UUID::isValid($data['source']['bundle_uuid'])) {
            $this->errors[] = "Invalid bundle UUID format";
        }

        $manifestFiles = $this->manifest->getFiles();
        $recordCount = 0;
        foreach ($manifestFiles as $file) {
            if (isset($file['records'])) {
                $recordCount += $file['records'];
            }
        }

        if ($recordCount === 0) {
            $this->warnings[] = 'Bundle contains no records';
        }
    }

    private function validateJsonIntegrity(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->extractDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->extractDir) + 1));
                $this->errors[] = "Invalid JSON in {$relative}: " . json_last_error_msg();
            }
        }
    }

    private function validateSignatures(): void
    {
        $sigPath = "{$this->extractDir}/signature.sig";
        $keyPath = "{$this->extractDir}/signing-key.pub";

        if (!file_exists($sigPath)) {
            return;
        }

        if (!file_exists($keyPath)) {
            $this->warnings[] = 'Signature file found but no public key available for verification';
            return;
        }

        $verified = $this->verifySignature();
        if (!$verified) {
            $this->errors[] = 'Signature verification failed';
        }
    }

    private function verifySignature(): bool
    {
        $sigPath = "{$this->extractDir}/signature.sig";
        $keyPath = "{$this->extractDir}/signing-key.pub";
        $checksumPath = "{$this->extractDir}/checksum.sha256";

        if (!function_exists('openssl_verify') && !function_exists('sodium_crypto_sign_verify_detached')) {
            $this->warnings[] = 'No signature verification extension available (openssl or sodium required)';
            return true;
        }

        $signature = file_get_contents($sigPath);
        $data = file_get_contents($checksumPath);
        $publicKey = file_get_contents($keyPath);

        $isRsa = str_contains($publicKey, 'BEGIN PUBLIC KEY') || str_contains($publicKey, 'BEGIN RSA PUBLIC KEY');

        if ($isRsa && function_exists('openssl_verify')) {
            $result = @openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);
            return $result === 1;
        }

        if (!$isRsa && function_exists('sodium_crypto_sign_verify_detached')) {
            $key = sodium_crypto_sign_publickey($publicKey);
            return sodium_crypto_sign_verify_detached($signature, $data, $key);
        }

        $this->warnings[] = 'Unable to verify signature: no suitable crypto extension';
        return true;
    }

    private function result(bool $valid, ?array $forcedErrors = null): array
    {
        return [
            'valid' => $valid,
            'manifest' => $this->manifest ? $this->manifest->toArray() : null,
            'errors' => $forcedErrors ?? $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
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
