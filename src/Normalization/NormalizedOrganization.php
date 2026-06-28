<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class NormalizedOrganization
{
    public function __construct(
        public string  $external_id,
        public string  $name,
        public ?string $legal_name = null,
        public array   $metadata = [],
    ) {}
}
