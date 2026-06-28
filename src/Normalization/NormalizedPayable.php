<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class NormalizedPayable
{
    public function __construct(
        public string  $external_id,
        public string  $direction, // payable | receivable
        public float   $amount,
        public string  $due_date,
        public string  $status, // pending | paid | cancelled | historical
        public ?string $supplier_external_id = null,
        public ?string $account_external_id = null,
        public ?string $category_external_id = null,
        public ?string $description = null,
        public ?string $paid_at = null,
        public array   $metadata = [],
    ) {}
}
