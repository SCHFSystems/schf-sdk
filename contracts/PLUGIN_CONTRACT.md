# Plugin Contract

## Purpose

Every source connector is a plugin. Plugins detect, inventory, validate, and normalize legacy sources into a Migration Bundle.

Plugins never write directly into SCHF Core.

## Required Capabilities

| Capability | Description |
|------------|-------------|
| `detect` | Determine whether the plugin can read the source |
| `inventory` | List tables, fields, indexes, keys, volumes, and date ranges |
| `validate` | Detect corrupt records, invalid types, missing relationships, duplicates |
| `map` | Produce a source-to-bundle mapping plan |
| `normalize` | Convert source records to bundle records |
| `preview` | Report import counts, ignored records, historical-only records |
| `export` | Generate a valid Migration Bundle |

## AI Boundaries

AI may propose mappings and transformations. It must not write to the source, the bundle, or SCHF Core without deterministic pipeline approval.
