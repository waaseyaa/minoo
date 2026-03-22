# Homepage UX Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Minoo's 11-item top nav with a minimal header (logo + search + user menu avatar) and sidebar-first navigation, then add image upload to post creation.

**Architecture:** Three sequential changes: (1) minimal header with user menu dropdown component, (2) sidebar nav moves into `base.html.twig` so it appears on all pages, (3) post form gets image upload via new `UploadService` + `images` field on post entity.

**Tech Stack:** PHP 8.4, Twig 3, vanilla CSS (@layer architecture in minoo.css), vanilla JS (FormData, URL.createObjectURL), PHPUnit 10.5, Symfony HttpFoundation

**Spec:** `docs/superpowers/specs/2026-03-22-homepage-ux-refresh-design.md`

**Issues:** #478 (header), #479 (sidebar), #480 (posting with images)

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `templates/components/user-menu.html.twig` | Avatar dropdown component |
| Create | `templates/components/sidebar-nav.html.twig` | Full sidebar nav (replaces feed-sidebar-left) |
| Create | `src/Support/UploadService.php` | Validate, move, delete uploaded files |
| Create | `tests/Minoo/Unit/Support/UploadServiceTest.php` | Upload service tests |
| Create | `migrations/20260322_200000_add_images_to_post.php` | Add images column |
| Modify | `templates/base.html.twig:28-86` | Replace nav with minimal header + sidebar layout |
| Modify | `templates/feed.html.twig:6-40` | Remove inline sidebar, use base layout |
| Modify | `templates/components/feed-create-post.html.twig` | Add photo upload UI |
| Modify | `templates/components/feed-card.html.twig:23-34` | Render post images |
| Modify | `src/Controller/EngagementController.php:214-245` | Handle multipart post with images |
| Modify | `src/Provider/EngagementServiceProvider.php:98-136` | Add `images` field to post |
| Modify | `src/Entity/Post.php` | Accept optional `images` field |
| Modify | `public/css/minoo.css` | Header, sidebar, user menu, image grid styles |
| Modify | `resources/lang/en.php` | User menu and photo upload i18n strings |

---

### Task 1: User menu dropdown component (#478)

**Files:**
- Create: `templates/components/user-menu.html.twig`
- Modify: `resources/lang/en.php`

- [ ] **Step 1: Add i18n strings for user menu**

In `resources/lang/en.php`, after the coordinator section, add:

```php
// User Menu
'usermenu.profile' => 'My Profile',
'usermenu.dashboard' => 'Dashboard',
'usermenu.theme' => 'Theme',
'usermenu.language' => 'Language',
'usermenu.sign_out' => 'Sign Out',
'usermenu.log_in' => 'Log In',
'search.placeholder' => 'Search...',
```

- [ ] **Step 2: Create user-menu.html.twig**

```twig
{# User menu dropdown - avatar with dropdown for authenticated, login button for guests #}
{% if account is defined and account.isAuthenticated() %}
<div class="user-menu" id="user-menu">
  <button class="user-menu__trigger" aria-expanded="false" aria-controls="user-menu-dropdown">
    <span class="user-menu__avatar">{{ account_initial }}</span>
  </button>
  <div class="user-menu__dropdown" id="user-menu-dropdown" hidden>
    <div class="user-menu__identity">
      <span class="user-menu__avatar user-menu__avatar--lg">{{ account_initial }}</span>
      <div>
        <div class="user-menu__name">{{ account_name }}</div>
        <div class="user-menu__email">{{ account_email }}</div>
      </div>
    </div>
    <div class="user-menu__section">
      <a href="/account" class="user-menu__item">{{ trans('usermenu.profile') }}</a>
      {% if 'elder_coordinator' in account.getRoles() %}
        <a href="/dashboard/coordinator" class="user-menu__item">{{ trans('usermenu.dashboard') }}</a>
      {% endif %}
    </div>
    <div class="user-menu__section">
      <button class="user-menu__item" id="user-menu-theme-toggle">
        {{ trans('usermenu.theme') }}
        <span class="user-menu__value" id="user-menu-theme-value">Dark</span>
      </button>
      {# Reuse existing language switcher markup from base.html.twig, extracted into inline format #}
      <a href="#" class="user-menu__item" id="user-menu-lang-toggle">
        {{ trans('usermenu.language') }}
        <span class="user-menu__value">{{ current_lang|default('EN')|upper }}</span>
      </a>
    </div>
    <div class="user-menu__section">
      <a href="/logout" class="user-menu__item user-menu__item--danger">{{ trans('usermenu.sign_out') }}</a>
    </div>
  </div>
</div>
{% else %}
<a href="{{ lang_url('/login') }}" class="btn btn--secondary btn--sm">{{ trans('usermenu.log_in') }}</a>
{% endif %}
```

