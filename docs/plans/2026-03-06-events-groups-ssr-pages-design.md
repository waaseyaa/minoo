# Events & Groups SSR Pages Design

Issue: #6 (Build SSR public pages for events and community groups)

## Approach

Demo listing and detail pages with hardcoded data, following the same pattern as the language page. Establishes card components and detail page layout that will repeat across teachings (#7) and language (#8). When real entity rendering lands, detail pages will migrate to `renderEntity()` with path aliases — the current path-conditional shim is intentionally temporary.

## Pages

### Events (`/events` and `/events/{slug}`)

Single template `events.html.twig` with path conditional:
- `/events` — Card grid with 3 demo events (powwow, gathering, ceremony — matching `ConfigSeeder` types)
- `/events/{slug}` — Full detail page for matched slug, prose-constrained body, back link

Demo events:
1. **Summer Solstice Powwow** (powwow) — June 21-22, Manitoulin Island
2. **Community Healing Circle** (gathering) — Weekly, Sudbury Community Centre
3. **Water Ceremony** (ceremony) — March 22, Lake Huron shoreline

### Groups (`/groups` and `/groups/{slug}`)

Single template `groups.html.twig` with path conditional:
- `/groups` — Card grid with 3 demo groups (online, local, advocacy — matching `ConfigSeeder` types)
- `/groups/{slug}` — Full detail page for matched slug

Demo groups:
1. **Anishinaabe Language Circle** (online) — Online community for Ojibwe language learners
2. **Sudbury Indigenous Hub** (local) — Northern Ontario, community space and resource centre
3. **Water Protectors Alliance** (advocacy) — Great Lakes region, environmental advocacy

## New Components

**Event card** (`templates/components/event-card.html.twig`)
- Props: `title`, `type`, `date`, `location`, `excerpt`, `url`
- Uses `.card` base, adds `.card__badge` for type and `.card__date` for date

**Group card** (`templates/components/group-card.html.twig`)
- Props: `name`, `type`, `region`, `excerpt`, `url`
- Uses `.card` base, adds `.card__badge` for type

## CSS Additions (in `@layer components` of `minoo.css`)

- `.card__badge` — Type indicator pill (distinct from `.card__tag` — positioned before title, uses `--text-sm`, `--info` or `--accent` background depending on context)
- `.card__date` — Date display (mono font, `--text-sm`, secondary color)
- `.detail` — Detail page layout wrapper
- `.detail__header` — Title + metadata area
- `.detail__body` — Prose-constrained content area
- `.detail__back` — Back navigation link

## Routing

Path-conditional in each template:

```twig
{% if path == '/events' %}
  {# listing content #}
{% elseif path starts with '/events/' %}
  {# detail content — match slug #}
{% else %}
  {# fallback #}
{% endif %}
```

Temporary shim. Will be replaced by `renderEntity()` + path aliases when real entity data exists.

## Files

| Action | File |
|--------|------|
| Create | `templates/events.html.twig` |
| Create | `templates/groups.html.twig` |
| Create | `templates/components/event-card.html.twig` |
| Create | `templates/components/group-card.html.twig` |
| Modify | `public/css/minoo.css` (badge, date, detail styles in components layer) |

## Implementation Order

Events end-to-end first (listing + detail), then groups. This establishes both patterns before repeating.
