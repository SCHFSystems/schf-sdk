<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

/**
 * Defines how a legacy source (table) maps to a SCHF entity type.
 *
 * @template T of object
 */
readonly class MappingProfile
{
    /**
     * @param string          $source_type    Database driver (firebird, mysql, ...).
     * @param string          $profile        Profile name (e.g. 'generic-firebird-finance').
     * @param string          $version        Semantic version of this profile.
     * @param string          $source_table   Legacy table name.
     * @param class-string<T> $target_class   Fully-qualified SCHF normalized class.
     * @param string          $target_entity  Human-readable entity name (e.g. 'suppliers').
     * @param MappingRule[]   $rules          Column mapping rules.
     * @param string|null     $description    Human-readable description.
     * @param array           $metadata       Additional metadata.
     */
    public function __construct(
        public string  $source_type,
        public string  $profile,
        public string  $version,
        public string  $source_table,
        public string  $target_class,
        public string  $target_entity,
        public array   $rules,
        public ?string $description = null,
        public array   $metadata = [],
    ) {}
}
