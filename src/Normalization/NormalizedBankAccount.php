<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class NormalizedBankAccount
{
    public function __construct(
        public string  $external_id,
        public string  $name,
        public string  $type, // cash | bank | credit | other
        public ?string $bank_external_id = null,
        public float   $opening_balance = 0.0,
        public array   $metadata = [],
    ) {}
}
