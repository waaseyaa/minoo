# Quickstart: Migrate community marker to explicit tenancy

Verification recipe each WP runs before claiming done. Steps are mechanical
and identical across WPs; the only thing that changes is which entity
files / provider blocks are in scope.

## 0. Pre-flight (once per WP)

```bash
# From project root
cd /home/jones/dev/minoo

# Confirm the WP's owning provider matches the data-model.md mapping.
# Pick a representative entity from the WP's cluster, e.g. Group for WP01.
grep -nE "new EntityType\(\s*'group'" src/Provider/Entity/

# If the provider differs from the data-model.md mapping, stop and update
# the WP boundary before editing.
```

## 1. Edit the provider — add `tenancy:` named argument

For each entity in the WP cluster, locate the `new EntityType(...)` call
inside the owning provider and add the named argument:

```php
$this->entityType(new EntityType(
    // ...existing named args...
    tenancy: ['scope' => 'community'],
));
```

The named-arg position is irrelevant to PHP; conventionally place it after
`label:` / `entityKeys:` and before any closure-style fields.

## 2. Edit the entity class — remove the marker

In each affected entity file under `src/Entity/`:

```diff
-use Waaseyaa\Entity\Community\HasCommunityInterface;
-
-final class Post extends ContentEntityBase implements HasCommunityInterface
+final class Post extends ContentEntityBase
```

(Adapt class name and base class per file.)

## 3. Bust the manifest cache

Stale `storage/framework/packages.php` can mask provider changes (per
CLAUDE.md "Stale manifest cache" gotcha):

```bash
rm -f storage/framework/packages.php
```

## 4. Run the test suite

```bash
./vendor/bin/phpunit
# expected: green (914+ tests, 2568+ assertions)
```

If any test fails, do **not** "fix" it by rewriting the test. The migration
should be byte-identical at the runtime-behavior level — a failing test is
either a bug in the migration or an assertion that depended on the marker
existing (rare; if found, mechanical replacement is allowed per FR-004).

## 5. Cold-boot smoke

```bash
# Start dev server (WSL2: bind 0.0.0.0 so Windows browsers reach via port forwarding)
php -S 0.0.0.0:8080 -t public public/index.php > /tmp/minoo-cold-boot.log 2>&1 &
PHPSERVER=$!
sleep 2

# Hit a route per affected entity. Examples — adapt per WP cluster:
# WP01: /communities, /communities/<slug>, ...
# WP02: /teachings, /events, /oral-histories
# WP03: /feed, /home

curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8080/feed
# ... add curl per affected route ...

kill $PHPSERVER

# Scan log for tenancy deprecation noise tied to this WP's entities
grep -E 'tenancy.deprecation|HasCommunityInterface' /tmp/minoo-cold-boot.log
# expected: 0 matches for the entities migrated in this WP
```

## 6. Final sweep (WP03 only)

```bash
# Per FR-003 / FR-006, no marker reference may remain in src/
grep -rn 'HasCommunityInterface' src/
# expected: 0 matches

# Reconcile occurrence_map.yaml — every entry classified `remove` is gone;
# every entry classified `preserve` still matches the surviving location.
```

## 7. Commit and PR

```bash
git add -A
git commit -m "$(cat <<'EOF'
migrate(<umbrella-issue>): wp0X <cluster> -> explicit tenancy: ['scope' => 'community']

Replace deprecated HasCommunityInterface marker with explicit
tenancy: ['scope' => 'community'] declaration on the EntityType
registrations for <entity list>. Remove the marker `implements`
clause and `use` import from each entity class.

Behavior preserved: PHPUnit suite green, cold-boot log clean for
these entity types.

Part of #<umbrella-issue>
EOF
)"
git push
gh pr create --title "migrate(#<umbrella>): wp0X <cluster> tenancy migration" \
  --body "Part of #<umbrella-issue>. See kitty-specs/migrate-community-marker-to-explicit-tenancy-01KR69KT/spec.md."
```

WP03's commit/PR uses `Closes #<umbrella-issue>` instead of `Part of`.
