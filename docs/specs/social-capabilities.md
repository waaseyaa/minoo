# Minoo social capabilities

Revision 1. Scope accepted for planning by Russell, 8 October 2026; detailed
transitions remain draft. Tracking: [#930](https://github.com/waaseyaa/minoo/issues/930).
This is not implementation, release or deployment acceptance.

## Product and dependency boundary

Minoo offers community participation around language, culture, knowledge and
local connections. Profiles, posts/feeds, comments/replies, reactions, following,
groups, private/group conversations, block/report, notifications and exit are
the intended social capability set. Existing community/cultural access and
Elder/Knowledge Keeper protections remain product obligations.

Use [Waaseyaa social contracts](https://github.com/waaseyaa/framework/blob/main/docs/specs/social-capabilities.md)
and [upstream design #3197](https://github.com/waaseyaa/framework/issues/3197)
for reusable behavior. Minoo owns community context, presentation, configuration,
scoped coordinator policy and adoption evidence. No competing application social
engine is the target; current app code is migration input, not automatic authority.

## Requirements and acceptance

| ID | Product requirement | Acceptance to qualify |
| --- | --- | --- |
| MS-01 | Public identity and authenticated authority remain distinct | A displayed contributor cannot act as a logged-in member without authenticated authority |
| MS-02 | Eligible members publish posts with explicit visibility and attribution | Forged author, draft/private/removed content and unauthorized community reads are refused across detail/feed/search |
| MS-03 | Members comment on permitted posts and reply to comments | Reply target stays on the same post; current visibility/block rules govern body, counts and notification; deleting a parent has an explicit child policy |
| MS-04 | Reactions, follows, saves and membership do not grant contact permission | Following a person or joining a community does not silently open DMs or reveal private state |
| MS-05 | Private and group conversations use recipient consent and scoped participant roles | Stranger/blocked sends fail; authorship cannot be forged; members cannot edit another sender or read position |
| MS-06 | Reporting and moderation have explicit community-scoped authority | Coordinator authority is not universal access to private conversations; evidence is restricted |
| MS-07 | Leaving, removal and account/content deletion propagate coherently | Removed content disappears from unauthorized derived views and delayed notification checks; last-admin handling is explicit |
| MS-08 | Notifications follow durable success, preferences and current visibility | Rollback sends nothing and retries do not create duplicate logical alerts |
| MS-09 | Minoo qualifies the adopted Framework version | Real kernel/session and production no-dev journeys pass against an exact lock; source tests alone do not close adoption |

Reply depth, edit/delete windows, guest visibility, community feed selection,
contact-request defaults, group admission, block effects on shared history and
report retention require coherent design decisions before implementation.
Familiar social behavior is the baseline, not permission to invent these defaults.

## Verified source boundary

At `8a906d93889d0968d3c8ffbcbc3103f35832b444`, the app locks older Framework
alpha.267 packages. SocialApiRouteProvider exposes posts, reactions and comments;
following routes are deferred and chat/messaging routes are not restored. Earlier
closed messaging issues and dormant tables do not prove a working current feature.
No new runtime or production verification is claimed here.

## Existing owners and deferred scope

#688 owns feed/engagement including replies; #817/#818 controller/UI adoption;
#574 uploads; #676 delete affordance; #432-#438 notifications; #351 moderation;
#582 attachments; #732 messaging acceptance; #763 Framework adoption. #930
coordinates this spec, not duplicate implementation checklists.

Upstream #2756 owns engagement target integrity, #1627/#2762 group contracts,
and #2745 notification routing. Treat them as a blocker only after recording a
specific failing acceptance dependency. Missing planned features remain features.
Paid contact, federation, voice/video and algorithmic ranking are not prerequisites
for this convergence slice. The [roadmap](../roadmap.md) owns delivery order.
