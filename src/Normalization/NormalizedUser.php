<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class NormalizedUser
{
    public function __construct(
        public string  $external_id,
        public string  $name,
        public string  $email,
        public array   $roles,
        public bool    $active = true,
        public array   $metadata = [],
    ) {}
}
