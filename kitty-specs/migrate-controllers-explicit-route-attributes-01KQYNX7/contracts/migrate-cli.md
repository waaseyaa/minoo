# Contract: `scripts/migrate-controller-attributes.php`

**Lifecycle**: Transient — created in WP01, used by WPs 01–06, removed before WP06 merges. **Never** on `main`.

## Synopsis

```
php scripts/migrate-controller-attributes.php [OPTIONS]
```

## Options

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `--cluster <name>` | string | (none) | Resolve to the fixed list of controllers for the named WP cluster. One of `wp01`, `wp02`, `wp03`, `wp04`, `wp05`, `wp06`. Mutually exclusive with `--filter`. |
| `--filter <ControllerName>` | string (repeatable) | (none) | Restrict to specific controller class names (without namespace, without `.php`). Mutually exclusive with `--cluster`. |
| `--path <dir>` | string | `src/Controller` | Root directory to scan. |
| `--dry-run` | flag | true (when neither `--apply` nor `--dry-run` is set, default to `--dry-run`) | Print the unified diff to stdout; make no file changes. |
| `--apply` | flag | false | Write changes to files in place. |
| `--verbose` | flag | false | Print one line per parameter migrated. |
| `--help` | flag | — | Print usage and exit 0. |

## Behavior

For each `*.php` file in the resolved set, the script:

1. **Reads** the file as bytes.
2. **Tokenizes** with `token_get_all($source, TOKEN_PARSE)`.
3. **Walks** tokens to find `function`-keyword param lists (only at method scope — closures inside method bodies are skipped).
4. For each param list:
   - Identifies parameters whose type is exactly `T_ARRAY` (a single `T_ARRAY` token, not a union/nullable) and whose variable name is exactly `$params` or `$query`.
   - Checks whether the parameter is already preceded by an `T_ATTRIBUTE` block containing `MapRoute` (for `$params`) or `MapQuery` (for `$query`). If so, **skip**.
   - Records the byte offset of the `T_ARRAY` token's start as a splice point.
5. **Inserts** the appropriate attribute prefix (`#[MapRoute] ` or `#[MapQuery] `) at each recorded offset (working from highest offset to lowest, so earlier offsets remain valid).
6. **Ensures** `use Waaseyaa\SSR\Attribute\MapRoute;` and `use Waaseyaa\SSR\Attribute\MapQuery;` are present, alphabetically positioned among the file's `Waaseyaa\` use statements (idempotent — skip if already present).
7. **Writes** the modified bytes back to the file (only under `--apply`).

## Exit codes

| Code | Meaning |
|------|---------|
| 0 | Success. Under `--dry-run`: diff printed (or empty diff if no changes needed). Under `--apply`: files written (or unchanged if already migrated). |
| 1 | Argument error (e.g. both `--cluster` and `--filter` passed; unknown cluster name). |
| 2 | Parse error in a target file (token list incomplete; could not splice safely). The offending file is left unchanged. |

## Example invocations

```bash
# Preview WP01 changes without writing
php scripts/migrate-controller-attributes.php --cluster wp01 --dry-run

# Apply WP01 changes
php scripts/migrate-controller-attributes.php --cluster wp01 --apply

# Migrate a single controller (smoke-test the script)
php scripts/migrate-controller-attributes.php --filter AuthController --apply --verbose

# Equivalent in a different cluster
php scripts/migrate-controller-attributes.php --filter EngagementController --filter MessagingController --apply
```

## Cluster definitions

| Cluster | Controllers (basename, no `.php`) |
|---------|-----------------------------------|
| `wp01`  | `AccountHomeController`, `AuthController`, `CoordinatorDashboardController`, `RoleManagementController`, `VolunteerController`, `VolunteerDashboardController` |
| `wp02`  | `AgimController`, `CrosswordController`, `GuessPriceController`, `JourneyController`, `MatcherController`, `ShkodaController` |
| `wp03`  | `NewsletterAdminApiController`, `NewsletterController`, `NewsletterEditorController` |
| `wp04`  | `BlockController`, `ChatController`, `EngagementController`, `FeedController`, `MessagingController` |
| `wp05`  | `BusinessController`, `CommunityController`, `ContributorController`, `EventController`, `GroupController`, `HomeController`, `LanguageController`, `LocationController`, `OpenGraphController`, `OralHistoryController`, `PeopleController`, `StaticPageController`, `TeachingController` |
| `wp06`  | `ElderSupportController`, `ElderSupportWorkflowController`, `IngestionApiController`, `IngestionDashboardController` |

## Idempotency

Running the script twice with `--apply` against the same cluster MUST produce no second-pass diff. This is verified during WP01 self-test before any other WP starts.

## Non-goals

- Does not modify framework code (`vendor/`).
- Does not modify routes, templates, CSS, or JS.
- Does not modify parameters whose type is `?array`, `array|null`, or `iterable`.
- Does not modify parameters whose name is anything other than `$params` or `$query`.
- Does not modify parameters that already carry the corresponding attribute.
- Does not edit closures inside method bodies.
