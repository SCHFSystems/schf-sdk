# SCHF Bundle CLI

**Version:** 1.0.0

---

## Overview

The Bundle CLI provides command-line access to all bundle operations. It is available via the `schf` binary after installing the SDK.

---

## Installation

```bash
composer global require joao-aschenbrenner/schf-sdk
```

Or run directly from the SDK directory:

```bash
php bin/schf bundle <command>
```

---

## Commands

### `schf bundle build`

Build a new `.schf` bundle.

```bash
schf bundle build --org-id=org-123 --org-name="Santa Casa" --source-type=firebird --output=./santacasa.schf
```

**Options:**
| Option | Description |
|--------|-------------|
| `--org-id` | Organization external ID (required) |
| `--org-name` | Organization name (required) |
| `--source-type` | Source database type (firebird, mysql, etc.) |
| `--output` | Output path for the .schf file |

### `schf bundle validate`

Validate a bundle without importing.

```bash
schf bundle validate santacasa.schf
```

**Exit codes:** 0 = valid, 1 = invalid

### `schf bundle doctor`

Run full diagnostics on a bundle.

```bash
schf bundle doctor santacasa.schf
schf bundle doctor santacasa.schf --deep
```

**Output:** Summary, compatibility check, integrity check, quality assessment.

| Flag | Description |
|------|-------------|
| `--deep` | Perform deep quality analysis of record data |

### `schf bundle inspect`

Open and inspect bundle contents without importing.

```bash
schf bundle inspect santacasa.schf
```

**Output:** UUID, version, organization, source, record counts.

### `schf bundle info`

Show bundle metadata as JSON.

```bash
schf bundle info santacasa.schf
```

### `schf bundle sign`

Sign a bundle with a private key.

```bash
schf bundle sign santacasa.schf ./private-key.pem
```

**Supported algorithms:**
- RSA (SHA-256) — via OpenSSL
- Ed25519 — via libsodium

### `schf bundle verify`

Verify a bundle's signature.

```bash
schf bundle verify santacasa.schf
schf bundle verify santacasa.schf ./public-key.pub
```

### `schf bundle history`

Show bundle history (requires prior recording).

```bash
schf bundle history
```

### `schf bundle version`

Show bundle SDK version information.

```bash
schf bundle version
```

### `schf bundle help`

Show usage information.

```bash
schf bundle help
```

---

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error or validation failure |

---

## Examples

```bash
# Build and validate in one pipeline
schf bundle build --org-id=sc --org-name="Santa Casa" --output=./sc.schf && \
schf bundle validate ./sc.schf && \
schf bundle doctor ./sc.schf
```
