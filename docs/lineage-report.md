# Phase 1 lineage reconciliation — minoo resurrection

Date: 2026-06-12. Trees compared:

- **A. Clone** `Local Sites\minoo` @ `e83b6c7` (main HEAD, 2026-05-21)
- **B. Deployed snapshot** `E:\backups\razor-crest\2026-05-21T000114Z\apps\deployer\minoo\current` (prod until 2026-05-20, alpha.187)
- **C. Working tree** `C:\Users\jones\Projects\Minoo` (alpha.157 lock, .git stripped)

## Verdict: main HEAD is strictly the most advanced tree. Nothing needs porting.

### B (deployed) == commit `8107dba`
`git diff --no-index --ignore-cr-at-eol` between B's `src/` and the clone at `8107dba`
("chore: bump waaseyaa/* to v0.1.0-alpha.187", 2026-05-20 17:57) is **empty**.
Main HEAD is 2 commits ahead of production: `861f8d0` (alpha.188 pin) and `e83b6c7`
(deploy-script fix). Neither ever ran in prod. No prod-only drift exists.

### The deployed "Access reorganization" is already in main
It is the `src/` domain-subdirectory layout (came via `refactor/src-infrastructure-reorg`
and follow-ups), present at `8107dba` and HEAD:
- `src/Access/<Domain>/` (Community, Editorial, ElderSupport, Events, Feed, Games,
  Groups, Ingestion, Language, Messaging, Newsletter, OralHistory, Teachings)
- `src/Http/Controller/<Domain>/`, `src/Http/Twig/`
- `src/Entity/<Domain>/` (incl. `Entity/Language/`)
- `src/Domain/<area>/` for services (Chat, Events, Feed, Geo, Newsletter)

### C (working tree) contributes nothing
C predates the reorg (flat `src/Controller`, `src/Entity`, `src/Access`; alpha.157).
Every piece of "undeployed language-domain work" in C was merged to main afterwards:

| C path | Main HEAD path | Content delta |
|---|---|---|
| src/Entity/Speaker.php | src/Entity/Language/Speaker.php | namespace only |
| src/Entity/DialectRegion.php | src/Entity/Language/DialectRegion.php | namespace only |
| src/Entity/WordPart.php | src/Entity/Language/WordPart.php | namespace only |
| src/Entity/DictionaryEntry.php | src/Entity/Language/DictionaryEntry.php | namespace only |
| src/Entity/ExampleSentence.php | src/Entity/Language/ExampleSentence.php | namespace only |
| src/Ingestion/** (13 files) | src/Ingestion/** | main slightly ahead (net -9 lines refactor) |

C-only files, all superseded or obsolete: `Controller/AdminController.php` (29-line stub;
main uses AdminRouteProvider + admin-surface), `Seed/ContentSeeder.php` (split into
main's Seed/* suite), `Support/Command/{CrisisOgAssets,MailTest,MessagingDigest,
GenealogyDemoSeed}Command.php` (renamed to `Console/*Handler.php` in main; Crisis/
Messaging/Genealogy are cut surfaces anyway).

**Decision: Phase 2 builds directly on main HEAD. B and C are reference-only from here.**

## Shape-A keep list → preserve-vs-rewrite (all paths = main HEAD)

Preserve as-is (P), preserve-with-edits during slimming (E):

- **Language entities** P: `src/Entity/Language/*` (5 entities), `src/Access/Language/`,
  `src/Http/Controller/Language/`, `src/Ingestion/**` (feeds Phase 4 corpus import)
- **Entity registration** E: `src/Provider/Entity/EntityFoundationProvider.php` registers
  `word_part`, `speaker` (with consent fields), `dictionary_entry`, `example_sentence`;
  `EntityCommunityProvider.php` registers `dialect_region` (move to a kept provider when
  community surface is cut)
- **Games** P: `src/Http/Controller/Games/{Shkoda,Matcher,Agim,Crossword,Journey}Controller`,
  `GameControllerTrait`, `src/Entity/Games/{GameSession,DailyChallenge,CrosswordPuzzle}`,
  `src/Access/Games/`. Cut: `GuessPriceController`.
- **Auth/accounts** P: `src/Http/Controller/Auth/`, `src/Http/Controller/Account/`
- **Admin** E: `src/Provider/Routing/AdminRouteProvider.php` + admin-surface package
- **Search** E: search controllers/providers (trim to dictionary scope in Phase 2)
- **i18n** P: framework package + app wiring
- **Consent/access machinery** P: `src/Access/**` for kept domains,
  `migrations/20260315_110500_add_consent_fields.php`
- **Chat** E (conditional): `src/Domain/Chat/`, `src/Http/Controller/Chat/` — keep only
  re-grounded on dictionary/corpus retrieval, else cut

## Phase 3 implications

- No migrations exist for `speaker`, `dialect_region`, `word_part` tables (the
  2026-03-20 migration `rename_speaker_to_contributor` is the *old* community speaker,
  unrelated). New tables must be migrated in Phase 3, as planned.
- `migrations/` stops at 2026-04-19; later schema (incl. the alpha.186/187 era) is
  presumably handled by framework schema sync — verify in Phase 3 against the restored DB.

## Phase 4 implication (consent)

`EntityFoundationProvider` defines `speaker.consent_public_display` **default 1** and
`consent_ai_training` default 0. Corpus import must explicitly set ALL consent flags OFF,
never rely on defaults.
