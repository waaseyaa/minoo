# Indigenous AI Alignment & Data Sovereignty — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Codify Minoo's alignment with Indigenous Protocol and AI Working Group principles, add a public data sovereignty page, and prepare outreach materials.

**Architecture:** Three independent deliverables — an internal alignment document, a public Twig template page, and a footer nav update. All content-only, no behavioral changes.

**Tech Stack:** Twig 3 templates, vanilla CSS (`minoo.css`), Markdown

**Spec:** `docs/superpowers/specs/2026-03-11-indigenous-ai-alignment-design.md`

---

## Chunk 1: All Tasks

### Task 1: Indigenous AI Alignment Document

**Files:**
- Create: `docs/indigenous-ai-alignment.md`

**Reference files (do not modify):**
- `docs/content-tone-guide.md` — narrative pillars, terminology
- `docs/superpowers/specs/2026-03-11-indigenous-ai-alignment-design.md` — spec sections A–D

- [ ] **Step 1: Write the alignment document**

Create `docs/indigenous-ai-alignment.md` with four sections. Follow the content tone guide voice (first-person plural, warm, direct). Reference the spec for section content:

```markdown
# Minoo & Indigenous AI — Alignment Statement

## How We Build

Minoo is built by a First Nations developer in northern Ontario using AI as a
collaborator — not the other way around. The developer leads. The AI assists.
Community values govern every decision.

What this looks like in practice:

- **Community voice, not corporate voice.** Our content tone guide requires
  first-person plural ("we," "our") or second-person ("you"). Every page speaks
  like a neighbor, not a product.
- **Anishinaabemowin as living language.** We use Indigenous language terms where
  they add meaning — miigwech, manoomin, aki — with English alongside, never as
  decoration.
- **Built by and for communities.** Minoo is not a corporate product. It exists
  because communities need it. Design follows community values. Data comes from
  community sources. The platform grows based on community needs.
- **AI serves the builder's vision.** We use Claude Code extensively in
  development. The AI is a powerful tool, but it does not set direction. An
  Indigenous developer decides what gets built, how it works, and who it serves.

## Principle Alignment

The [Indigenous Protocol and AI Working Group](https://www.indigenous-ai.net/)
published a 205-page position paper in 2020 articulating principles for
Indigenous-led AI development. We discovered their work after building Minoo and
recognized the principles we had been following by instinct.

| Indigenous AI Principle | How Minoo Practices It |
|---|---|
| **Relationality** | Knowledge in Minoo is relational. Teachings connect to Elders. Elders connect to communities. Communities connect to language speakers. Nothing exists in isolation. |
| **Reciprocity** | Our Reciprocal Care pillar frames volunteering as an honor, not charity. The Elder Support Program flows both ways — Elders receive help, volunteers receive purpose and connection. |
| **Locality** | Minoo is location-aware by design. It is rooted in the geography of northern Ontario First Nations — real communities, real coordinates, real distances. Not a generic platform deployed anywhere. |
| **Indigenous Data Sovereignty** | Community data stays community-controlled. We do not sell, mine, or share data with third parties. Formal governance frameworks are needed and must come from communities. |
| **AI as Kin, not Tool** | Our development model treats AI as a collaborator that serves Indigenous vision rather than extracting from it. The relationship is intentional and directed. |

## What Minoo Does Not Do

These commitments stand now, before any formal governance framework is in place:

- **No data mining.** We do not analyze community data for patterns, trends, or
  insights beyond what the platform needs to function.
- **No selling community data.** Community information is never sold, licensed, or
  monetized.
- **No AI training on community knowledge.** Teachings, language entries, Elder
  information, and other community knowledge are never used to train AI models.
- **No surveillance.** We do not track, profile, or monitor community members
  beyond basic session functionality.
- **No third-party data sharing.** Community data is not shared with external
  organizations without explicit community consent.

## Where Governance Must Lead

There are decisions a developer cannot and should not make alone:

- **Who owns the data.** Community data may belong to band councils, to the
  communities collectively, or to the individuals who contributed it. This is a
  governance question.
- **What is public, what is private.** Some Teachings may be meant for everyone.
  Others may be restricted by protocol, season, or ceremony. Communities decide.
- **How language data is governed.** Anishinaabemowin dictionary entries, example
  sentences, and speaker recordings carry cultural weight. Whether and how they
  can be shared needs community direction.
- **What a community data agreement looks like.** The Kaitiakitanga License by
  Te Hiku Media treats Māori language data as collectively owned taonga
  (treasure). Whether an Anishinaabe-specific framework exists or should be
  developed is a conversation for Knowledge Keepers and community leaders.

Minoo's role is to build the platform so it *can* enforce whatever communities
decide. The technology should never be the bottleneck for sovereignty.

---

*This document was written in March 2026. It is a living document that evolves
as the project does and as community governance frameworks develop.*
```

