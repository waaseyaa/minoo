# Contract: `scripts/check-implicit-array-params.php`

**Lifecycle**: Long-lived — committed in WP06, lives on `main` indefinitely as a regression guard.

## Synopsis

```
php scripts/check-implicit-array-params.php [OPTIONS]
```

## Options

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--path <dir>` | string | `src/Controller` | Root directory to scan recursively. |
| `--format <text\|json>` | string | `text` | Output format. `text` for human/CI logs, `json` for machine consumers. |
| `--quiet` | flag | false | Suppress per-offender lines; only emit the summary count. |
| `--help` | flag | — | Print usage and exit 0. |

## Behavior

Walks `*.php` files under `--path`. For each method-scope parameter list, identifies any parameter that:

- has type exactly `array` (not `?array`, not `array|null`, not `iterable`), AND
- is named exactly `$params` or `$query`, AND
- is **not** preceded by an attribute block containing `MapRoute` (for `$params`) or `MapQuery` (for `$query`).

For each offender, prints to stdout:

```
<FQCN>::<method> $<param> -> #[<RecommendedAttribute>]
```

After the per-offender lines, prints a summary line to stderr:

```
TOTAL: <N> unannotated array params across <M> controllers
```

## Exit codes

| Code | Meaning |
|------|---------|
| 0 | Zero offenders — repo is clean. |
| 1 | One or more offenders detected. The summary count to stderr. |
| 2 | Argument error (unknown flag; bad `--path`). |
| 3 | Parse error in a scanned file. |

## Example invocations

```bash
# Run from repo root (project default)
php scripts/check-implicit-array-params.php

# JSON output for downstream tooling
php scripts/check-implicit-array-params.php --format json

# Quiet check (CI/lefthook — exit code is the contract)
php scripts/check-implicit-array-params.php --quiet
```

## Example output (text format)

```
$ php scripts/check-implicit-array-params.php
App\Controller\AuthController::loginForm $params -> #[MapRoute]
App\Controller\AuthController::loginForm $query -> #[MapQuery]
TOTAL: 2 unannotated array params across 1 controllers
$ echo $?
1
```

## Example output (json format)

```json
{
  "schema": "implicit-array-params/v1",
  "scanned_path": "src/Controller",
  "offenders": [
    {
      "fqcn": "App\\Controller\\AuthController",
      "method": "loginForm",
      "parameter": "params",
      "recommended_attribute": "MapRoute"
    }
  ],
  "total_offenders": 1,
  "total_files": 1
}
```

## Performance contract

- < 2s wall time on `src/Controller/` (NFR-003).
- No Composer autoload, no framework boot — `php` + `token_get_all` only.

## CI / hook integration (deferred)

This mission **does not** wire the script into `.github/workflows/*` or `.husky/`. The script is committed as a standalone tool. CI integration is a follow-up issue (see C-004 in spec.md).

A representative future invocation (NOT part of this mission) would be:

```yaml
# .github/workflows/lint.yml (hypothetical follow-up)
- name: Check for implicit array params in controllers
  run: php scripts/check-implicit-array-params.php
```

## Non-goals

- Does not auto-fix. Migration is the migration script's job (transient, removed in WP06).
- Does not scan outside `--path`. Framework, tests, and other directories are out of scope.
- Does not understand `?array` or union types as offenders — only the bare `array` type.
