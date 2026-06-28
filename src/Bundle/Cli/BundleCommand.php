<?php

namespace SCHF\SDK\Bundle\Cli;

use SCHF\SDK\Bundle\Builder;
use SCHF\SDK\Bundle\Validator;
use SCHF\SDK\Bundle\Doctor;
use SCHF\SDK\Bundle\Inspector;
use SCHF\SDK\Bundle\Signer;
use SCHF\SDK\Bundle\Verifier;
use SCHF\SDK\Bundle\History;
use SCHF\SDK\Bundle\Version;

class BundleCommand
{
    public static function execute(array $argv): int
    {
        $command = $argv[1] ?? null;
        $subcommand = $argv[2] ?? null;

        if ($command !== 'bundle' || $subcommand === null) {
            self::usage();
            return 1;
        }

        return match ($subcommand) {
            'build' => self::build($argv),
            'validate' => self::validate($argv),
            'doctor' => self::doctor($argv),
            'inspect' => self::inspect($argv),
            'info' => self::info($argv),
            'sign' => self::sign($argv),
            'verify' => self::verify($argv),
            'history' => self::history($argv),
            'version' => self::versionInfo(),
            'help' => self::usage(),
            default => (self::usage(), 1),
        };
    }

    private static function build(array $argv): int
    {
        $options = self::parseOptions($argv, 3);

        if (!isset($options['_'][0])) {
            fwrite(STDERR, "Usage: schf bundle build --org-id=<id> --org-name=<name> [--source-type=<type>] [--output=<path>]\n");
            return 1;
        }

        $output = $options['output'] ?? null;
        $orgId = $options['org-id'] ?? $options['organization-id'] ?? 'unknown';
        $orgName = $options['org-name'] ?? $options['organization-name'] ?? 'Unknown';
        $sourceType = $options['source-type'] ?? 'unknown';

        try {
            $builder = new Builder();
            $builder->setOrganization((string)$orgId, (string)$orgName);
            $builder->setSource((string)$sourceType);

            $path = $builder->build();

            if ($output && $output !== $path) {
                rename($path, $output);
                $path = $output;
            }

            echo "Bundle built successfully\n";
            echo "Path: {$path}\n";
            echo "Size: " . self::formatBytes(filesize($path)) . "\n";
            return 0;
        } catch (\Throwable $e) {
            fwrite(STDERR, "Build failed: {$e->getMessage()}\n");
            return 1;
        }
    }

    private static function validate(array $argv): int
    {
        $path = $argv[3] ?? null;
        if (!$path) {
            fwrite(STDERR, "Usage: schf bundle validate <bundle.schf>\n");
            return 1;
        }

        $validator = new Validator();
        $result = $validator->validate($path);

        if ($result['valid']) {
            echo "✓ Bundle is valid\n";
            if (!empty($result['warnings'])) {
                echo "\nWarnings:\n";
                foreach ($result['warnings'] as $w) {
                    echo "  ⚠ {$w}\n";
                }
            }
            return 0;
        }

        echo "✗ Bundle is INVALID\n";
        foreach ($result['errors'] as $error) {
            echo "  ✗ {$error}\n";
        }
        if (!empty($result['warnings'])) {
            echo "\nWarnings:\n";
            foreach ($result['warnings'] as $w) {
                echo "  ⚠ {$w}\n";
            }
        }
        return 1;
    }

    private static function doctor(array $argv): int
    {
        $path = $argv[3] ?? null;
        if (!$path) {
            fwrite(STDERR, "Usage: schf bundle doctor <bundle.schf> [--deep]\n");
            return 1;
        }

        $options = self::parseOptions($argv, 4);
        $deep = isset($options['deep']);

        $doctor = new Doctor();
        $report = $doctor->diagnose($path, $deep);

        echo "SCHF Bundle Doctor Report\n";
        echo str_repeat('=', 50) . "\n";
        echo "Path: {$report['bundle_path']}\n";
        echo "Size: " . self::formatBytes($report['bundle_size']) . "\n";

        if ($report['manifest']) {
            $s = $report['summary'];
            echo "Version: {$s['bundle_version']}\n";
            echo "SDK: {$s['sdk_version']}\n";
            echo "Organization: {$s['organization']}\n";
            echo "Source: {$s['source_type']}\n";
            echo "Files: {$s['files']}\n";
            echo "Records: {$s['total_records']}\n";
            echo "UUID: {$s['uuid']}\n";
            echo "Generated: {$s['generated_at']}\n";
        }

        echo "\nCompatibility: ";
        $c = $report['compatibility'];
        echo $c['compatible'] ? "✓ Compatible\n" : "✗ Issues found\n";
        foreach ($c['warnings'] ?? [] as $w) {
            echo "  ⚠ {$w}\n";
        }
        foreach ($c['issues'] as $i) {
            echo "  ✗ {$i}\n";
        }

        echo "\nIntegrity: ";
        $i = $report['integrity'];
        echo $i['valid'] ? "✓ Passed\n" : "✗ Failed\n";
        foreach ($i['details'] as $d) {
            echo "  - {$d}\n";
        }

        if (isset($report['quality'])) {
            $q = $report['quality'];
            echo "\nQuality: {$q['rating']} (score: {$q['score']})\n";
            foreach ($q['issues'] as $issue) {
                echo "  ⚠ {$issue}\n";
            }
        }

        echo "\nStatus: ";
        echo $report['ready_to_import'] ? "✓ READY TO IMPORT\n" : "✗ NOT READY\n";

        if (!empty($report['errors'])) {
            echo "\nErrors:\n";
            foreach ($report['errors'] as $e) {
                echo "  ✗ {$e}\n";
            }
        }

        return $report['ready_to_import'] ? 0 : 1;
    }

