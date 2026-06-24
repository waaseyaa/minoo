#!/usr/bin/env node
// Renders Open Graph (social-card) PNGs for minoo.live.
// Requires: npm install (Playwright is already a dev dependency) + a chromium.
//
// Minoo already ships the OG meta plumbing (templates/layouts/base.html.twig)
// and a hand-made og-default.png. This adds a branded per-page card for each
// STATIC public page, rendered to public/img/og/<slug>.png. At runtime the
// og_image_path() Twig function (src/Provider/AppBootServiceProvider.php) serves
// public/img/og/<slug>.png if it exists for the page's logical route, else falls
// back to og-default.png. The slug rule here MUST match og_image_path():
//   route -> lowercase, trim '/', '' => 'home', '/' -> '-'
//   ('/' => 'home', '/games/shkoda' => 'games-shkoda').
//
// The page list is a curated manifest (Minoo is i18n with non-1:1 route/template
// mapping, so titles come from the resolved English strings, not scraped Twig).
// Add a static page = add a row below; dynamic per-entity pages keep the default
// card. Run: node scripts/generate-og.js  (add --only-missing to fill gaps only)

const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const projectRoot = path.join(__dirname, '..');
const scriptDir = __dirname;
const ogOutDir = path.join(projectRoot, 'public', 'img', 'og');

const onlyMissing = process.argv.includes('--only-missing') || process.env.OG_ONLY_MISSING === '1';

const DEFAULT_DESC = 'Learn Anishinaabemowin and find your community on the north shore of Lake Huron.';

// Curated static public pages. Dynamic per-entity routes (/communities/{slug},
// /events/{slug}, /groups/{slug}, /language/{slug}, /lessons/{slug}) and noindex
// pages (/search, /language/search, auth) are intentionally excluded.
const PAGES = [
  { route: '/', title: 'Learn Anishinaabemowin', description: 'Learn Anishinaabemowin with a community dictionary and word games built for language learners on the north shore of Lake Huron.' },
  { route: '/lessons', title: 'Lessons', description: 'Free Anishinaabemowin lessons built from the words Knowledge Keepers share — start with the kitchen, taught by Steven Bennett with audio.' },
  { route: '/language', title: 'Language', description: 'A community Anishinaabemowin dictionary you can search, hear, and save, on the north shore of Lake Huron.' },
  { route: '/communities', title: 'Communities', description: 'The seven First Nations of Mamaweswen, The North Shore Tribal Council, on the north shore of Lake Huron in Robinson-Huron Treaty territory.' },
  { route: '/events', title: 'Events', description: 'Community events across the north shore of Lake Huron. ' + DEFAULT_DESC },
  { route: '/groups', title: 'Groups', description: 'Cultural groups and gatherings across the north shore of Lake Huron. ' + DEFAULT_DESC },
  { route: '/chat', title: 'Language Assistant', description: 'A cite-only Anishinaabemowin language assistant that answers from the community dictionary.' },
  { route: '/games', title: 'Games', description: 'Free Anishinaabemowin word games — Shkoda, Crossword, Matcher, Agim, and Journey. Practice the language with daily challenges.' },
  { route: '/games/shkoda', title: 'Shkoda', description: 'Shkoda — a free Anishinaabemowin word game on Minoo. Practice the language with daily challenges.' },
  { route: '/games/crossword', title: 'Crossword', description: 'Crossword — a free Anishinaabemowin word game on Minoo. Practice the language with daily challenges.' },
  { route: '/games/agim', title: 'Agim', description: 'Agim — a free Anishinaabemowin number game on Minoo. Practice counting in the language.' },
  { route: '/games/matcher', title: 'Matcher', description: 'Matcher — a free Anishinaabemowin matching game on Minoo. Match words to their meanings.' },
  { route: '/about', title: 'About Minoo', description: 'What Minoo is, who it is for, and how it is built with and for the community. ' + DEFAULT_DESC },
  { route: '/data-sovereignty', title: 'Data Sovereignty', description: 'How Minoo holds community language data: consent-based, community-owned, OCAP-aligned.' },
  { route: '/studio', title: 'The Studio', description: 'Watch Minoo being built, live, from inside Minoo. Russell streams the work from Sagamok Anishnawbek.' },
];

function routeSlug(route) {
  const s = route.replace(/^\/+|\/+$/g, '').toLowerCase();
  return s === '' ? 'home' : s.replace(/\//g, '-');
}

function urlForRoute(route) {
  return route === '/' ? 'minoo.live' : 'minoo.live' + route;
}

function htmlEscape(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

async function renderTemplateString(page, html, outputAbs) {
  fs.mkdirSync(path.dirname(outputAbs), { recursive: true });
  await page.setContent(html, { waitUntil: 'load' });
  // Wait for the webfonts (Fraunces / DM Sans) so the card is brand-accurate;
  // never hang if they fail to load.
  try {
    await page.evaluate(() => document.fonts && document.fonts.ready);
    await page.waitForTimeout(250);
  } catch (e) { /* fonts API unavailable: render with the fallback stack */ }
  await page.screenshot({ path: outputAbs, type: 'png' });
}

async function main() {
  const autoTemplate = fs.readFileSync(path.join(scriptDir, 'og-template-auto.html'), 'utf-8');
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1200, height: 630 });

    for (const p of PAGES) {
      const slug = routeSlug(p.route);
      const outPath = path.join(ogOutDir, slug + '.png');
      if (onlyMissing && fs.existsSync(outPath)) {
        console.log('skip (exists): ', path.relative(projectRoot, outPath));
        continue;
      }
      const html = autoTemplate
        .replace('{{TITLE}}', htmlEscape(p.title))
        .replace('{{DESCRIPTION}}', htmlEscape(p.description))
        .replace('{{URL}}', htmlEscape(urlForRoute(p.route)));
      await renderTemplateString(page, html, outPath);
      console.log('card:        ', path.relative(projectRoot, outPath), `(${p.title})`);
    }
  } finally {
    await browser.close();
  }
}

main().catch(err => { console.error(err); process.exit(1); });
