<?php

namespace SCHF\SDK\Bundle;

class Version
{
    public const CURRENT = '1.0.0';
    public const SDK_MIN = '0.1.0';
    public const CORE_MIN = '1.5.0';
    public const CORE_MAX = null;

    public static function current(): string
    {
        return self::CURRENT;
    }

    public static function isValid(string $version): bool
    {
        return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) === 1;
    }

    public static function compare(string $a, string $b): int
    {
        return version_compare($a, $b);
    }

    public static function isCompatible(string $bundleVersion, string $coreVersion): array
    {
        $issues = [];

        if (!self::isValid($bundleVersion)) {
            $issues[] = "Invalid bundle version format: {$bundleVersion}";
        }

        if (!self::isValid($coreVersion)) {
            $issues[] = "Invalid core version format: {$coreVersion}";
        }

        $major = explode('.', $bundleVersion)[0];
        $coreMajor = explode('.', $coreVersion)[0];

        if ($major !== $coreMajor) {
            $issues[] = "Major version mismatch: bundle={$major}, core={$coreMajor}";
        }

        if (version_compare($coreVersion, self::CORE_MIN, '<')) {
            $issues[] = "Core version {$coreVersion} is below minimum required " . self::CORE_MIN;
        }

        if (self::CORE_MAX !== null && version_compare($coreVersion, self::CORE_MAX, '>')) {
            $issues[] = "Core version {$coreVersion} exceeds maximum supported " . self::CORE_MAX;
        }

        return [
            'compatible' => empty($issues),
            'bundle_version' => $bundleVersion,
            'core_version' => $coreVersion,
            'core_min' => self::CORE_MIN,
            'core_max' => self::CORE_MAX,
            'issues' => $issues,
        ];
    }

    public static function isSdkCompatible(string $sdkVersion): bool
    {
        return self::isValid($sdkVersion) && version_compare($sdkVersion, self::SDK_MIN, '>=');
    }

    public static function bumpMajor(string $version): string
    {
        [$major, $minor, $patch] = explode('.', $version);
        return ((int)$major + 1) . '.0.0';
    }

    public static function bumpMinor(string $version): string
    {
        [$major, $minor, $patch] = explode('.', $version);
        return $major . '.' . ((int)$minor + 1) . '.0';
    }

    public static function bumpPatch(string $version): string
    {
        [$major, $minor, $patch] = explode('.', $version);
        return $major . '.' . $minor . '.' . ((int)$patch + 1);
    }

    public static function parse(string $version): ?array
    {
        if (!self::isValid($version)) {
            return null;
        }
        [$major, $minor, $patch] = explode('.', $version);
        return [
            'major' => (int)$major,
            'minor' => (int)$minor,
            'patch' => (int)$patch,
            'string' => $version,
        ];
    }
}
