# SSR Page Consistency Audit Matrix

Homepage source-of-truth: `templates/feed.html.twig` and `public/css/minoo.css` (`.feed-layout`, `.feed-main`, sidebar behavior).

## Buckets

| Bucket | Templates | Notes |
|---|---|---|
| Feed/Home | `templates/feed.html.twig` | Canonical content rhythm |
| Domain listing/detail | `templates/events.html.twig`, `templates/groups.html.twig`, `templates/teachings.html.twig`, `templates/people.html.twig`, `templates/businesses.html.twig`, `templates/oral-histories.html.twig`, `templates/language.html.twig`, `templates/communities/list.html.twig` | Listing + detail hybrids |
| Search | `templates/search.html.twig` | Search layout + filters |
| Games | `templates/games.html.twig`, `templates/shkoda.html.twig`, `templates/crossword.html.twig`, `templates/matcher.html.twig` | Product-specific surfaces |
| Marketing/static | `templates/about.html.twig`, `templates/how-it-works.html.twig`, `templates/get-involved.html.twig`, `templates/legal.html.twig`, `templates/safety.html.twig`, `templates/data-sovereignty.html.twig` | Informational pages |
| Auth | `templates/auth/*.html.twig` | Centered forms, no sidebar |
| Account/dashboards/admin | `templates/account/home.html.twig`, `templates/dashboard/*.html.twig`, `templates/admin/*.html.twig` | Operational pages |

## Checklist

| Check | Pass Criteria |
|---|---|
| Width | Uses shared max-width rhythm utility/token (not one-off inline widths) |
| Hero | Uses shared hero treatment (`listing-hero`) or intentional documented variant |
| Grid | Uses `card-grid`/`card-grid--compact`; no page-local third-column override |
| Filters | Uses established filter patterns (chips/select/facet) without duplicating style logic |
| Drift | No orphan classes in templates lacking CSS rules |

## Initial Findings

| Page | Finding | Action |
|---|---|---|
| `templates/people.html.twig` | Uses `mentor-callout` class without dedicated stylesheet rule in `minoo.css` | Add component rule in CSS |
| `templates/components/feed-sidebar-left.html.twig` | Not currently included by `templates/feed.html.twig` | Keep as non-blocking drift note; validate whether this is future-facing |
| Listing hubs (multiple) | Shared `flow-lg` + `card-grid` already established | Keep; avoid forcing third card column |