- [ ] **Step 2: Commit**

```bash
git add docs/indigenous-ai-alignment.md
git commit -m "docs: add Indigenous AI alignment statement

Codifies Minoo's alignment with the Indigenous Protocol and AI Working
Group's principles — development practice, principle mapping, data
commitments, and governance boundaries."
```

---

### Task 2: Data Sovereignty Statement Page

**Files:**
- Create: `templates/data-sovereignty.html.twig`
- Modify: `templates/base.html.twig:74-78` (footer nav)

**Reference files (do not modify):**
- `templates/safety.html.twig` — template pattern to follow
- `templates/base.html.twig` — footer nav structure
- `docs/content-tone-guide.md` — voice, SHOW + TELL + INVITE

- [ ] **Step 1: Create the data sovereignty template**

Create `templates/data-sovereignty.html.twig` extending `base.html.twig`. Follow the `safety.html.twig` pattern — `content-section flow-lg` wrapper, `section class="flow"` for each content block. Use the content tone guide voice throughout.

```twig
{% extends "base.html.twig" %}

{% block title %}Data Sovereignty — Minoo{% endblock %}

{% block content %}
  <div class="content-section flow-lg">
    <div>
      <h1>Data Sovereignty</h1>
      <p class="text-secondary">Your data belongs to your community. Not to us, not to any corporation, not to any algorithm.</p>
    </div>

    <section class="flow">
      <h2>What We Hold and Why</h2>
      <p>Minoo stores information that helps communities connect, support Elders, and keep culture alive. Everything we hold exists to serve you — not to serve us.</p>
      <ul class="how-it-works__list">
        <li><strong>Community profiles</strong> — names, locations, treaty areas, and contact information for First Nations and municipalities across northern Ontario</li>
        <li><strong>People</strong> — Knowledge Keepers, Elders, language speakers, and community leaders who have chosen to be listed</li>
        <li><strong>Teachings</strong> — cultural knowledge shared by community members for the benefit of the next generation</li>
        <li><strong>Language entries</strong> — Anishinaabemowin words, phrases, and example sentences contributed by speakers</li>
        <li><strong>Events</strong> — powwows, gatherings, ceremonies, and community programs</li>
        <li><strong>Elder support requests</strong> — requests for help from Elders, handled by community coordinators</li>
      </ul>
    </section>

    <section class="flow">
      <h2>What We Never Do</h2>
      <p>These are hard commitments. They are not negotiable.</p>
      <ul class="how-it-works__list">
        <li><strong>We never sell your data.</strong> Community information is never sold, licensed, or monetized — not now, not ever.</li>
        <li><strong>We never mine your data.</strong> We do not analyze community information for patterns, trends, or insights beyond what the platform needs to work.</li>
        <li><strong>We never train AI on your knowledge.</strong> Teachings, language entries, Elder information, and other community knowledge are never used to train artificial intelligence models.</li>
        <li><strong>We never share your data with third parties.</strong> Community data is not shared with external organizations without explicit community consent.</li>
        <li><strong>We never surveil you.</strong> We do not track, profile, or monitor community members beyond what is needed for basic site functionality.</li>
      </ul>
    </section>

    <section class="flow">
      <h2>Who Decides</h2>
      <p>Minoo is built by a developer. But data governance is not a developer's decision to make alone.</p>
      <p>The real questions — who owns community data, what can be shared publicly, how Teachings and language data are governed — belong to band councils, Knowledge Keepers, and community leadership. This page is a starting point. The communities we serve will shape what comes next.</p>
      <p>Our job is to build the platform so it <em>can</em> enforce whatever communities decide. The technology should never be the bottleneck for sovereignty.</p>
    </section>

    <section class="flow">
      <h2>Learning from Others</h2>
      <p>We are inspired by the work of others walking this path.</p>
      <p>The <a href="https://www.indigenous-ai.net/">Indigenous Protocol and AI Working Group</a> has articulated principles for Indigenous-led technology development — relationality, reciprocity, locality, and data sovereignty. We recognized these as the principles we had been building by instinct.</p>
      <p>Te Hiku Media in Aotearoa (New Zealand) created the <strong>Kaitiakitanga License</strong>, which treats Māori language data as collectively owned taonga — treasure. Their approach says: this knowledge belongs to the people, not to whoever collects it. We carry that same understanding.</p>
      <p>An Anishinaabe-specific data sovereignty framework may already exist or may need to be developed. That conversation belongs to our communities.</p>
    </section>

    <section class="flow">
      <h2>Questions or Concerns</h2>
      <p>If you are a community leader, band council member, or Knowledge Keeper with questions about how Minoo handles community data, we want to hear from you.</p>
      <p>Call us or reach out through your band office. You can also email us at <a href="mailto:hello@minoo.live">hello@minoo.live</a>.</p>
      <p>This is your platform. Your questions shape how it grows.</p>
    </section>
  </div>
{% endblock %}
```

