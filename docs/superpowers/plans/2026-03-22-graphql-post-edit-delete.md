# GraphQL Post Edit/Delete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose the Waaseyaa GraphQL endpoint in Minoo and add frontend post edit/delete via GraphQL mutations.

**Architecture:** The Waaseyaa framework already auto-registers `/graphql` when `waaseyaa/graphql` is installed (via `BuiltinRouteRegistrar`). The `ControllerDispatcher` natively handles the `graphql.endpoint` controller ID, instantiating `GraphQlEndpoint` with the session account and entity type manager. Auto-generated CRUD mutations for all entity types (including `post`) already exist. No new service provider is needed — only a smoke test to verify the endpoint and frontend UI for post edit/delete.

**Tech Stack:** Waaseyaa GraphQL (webonyx/graphql-php v15), Twig templates, vanilla JS, PHPUnit

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `tests/Minoo/Integration/GraphQlEndpointTest.php` | Create | Smoke test + schema shape verification |
| `src/Feed/FeedItemFactory.php` | Modify | Include `authorId` in post payload |
| `templates/components/feed-card.html.twig` | Modify | Add kebab menu (edit/delete) for post authors + coordinators |
| `templates/feed.html.twig` | Modify | Add edit modal HTML + GraphQL JS helpers |
| `public/css/minoo.css` | Modify | Kebab menu, edit modal styles |

---

### Task 1: GraphQL Endpoint Smoke Test + Schema Shape Verification

**Files:**
- Create: `tests/Minoo/Integration/GraphQlEndpointTest.php`

Verifies the auto-registered `/graphql` endpoint works in Minoo, confirms post CRUD mutations exist with correct shapes, and validates anonymous introspection is blocked.

- [ ] **Step 1: Write the integration test**

Follow the established pattern from `BootTest.php` — boot kernel once in `setUpBeforeClass()`, clean up in `tearDownAfterClass()`.

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\GraphQL\GraphQlEndpoint;

#[CoversNothing]
final class GraphQlEndpointTest extends TestCase
{
    private static string $projectRoot;
    private static EntityTypeManager $entityTypeManager;
    private static EntityAccessHandler $accessHandler;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 3);

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        $kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke($kernel);

        self::$entityTypeManager = (new \ReflectionProperty(AbstractKernel::class, 'entityTypeManager'))
            ->getValue($kernel);
        self::$accessHandler = (new \ReflectionProperty(AbstractKernel::class, 'accessHandler'))
            ->getValue($kernel);
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    private function buildEndpoint(int $userId = 1, bool $authenticated = true): GraphQlEndpoint
    {
        $account = $this->createStub(AccountInterface::class);
        $account->method('id')->willReturn($userId);
        $account->method('isAuthenticated')->willReturn($authenticated);
        $account->method('hasPermission')->willReturn($authenticated);

        return new GraphQlEndpoint(
            entityTypeManager: self::$entityTypeManager,
            accessHandler: self::$accessHandler,
            account: $account,
        );
    }

    #[Test]
    public function post_entity_type_is_registered(): void
    {
        self::assertTrue(
            self::$entityTypeManager->hasDefinition('post'),
            'Post entity type must be registered for GraphQL mutations to work',
        );
    }

    #[Test]
    public function authenticated_user_can_query_schema(): void
    {
        $result = $this->buildEndpoint()->handle('POST', json_encode([
            'query' => '{ __typename }',
        ]), []);

        self::assertSame(200, $result['statusCode']);
        self::assertArrayHasKey('data', $result['body']);
        self::assertSame('Query', $result['body']['data']['__typename']);
    }

    #[Test]
    public function post_mutations_exist_in_schema(): void
    {
        $result = $this->buildEndpoint()->handle('POST', json_encode([
            'query' => '{ __schema { mutationType { fields { name } } } }',
        ]), []);

        self::assertSame(200, $result['statusCode']);
        $fieldNames = array_column(
            $result['body']['data']['__schema']['mutationType']['fields'],
            'name',
        );
        self::assertContains('createPost', $fieldNames);
        self::assertContains('updatePost', $fieldNames);
        self::assertContains('deletePost', $fieldNames);
    }

    #[Test]
    public function delete_post_mutation_accepts_id_arg(): void
    {
        $result = $this->buildEndpoint()->handle('POST', json_encode([
            'query' => '{ __type(name: "Mutation") { fields { name args { name type { name kind ofType { name } } } } } }',
        ]), []);

        self::assertSame(200, $result['statusCode']);
        $fields = $result['body']['data']['__type']['fields'];
        $deletePost = array_values(array_filter($fields, fn($f) => $f['name'] === 'deletePost'));
        self::assertNotEmpty($deletePost, 'deletePost mutation should exist');
        self::assertSame('id', $deletePost[0]['args'][0]['name']);
    }

    #[Test]
    public function update_post_mutation_accepts_id_and_input(): void
    {
        $result = $this->buildEndpoint()->handle('POST', json_encode([
            'query' => '{ __type(name: "Mutation") { fields { name args { name type { name kind ofType { name } } } } } }',
        ]), []);

        self::assertSame(200, $result['statusCode']);
        $fields = $result['body']['data']['__type']['fields'];
        $updatePost = array_values(array_filter($fields, fn($f) => $f['name'] === 'updatePost'));
        self::assertNotEmpty($updatePost, 'updatePost mutation should exist');
        $argNames = array_column($updatePost[0]['args'], 'name');
        self::assertContains('id', $argNames);
        self::assertContains('input', $argNames);
    }

    #[Test]
    public function anonymous_user_cannot_introspect(): void
    {
        $result = $this->buildEndpoint(userId: 0, authenticated: false)->handle('POST', json_encode([
            'query' => '{ __schema { queryType { name } } }',
        ]), []);

        self::assertSame(200, $result['statusCode']);
        self::assertNotEmpty($result['body']['errors'] ?? []);
    }
}
```

- [ ] **Step 2: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Integration/GraphQlEndpointTest.php -v`
Expected: 6 tests PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Minoo/Integration/GraphQlEndpointTest.php
git commit -m "test: add GraphQL endpoint smoke tests and schema shape verification"
```

---

### Task 2: Kebab Menu on Post Cards

**Files:**
- Modify: `src/Feed/FeedItemFactory.php` (include `authorId` in post payload)
- Modify: `templates/components/feed-card.html.twig` (post card section)
- Modify: `public/css/minoo.css` (kebab menu + position relative on post card)

Adds a `...` overflow menu to post cards, visible only to the post author or coordinators.

**Context:**
- The `account` variable is available in templates via `base.html.twig`
- Coordinators have `administer content` permission
- `item.payload.authorId` needs to be added to FeedItemFactory

- [ ] **Step 1: Update FeedItemFactory to include authorId in post payload**

In `src/Feed/FeedItemFactory.php`, in `buildPost()`, change the payload line:

```php
// Before:
payload: is_array($images) && $images !== [] ? ['images' => $images] : [],

