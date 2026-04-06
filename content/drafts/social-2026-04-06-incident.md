---
title: "Social posts: Three Stacked Bugs incident (2026-04-06)"
draft: true
---

Blog post link: https://minoo.live/blog/2026/04/06/three-stacked-bugs-and-alpha-107/

---

## Twitter / X

### 1. Shock (265 chars)

Loaded minoo.live this morning and saw a white screen. 200 OK, zero bytes, dead silence.

Turned out to be three stacked bugs, each hiding the next. A framework bump, a kernel that refused to speak, and PHPUnit leaking into production.

Writeup: https://minoo.live/blog/2026/04/06/three-stacked-bugs-and-alpha-107/

### 2. Lesson (272 chars)

Three things I am taking from today's incident:

1. Never autoload a class that extends a dev-only dep. Use autoload-dev.
2. Response::send() is unconditional. No PHP_SAPI guards.
3. Verify prod with curl body fetches, not status codes. Dead kernels return 200.

https://minoo.live/blog/2026/04/06/three-stacked-bugs-and-alpha-107/

---

## LinkedIn

### 1. Shock

The worst feeling in web development is loading your own site and seeing nothing. Not an error page. Not a stack trace. White.

That was minoo.live this morning, ninety seconds after I landed a framework bump I had been fighting for a week.

Every route was returning 200 OK with zero bytes. Caddy was happy. PHP-FPM was happy. The kernel was running. The response object was being built. It was just never being flushed.

Root cause: a guard in public/index.php that only called Response::send() under PHP's built-in dev server. Fine for the old response object (which echoed inside handle()). Completely broken once I migrated 29 controllers to Symfony HttpFoundation in PR #632.

The rollback took one SSH session and about sixty seconds. The lesson took longer.

Full incident writeup, including two more stacked bugs behind this one: https://minoo.live/blog/2026/04/06/three-stacked-bugs-and-alpha-107/

#PHP #Symfony #BuildInPublic #IncidentResponse

### 2. Lesson

Forty-five minutes from "production is dark" to "production is live on alpha.107." Three stacked bugs. Here is what I am keeping:

1. Never put a class that extends a dev-only dependency under production autoload. The framework's manifest compiler reflects every class at boot. If Reflection hits PHPUnit\Framework\TestCase and PHPUnit isn't installed (because you ran --no-dev like a grown-up), the kernel crashes before the first request. Use autoload-dev or keep the file out of the production classmap entirely.

2. Always call Response::send() unconditionally in public/index.php. PHP_SAPI gating is a footgun for fpm-fcgi.

3. Verify production with curl body fetches and title-tag greps, never with status codes alone. A dead kernel can still return 200 OK.

4. When a deploy pipeline has been red for multiple runs, assume multiple bugs are stacked. Each fix exposes the next. That is not failure of diagnosis; that is the nature of stacked regressions.

Going public with Minoo over the next few weeks, including launching a Twitch stream of the development work. Days like this one are exactly why building in public is worth it.

https://minoo.live/blog/2026/04/06/three-stacked-bugs-and-alpha-107/

#SoftwareEngineering #Postmortem #PHP #IndigenousTech

---

## Bluesky

### 1. Shock (288 chars)

Loaded minoo.live this morning and got a white screen. 200 OK, zero bytes.

Three stacked bugs: a broken deploy smoke-test, a Response::send() gated on PHP_SAPI (fine in dev, silent in fpm-fcgi), and PHPUnit leaking into production autoload.

https://minoo.live/blog/2026/04/06/three-stacked-bugs-and-alpha-107/

### 2. Lesson (293 chars)

Lessons from today's Minoo incident:

Never autoload a class that extends a dev-only dep.
Response::send() is unconditional, no SAPI gating.
Verify prod with curl body fetches, not status codes. A dead kernel returns 200.

Multiple red deploys = multiple stacked bugs.

https://minoo.live/blog/2026/04/06/three-stacked-bugs-and-alpha-107/
