# Migration Bundle Contract

## Purpose

The Migration Bundle is the only format accepted by SCHF Core for migrated data.

It is deterministic, auditable, versioned, and portable. A bundle can be validated without knowing the source database.

## Required Files

| File | Required | Description |
|------|----------|-------------|
| `manifest.json` | yes | Bundle metadata, compatibility, source inventory, and file manifest |
| `organization.json` | yes | Target organization record |
| `users.json` | yes | Users to create or map |
| `roles.json` | yes | Roles to create or map |
| `permissions.json` | yes | Permissions to create or map |
| `suppliers.json` | yes | Suppliers/vendors |
| `accounts.json` | yes | Financial accounts |
| `banks.json` | yes | Bank definitions |
| `categories.json` | yes | Financial categories |
| `payments.json` | yes | Payables/receivables/payment records |
| `expenses.json` | yes | Expense records |
| `report.json` | yes | Export report and validation summary |
| `checksum.sha256` | yes | SHA-256 checksums for every bundle file |

## Import Rules

1. Core validates checksum before reading records.
2. Core validates `manifest.json` against the schema.
3. Core validates bundle version compatibility.
4. Core previews import effects before writing.
5. Core imports records only after explicit confirmation.
6. Core writes an import report and audit trail.

## Forbidden Content

- Legacy raw database files.
- Legacy dump files.
- Credentials.
- Client documents not required by the normalized import.
- Source-specific migration scripts.
