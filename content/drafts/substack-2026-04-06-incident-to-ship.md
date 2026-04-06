---
title: "From WSOD to Ship in 45 Minutes"
subtitle: "What today felt like, and why I am going public anyway"
date: 2026-04-06
publication: "Ahnii!"
draft: true
---

There is a specific feeling when you load your own site and see nothing. Not an error page. Not a stack trace. Just white. The browser tab spinner stops, the page is 200 OK, and there is no content at all. Your brain does a small dishonest thing where it tries to tell you the CSS is still loading. Then the developer console confirms what you already know: zero bytes. The kernel is running, the server is responding, and somehow nothing is being said.

That was minoo.live this morning, about ten minutes after I finally landed a framework bump I had been fighting for a week.

I want to tell you what happened, because I am about to start streaming development on Twitch and the whole point of that is to not pretend days like this are rare.

## The backstory

Minoo runs on a framework I wrote called Waaseyaa. Most of my time this month has gone into a migration: 29 controllers needed to move off a homegrown response object I had built too early, onto Symfony's HttpFoundation Response, because the framework kernel had moved on and I could not upgrade past `alpha.75` until Minoo caught up. Boring, tedious, mechanical work. Two days of it. PR #632.

It landed green this morning. I pushed the framework bump behind it. Four previous deploy runs had been red. This one was going to be the one.

It was not the one.

## What rolling back at minute one feels like

When I saw the white screen I did what you do: SSH to the box, `ls releases/`, swap the symlink back to the previous release, reload PHP-FPM. Sixty seconds, maybe. Site came back. Release 280 alive again. My heartbeat also came back.

Here is the part I want to name out loud. In the moment of rollback, I felt two things at the same time. One was relief, because the site was up. The other was a quiet kind of shame, because I had shipped a thing that did not work, and the evidence was public for however long it took me to notice. The Indigenous community this site is for does not care about my framework migration. They care whether the events page loads. This morning, for about ninety seconds, it did not.

I think it is important to sit in that feeling for a second rather than paper over it. Building in public means you will have ninety-second windows where your users see the thing broken. You get faster at rollback. You get better at monitoring. You build tools that catch these earlier. But you do not get to opt out of the feeling.

## Three bugs in a trench coat

What the white screen turned out to be was not one bug. It was three, standing on each other's shoulders.

The first bug was my own deploy pipeline. I had been running Playwright smoke tests in CI without setting `APP_ENV=testing`, which meant the dev server was trying to boot with production config on the runner, which has no secrets, and it crashed before Playwright could even connect. I had been staring at the Playwright errors across four failed runs and missing the real crash above them. One line in a YAML file. Four red runs.

The second bug was the white screen itself. Months ago I wrote a guard in `public/index.php` that only called `$response->send()` when running under PHP's built-in dev server. At the time, the old response object echoed its content directly inside `handle()`, so under production `fpm-fcgi` this was fine. Once I moved to Symfony Response, the body lived on the object and needed explicit flushing. The guard silently swallowed every response under production. The kernel was running. The response was being built. It was just never being spoken out loud.

The third bug was the one the hot-patch revealed. When I dropped the guard, the site gave me a proper 500 instead of a white screen, and the error was: "Class PHPUnit\\Framework\\TestCase not found." Which, excuse me, production? PHPUnit should not be within a mile of production. I traced it to the `waaseyaa/graphql` package: `alpha.106` had added a test helper class inside the package's production autoload path. The helper extended `PHPUnit\Framework\TestCase`. On dev installs this was fine. On production (`composer install --no-dev`), PHPUnit was not installed, and the framework's manifest compiler scans every class via Reflection at boot, which loads the parent, which does not exist, which crashes the kernel before the first request.

Each fix revealed the next. That is the thing about stacked bugs: you cannot see past the one in front of you until you fix it.

## The fix and the ship

Moving the helper to a separate `testing/` directory and registering it under `autoload-dev` took about five minutes once I understood the shape. I tagged `v0.1.0-alpha.107`. Bumped Minoo. Pushed. This time the deploy pipeline ran green on the first try. I verified production the right way (curling actual body content, grepping title tags, checking response sizes across a dozen routes) instead of just checking HTTP status codes, because I had just learned the hard way that a broken kernel can still return 200.

Forty-five minutes from "production is dark" to "production is live on alpha.107." Including a framework release. I will take it.

## Why I am telling you this

I am switching to a new machine this week and setting up Twitch to stream development. This post is the ship-note for that, in a way. Going public with the work means going public with the days when the work breaks, because those days are where the learning actually lives.

The three things I am keeping from today:

1. Never put a class that extends a dev-only dependency under production autoload. Ever. Use `autoload-dev` or keep the file in a directory that is not on the production classmap.
2. `Response::send()` is unconditional. Gate nothing. The dev server is not a special case worth a guard.
3. Check production with body fetches, not status codes. A dead kernel can still say "200 OK."

Claude Code was with me through the whole thing. I caught the deploy.yml fix. Claude caught the `PHP_SAPI` guard in about four seconds. That is the kind of pairing I am going to keep doing on stream, and I think it is more useful to show than to explain.

If you want in:

- [minoo.live](https://minoo.live) is running on alpha.107 as of this afternoon.
- The code is at [github.com/waaseyaa/minoo](https://github.com/waaseyaa/minoo). A star helps more than you think.
- Subscribe here on Substack for the personal writing; I am going to keep doing these.
- Twitch stream launches this week. I will post the URL in the next Ahnii! issue the moment it is live.

Miigwech for reading. Onward.

Russell
