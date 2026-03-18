# Minoo Launch Verification Checklist

Pre-launch manual verification for minoo.live.

## Homepage (/)

- [ ] Desktop: hero, search bar, tabs (Events/People/Groups/Teachings/Businesses) all visible
- [ ] Mobile (375px): search bar stacks cleanly, tabs scroll horizontally
- [ ] Location bar shows detected community or "All Communities"
- [ ] Each tab loads content (or shows empty state)
- [ ] Featured section renders if data exists
- [ ] Business cards link to /businesses/{slug} (not /groups/)
- [ ] OG meta image loads (og-default.png)

## List Pages

### /events
- [ ] Desktop: event cards render with date, title, community
- [ ] Mobile: single-column layout, readable text
- [ ] Pagination works if > 12 items

### /people
- [ ] People cards with name, role, community
- [ ] Elder badge displays where applicable

### /groups
- [ ] Group cards with name, type, community
- [ ] Does not include type=business entries

### /businesses
- [ ] Business cards render separately from groups
- [ ] Links go to /businesses/{slug}

### /teachings
- [ ] Teaching cards with title, category
- [ ] Related sections on detail pages

### /elders
- [ ] Elder profiles render
- [ ] Respectful presentation of elder information

## Detail Pages

- [ ] /events/{slug}: full event detail, related events section
- [ ] /people/{slug}: person detail, roles, community association
- [ ] /groups/{slug}: group detail, members/events if available
- [ ] /businesses/{slug}: business detail, owner card if available
- [ ] /teachings/{slug}: teaching detail, related teachings

## Static Pages

- [ ] /about: content renders
- [ ] /how-it-works: content renders
- [ ] /volunteer: content renders
- [ ] /safety: content renders
- [ ] /legal: content renders
- [ ] /data-sovereignty: content renders
- [ ] /language: content renders, Ojibwe text displays correctly

## Search

- [ ] /search: search input works
- [ ] Results display for known terms
- [ ] Empty state for no results

## Navigation

- [ ] Header: logo links to /, nav links work
- [ ] Footer: all links functional
- [ ] Skip-to-content link works
- [ ] 404 page renders for invalid URLs

## Localization

- [ ] English (default): all UI strings resolve (no translation keys visible)
- [ ] /oj/ prefix: Ojibwe UI strings display where available
- [ ] Language switcher toggles between en/oj

## Images and Assets

- [ ] Leaflet map markers display (PNG files deployed)
- [ ] OG default image accessible at /img/og-default.png
- [ ] Favicon loads

## Cross-Browser

- [ ] Chrome (desktop + mobile): all pages render
- [ ] Firefox (desktop): all pages render
- [ ] Safari (desktop + mobile): all pages render

## Performance

- [ ] Homepage loads in < 3s on 3G throttle
- [ ] No console errors on any page
- [ ] No broken image links

## Social Sharing

- [ ] Share homepage URL on Twitter/X: preview card shows
- [ ] Share homepage URL on Facebook: preview card shows
- [ ] OG tags present on all public pages

## Deployment

- [ ] `task deploy` succeeds
- [ ] Production site matches local dev
- [ ] SSL certificate valid
- [ ] HTTP to HTTPS redirect works
