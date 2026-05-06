# Mission closed — inline resolution

**Closed:** 2026-05-06 (out of band, not via spec-kitty workflow)
**Tracking:** waaseyaa/minoo#750
**Landed via:** PR #751 (commit `1a9f5b4f`)
**Final framework version:** waaseyaa/* alpha.173

## Why closed inline

Mission scope was "upgrade Minoo to alpha.171". Two upstream contract breaks blocked it:

1. **framework#1388** — groups + taxonomy `description` `FieldDefinition` missing `targetEntityTypeId`. Closed in alpha.172.
2. **framework#1390** — controller dispatcher rejected unannotated `array $params, array $query` signatures (184 methods × 37 controller files in Minoo). Closed in alpha.173 by an implicit-array compatibility shim (`AppParameterBindingBuilder`) merged via the `dispatcher-array-param-compat-shim-01KQW12S` mission upstream.

When alpha.173 shipped, the mission's frozen state was 7/10 WPs approved at alpha.171. Rather than re-scope WP08–WP10 from alpha.171 → alpha.173 and run them through the formal review loop, we collapsed the remaining work into PR #751 directly:

- Composer bump alpha.171 → alpha.173 (single commit on `lane-a`)
- HasCommandsInterface opt-in fix for `AppCommandServiceProvider` (alpha.173 contract change surfaced by CI; not present in original WP scope)
- CLAUDE.md sync line refreshed to alpha.173

Verification:
- `./vendor/bin/phpunit`: 1091 tests / 3375 assertions / 3 known skips
- Route smoke (8 paths incl. dynamic community detail): all 200/302, no #1390 rejection text

## Follow-ups carried forward (filed against milestone v0.14)

- **#748** — Surface `dispatcher.deprecation` notice logs. The shim emits one `notice`-level log per `(controller_class::method, parameter_name)` per dispatcher build for migration-debt inventory, but Minoo's logger drops `notice` level today, so the migration backlog (~184 entries) is invisible.
- **#749** — Migrate `HasCommunityInterface` markers on 7 entities to `EntityType` `tenancy: ['scope' => 'community']`. Framework warns these markers will be removed in the next minor release.

The 184-method controller-attribute migration (replacing implicit `array $params, array $query` with explicit `#[MapRoute]`/`#[MapQuery]`) is a future cleanup, blocked on #748 surfacing the inventory.

## Artifacts

The mission's spec, plan, research, and WP files are kept under this directory as historical record. They were authored against alpha.171 and reflect that scope; they are not authoritative for alpha.173.

- `spec.md` — original mission spec (alpha.171 scope)
- `plan.md` — original implementation plan
- `research/` — investigation notes incl. `smoke-test-results.md` (alpha.172 retest) and `alpha-172-retest-finding.md` (corrected #1388/#1390 attribution)
- `tasks/WP01.md` … `WP10.md` — work package files
- `status.json`, `status.events.jsonl` — spec-kitty state files (frozen at the planning snapshot pushed to origin; locally-mutated state during the freeze was discarded)

## Lane branches

- `kitty/mission-upgrade-waaseyaa-alpha-171-01KQTDC2-lane-a` — merged via PR #751 and deleted
- `kitty/mission-upgrade-waaseyaa-alpha-171-01KQTDC2-lane-planning` — still on alpha.171 baseline; not merged. Safe to delete (planning artifacts only).
