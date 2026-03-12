# V1 PR Checklist for Maintainers

Copy-paste this into every PR description before merging to main during the V1 release cycle.

---

## Required (all boxes must be checked)

### Issue & Process
- [ ] PR title includes issue number: `feat(#N): description`
- [ ] Issue is assigned to a milestone
- [ ] PR targets `main` branch

### Tests
- [ ] All existing PHPUnit tests pass (`./vendor/bin/phpunit`)
- [ ] New functionality includes unit tests
- [ ] Playwright tests pass locally if templates/CSS changed
- [ ] No tests skipped or marked incomplete without explanation

### Security
- [ ] No secrets, API keys, or credentials in committed files
- [ ] User input is validated/sanitized (no raw `$_GET`/`$_POST` usage)
- [ ] All database queries use parameterized statements
- [ ] Twig templates use autoescape (no `|raw` without justification)
- [ ] Auth-gated routes verified: unauthenticated users get redirect, not data

### Compliance (CC BY-NC-SA / Data Sovereignty)
- [ ] No payment, subscription, or commercial features introduced
- [ ] OPD-derived content includes attribution metadata
- [ ] New entities with user content include `consent_public` field
- [ ] No new public API endpoints that export community data
- [ ] Media entities include `copyright_status` field if applicable

### Code Quality
- [ ] `declare(strict_types=1)` in every new PHP file
- [ ] Classes are `final` unless inheritance is intentional
- [ ] No hardcoded demo/placeholder data in templates
- [ ] CSS uses logical properties only (`margin-block`, not `margin-top`)
- [ ] Content tone follows `docs/content-tone-guide.md` for user-facing copy

### Accessibility
- [ ] New images have `alt` text
- [ ] New forms have associated `<label>` elements
- [ ] New interactive elements are keyboard-navigable
- [ ] Color contrast meets 4.5:1 ratio

### Before Merge
- [ ] CI pipeline is green (lint + PHPUnit + Playwright + audit)
- [ ] At least one reviewer has approved
- [ ] No merge conflicts with main
- [ ] Squash-merge if PR has >3 commits (keep history clean)
