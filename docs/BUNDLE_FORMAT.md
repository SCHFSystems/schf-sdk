# Bundle Format

The bundle is a ZIP archive containing normalized JSON files and a SHA-256 checksum file.

## Validation Order

1. Open ZIP safely into a temporary directory.
2. Reject archives with path traversal entries.
3. Ensure all required files exist.
4. Validate `checksum.sha256` against actual files.
5. Validate `manifest.json` against `schemas/bundle/manifest.schema.json`.
6. Validate each data file against its record schema.
7. Produce preview before import.

## File Rules

- JSON files must be UTF-8.
- Amounts are decimal numbers.
- Dates use ISO `YYYY-MM-DD`.
- Date-times use RFC 3339.
- IDs are source-stable `external_id` strings.
- Attachments are optional and referenced by metadata.
