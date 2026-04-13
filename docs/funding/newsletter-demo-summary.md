# Elder Newsletter — Funding Demo Package

## What This Is

The Minoo Elder Newsletter is a printed, hand-delivered booklet connecting
Elders in Manitoulin Island communities with news, events, teachings, and
language. Many Elders do not use the internet. This newsletter ensures they
are not left out.

Issue #1 is a 12-page B&W saddle-stitched booklet serving Wiikwemkoong,
Sheguiandah, and Aundeck Omni Kaning. It is the first tangible artifact
produced by the Minoo platform — proof that the technology works and that
it produces real community value.

## Who It Serves

- **Elders** who are offline or have limited internet access
- **Community members** who contribute news, stories, and teachings
- **Knowledge Keepers** whose teachings reach a wider audience through print
- **Language speakers** who contribute Anishinaabemowin content to the
  language corner

## The Community Impact Story

In many First Nations communities, digital platforms serve people who are
already connected. Elders — the ones who carry the most knowledge — are
often the least connected. They miss community news, event announcements,
and the teachings being shared online.

The Elder Newsletter bridges this gap. It takes the living content of the
Minoo platform — events, teachings, community posts, language — and puts
it into the hands of Elders as a physical booklet. No login required. No
internet required. Just a newsletter delivered to their door.

This is not a one-time effort. The pipeline is automated: content is curated
from the platform, assembled into sections, rendered to a print-ready PDF,
and sent to a local print partner. Each issue can be produced in hours, not
weeks.

## How the Technology Works

### Content Pipeline

1. **Curation** — Community content (events, teachings, posts, dictionary
   entries) is selected from the Minoo platform using configurable section
   quotas. An assembler ranks candidates and fills each section.

2. **Inline Sections** — Hand-authored content (Editor's Note, jokes,
   puzzles, horoscope, language corner) is seeded separately and placed
   in fixed positions in the layout.

3. **Edition Lifecycle** — Each edition moves through a controlled state
   machine: Draft, Curating, Approved, Generated, Sent. Transitions are
   idempotent and auditable.

4. **PDF Rendering** — A tokenized URL serves the print template via
   headless Chromium (Playwright). The renderer writes to a temporary file,
   verifies it, then atomically moves it to the final path. A SHA-256 hash
   is persisted on the edition record.

5. **Print Delivery** — The PDF is sent to OJ Graphix (Espanola, ON) for
   200-copy print runs on standard paper, saddle-stitched.

### Technical Stack

- **Platform:** PHP 8.4, Waaseyaa CMS framework, SQLite
- **Templates:** Twig 3, vanilla CSS (no build step)
- **PDF Rendering:** Playwright (headless Chromium), Node.js
- **Print Format:** Letter pages (8.5 x 11"), B&W, imposed onto 11 x 17"
  tabloid by the printer for saddle-stitch binding

## Editorial Design

### Sections (12 pages)

| Page | Section | Content Source |
|------|---------|---------------|
| 1 | Cover | Masthead, volume/issue, communities served |
| 2 | Editor's Note | Hand-written editorial |
| 3-4 | Community News | Platform posts (3 items) |
| 5-6 | Events | Platform events (5 items) |
| 7 | Teachings | Platform teachings (2 items) |
| 8 | Language + Anishinaabemowin Corner | Dictionary entry + language content |
| 9-10 | Community Voices | Community submissions (4 items) |
| 11 | Jokes & Humour + Puzzles | Hand-authored |
| 12 | Horoscope + Elder Spotlight + Back Page | Hand-authored + closing |

### Voice and Cultural Care

All content follows the Minoo content tone guide:

- **First-person plural** ("we," "our") — never corporate third-person
- **Respectful terminology** — Elder, Knowledge Keeper, Teachings (capitalized)
- **Living culture** — teachings are practical wisdom for today, not artifacts
- **SHOW + TELL + INVITE** — every section shows real content, explains why
  it matters, and invites participation

## Community Partnerships

- **OJ Graphix** (Espanola, ON) — local print partner for physical production
- **Barbara Nolan** — language collaborator for Anishinaabemowin Corner content
- **Community submissions** — open to all community members via the platform

## What's Next

- **More communities** — the pipeline supports per-community editions with
  separate content curation and print runs
- **Digital delivery** — email PDF distribution for community members who
  prefer digital
- **Language content** — expanding the Anishinaabemowin Corner with
  contributed vocabulary, phrases, and cultural context
- **Seasonal themes** — aligning content with seasonal ceremonies and
  community calendars
- **Submission growth** — encouraging more community members to submit
  stories, recipes, and notices

## Artifacts in This Package

| Artifact | Location | Description |
|----------|----------|-------------|
| Print-ready PDF | `storage/newsletter/regional/1-1.pdf` | Final 12-page B&W PDF for Issue #1 |
| Language review PDF | `storage/newsletter/review/1-1-language-review.pdf` | Review draft with collaborator annotations |
| Content plan | `docs/plans/newsletter-issue-1-content-plan.md` | Section quotas and content strategy |
| Print template | `templates/newsletter/edition.html.twig` | Twig template for PDF rendering |
| Curation script | `scripts/curate-edition-1.php` | Idempotent content assembly |
| Generation script | `scripts/generate-edition-1-pdf.php` | Full render pipeline |
| Newsletter config | `config/newsletter.php` | Section quotas, printer details, PDF settings |

## How to Demo This

### For Funders and Partners

**Start with the PDF.** Open the print-ready PDF (`storage/newsletter/regional/1-1.pdf`)
and walk through it page by page. This is the thing that gets printed and
delivered to Elders — it is real, not a mockup.

**Emphasize three things:**

1. **This is automated.** The content comes from a living platform. New
   issues can be produced in hours, not weeks. The curation, rendering, and
   print pipeline are all scripted.

2. **This reaches people who are offline.** The Elder Newsletter exists
   because many Elders do not use the internet. This is not a digital-first
   product with a print afterthought — print is the primary delivery channel
   for the people who need it most.

3. **This is community-driven.** The content comes from community members,
   Knowledge Keepers, and language speakers. The platform invites
   participation — it does not broadcast at people.

**Then show the platform.** If you have access to a running Minoo instance,
show the events, teachings, and dictionary that feed the newsletter. Show
the submission form where community members contribute content.

**Close with the language collaboration.** Show the language review PDF
(`storage/newsletter/review/1-1-language-review.pdf`) to demonstrate that
the newsletter actively seeks Indigenous language contributions. The
Anishinaabemowin Corner is a collaboration space, not a placeholder.

### What Not to Emphasize

- Do not lead with the technology. Funders care about community impact first.
- Do not describe Minoo as a "CMS" or "platform" — say "community knowledge
  tool" or "community connection tool."
- Do not use "users," "content," or "stakeholders" — use "community members,"
  "teachings and stories," and "partners."
