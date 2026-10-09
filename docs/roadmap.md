# Minoo roadmap

Updated 8 October 2026. This is the current delivery sequence; GitHub owns live
status, [specs](specs/workflow.md) own behavior, and individual issues retain
acceptance/evidence. Existing unrelated language, content, ingestion and community
work remains with its owners. No dates or release promises are assigned here.

| Phase | Outcome | Owners and exit |
| --- | --- | --- |
| 1. Baseline and contracts | Reconcile installed Framework versions, current social routes, community/privacy policy and stale designs | #930 and #763; accepted social transitions and exact adoption map, not historical completion claims |
| 2. Posts and participation | Reliable feed, posting, comments/replies, reactions and moderation using shared operations | #688, #817/#818, #574, #676, #351; visibility, revocation, retry and removal acceptance through the app |
| 3. Relationships and conversations | Following, membership and private/group messaging with contact control | #930 coordinates bounded slices after shared contracts; #732 owns messaging acceptance, #582 later attachments |
| 4. Notifications and qualification | Privacy-preserving notifications and complete cross-package journeys | #432-#438 and #763; exact released lock, kernel/session/no-dev and failure-path evidence |

[Social specification](specs/social-capabilities.md) includes comments and replies
as intended capability. It does not claim every row is implemented. Waaseyaa
[#3197](https://github.com/waaseyaa/framework/issues/3197) owns reusable social
design. Framework defects remain upstream; Minoo issues record their affected
scenario and unblock condition. Stop only that slice, continue independent work,
and verify the real app after adopting the fix.

Use [the dependency workflow skill](../skills/framework-dependency-workflow/SKILL.md).
Confirmed defects, dependency upgrades and future features are distinct work.
A closed historical issue does not establish current functionality. The retired
March messaging design and plan are replaced by the current social contract;
Git history preserves them. Feature implementation and deployment need their own
accepted contracts and authorization.
