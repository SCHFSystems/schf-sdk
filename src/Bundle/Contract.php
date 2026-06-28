<?php

namespace SCHF\SDK\Bundle;

interface Contract
{
    public const REQUIRED_FILES = [
        'manifest.json',
        'organization.json',
        'users.json',
        'roles.json',
        'permissions.json',
        'suppliers.json',
        'accounts.json',
        'banks.json',
        'categories.json',
        'payments.json',
        'expenses.json',
        'report.json',
        'checksum.sha256',
    ];

    public const RECORD_FILES = [
        'users.json' => 'schemas/records/users.schema.json',
        'roles.json' => 'schemas/records/roles.schema.json',
        'permissions.json' => 'schemas/records/permissions.schema.json',
        'suppliers.json' => 'schemas/records/suppliers.schema.json',
        'accounts.json' => 'schemas/records/accounts.schema.json',
        'banks.json' => 'schemas/records/banks.schema.json',
        'categories.json' => 'schemas/records/categories.schema.json',
        'payments.json' => 'schemas/records/payments.schema.json',
        'expenses.json' => 'schemas/records/expenses.schema.json',
    ];

    public const SCHEMA_PATHS = [
        'organization.json' => 'schemas/records/organization.schema.json',
        'manifest.json' => 'schemas/bundle/manifest.schema.json',
        'report.json' => 'schemas/bundle/report.schema.json',
    ];

    public const EXTENSION = 'schf';
    public const MIME_TYPE = 'application/vnd.schf.bundle';
}