- [ ] **Step 2: Add footer navigation link**

In `templates/base.html.twig`, add a "Data Sovereignty" link to the footer legal nav (after the existing Accessibility link at line 78):

```twig
{# Find this block (lines 74-79): #}
          <nav class="site-footer__links" aria-label="Legal">
            <a href="/about">About</a>
            <a href="/legal/privacy">Privacy</a>
            <a href="/legal/terms">Terms</a>
            <a href="/legal/accessibility">Accessibility</a>
          </nav>

{# Replace with: #}
          <nav class="site-footer__links" aria-label="Legal">
            <a href="/about">About</a>
            <a href="/legal/privacy">Privacy</a>
            <a href="/legal/terms">Terms</a>
            <a href="/legal/accessibility">Accessibility</a>
            <a href="/data-sovereignty">Data Sovereignty</a>
          </nav>
```

- [ ] **Step 3: Verify page renders**

Start the dev server and check that `/data-sovereignty` renders correctly:

```bash
php -S localhost:8081 -t public &
sleep 1
curl -s -o /dev/null -w "%{http_code}" http://localhost:8081/data-sovereignty
# Expected: 200
kill %1
```

- [ ] **Step 4: Commit**

```bash
git add templates/data-sovereignty.html.twig templates/base.html.twig
git commit -m "feat: add data sovereignty statement page

Public page at /data-sovereignty with hard commitments on data handling,
community governance framing, and references to Indigenous AI and
Kaitiakitanga principles. Footer nav link added."
```

---

### Task 3: Run Full Test Suite

**Files:** None modified — verification only.

- [ ] **Step 1: Run PHPUnit**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
# Expected: 253 tests, 630 assertions, all passing
```

- [ ] **Step 2: Run Playwright**

```bash
npx playwright test
# Expected: 42 passing, 3 skipped
```

- [ ] **Step 3: Final commit if any adjustments were needed**

Only if tests revealed issues that required fixes.
