# Homepage UX Refresh — Design Spec

## Goal

Consolidate Minoo's navigation from 11 top-level items to a clean sidebar-first layout with a minimal header, user menu dropdown, and an enhanced post creation form with image upload.

## Architecture

Three changes that affect the same template area:

1. **Minimal header** — Logo + search bar + avatar (user menu dropdown)
2. **Sidebar-first navigation** — All nav moves to left sidebar, grouped by section
3. **Post creation with images** — Text + photo upload

## 1. Minimal Header

**Current:** 11 top-level nav items (Communities, People, Teachings, Events, Oral Histories, Programs dropdown, Search, Dashboard, Account, Language, Theme)

**New:** Logo + Search + Avatar = 3 elements

### Header layout
```
[Minoo logo] [rainbow bar]                    [Search... input]  [RJ avatar]
```

### User menu dropdown (click avatar)
Grouped sections:
- **Identity:** Initials avatar + name + email
- **Navigation:** My Profile, Dashboard (coordinator only)
- **Preferences:** Theme (shows current), Language (shows current)
- **Session:** Sign Out (red)

### Guest state
- Avatar replaced with "Log In" button
- No dropdown

### Mobile
- Search becomes icon (expands on tap)
- Avatar stays, dropdown unchanged
- Hamburger button toggles left sidebar as overlay

## 2. Sidebar-First Navigation

**Current:** Top nav bar + left sidebar (partial duplicate with Events, Communities, etc.)

**New:** Left sidebar is the single source of navigation, included in `base.html.twig` (not feed-only). Three groups:

### Navigate (main sections)
- Home (feed)
- Communities
- Events
- Teachings
- People
- Oral Histories

### Programs
- Elder Support
- Volunteer

### Your Communities (authenticated only)
- List of followed communities with initials avatar
- Links to `/communities/{slug}`

### Sidebar behavior
- **Desktop:** Always visible, ~220px wide
- **Tablet:** Collapsed to icons only (~60px), hover/click expands
- **Mobile:** Hidden by default, hamburger in header toggles as overlay
- Active page highlighted with background color

### Structural change: sidebar in base.html.twig

The sidebar currently only exists in `feed.html.twig`. It must move into `base.html.twig` so it appears on all pages (events, teachings, communities, etc.). This means:
- `base.html.twig` gets a two-column layout: sidebar + `{% block page_content %}`
- `feed.html.twig` and other page templates fill `page_content`
- Pages that want no sidebar (e.g. login, register) use `{% set hide_sidebar = true %}`

### What moves OUT of the header
- Communities, People, Teachings, Events, Oral Histories → sidebar "Navigate"
- Programs (Elder Support, Volunteer) → sidebar "Programs"
- Search → stays in header as search bar
- Dashboard → user menu dropdown
- Account → user menu dropdown (My Profile)
- Language → user menu dropdown
- Theme toggle → user menu dropdown
- Businesses → sidebar "Navigate" (links to `/?filter=business` on feed, no standalone page)

## 3. Post Creation with Images

### Current state
- Text-only post form with community selector
- Click prompt → expand hidden form
- `POST /api/engagement/post` with JSON body `{ body, community_id }`

### New: Add image upload
- **Photo button** below textarea (camera/image icon + "Photo" label)
- Click opens file picker (`accept="image/*"`, multiple allowed)
- **Image preview** shows thumbnails via `URL.createObjectURL()` with X remove button
- Images uploaded as `FormData` (multipart/form-data) alongside text
- Max 4 images per post
- Max 5MB per image

### Post form layout
```
[Avatar] [Textarea: What's happening in your community?    ]
         [Image previews: [img1 x] [img2 x] [+ add]       ]
         [Photo button]              [Community ▾] [Post btn]
```

### JS approach
- Use `FormData` API for multipart submission (replaces current `fetch` + JSON)
- `URL.createObjectURL()` for instant image previews (no FileReader needed)
- Track selected files in a JS array, update previews on add/remove
- Enforce max 4 files and 5MB per file client-side with error message
- Revoke object URLs on remove and form reset

### API changes
- `POST /api/engagement/post` switches from JSON to `multipart/form-data`
- Use Symfony `$request->files->get('images')` for uploaded files (Symfony Request already supports this)
- Use Symfony `$request->request->get('body')` for text fields (replaces `jsonBody()`)
- Controller sequence: validate → create post entity → save → move uploaded files to `storage/uploads/posts/{post_id}/` → update post with image paths → save again
- Post entity gets `images` field (JSON array of relative file paths)
- Feed card renders images in a grid (1 = full width, 2 = side by side, 3-4 = 2x2 grid)

### Image storage
- Use plain PHP `move_uploaded_file()` via a thin `UploadService` in Minoo (not framework — no Waaseyaa upload service exists)
- `UploadService` handles: validate type/size, generate safe filename, move to target dir, return relative path
- Store in `storage/uploads/posts/{post_id}/` (created after post entity saved, so ID is known)
- Serve via symlink: `public/uploads` → `storage/uploads` (created during deploy)
- On post deletion: `UploadService::deletePostImages($postId)` removes the directory

### Image serving
- **Symlink approach** (not controller-served) — simpler, better performance
- `public/uploads` symlink created in Deployer recipe and local setup
- Images served directly by Caddy as static files

### Avatar — deferred
Avatar field on User entity and profile photo upload are **fully out of scope**. The user menu shows initials only (using existing `account_initial` variable). Avatar upload will be its own spec when the profile page is built.

## Files to modify

### Templates
- `templates/base.html.twig` — Replace nav with minimal header + sidebar layout wrapper
- `templates/components/sidebar-nav.html.twig` — New: full sidebar nav (replaces `feed-sidebar-left.html.twig`)
- `templates/components/feed-create-post.html.twig` — Add photo upload UI
- `templates/components/feed-card.html.twig` — Render post images in grid
- `templates/components/user-menu.html.twig` — New: avatar dropdown component
- `templates/feed.html.twig` — Remove inline sidebar, use base layout

### CSS
- `public/css/minoo.css` — Header, sidebar, user menu, post form, image grid styles

### Controllers
- `src/Controller/EngagementController.php` — Handle multipart post with images

### Services
- `src/Support/UploadService.php` — New: validate, move, delete uploaded files

### Entity
- Post entity — Add `images` field definition (JSON array)

### Provider
- `src/Provider/EngagementServiceProvider.php` — Add `images` field to post entity type

### Migrations
- Add `images` column to post table

### JS
- `templates/feed.html.twig` — Update post form JS for file upload, image preview, FormData submission

### i18n
- `resources/lang/en.php` — User menu labels, photo button label, image limit error strings

## Out of scope

- Profile page / profile photo upload UI (separate spec)
- Avatar field on User entity (deferred)
- Video upload
- Image cropping/resizing
- Direct messaging from user menu
- Notification bell in header
- Businesses standalone page (links to feed filter)

## Dependencies

- Post entity type (exists, needs `images` field + migration)
- Symfony Request `$request->files` (available, Waaseyaa uses Symfony HttpFoundation)
- Deployer recipe update for `public/uploads` symlink