- [ ] **Step 3: Commit**

```bash
git add templates/components/user-menu.html.twig resources/lang/en.php
git commit -m "feat(#478): user menu dropdown component and i18n strings"
```

---

### Task 2: Minimal header in base.html.twig (#478)

**Files:**
- Modify: `templates/base.html.twig:28-86`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Replace the header nav section in base.html.twig**

Replace lines 28-86 (the entire `<header>` and `<nav>`) with:

```twig
<header class="site-header">
  <div class="site-header__inner">
    <div class="site-header__left">
      <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 5h14M3 10h14M3 15h14"/>
        </svg>
      </button>
      <a href="{{ lang_url('/') }}" class="site-header__logo">Minoo</a>
    </div>
    <div class="site-header__center">
      <form class="header-search" action="{{ lang_url('/search') }}" method="get">
        <svg class="header-search__icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7" cy="7" r="5"/><path d="M11 11l4 4"/></svg>
        <input class="header-search__input" type="search" name="q" placeholder="{{ trans('search.placeholder')|default('Search...') }}" autocomplete="off">
      </form>
    </div>
    <div class="site-header__right">
      {% include "components/user-menu.html.twig" %}
    </div>
  </div>
</header>
```

- [ ] **Step 2: Add header CSS to minoo.css**

In `@layer components`, add the site-header, header-search, and user-menu styles. Key patterns:
- `.site-header` — sticky top, single row flexbox, ~56px height
- `.site-header__inner` — max-width container, flex with space-between
- `.header-search` — flex row with icon + input, rounded border, ~300px max-width
- `.sidebar-toggle` — hidden on desktop, visible on mobile/tablet
- `.user-menu__trigger` — circular avatar button
- `.user-menu__dropdown` — absolute positioned, right-aligned, rounded card with sections
- `.user-menu__item` — flex row with icon, hover highlight
- `.user-menu__item--danger` — red text for sign out

- [ ] **Step 3: Update header JS in base.html.twig**

Replace the old nav toggle JS (hamburger menu) with:
- User menu toggle (click avatar → show/hide dropdown, click outside → close)
- Sidebar toggle (click hamburger → toggle sidebar overlay on mobile)
- Theme toggle wired to the user menu button

- [ ] **Step 4: Remove old nav CSS**

Remove or replace the old `.site-nav`, `.site-nav__dropdown`, `.nav-toggle` CSS classes that are no longer used.

- [ ] **Step 5: Test in browser**

Run: `php -S localhost:8081 -t public`
Verify: header shows logo + search + avatar, user menu dropdown works, no old nav visible.

- [ ] **Step 6: Commit**

```bash
git add templates/base.html.twig public/css/minoo.css
git commit -m "feat(#478): minimal header with search and user menu"
```

---

### Task 3: Sidebar navigation component (#479)

**Files:**
- Create: `templates/components/sidebar-nav.html.twig`
- Modify: `resources/lang/en.php`

- [ ] **Step 1: Add sidebar i18n strings**

```php
// Sidebar Navigation
'sidebar.navigate' => 'Navigate',
'sidebar.programs' => 'Programs',
'sidebar.your_communities' => 'Your Communities',
'sidebar.home' => 'Home',
```

- [ ] **Step 2: Create sidebar-nav.html.twig**

Three groups: Navigate (Home, Communities, Events, Teachings, People, Oral Histories), Programs (Elder Support, Volunteer), Your Communities (conditional). Each item has SVG icon + label. Active page gets `.sidebar-nav__item--active` class based on current path.

