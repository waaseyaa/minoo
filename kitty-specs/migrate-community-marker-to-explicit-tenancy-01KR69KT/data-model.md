# Data Model: Migrate community marker to explicit tenancy

This is a metadata-shape migration; no runtime data structures change. The
"data model" here is the set of `EntityType` registrations whose tenancy
declaration is moving from marker-interface to constructor argument.

## Entity Registration Map

| #  | Entity        | Class file                       | Current marker (`implements`)               | Target declaration (constructor arg)            | Owning provider (provisional)             |
| -- | ------------- | -------------------------------- | ------------------------------------------- | ----------------------------------------------- | ----------------------------------------- |
| 1  | Group         | `src/Entity/Group.php`           | `HasCommunityInterface`                     | `tenancy: ['scope' => 'community']`             | `EntityCommunityProvider`                  |
| 2  | Leader        | `src/Entity/Leader.php`          | `HasCommunityInterface`                     | `tenancy: ['scope' => 'community']`             | `EntityCommunityProvider`                  |
| 3  | Contributor   | `src/Entity/Contributor.php`     | `HasCommunityInterface`                     | `tenancy: ['scope' => 'community']`             | `EntityCommunityProvider`                  |
| 4  | OralHistory   | `src/Entity/OralHistory.php`     | `HasCommunityInterface`                     | `tenancy: ['scope' => 'community']`             | `EntityContentProvider`                    |
| 5  | Teaching      | `src/Entity/Teaching.php`        | `HasCommunityInterface`                     | `tenancy: ['scope' => 'community']`             | `EntityContentProvider`                    |
| 6  | Event         | `src/Entity/Event.php`           | `HasCommunityInterface`                     | `tenancy: ['scope' => 'community']`             | `EntityContentProvider`                    |
| 7  | Post          | `src/Entity/Post.php`            | `HasCommunityInterface`                     | `tenancy: ['scope' => 'community']`             | `EntityFoundationProvider`                 |

## Per-WP Verification Greps

WP implementers run these from project root before claiming the WP done.

### WP01 (EntityCommunityProvider cluster)
```bash
grep -n 'HasCommunityInterface' src/Entity/Group.php src/Entity/Leader.php src/Entity/Contributor.php
# expected: 0 matches after the WP
grep -nE "new EntityType\(.*'(group|leader|contributor)'" src/Provider/Entity/EntityCommunityProvider.php
# expected: each line shows surrounding `tenancy: ['scope' => 'community']`
```

### WP02 (EntityContentProvider cluster)
```bash
grep -n 'HasCommunityInterface' src/Entity/OralHistory.php src/Entity/Teaching.php src/Entity/Event.php
# expected: 0 matches after the WP
grep -nE "new EntityType\(.*'(oral_history|teaching|event)'" src/Provider/Entity/EntityContentProvider.php
# expected: each line shows surrounding `tenancy: ['scope' => 'community']`
```

### WP03 (EntityFoundationProvider + final sweep)
```bash
grep -n 'HasCommunityInterface' src/Entity/Post.php
# expected: 0 matches after the WP
grep -rn 'HasCommunityInterface' src/
# expected: 0 matches across the entire src/ tree
grep -rn 'HasCommunityInterface' tests/
# expected: 0 matches OR matches that are explicit reflective tests (none expected today)
```

## Constraints (from spec)

| ID    | Mapping                                                                                                                                |
| ----- | -------------------------------------------------------------------------------------------------------------------------------------- |
| C-001 | No behavioral change — only the registration shape changes. Access policies and queries are untouched.                                  |
| C-002 | Each entity's marker removal AND provider edit land in the same commit (atomicity).                                                     |
| C-003 | Framework alpha.173+ guarantees the explicit `tenancy:` declaration; do not target older framework versions.                            |

## Notes

- Entity bundle keys (`'group'`, `'leader'`, etc.) in the verification
  greps are best-effort guesses based on Minoo conventions
  (`group`, `leader`, `contributor`, `oral_history`, `teaching`, `event`,
  `post`). The implementer should confirm the actual bundle string per
  provider before the grep commands above are useful.
- Provider ownership is provisional. WP01 begins with a 30-second
  verification pass that confirms each entity's actual registration site;
  any drift triggers a WP-boundary adjustment before edits begin.
