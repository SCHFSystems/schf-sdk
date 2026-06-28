<?php

namespace SCHF\SDK\Bundle;

class Doctor
{
    private const CHECKS = [
        'structure',
        'integrity',
        'compatibility',
        'content',
        'quality',
    ];

    public function diagnose(string $bundlePath, bool $deep = false): array
    {
        $validator = new Validator();
        $validation = $validator->validate($bundlePath);

        $report = [
            'valid' => $validation['valid'],
            'bundle_path' => $bundlePath,
            'bundle_size' => file_exists($bundlePath) ? filesize($bundlePath) : 0,
            'bundle_extension' => strtolower(pathinfo($bundlePath, PATHINFO_EXTENSION)),
            'checks_performed' => self::CHECKS,
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'manifest' => $validation['manifest'],
        ];

        if ($validation['manifest'] !== null) {
            $report['summary'] = $this->summarize($validation['manifest']);
            $report['compatibility'] = $this->checkCompatibility($validation['manifest']);
            $report['integrity'] = $this->checkIntegrity($validator->getExtractDir());
        }

        if ($deep && $validation['valid']) {
            $report['quality'] = $this->assessQuality($validator->getExtractDir(), $validation['manifest']);
        }

        $report['ready_to_import'] = $validation['valid']
            && empty($report['compatibility']['issues'] ?? [])
            && ($report['integrity']['valid'] ?? false);

        return $report;
    }

    public function summarize(array $manifest): array
    {
        $totalRecords = 0;
        $fileCount = count($manifest['files'] ?? []);
        foreach ($manifest['files'] ?? [] as $file) {
            $totalRecords += $file['records'] ?? 0;
        }

        return [
            'bundle_version' => $manifest['bundle_version'] ?? 'unknown',
            'sdk_version' => $manifest['sdk_version'] ?? 'unknown',
            'generated_at' => $manifest['generated_at'] ?? 'unknown',
            'organization' => $manifest['organization']['name'] ?? 'unknown',
            'source_type' => $manifest['source']['type'] ?? 'unknown',
            'files' => $fileCount,
            'total_records' => $totalRecords,
            'uuid' => $manifest['source']['bundle_uuid'] ?? null,
        ];
    }

    public function checkCompatibility(array $manifest): array
    {
        $issues = [];
        $warnings = [];

        $bundleVersion = $manifest['bundle_version'] ?? '0.0.0';
        $sdkVersion = $manifest['sdk_version'] ?? '0.0.0';

        if (!Version::isValid($bundleVersion)) {
            $issues[] = "Bundle version '{$bundleVersion}' has invalid format";
        }

        if (!Version::isSdkCompatible($sdkVersion)) {
            $warnings[] = "SDK version {$sdkVersion} is below minimum compatible " . Version::SDK_MIN;
        }

        if (Version::compare($bundleVersion, Version::CURRENT) > 0) {
            $warnings[] = "Bundle version {$bundleVersion} is newer than current SDK version " . Version::CURRENT;
        }

        $coreMin = $manifest['core_min_version'] ?? null;
        if ($coreMin && Version::compare($coreMin, Version::CORE_MIN) > 0) {
            $warnings[] = "Bundle requires core >= {$coreMin}, current minimum is " . Version::CORE_MIN;
        }

        $coreMax = $manifest['core_max_version'] ?? null;
        if ($coreMax) {
            $warnings[] = "Bundle has max core version: {$coreMax}";
        }

        return [
            'compatible' => empty($issues),
            'bundle_version' => $bundleVersion,
            'sdk_version' => $sdkVersion,
            'current_sdk_version' => Version::CURRENT,
            'core_min' => $coreMin ?? Version::CORE_MIN,
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    public function checkIntegrity(string $extractDir): array
    {
        $valid = true;
        $details = [];

        $checksumPath = "{$extractDir}/checksum.sha256";
        if (!file_exists($checksumPath)) {
            return ['valid' => false, 'details' => ['Missing checksum.sha256']];
        }

        $lines = file($checksumPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $checked = 0;
        $mismatches = 0;

        foreach ($lines as $line) {
            if (!preg_match('/^([A-Fa-f0-9]{64})\s+(.+)$/', trim($line), $matches)) {
                continue;
            }
            $expected = strtoupper($matches[1]);
            $relative = trim($matches[2]);
            $path = "{$extractDir}/{$relative}";

            if (!file_exists($path)) {
                $details[] = "Missing file referenced in checksum: {$relative}";
                $valid = false;
                continue;
            }

            $actual = strtoupper(hash_file('sha256', $path));
            if ($actual !== $expected) {
                $details[] = "Integrity violation: {$relative}";
                $mismatches++;
                $valid = false;
            }
            $checked++;
        }

        $details[] = "Files checked: {$checked}";
        if ($mismatches > 0) {
            $details[] = "Mismatches: {$mismatches}";
        }

        return ['valid' => $valid, 'details' => $details];
    }

    public function assessQuality(string $extractDir, array $manifest): array
    {
        $issues = [];
        $score = 100;

        foreach ($manifest['files'] ?? [] as $file) {
            $path = "{$extractDir}/{$file['path']}";
            if (!file_exists($path)) {
                continue;
            }

            $content = json_decode(file_get_contents($path), true);
            if ($content === null) {
                $issues[] = "Cannot parse {$file['path']}";
                $score -= 10;
                continue;
            }

            $records = is_array($content) && array_is_list($content) ? $content : [$content];
            foreach ($records as $i => $record) {
                if (!is_array($record)) {
                    continue;
                }
                if (isset($record['name']) && $record['name'] === '') {
                    $issues[] = "{$file['path']}[{$i}]: empty name field";
                    $score -= 1;
                }
                if (isset($record['external_id']) && $record['external_id'] === '') {
                    $issues[] = "{$file['path']}[{$i}]: empty external_id";
                    $score -= 1;
                }
            }
        }

        $recordCount = array_sum(array_map(fn($f) => $f['records'] ?? 0, $manifest['files'] ?? []));
        if ($recordCount === 0) {
            $score -= 20;
            $issues[] = 'Bundle is empty (0 records total)';
        }

        return [
            'score' => max(0, $score),
            'rating' => $this->rating($score),
            'issues' => $issues,
            'total_records' => $recordCount,
        ];
    }

    private function rating(int $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'fair',
            default => 'poor',
        };
    }
}
