<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class NormalizedSupplier
{
    public function __construct(
        public string  $external_id,
        public string  $name,
        public ?string $document = null,
        public bool    $active = true,
        public array   $metadata = [],
    ) {}
}