// After:
payload: array_filter([
    'images' => is_array($images) && $images !== [] ? $images : null,
    'authorId' => $userId,
], fn($v) => $v !== null),
```

Note: `$userId` is already resolved earlier in `buildPost()` from `$entity->get('user_id')`.

- [ ] **Step 2: Add kebab menu HTML to post card**

In `templates/components/feed-card.html.twig`, inside the `{% elseif item.type == 'post' %}` block, add immediately after the opening `<article>` tag (before the attribution div):

```twig
{% if account is defined and account.isAuthenticated() and (account.id() == item.payload.authorId or account.hasPermission('administer content')) %}
  <div class="feed-card__menu">
    <button class="feed-card__menu-trigger" aria-label="Post options" aria-haspopup="true" aria-expanded="false" data-action="toggle-menu">&middot;&middot;&middot;</button>
    <div class="feed-card__menu-dropdown" hidden>
      <button class="feed-card__menu-item" data-action="edit-post" data-id="{{ item.id|split(':')|last }}">Edit</button>
      <button class="feed-card__menu-item feed-card__menu-item--danger" data-action="delete-post" data-id="{{ item.id|split(':')|last }}">Delete</button>
    </div>
  </div>
{% endif %}
```

Note: Post body is NOT stored in `data-body` attribute (XSS/encoding risk for large text). Instead, the edit handler will fetch it via GraphQL query.

- [ ] **Step 3: Add kebab menu CSS**

In `public/css/minoo.css`, add in `@layer components`:

```css
/* Post card needs relative positioning for the kebab menu */
.feed-card--post {
  position: relative;
}

/* Post kebab menu */
.feed-card__menu {
  position: absolute;
  inset-block-start: var(--space-sm);
  inset-inline-end: var(--space-sm);
}

.feed-card__menu-trigger {
  background: none;
  border: none;
  font-size: 1.25rem;
  letter-spacing: 0.15em;
  color: var(--text-muted);
  cursor: pointer;
  padding: var(--space-2xs) var(--space-xs);
  border-radius: var(--radius-sm);
  line-height: 1;
}

.feed-card__menu-trigger:hover {
  background: var(--surface-hover);
  color: var(--text-primary);
}

