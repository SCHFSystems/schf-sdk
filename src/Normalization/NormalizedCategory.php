<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class NormalizedCategory
{
    public function __construct(
        public string  $external_id,
        public string  $name,
        public string  $type, // income | expense | transfer | other
        public ?string $parent_external_id = null,
        public array   $metadata = [],
    ) {}
}