    private static function inspect(array $argv): int
    {
        $path = $argv[3] ?? null;
        if (!$path) {
            fwrite(STDERR, "Usage: schf bundle inspect <bundle.schf>\n");
            return 1;
        }

        $inspector = new Inspector();
        $result = $inspector->open($path);

        if (!$result['valid']) {
            echo "Cannot inspect: bundle is invalid\n";
            foreach ($result['errors'] as $e) {
                echo "  ✗ {$e}\n";
            }
            return 1;
        }

        echo "Bundle Inspection\n";
        echo str_repeat('=', 50) . "\n";

        $history = $inspector->getHistory();
        echo "UUID: {$history['uuid']}\n";
        echo "Version: {$history['version']}\n";
        echo "Organization: {$history['organization']['name']}\n";
        echo "Source: {$history['source']['type']}\n";
        echo "Generated: {$history['generated_at']}\n";
        echo "Generator: {$history['generator']['name']} v{$history['generator']['version']}\n";
        echo "Total records: {$inspector->getRecordCount()}\n";

        $orgs = $inspector->getOrganizations();
        if (!empty($orgs)) {
            echo "\nOrganizations: " . count($orgs) . "\n";
        }

        $suppliers = $inspector->getSuppliers();
        if (!empty($suppliers)) {
            echo "Suppliers: " . count($suppliers) . "\n";
        }

        $categories = $inspector->getCategories();
        if (!empty($categories)) {
            echo "Categories: " . count($categories) . "\n";
        }

        $accounts = $inspector->getAccounts();
        if (!empty($accounts)) {
            echo "Accounts: " . count($accounts) . "\n";
        }

        $payments = $inspector->getPayments();
        if (!empty($payments)) {
            echo "Payments: " . count($payments) . "\n";
        }

        if (!empty($result['warnings'])) {
            echo "\nWarnings:\n";
            foreach ($result['warnings'] as $w) {
                echo "  ⚠ {$w}\n";
            }
        }

        $inspector->close();
        return 0;
    }

    private static function info(array $argv): int
    {
        $path = $argv[3] ?? null;
        if (!$path) {
            fwrite(STDERR, "Usage: schf bundle info <bundle.schf>\n");
            return 1;
        }

        $inspector = new Inspector();
        $result = $inspector->open($path);

        if (!$result['valid']) {
            echo "Cannot read info: bundle is invalid\n";
            return 1;
        }

        $history = $inspector->getHistory();
        echo json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        $inspector->close();
        return 0;
    }

    private static function sign(array $argv): int
    {
        $path = $argv[3] ?? null;
        $keyPath = $argv[4] ?? null;

        if (!$path || !$keyPath) {
            fwrite(STDERR, "Usage: schf bundle sign <bundle.schf> <private-key>\n");
            return 1;
        }

        $signer = new Signer();
        $result = $signer->sign($path, $keyPath);

        if ($result['success']) {
            echo "✓ Bundle signed successfully\n";
            echo "Algorithm: {$result['algorithm']}\n";
            return 0;
        }

        echo "✗ Signing failed: {$result['error']}\n";
        return 1;
    }

    private static function verify(array $argv): int
    {
        $path = $argv[3] ?? null;
        $keyPath = $argv[4] ?? null;

        if (!$path) {
            fwrite(STDERR, "Usage: schf bundle verify <bundle.schf> [public-key]\n");
            return 1;
        }

        $verifier = new Verifier();
        $result = $verifier->verify($path, $keyPath);

        if ($result['verified']) {
            echo "✓ Signature verified ({$result['algorithm']})\n";
            return 0;
        }

        echo "✗ Verification failed: {$result['error']}\n";
        return 1;
    }

    private static function history(array $argv): int
    {
        $history = new History();
        $records = $history->findAll();

        if (empty($records)) {
            echo "No bundle history recorded yet.\n";
            return 0;
        }

        echo "Bundle History\n";
        echo str_repeat('=', 50) . "\n";
        foreach (array_reverse($records) as $entry) {
            echo "{$entry['uuid']} | {$entry['client']} | v{$entry['bundle_version']} | {$entry['record_count']} records | {$entry['recorded_at']}\n";
        }
        echo "\nTotal: " . count($records) . " bundles\n";
        return 0;
    }

    private static function versionInfo(): int
    {
        echo "SCHF Bundle SDK v" . Version::CURRENT . "\n";
        echo "Bundle format: v" . Version::CURRENT . "\n";
        echo "Core min: " . Version::CORE_MIN . "\n";
        return 0;
    }

    private static function parseOptions(array $argv, int $startIndex): array
    {
        $options = ['_' => []];
        for ($i = $startIndex; $i < count($argv); $i++) {
            $arg = $argv[$i];
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                if (count($parts) === 2) {
                    $options[$parts[0]] = $parts[1];
                } else {
                    $options[$parts[0]] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                $options[ltrim($arg, '-')] = true;
            } else {
                $options['_'][] = $arg;
            }
        }
        return $options;
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private static function usage(): int
    {
        echo "SCHF Bundle CLI v" . Version::CURRENT . "\n";
        echo str_repeat('=', 50) . "\n";
        echo "Usage: schf bundle <command> [options]\n\n";
        echo "Commands:\n";
        echo "  build     Build a new bundle\n";
        echo "  validate  Validate a bundle\n";
        echo "  doctor    Run full diagnostics on a bundle\n";
        echo "  inspect   Inspect bundle contents\n";
        echo "  info      Show bundle metadata as JSON\n";
        echo "  sign      Sign a bundle with a private key\n";
        echo "  verify    Verify bundle signature\n";
        echo "  history   Show bundle import history\n";
        echo "  version   Show bundle version info\n";
        echo "  help      Show this help\n";
        return 0;
    }
}