.feed-card__menu-dropdown {
  position: absolute;
  inset-inline-end: 0;
  inset-block-start: 100%;
  background: var(--surface-card);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  box-shadow: 0 4px 12px oklch(0% 0 0 / 0.15);
  min-inline-size: 8rem;
  z-index: 10;
  overflow: hidden;
}

.feed-card__menu-item {
  display: block;
  inline-size: 100%;
  padding: var(--space-xs) var(--space-sm);
  background: none;
  border: none;
  text-align: start;
  font-size: var(--text-sm);
  color: var(--text-primary);
  cursor: pointer;
}

.feed-card__menu-item:hover {
  background: var(--surface-hover);
}

.feed-card__menu-item--danger {
  color: var(--color-error, #dc2626);
}

.feed-card__menu-item--danger:hover {
  background: oklch(0.95 0.05 25);
}
```

- [ ] **Step 4: Run tests to ensure no regressions**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add src/Feed/FeedItemFactory.php templates/components/feed-card.html.twig public/css/minoo.css
git commit -m "feat: add kebab menu to post cards for edit/delete"
```

---

### Task 3: GraphQL JS Helper + Delete Post

**Files:**
- Modify: `templates/feed.html.twig` (add graphqlRequest helper + menu toggle + delete handler in scripts block)

Adds a minimal `graphqlRequest()` function, the kebab menu toggle, and wires up the delete action.

- [ ] **Step 1: Add graphqlRequest helper, menu toggle, and delete handler**

In `templates/feed.html.twig`, inside the `{% block scripts %}` `<script>` block, add before the existing engagement code:

```javascript
// === GraphQL Helper ===
async function graphqlRequest(query, variables = {}) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const res = await fetch('/graphql', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    body: JSON.stringify({ query, variables }),
  });
  const json = await res.json();
  if (json.errors?.length) {
    throw new Error(json.errors[0].message);
  }
  return json.data;
}

// === Kebab Menu Toggle ===
document.addEventListener('click', function(e) {
  if (!e.target.closest('.feed-card__menu')) {
    document.querySelectorAll('.feed-card__menu-dropdown').forEach(d => {
      d.hidden = true;
      d.previousElementSibling?.setAttribute('aria-expanded', 'false');
    });
    return;
  }
  const trigger = e.target.closest('[data-action="toggle-menu"]');
  if (trigger) {
    const dropdown = trigger.nextElementSibling;
    const wasHidden = dropdown.hidden;
    document.querySelectorAll('.feed-card__menu-dropdown').forEach(d => {
      d.hidden = true;
      d.previousElementSibling?.setAttribute('aria-expanded', 'false');
    });
    dropdown.hidden = !wasHidden;
    trigger.setAttribute('aria-expanded', String(!wasHidden === false));
  }
});

// Close menu on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.feed-card__menu-dropdown').forEach(d => {
      d.hidden = true;
      d.previousElementSibling?.setAttribute('aria-expanded', 'false');
    });
  }
});

// === Delete Post ===
document.addEventListener('click', async function(e) {
  const btn = e.target.closest('[data-action="delete-post"]');
  if (!btn) return;
  if (!confirm('Delete this post?')) return;
  const id = btn.dataset.id;
  btn.disabled = true;
  btn.textContent = 'Deleting...';
  try {
    await graphqlRequest(
      'mutation DeletePost($id: ID!) { deletePost(id: $id) { deleted } }',
      { id }
    );
    const card = btn.closest('.feed-card');
    card.remove();
  } catch (err) {
    alert('Could not delete post: ' + err.message);
    btn.disabled = false;
    btn.textContent = 'Delete';
  }
});
```

- [ ] **Step 2: Test delete flow manually**

1. Start dev server: `php -S localhost:8081 -t public`
2. Log in and create a post
3. Click `...` → Delete
4. Confirm dialog
5. Post disappears from feed
6. Refresh — post is gone

- [ ] **Step 3: Commit**

```bash
git add templates/feed.html.twig
git commit -m "feat: add GraphQL helper and post delete via mutation"
```

---

### Task 4: Edit Post Modal + Mutation

**Files:**
- Modify: `templates/feed.html.twig` (add edit modal HTML + JS handler)
- Modify: `public/css/minoo.css` (add modal styles)

- [ ] **Step 1: Add edit modal HTML**

In `templates/feed.html.twig`, before the closing `{% endblock content %}`, add:

```html
{# === Edit Post Modal === #}
<dialog class="edit-post-modal" id="edit-post-modal">
  <form class="edit-post-modal__form" id="edit-post-form">
    <h3 class="edit-post-modal__title">Edit post</h3>
    <textarea class="edit-post-modal__textarea" id="edit-post-body" rows="4" maxlength="5000" required></textarea>
    <div class="edit-post-modal__actions">
      <button type="button" class="btn btn--ghost" id="edit-post-cancel">Cancel</button>
      <button type="submit" class="btn btn--primary" id="edit-post-save">Save</button>
    </div>
    <input type="hidden" id="edit-post-id">
  </form>
</dialog>
```

- [ ] **Step 2: Add edit JS handler**

In `templates/feed.html.twig`, in the scripts block, add after the delete handler:

```javascript
// === Edit Post ===
const editModal = document.getElementById('edit-post-modal');
const editBody = document.getElementById('edit-post-body');
const editId = document.getElementById('edit-post-id');

document.addEventListener('click', async function(e) {
  const btn = e.target.closest('[data-action="edit-post"]');
  if (!btn) return;
  const id = btn.dataset.id;
  // Close the kebab menu
  document.querySelectorAll('.feed-card__menu-dropdown').forEach(d => d.hidden = true);
  // Fetch current body via GraphQL
  try {
    const data = await graphqlRequest(
      'query GetPost($id: ID!) { post(id: $id) { body } }',
      { id }
    );
    editId.value = id;
    editBody.value = data.post.body;
    editModal.showModal();
  } catch (err) {
    alert('Could not load post: ' + err.message);
  }
});

document.getElementById('edit-post-cancel')?.addEventListener('click', () => editModal.close());

document.getElementById('edit-post-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const id = editId.value;
  const body = editBody.value.trim();
  if (!body) return;
  const saveBtn = document.getElementById('edit-post-save');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving...';
  try {
    const data = await graphqlRequest(
      `mutation UpdatePost($id: ID!, $input: PostUpdateInput!) {
        updatePost(id: $id, input: $input) { pid body }
      }`,
      { id, input: { body } }
    );
    // Update the card in the DOM
    const card = document.querySelector(`[data-entity-type="post"][data-id="post:${id}"]`);
    if (card) {
      const bodyEl = card.querySelector('.feed-card__body');
      if (bodyEl) bodyEl.textContent = data.updatePost.body;
    }
    editModal.close();
  } catch (err) {
    alert('Could not update post: ' + err.message);
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save';
  }
});
```

- [ ] **Step 3: Add modal CSS**

In `public/css/minoo.css`, add in `@layer components`:

```css
/* Edit post modal */
.edit-post-modal {
  border: none;
  border-radius: var(--radius-md);
  padding: 0;
  max-inline-size: min(32rem, 90vw);
  inline-size: 100%;
  box-shadow: 0 8px 32px oklch(0% 0 0 / 0.2);
  background: var(--surface-card);
  color: var(--text-primary);
}

.edit-post-modal::backdrop {
  background: oklch(0% 0 0 / 0.5);
}

.edit-post-modal__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  padding: var(--space-md);
}

.edit-post-modal__title {
  font-size: var(--text-lg);
  font-weight: 600;
  margin: 0;
}

.edit-post-modal__textarea {
  font-family: inherit;
  font-size: var(--text-base);
  padding: var(--space-xs) var(--space-sm);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  background: var(--surface-input, var(--surface-base));
  color: var(--text-primary);
  resize: vertical;
  min-block-size: 5rem;
}

.edit-post-modal__textarea:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

.edit-post-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-xs);
}
```

- [ ] **Step 4: Test edit flow manually**

1. Click `...` → Edit on own post
2. Modal opens with current body (fetched via GraphQL)
3. Edit text, click Save
4. Card body updates in-place
5. Refresh — edit persists

- [ ] **Step 5: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (no regressions)

- [ ] **Step 6: Commit**

```bash
git add templates/feed.html.twig public/css/minoo.css
git commit -m "feat: add post edit modal via GraphQL updatePost mutation"
```

---

## Notes

- The `/graphql` route uses `allowAll()` + `csrfExempt()` at the route level, but the `GraphQlEndpoint` enforces auth internally (anonymous users get introspection blocked, and mutations fail via access policies).
- The `PostAccessPolicy` already governs update/delete — owner can delete own posts, coordinators can delete any post. The same policy will apply to GraphQL mutations automatically.
- No migration needed — GraphQL operates through existing entity storage.
- Existing REST endpoints remain untouched.
- The CSRF token header is included in `graphqlRequest()` for consistency even though the route is CSRF-exempt. This is harmless and future-proofs against potential policy changes.
- The edit handler fetches the current post body via GraphQL query (`post(id: $id) { body }`) instead of storing it in a `data-body` attribute, avoiding XSS/encoding issues with large text.
