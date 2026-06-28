<?php

declare(strict_types=1);

namespace SCHF\SDK\Normalization;

readonly class QualityIssue
{
    public function __construct(
        public string  $type, // 'invalid_document' | 'empty_name' | 'invalid_date' | 'negative_value' | 'duplicate' | 'orphan' | 'missing_required'
        public string  $severity, // 'error' | 'warning'
        public string  $entity, // e.g. 'suppliers', 'payables'
        public string  $external_id,
        public string  $field,
        public string  $message,
        public mixed   $value = null,
    ) {}
}
