# Staging gate — verify before minoo.live (#783)

Minoo deploys by bumping `MINOO_REF` to a commit in the `waaseyaa-infra`
compose service (a human action). Production has been bitten before by a kernel
that emits 200s with empty bodies (alpha.75→107 WSOD), so **every promotion goes
through a verify-before-prod gate**: stand the candidate ref up on a staging
surface, prove it with `scripts/verify-http.php` (body-size + title, never status
alone), and only then promote.

## The gate

1. **Pick the candidate** — a green commit on `main` (CI green, `./vendor/bin/phpunit`
   + `composer phpstan` clean locally).
2. **Stand it up on staging** — point the staging surface at the candidate ref
   (see "Staging surface" below).
3. **Verify staging**:
   ```
   php scripts/verify-http.php https://staging.minoo.live
   ```
   All checks must read `[PASS]`. This asserts each public page returns 200 with
   a non-trivial body and the expected `<title>` — a crashing kernel that returns
   empty 200s fails here, not in production.
4. **Promote** — bump `MINOO_REF` to the verified commit in `waaseyaa-infra`
   (the deliberate human step).
5. **Verify prod**:
   ```
   php scripts/verify-http.php https://minoo.live
   ```
6. **Roll back** by reverting `MINOO_REF` to the previous commit if prod verify
   fails; re-run the prod verify after rollback.

## Staging surface

Two ways to satisfy the gate, in order of preference:

- **Staging compose service (target state, infra repo):** add a `minoo-staging`
  service in `waaseyaa-infra/compose/minoo` with its own `MINOO_REF`, its own
  SQLite volume (a copy of the prod snapshot — never the live file), and a
  Cloudflare tunnel route to `staging.minoo.live`. The tunnel hostname + DNS are
  a human/Cloudflare step (same pattern as the prod cutover). The staging
  service runs `SENDGRID_API_KEY` empty (email-less auth no-ops) and
  `WAASEYAA_DEV_FALLBACK_ADMIN` OFF, like prod.
- **Local pre-flight (minimum gate, always available):** before any promotion,
  run the app locally and verify it:
  ```
  php -S 127.0.0.1:8099 -t public public/index.php
  php scripts/verify-http.php http://127.0.0.1:8099
  bin/smoke-test          # kernel/entity/data/route checks against the DB
  ```

## Notes

- `scripts/verify-http.php` takes a base URL arg or `BASE_URL` env; exit 0 = all
  pass, 1 = failure(s). Extend the `$checks` list as new public pages ship.
- This complements `docs/launch-verification-checklist.md` (manual launch sweep)
  and `bin/smoke-test` (in-process kernel checks). The HTTP verifier is the piece
  that proves a *remote* deployment is actually serving content.
- Crossword puzzles self-heal on demand per environment, so staging needs no
  puzzle seeding. The corpus directory (`MINOO_CORPUS_PATH`) is provided
  out-of-band and is not needed for the public surface.