```twig
<nav class="sidebar-nav" id="sidebar-nav" aria-label="Main navigation">
  <div class="sidebar-nav__group">
    <div class="sidebar-nav__label">{{ trans('sidebar.navigate') }}</div>
    <a href="{{ lang_url('/') }}" class="sidebar-nav__item{% if current_path == '/' %} sidebar-nav__item--active{% endif %}">
      {# Home icon SVG #}
      <span class="sidebar-nav__text">{{ trans('sidebar.home') }}</span>
    </a>
    <a href="{{ lang_url('/communities') }}" class="sidebar-nav__item{% if current_path starts with '/communities' %} sidebar-nav__item--active{% endif %}">
      {# Grid icon SVG #}
      <span class="sidebar-nav__text">{{ trans('nav.communities') }}</span>
    </a>
    {# Events, Teachings, People, Businesses, Oral Histories with same pattern #}
    <a href="{{ lang_url('/events') }}" class="sidebar-nav__item{% if current_path starts with '/events' %} sidebar-nav__item--active{% endif %}">
      <span class="sidebar-nav__text">{{ trans('nav.events') }}</span>
    </a>
    <a href="{{ lang_url('/teachings') }}" class="sidebar-nav__item{% if current_path starts with '/teachings' %} sidebar-nav__item--active{% endif %}">
      <span class="sidebar-nav__text">{{ trans('nav.teachings') }}</span>
    </a>
    <a href="{{ lang_url('/people') }}" class="sidebar-nav__item{% if current_path starts with '/people' %} sidebar-nav__item--active{% endif %}">
      <span class="sidebar-nav__text">{{ trans('nav.people') }}</span>
    </a>
    <a href="/?filter=business" class="sidebar-nav__item">
      <span class="sidebar-nav__text">{{ trans('nav.businesses') }}</span>
    </a>
    <a href="{{ lang_url('/oral-histories') }}" class="sidebar-nav__item{% if current_path starts with '/oral-histories' %} sidebar-nav__item--active{% endif %}">
      <span class="sidebar-nav__text">{{ trans('nav.oral_histories') }}</span>
    </a>
  </div>
  <div class="sidebar-nav__group">
    <div class="sidebar-nav__label">{{ trans('sidebar.programs') }}</div>
    <a href="{{ lang_url('/elders/request') }}" class="sidebar-nav__item">
      <span class="sidebar-nav__text">{{ trans('nav.elder_support') }}</span>
    </a>
    <a href="{{ lang_url('/elders/volunteer') }}" class="sidebar-nav__item">
      <span class="sidebar-nav__text">{{ trans('nav.volunteer') }}</span>
    </a>
  </div>
  {% if account is defined and account.isAuthenticated() and user_communities is defined and user_communities|length > 0 %}
  <div class="sidebar-nav__group">
    <div class="sidebar-nav__label">{{ trans('sidebar.your_communities') }}</div>
    {% for community in user_communities %}
    <a href="/communities/{{ community.slug }}" class="sidebar-nav__item">
      <span class="sidebar-nav__community-avatar">{{ community.name|first }}</span>
      <span class="sidebar-nav__text">{{ community.name }}</span>
    </a>
    {% endfor %}
  </div>
  {% endif %}
</nav>
```

- [ ] **Step 3: Commit**

```bash
git add templates/components/sidebar-nav.html.twig resources/lang/en.php
git commit -m "feat(#479): sidebar navigation component"
```

---

### Task 4: Move sidebar into base.html.twig (#479)

**Files:**
- Modify: `templates/base.html.twig`
- Modify: `templates/feed.html.twig`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add sidebar layout to base.html.twig**

After the header and before `{% block content %}`, wrap in a two-column layout:

```twig
<div class="app-layout{% if hide_sidebar is defined and hide_sidebar %} app-layout--no-sidebar{% endif %}">
  {% if hide_sidebar is not defined or not hide_sidebar %}
    <aside class="app-sidebar" id="app-sidebar">
      {% include "components/sidebar-nav.html.twig" %}
    </aside>
    <div class="app-sidebar__overlay" id="sidebar-overlay"></div>
  {% endif %}
  <main class="app-main">
    {# existing content block, flash messages, etc. #}
    {% block content %}{% endblock %}
  </main>
</div>
```

