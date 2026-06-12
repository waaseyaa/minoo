# Minoo resurrection — progress and decision log

Mode: autonomous Phases 1-5, no per-phase review. Audit log for Russell.
Target: language-platform-only Minoo on latest Waaseyaa alpha, live at minoo.live on the Pi beside oiatc.ca.

## Ground rules in force
- E:\backups read-only; all work from copies.
- Phase 4 corpus: consent flags OFF, admin-only, no search, no AI grounding. Unsure = gated.
- Counts must verify (dictionary_entry 21721, user 4, game_session 118) before deploy.
- No table drops. No AI-generated Anishinaabemowin. No orthography corrections. Corpus content never committed.
- Stop-and-ask: force-pushes, non-minoo.live DNS, deletions outside the clone. minoo.live cutover pre-approved.

## Phase 0 — repo (done)
- Canonical repo: github.com/waaseyaa/minoo, cloned to Local Sites\minoo.
- HEAD: e83b6c7 (2026-05-21) "fix(deploy): pin waaseyaa framework checkout to composer.lock version".
- Note: gh repo clone hung for an hour with no output; killed, plain git clone succeeded in seconds.

## Phase 1 — lineage reconciliation (done)
Full report: docs/lineage-report.md. Highlights:
- Latest framework release: v0.1.0-alpha.208 (2026-06-12). Target for Phase 2.
- Deployed snapshot src/ is byte-identical to commit 8107dba (alpha.187 bump). No prod-only drift.
- Working tree (Projects\Minoo) is an alpha.157-era fork; ALL its language-domain work (5 entities + Ingestion subsystem) was later merged to main, identical modulo namespaces. Contributes nothing.
- Decision: build on main HEAD (e83b6c7). Snapshot and working tree are reference-only from here.
- Phase 4 alert: provider defines speaker.consent_public_display DEFAULT 1 — corpus import must force every consent flag OFF explicitly.

## Decisions
- (running list, newest last)
- D1: gh repo clone hung; switched to plain git clone. (Phase 0)
- D2: Use main HEAD as the only source tree; no porting from snapshot or working tree needed (evidence in docs/lineage-report.md). (Phase 1)
- D3: Worktree minoo-at-187 created beside the clone for fingerprinting; will remove after Phase 2 starts. (Phase 1)
