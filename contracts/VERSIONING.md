# Version Contract

## Current Bundle Version

`1.0.0`

## Compatibility

SCHF Core declares the bundle version range it accepts. SCHF Migration exports the bundle version it generated.

Compatibility is evaluated with semantic versioning:

- PATCH: backward-compatible corrections.
- MINOR: backward-compatible additions.
- MAJOR: breaking changes.

## Manifest Fields

Every bundle manifest must include:

- `bundle_version`
- `sdk_version`
- `core_min_version`
- `core_max_version`
- `generated_at`
- `generator`
- `files`