- [ ] **Step 2: Update feed.html.twig**

Remove the left sidebar include from feed.html.twig (it's now in base). The feed layout becomes two-column (feed + right sidebar) instead of three-column.

- [ ] **Step 3: Add sidebar CSS**

```css
.app-layout { display: grid; grid-template-columns: 220px 1fr; min-height: 100dvh; }
.app-layout--no-sidebar { grid-template-columns: 1fr; }
.app-sidebar { position: sticky; top: 56px; height: calc(100dvh - 56px); overflow-y: auto; }
```

Responsive:
- Tablet: `.app-layout { grid-template-columns: 60px 1fr; }` with `.sidebar-nav__text { display: none; }`
- Mobile: `.app-sidebar { position: fixed; transform: translateX(-100%); }` with `.app-sidebar--open { transform: translateX(0); }`

- [ ] **Step 4: Wire sidebar toggle JS**

In base.html.twig scripts, add sidebar toggle handler:
```js
document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
  document.getElementById('app-sidebar')?.classList.toggle('app-sidebar--open');
  document.getElementById('sidebar-overlay')?.classList.toggle('app-sidebar__overlay--visible');
});
```

- [ ] **Step 5: Add `hide_sidebar` to auth pages**

In `templates/auth/login.html.twig`, `register.html.twig`, etc., add `{% set hide_sidebar = true %}` at top.

- [ ] **Step 6: Visual test all major pages**

Run: `php -S localhost:8081 -t public`
Check: homepage (sidebar + feed), communities page (sidebar + content), events page, login (no sidebar), mobile responsive.

- [ ] **Step 7: Commit**

```bash
git add templates/base.html.twig templates/feed.html.twig public/css/minoo.css templates/auth/
git commit -m "feat(#479): sidebar-first layout in base.html.twig"
```

---

### Task 5: UploadService (#480)

**Files:**
- Create: `src/Support/UploadService.php`
- Create: `tests/Minoo/Unit/Support/UploadServiceTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
declare(strict_types=1);
namespace Minoo\Tests\Unit\Support;

use Minoo\Support\UploadService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadService::class)]
final class UploadServiceTest extends TestCase
{
    #[Test]
    public function validate_rejects_oversized_file(): void
    {
        $service = new UploadService('/tmp/test-uploads');
        $errors = $service->validateImage(['size' => 6_000_000, 'error' => UPLOAD_ERR_OK, 'type' => 'image/jpeg']);
        $this->assertNotEmpty($errors);
    }

    #[Test]
    public function validate_rejects_non_image(): void
    {
        $service = new UploadService('/tmp/test-uploads');
        $errors = $service->validateImage(['size' => 1000, 'error' => UPLOAD_ERR_OK, 'type' => 'text/plain']);
        $this->assertNotEmpty($errors);
    }

    #[Test]
    public function validate_accepts_valid_image(): void
    {
        $service = new UploadService('/tmp/test-uploads');
        $errors = $service->validateImage(['size' => 1000, 'error' => UPLOAD_ERR_OK, 'type' => 'image/jpeg']);
        $this->assertEmpty($errors);
    }

    #[Test]
    public function generate_safe_filename_strips_unsafe_chars(): void
    {
        $service = new UploadService('/tmp/test-uploads');
        $safe = $service->generateSafeFilename('../../evil file (1).jpg');
        $this->assertStringNotContainsString('..', $safe);
        $this->assertStringNotContainsString(' ', $safe);
        $this->assertStringEndsWith('.jpg', $safe);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter UploadServiceTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement UploadService**

```php
<?php
declare(strict_types=1);
namespace Minoo\Support;

final class UploadService
{
    private const int MAX_SIZE = 5_242_880; // 5MB
    private const array ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private readonly string $basePath) {}

    /** @return string[] validation errors */
    public function validateImage(array $file): array { /* ... */ }

    public function generateSafeFilename(string $original): string { /* ... */ }

    /** @return string relative path */
    public function moveUpload(array $file, string $subdir): string { /* ... */ }

    public function deleteDirectory(string $subdir): void { /* ... */ }
}
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/phpunit --filter UploadServiceTest`
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add src/Support/UploadService.php tests/Minoo/Unit/Support/UploadServiceTest.php
git commit -m "feat(#480): UploadService for image validation and storage"
```

