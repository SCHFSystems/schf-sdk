<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class NormalizedExpense
{
    public function __construct(
        public string  $external_id,
        public float   $amount,
        public string  $date,
        public string  $status = 'posted', // posted | cancelled | historical
        public ?string $account_external_id = null,
        public ?string $category_external_id = null,
        public ?string $supplier_external_id = null,
        public ?string $description = null,
        public array   $metadata = [],
    ) {}
}
