# Indigenous AI Alignment & Data Sovereignty — Design Spec

**Date:** 2026-03-11
**Milestone:** v0.14 — Quality & Polish

## Overview

Codify Minoo's alignment with the Indigenous Protocol and AI Working Group's principles, add a public data sovereignty statement page, and prepare outreach to the group as a potential community partner.

This is a documentation and content initiative — no codebase behavior changes, no AI features, no formal license adoption.

## Background

The [Indigenous Protocol and AI Working Group](https://www.indigenous-ai.net/) was founded in 2019 by Jason Edward Lewis (Cherokee/Hawaiian/Samoan, Concordia University) and co-founders. Their 205-page position paper (2020) articulates principles for Indigenous-led AI development: relationality, reciprocity, locality, data sovereignty, AI as kin not tool, abundance over scarcity.

The work has evolved into [Abundant Intelligences](https://abundant-intelligences.net/) (2023–2029), a $22M+ funded six-year research program with 48 collaborators at 13 universities and 8 community organizations.

A notable output is the [Kaitiakitanga License](https://github.com/TeHikuMedia/Kaitiakitanga-License) by Te Hiku Media — a data sovereignty license treating Maori language data as collectively owned taonga (treasure).

Minoo's existing practices — Living Knowledge, Visible Strength, Rooted Connection, Reciprocal Care — already align with these principles. This work makes that alignment explicit and public.

## Deliverables

### 1. Indigenous AI Alignment Document

**File:** `docs/indigenous-ai-alignment.md`

Internal-facing document (committed to repo, not a public page) with four sections:

**Section A — Development Practice**
How Minoo is built with AI as collaborator under Indigenous direction. The developer leads, the AI assists, community values govern every decision. Concrete examples:
- Content tone guide enforces cultural respect
- Anishinaabemowin used naturally, not decoratively
- Platform is "built by and for" communities
- AI (Claude Code) is a tool that serves the builder's vision — not the reverse

**Section B — Principle Mapping**
Clear mapping between position paper principles and Minoo's existing practices:

| Indigenous AI Principle | Minoo Practice |
|---|---|
| Relationality | Knowledge is relational — Teachings connect to Elders, communities, language speakers |
| Reciprocity | Reciprocal Care pillar, Elder Support Program, volunteer model |
| Locality | Location-aware design, rooted in northern Ontario First Nations geography |
| Indigenous Data Sovereignty | Community data stays community-controlled (formal governance frameworks needed) |
| AI as Kin, not Tool | Development model where AI serves Indigenous vision rather than extracting from it |

**Section C — What Minoo Does Not Do**
Explicit developer commitments, stated now even before formal governance:
- No data mining
- No selling community data
- No training AI models on community knowledge
- No surveillance
- No third-party data sharing without community consent

**Section D — Where Governance Must Lead**
Honestly names what the developer cannot decide alone:
- Who owns the data
- What can be shared publicly vs. held privately
- How Teachings and language data are governed
- What a community data agreement looks like
- Whether an Anishinaabe-specific data sovereignty framework exists or should be developed

Frames this as work that needs band councils and community leadership.

### 2. Data Sovereignty Statement Page

**Template:** `templates/data-sovereignty.html.twig` (extends `base.html.twig`)
**URL:** `/data-sovereignty` (served by framework path-based template routing)

Written in Minoo's content tone — warm, direct, first-person plural. Follows SHOW + TELL + INVITE pattern.

**Content structure:**

1. **Opening** — "Your data belongs to your community. Not to us, not to any corporation, not to any algorithm."
2. **What we hold and why** — Plain-language explanation of what Minoo stores (community profiles, Elder support requests, Teachings, language entries, events) and why each exists to serve the community
3. **What we never do** — Hard commitments: no selling, no mining, no third-party sharing, no AI training on community data, no surveillance
4. **Who decides** — Data governance belongs to communities and band councils, not the platform developer. This page is a starting point, not the final word
5. **The Kaitiakitanga principle** — Reference to Te Hiku Media's work and the idea that community knowledge is collectively held treasure. Named as inspiration, not adopted verbatim (it's Maori-specific)
6. **Questions or concerns** — How community leaders can reach out about data governance

### 3. Footer Navigation Link

**File:** `templates/base.html.twig`

Add "Data Sovereignty" link to the footer legal navigation, alongside existing Privacy, Terms, and Accessibility links.

### 4. CSS (if needed)

**File:** `public/css/minoo.css`

Only if the data sovereignty page requires component styling not already covered by existing patterns. The legal pages already have styling that may be reusable.

### 5. Draft Outreach Email

Not a deliverable for the codebase — included here for review and personalization.

**To:** Jason Edward Lewis (Concordia University / Abundant Intelligences)
**Subject:** Community platform builder in northern Ontario — your position paper resonated

**Draft:**

> Hi Jason,
>
> I'm a First Nations developer in northern Ontario. I built Minoo (minoo.live), a community platform that connects Indigenous communities, Knowledge Keepers, and the next generation — a living map of people, Teachings, language, and events across the north.
>
> I came across your position paper and the Indigenous Protocol and AI Working Group's work, and I recognized the principles we've been building by instinct — relationality, locality, reciprocity, data sovereignty. Minoo holds community knowledge (Anishinaabemowin language entries, Elder information, Teachings) and I've been developing it using AI as a collaborator under Indigenous direction, not the other way around.
>
> We're at the point where we need community governance frameworks for the data Minoo holds. I've written up how our practices align with your position paper's principles, and I'd welcome any guidance or conversation about how your work could inform ours.
>
> I'm interested in participating as a community partner in the broader Indigenous AI conversation. What we're building is small but real — and I think practitioner perspectives from people building community tools could contribute something useful.
>
> Miigwech for the work you and the group are doing.

## Scope Boundaries

**Not included:**
- No formal license adoption (Kaitiakitanga is Maori-specific; any Anishinaabe framework must come from community)
- No AI features (language learning, knowledge recommendation — future work)
- No community data agreement template (governance work, not developer work)
- No changes to Minoo's codebase behavior (access control, data handling unchanged)
- No modifications to existing legal pages (privacy, terms stay as-is)

## Implementation Notes

- Data sovereignty page follows the same pattern as existing path-based templates (legal, safety, how-it-works)
- Content tone must follow `docs/content-tone-guide.md` — first-person plural, warm, direct, accessible
- The alignment document is a repo document, not a public page — it evolves as the project does
- The outreach email is a draft for the developer to personalize and send manually