---

### Task 6: Post entity images field + migration (#480)

**Files:**
- Modify: `src/Provider/EngagementServiceProvider.php:98-136`
- Modify: `src/Entity/Post.php`
- Create: `migrations/20260322_200000_add_images_to_post.php`

- [ ] **Step 1: Add `images` field to post entity type definition**

In `EngagementServiceProvider.php`, add after `community_id` field:
```php
'images' => ['type' => 'text_long', 'label' => 'Images', 'weight' => 3],
```

- [ ] **Step 2: Update Post.php constructor**

Add optional `images` default (empty JSON array):
```php
if (!array_key_exists('images', $values)) {
    $values['images'] = '[]';
}
```

- [ ] **Step 3: Create migration**

```php
<?php
declare(strict_types=1);
use Waaseyaa\Database\Migration;

return new Migration(
    up: function (\PDO $pdo): void {
        $pdo->exec('ALTER TABLE post ADD COLUMN images TEXT DEFAULT \'[]\'');
    },
    down: function (\PDO $pdo): void {
        // SQLite doesn't support DROP COLUMN before 3.35
    },
);
```

- [ ] **Step 4: Run migration and tests**

Run: `bin/waaseyaa migrate && ./vendor/bin/phpunit`
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add src/Provider/EngagementServiceProvider.php src/Entity/Post.php migrations/
git commit -m "feat(#480): add images field to post entity"
```

---

### Task 7: Multipart post creation with images (#480)

**Files:**
- Modify: `src/Controller/EngagementController.php:214-245`
- Create or modify: `tests/Minoo/Unit/Controller/EngagementControllerTest.php` (add image upload test)

- [ ] **Step 1: Write failing test for multipart post with images**

```php
#[Test]
public function create_post_with_images_stores_paths(): void
{
    // Mock UploadService to return predictable paths
    $uploadService = $this->createMock(UploadService::class);
    $uploadService->method('validateImage')->willReturn([]);
    $uploadService->method('moveUpload')->willReturn('posts/1/photo.jpg');

    // Mock storage to capture create() args
    $entity = $this->createMock(ContentEntityBase::class);
    $entity->method('id')->willReturn(1);
    $entity->method('set')->willReturnSelf();

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('create')->willReturn($entity);
    $storage->expects($this->atLeastOnce())->method('save');

    // Build multipart request with a fake uploaded file
    $file = new UploadedFile(tempnam(sys_get_temp_dir(), 'img'), 'photo.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);
    $request = new HttpRequest([], ['body' => 'Hello', 'community_id' => '1'], [], [], ['images' => [$file]], ['REQUEST_METHOD' => 'POST']);

    // ... controller call and assertions
}
```

- [ ] **Step 2: Update EngagementController::createPost()**

Switch from `jsonBody()` to `$request->request->get('body')` and `$request->files->all('images')`. After creating the post entity, call `UploadService::moveUpload()` for each valid image, collect paths, set `images` field as JSON, save again.

- [ ] **Step 2b: Wire post deletion cleanup**

In `EngagementController::deletePost()`, after deleting the entity, call `$this->uploadService->deleteDirectory('posts/' . $postId)` to remove uploaded images. Add `UploadService` as a constructor dependency.

- [ ] **Step 3: Run tests**

Run: `./vendor/bin/phpunit --filter EngagementControllerTest`
Expected: All pass

- [ ] **Step 4: Commit**

```bash
git add src/Controller/EngagementController.php tests/Minoo/Unit/Controller/EngagementControllerTest.php
git commit -m "feat(#480): multipart post creation with image upload"
```

---

### Task 8: Post form UI with image upload (#480)

**Files:**
- Modify: `templates/components/feed-create-post.html.twig`
- Modify: `templates/feed.html.twig` (JS section)
- Modify: `resources/lang/en.php`

- [ ] **Step 1: Add i18n strings**

```php
'feed.photo' => 'Photo',
'feed.max_images' => 'Maximum 4 images',
'feed.image_too_large' => 'Image must be under 5MB',
```

- [ ] **Step 2: Update feed-create-post.html.twig**

Add photo button and image preview container to the form. Photo button opens `<input type="file" accept="image/*" multiple>` (hidden). Preview area shows thumbnails with remove buttons.

- [ ] **Step 3: Update post form JS**

In `feed.html.twig`, update the post form handler:
- Track files in a `selectedFiles` array
- Photo button click → trigger hidden file input
- On file select → validate count/size, create previews via `URL.createObjectURL()`
- Remove button → splice from array, revoke object URL
- Submit → build `FormData` with `body`, `community_id`, `_csrf_token`, and `images[]` files
- `fetch()` with `FormData` (no Content-Type header — browser sets multipart boundary)

- [ ] **Step 4: Visual test**

Run: `php -S localhost:8081 -t public`
Test: click photo → select images → see previews → remove one → post → verify images saved

- [ ] **Step 5: Commit**

```bash
git add templates/components/feed-create-post.html.twig templates/feed.html.twig resources/lang/en.php
git commit -m "feat(#480): post form UI with image upload and preview"
```

---

### Task 9: Render post images in feed cards (#480)

**Files:**
- Modify: `templates/components/feed-card.html.twig:23-34`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Add image grid to post cards**

In feed-card.html.twig, inside the post card block, after the body text:

**Note:** Twig has no built-in `json_decode` filter. The `FeedController` must decode the `images` JSON string and pass it as a PHP array in the template context (e.g., add `'images' => json_decode($post->get('images') ?? '[]', true)` to each post's card data). The template then uses the array directly:

```twig
{% if item_images is defined and item_images|length > 0 %}
  <div class="feed-card__images feed-card__images--{{ item_images|length|min(4) }}">
    {% for img in item_images|slice(0, 4) %}
      <img src="/uploads/{{ img }}" alt="" class="feed-card__image" loading="lazy">
    {% endfor %}
  </div>
{% endif %}
```

Update `FeedController` to decode images for post-type feed items and pass as `item_images` alongside each card include.

- [ ] **Step 2: Add image grid CSS**

```css
.feed-card__images { display: grid; gap: 2px; border-radius: var(--radius); overflow: hidden; margin-block-start: var(--space-sm); }
.feed-card__images--1 { grid-template-columns: 1fr; }
.feed-card__images--2 { grid-template-columns: 1fr 1fr; }
.feed-card__images--3 { grid-template-columns: 1fr 1fr; }
.feed-card__images--4 { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
.feed-card__image { width: 100%; height: 100%; object-fit: cover; max-height: 300px; }
```

- [ ] **Step 3: Create uploads symlink**

```bash
ln -sf ../storage/uploads public/uploads
mkdir -p storage/uploads/posts
```

- [ ] **Step 4: Visual test**

Create a post with images, verify they render in the feed with correct grid layout.

- [ ] **Step 5: Commit**

```bash
git add templates/components/feed-card.html.twig public/css/minoo.css
git commit -m "feat(#480): render post images in feed cards"
```

---

### Task 10: Full regression, Playwright, Deployer, PHPStan

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All pass

- [ ] **Step 2: Update Playwright tests**

The sidebar change removes `.feed-sidebar--left` and replaces with `.app-sidebar`. Update any Playwright tests that assert on the old sidebar selectors. Check `tests/playwright/*.spec.ts` for references to `feed-sidebar`, `site-nav`, or `nav-toggle`.

- [ ] **Step 3: Update Deployer recipe for uploads symlink**

In `deploy.php`, add a shared directory or symlink task:
```php
// In deploy.php shared_dirs or after deploy:symlink
task('deploy:uploads', function () {
    run('ln -sfn {{deploy_path}}/shared/storage/uploads {{release_path}}/public/uploads');
});
```

- [ ] **Step 4: Regenerate PHPStan baseline**

Run: `./vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`

- [ ] **Step 5: Visual smoke test all pages**

Check: homepage feed, communities, events, teachings, login (no sidebar), mobile responsive, post with images.

- [ ] **Step 6: Commit**

```bash
git add phpstan-baseline.neon deploy.php tests/playwright/
git commit -m "chore: regression fixes — Playwright, Deployer, PHPStan baseline"
```
