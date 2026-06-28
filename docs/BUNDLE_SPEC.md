# SCHF Bundle Specification

**Version:** 1.0.0  
**Status:** Active  

---

## 1. Overview

The SCHF Bundle (`.schf`) is the **only official transport format** for data moving between SCHF Migration and SCHF Core. Every migration produces a bundle; every import consumes one.

No direct database communication between Migration and Core is permitted.

---

## 2. File Format

A bundle is a standard **ZIP archive** (`.schf` extension) containing:

- Normalized JSON data files
- A manifest describing the bundle contents
- A checksum file for integrity verification
- Optional signature files for authenticity verification

### 2.1 Extension

| Property | Value |
|----------|-------|
| Extension | `.schf` |
| MIME type | `application/vnd.schf.bundle` |
| Internal format | ZIP (PKZIP 2.0+) |

### 2.2 Required Files

Every bundle **must** contain:

| File | Description |
|------|-------------|
| `manifest.json` | Bundle metadata, versions, file manifest with SHA256 |
| `organization.json` | Target organization record |
| `users.json` | Users to create or map |
| `roles.json` | Roles to create or map |
| `permissions.json` | Permissions to create or map |
| `suppliers.json` | Suppliers/vendors |
| `accounts.json` | Financial accounts |
| `banks.json` | Bank definitions |
| `categories.json` | Financial categories |
| `payments.json` | Payable/payment records |
| `expenses.json` | Expense records |
| `report.json` | Export report with validation summary |
| `checksum.sha256` | SHA-256 checksums for every bundle file |

### 2.3 Optional Files

| File | Description |
|------|-------------|
| `signature.sig` | Cryptographic signature of checksum.sha256 |
| `signing-key.pub` | Public key for signature verification |
| `attachments/` | Additional reference files (logs, screenshots) |

---

## 3. Directory Structure

```
Client.schf
├── manifest.json
├── organization.json
├── users.json
├── roles.json
├── permissions.json
├── suppliers.json
├── accounts.json
├── banks.json
├── categories.json
├── payments.json
├── expenses.json
├── report.json
├── checksum.sha256
├── signature.sig          (optional)
├── signing-key.pub        (optional)
└── attachments/           (optional)
    ├── log.txt
    └── ...
```

---

## 4. Manifest Schema

The `manifest.json` file follows this schema:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `bundle_version` | string (semver) | yes | Bundle format version |
| `sdk_version` | string (semver) | yes | SDK version used to generate |
| `core_min_version` | string (semver) | yes | Minimum Core version required |
| `core_max_version` | string (semver) | no | Maximum Core version supported |
| `generated_at` | string (RFC 3339) | yes | Generation timestamp |
| `generator` | object | yes | Generator metadata (name, version, plugin) |
| `organization` | object | yes | Target organization (external_id, name) |
| `source` | object | yes | Source database info (type, product, version, inventory_hash, bundle_uuid) |
| `files` | array | yes | File manifest with path, schema, records, sha256 |

See `schemas/bundle/manifest.schema.json` for the full JSON Schema.

---

## 5. Data Rules

- JSON files must be **UTF-8 encoded**
- Monetary amounts are **decimal numbers** (as strings in JSON to avoid float precision loss)
- Dates use **ISO 8601** (`YYYY-MM-DD`)
- Date-times use **RFC 3339** (`2026-06-28T12:00:00Z`)
- IDs are **source-stable external_id strings**
- All records in a file must share the **same schema**

---

## 6. Validation Order

1. Open ZIP safely (reject path traversal)
2. Ensure all required files exist
3. Validate `checksum.sha256` against actual file contents
4. Validate `manifest.json` structure and fields
5. Validate each JSON data file is parseable
6. Validate signatures (if present)
7. Produce preview before import

---

## 7. Checksum Format

The `checksum.sha256` file contains one line per file:

```
<SHA256_HEX_UPPERCASE>  <relative_path>
```

Lines are sorted alphabetically by path. Example:

```
37517E5F3DC66819F61F5A7BB8ACE1921282415F10551D2DEFA5C3EB0985B570  categories.json
FE4F754B6330ACA38AA2F956D47B00E3C1B4A40CA1693929C051553C18DEFDE7  manifest.json
```

---

## 8. UUID

Every bundle is assigned a **UUID v4** at build time, stored in `manifest.json` under `source.bundle_uuid`. This UUID is used for:

- Tracking in bundle history
- Deduplication during import
- Audit trail correlation

---

## 9. Version Compatibility

| Bundle Version | SDK Version | Core Min | Core Max |
|---------------|-------------|----------|----------|
| 1.0.x | >= 0.1.0 | 1.5.0 | * |

**Rules:**
- Major version must match for compatibility
- Core version must be >= `core_min_version`
- SDK version must be >= `sdk_min_version`

---

## 10. Forbidden Content

- Legacy raw database files
- Database dumps (SQL, FBK, etc.)
- Credentials or secrets
- Client documents not required by the normalized import
- Source-specific migration scripts
- Executable code
