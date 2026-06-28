<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class NormalizationResult
{
    /**
     * @param array<NormalizedOrganization> $organizations
     * @param array<NormalizedUser>         $users
     * @param array<NormalizedSupplier>     $suppliers
     * @param array<NormalizedBankAccount>  $accounts
     * @param array<NormalizedCategory>     $categories
     * @param array<NormalizedPayable>      $payables
     * @param array<NormalizedExpense>      $expenses
     * @param array<QualityIssue>           $issues
     * @param array                         $summary
     */
    public function __construct(
        public array $organizations = [],
        public array $users = [],
        public array $suppliers = [],
        public array $accounts = [],
        public array $categories = [],
        public array $payables = [],
        public array $expenses = [],
        public array $issues = [],
        public array $summary = [],
    ) {}
}
