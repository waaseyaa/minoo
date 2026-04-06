---
title: "Three Stacked Bugs and the Road to alpha.107"
date: 2026-04-06
draft: true
tags: ["minoo", "waaseyaa", "php", "symfony", "deployment", "postmortem"]
summary: "A framework bump that had been stuck for four failed deploys finally landed. Shipping it exposed a chain of three bugs, each hiding the next."
---

The framework bump from waaseyaa/framework `alpha.75` to `alpha.106` had been red for four deploy runs. Today it finally landed, and then everything broke in a new way. Three times. Here is how it went, because I think the lesson is worth the bruise.

## The block

Minoo runs on Waaseyaa, a framework I maintain in a sibling monorepo. For most of the last month I could not upgrade past `alpha.75` because the kernel had moved to Symfony HttpFoundation, and Minoo's 29 controllers were still returning a homegrown `SsrResponse` value object. PR #632 was the bridge: a mechanical but tedious migration of every controller to `Symfony\Component\HttpFoundation\Response`. Two days of work, 29 files, one green CI run. I merged it this morning and pushed the framework bump behind it.

Deploy went red. Again.

## Bug one: the deploy pipeline itself

The Playwright smoke-test step in `.github/workflows/deploy.yml` was starting a dev server without `APP_ENV=testing`. The server tried to boot production config (which expects real secrets in the runner), crashed before the first request, and Playwright reported "no server." I had been reading the Playwright errors for four runs and missing the dev server crash buried above them.

One line:

```yaml
- name: Start server for smoke tests
  run: APP_ENV=testing php -S 127.0.0.1:8081 -t public &
```

Deploy went green. I exhaled. Then I opened minoo.live.

## Bug two: the white screen of nothing

Every route returned `200 OK` with zero bytes. A real white screen. `curl -I` looked healthy; `curl -v` showed `Content-Length: 0`. Caddy was happy. PHP-FPM was happy. The kernel was running. The response simply was not being emitted.

The culprit was a single guard in `public/index.php` that I had written months ago for the built-in dev server and then forgotten:

```php
// OLD: gated on cli-server
if (PHP_SAPI === 'cli-server') {
    $response->send();
}
```

Under the old `SsrResponse`-era kernel this was fine, because content was echoed inside `handle()`. Once controllers started returning Symfony `Response` objects, the body lived on the response object and needed an explicit `->send()` to flush. Under `fpm-fcgi` that call never fired. I had quite literally built a kernel that refused to speak in production.

SSH to the box, hot-patch `public/index.php` to drop the guard, push the real fix to `main`. Reload the page.

## Bug three: "Class PHPUnit\\Framework\\TestCase not found"

The hot-patch gave me a proper 500. Progress, kind of. The error:

```
Application failed to boot.
Class "PHPUnit\Framework\TestCase" not found
```

PHPUnit in production? Production is `composer install --no-dev`. PHPUnit is not installed. Yet the manifest compiler was trying to reflect a class that extended it.

Trace it: `waaseyaa/graphql alpha.106` had added a helper called `AbstractGraphQlSchemaContractTestCase` inside `packages/graphql/src/Testing/`. That path is under the package's production PSR-4 autoload. The framework's `PackageManifestCompiler` scans every class in the app classmap via Reflection to discover entity types and service providers. Reflecting a class triggers loading its parents. Parent is `PHPUnit\Framework\TestCase`. Boom: fatal before the kernel ever sees a request.

This is the kind of bug that is invisible in dev because `--dev` installs PHPUnit and everything Just Works.

## Recovery, in order

Time matters in a dark-production incident, so here is the actual sequence:

**00:00** — Confirmed WSOD was global, not a specific route.
**00:01** — Rolled back: `ln -sfn releases/280 current && sudo systemctl reload php-fpm`. Site back on `alpha.75`. Minute one, done.
**00:05** — Reproduced the WSOD locally under `fpm-fcgi` by removing the `cli-server` guard test. Confirmed root cause.
**00:12** — Moved `AbstractGraphQlSchemaContractTestCase` out of `packages/graphql/src/Testing/` into `packages/graphql/testing/`, registered `Waaseyaa\GraphQL\Testing\` under `autoload-dev` only in the package's `composer.json`. Consumers with `--dev` still get it; production never sees the file.
**00:18** — Caught a second pre-existing failure while I was in there: `UserServiceProviderTest::throws_when_app_url_not_configured` had been stale since a refactor weeks ago. Fixed the assertion. Also updated `docs/specs/access-control.md` and `docs/specs/infrastructure.md` to clear two drift-detector warnings that had been nagging me.
**00:25** — Tagged `v0.1.0-alpha.107` on the framework monorepo. The split workflow pushed to per-package repos.
**00:32** — In Minoo: `composer update 'waaseyaa/*'` to `alpha.107`, committed alongside the `public/index.php` fix. Pushed.
**00:41** — Deploy pipeline green on the first try.
**00:45** — Verified production with `curl -s https://minoo.live/ | grep '<title>'` and body-size checks across twelve routes. Every route serving 17 to 162 KB of rendered HTML. Release 282 live.

Roughly 45 minutes from dark to live, including a framework release.

## What I am taking away

**Never put a class that extends a dev-only dependency under production autoload.** Use `autoload-dev`, or keep the file in a directory that isn't on the production classmap. This is the PHP equivalent of importing test helpers from your production entry point.

**Always call `Response::send()` unconditionally in `public/index.php`.** `PHP_SAPI` gating is wrong for `fpm-fcgi`. If you forget this, you will serve zero bytes to real users and your monitors will not notice because the status code is 200.

**Verify production with curl body fetches and title-tag greps, never with status codes alone.** A dead kernel can still emit headers. I have a `scripts/verify-production.sh` now that grabs bodies and checks them against a size floor.

**When a pipeline has been red for multiple runs, assume multiple bugs.** I kept thinking it was one thing. It was three. Each fix exposed the next. That is not a failure of diagnosis; it is the nature of stacked regressions.

## What is next

I am moving to a new machine this week and starting to stream development on Twitch. This is the ship-note that kicks off the public phase of Minoo. The point of building in public is not to look smart. It is to be honest about days like this one, where four failed deploys become three stacked bugs become one framework release and a site that renders again.

If you want to see where this is headed:

- **Try Minoo**: [minoo.live](https://minoo.live). Events, teachings, language, communities. Real Anishinaabe content, real SSR, no JavaScript framework between you and the words.
- **Star the repo**: [github.com/waaseyaa/minoo](https://github.com/waaseyaa/minoo). Everything is open, including the bruises.
- **Subscribe to Ahnii!**: [jonesrussell42.substack.com](https://jonesrussell42.substack.com). Longer-form personal writing about building this thing.
- **Follow the Twitch stream**: launching this week. I will post the URL on Substack and here first.

Pair-programmed this incident with Claude Code. I caught the deploy.yml fix; Claude caught the `PHP_SAPI` gate in about four seconds flat. Good day to be working with a partner who does not panic when production goes dark.

Miigwech for reading.
