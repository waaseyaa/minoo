---
affected_files: []
cycle_number: 1
mission_slug: migrate-community-marker-to-explicit-tenancy-01KR69KT
reproduction_command:
reviewed_at: '2026-05-09T12:30:28Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP01
---

# Rescope feedback for WP01

T001 verification surfaced fundamental provider-ownership drift between
CLAUDE.md documentation and actual code:

- WP01's planned cluster (Group, Leader, Contributor) is wrong:
  - Group: NOT REGISTERED in any provider (orphan in src/Entity/Group.php)
  - Leader: registered in EntityContentProvider.php:470 (not Community)
  - Contributor: registered in EntityContentProvider.php:322 (not Community)

The mission's WP boundaries are being re-planned to match code truth:

- New WP01: EntityContentProvider cluster (OralHistory, Contributor, Post, Leader)
- New WP02: EntityFoundationProvider cluster (Event, Teaching)
- New WP03: Group orphan + final reconciliation

CLAUDE.md drift filed as follow-up issue #760.
