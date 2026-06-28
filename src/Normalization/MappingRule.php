<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

/**
 * A single rule mapping a source column to a target SCHF field.
 */
readonly class MappingRule
{
    /**
     * @param string      $source_column    Name of the column in the legacy table.
     * @param string      $target_field     Name of the field in the SCHF normalized object.
     * @param string|null $transform        Optional transform: 'trim', 'upper', 'lower', 'date', 'number', 'boolean', 'concat', 'split', 'map_values'.
     * @param array|null  $value_map        Optional explicit value mapping (e.g. ['S' => 'active', 'N' => 'inactive']).
     * @param mixed|null  $default          Default value if source is null.
     * @param bool        $required         Whether this field must be non-null after transform.
     * @param string|null $description      Human-readable explanation.
     */
    public function __construct(
        public string  $source_column,
        public string  $target_field,
        public ?string $transform = null,
        public ?array  $value_map = null,
        public mixed   $default = null,
        public bool    $required = false,
        public ?string $description = null,
    ) {}
}
