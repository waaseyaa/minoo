# Issue 1-1 newsletter: TOC (p3) + Back Cover (p16) drafts

Date: 2026-04-13
Context: Editorial review of `storage/newsletter/regional/1-1.pdf` found the booklet at 15 pages (must be a multiple of 4 for saddle-stitch) with a missing back cover and no Table of Contents. These drafts bring it to 16 pages and fix the reader-spread sequence.

## New reader-spread sequence (16pp)

| pp | Left (verso) | Right (recto) | Notes |
|---|---|---|---|
| 1 | — | Cover | Solo. Add hero image + 3 teasers. |
| 2–3 | Keeper's Note | **In This Issue (TOC) — NEW** | Editor's letter facing contents. Industry standard. |
| 4–5 | Community News | News Briefs | |
| 6–7 | Events Calendar | Teachings (Ziigwan) | |
| 8–9 | Language Corner (Ziigwan) | Our Territory | Language↔teaching mirror preserved across gutter-adjacent pages. |
| 10–11 | Puzzles | Clan Horoscopes | Lean-back pairing. |
| 12–13 | Elder Spotlight (Grace) — spread | | True feature spread, portrait bleeds across. |
| 14–15 | Reader Mail / Jokes | Events/map overflow or ad | |
| 16 | — | **Back Cover — NEW** | Colophon + mailing panel + next issue teaser. |

> The TOC and back cover below are the two artifacts you asked me to draft. Reordering the existing pages is a separate edit.

---

## 1. PAGE 3 — "In This Issue" (Table of Contents)

### Editorial approach

- Title: **"In This Issue"** (not "Contents" — warmer, matches the voice in `docs/content-tone-guide.md`).
- Anishinaabemowin parallel: small italic **"Biindigen — come inside"** under the title.
- 8–10 entries max. Each entry: a short, evocative label + the page number. No section headings as-is; rewrite in the editor's voice ("Grace Manitowabi on Minigoziwin" beats "Elder Spotlight").
- Right rail: a **"From the Keeper"** pullquote from Russell's p2 letter, tying the two facing pages together visually.
- Bottom strip: **"In the next issue"** teaser line + riddle callout.

### Drop-in Twig (replace current PAGE 2 comment block's sibling, insert between p2 and current p3)

```twig
{# ============================================================
   PAGE 3 — In This Issue (TOC + welcome)
   ============================================================ #}
<div class="page toc-page">
    <h2>In This Issue</h2>
    <div class="section-intro"><em>Biindigen</em> &mdash; come inside.</div>

    <div class="toc-layout">
        <div class="toc-main">
            {% if items_by_section['toc'] is defined and items_by_section['toc']|length %}
                {% for item in items_by_section['toc'] %}
                    {% if item.get('included') and item.get('kind') == 'toc' %}
                        {% set toc = item.get('structured') %}
                        <table class="toc-table">
                            {% for entry in (toc.entries ?? []) %}
                            <tr>
                                <td class="toc-label">
                                    <span class="toc-kicker">{{ entry.kicker ?? '' }}</span>
                                    <span class="toc-title-line">{{ entry.label }}</span>
                                </td>
                                <td class="toc-dots" aria-hidden="true"></td>
                                <td class="toc-page">{{ entry.page }}</td>
                            </tr>
                            {% endfor %}
                        </table>
                    {% endif %}
                {% endfor %}
            {% else %}
                {# Fallback for Issue 1-1 if no structured TOC has been authored #}
                <table class="toc-table">
                    <tr><td class="toc-label"><span class="toc-kicker">News</span><span class="toc-title-line">Treaty Chiefs say no to herbicide spraying</span></td><td class="toc-dots"></td><td class="toc-page">4</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">News Briefs</span><span class="toc-title-line">Anishinaabemowin gathering &middot; child welfare &middot; more</span></td><td class="toc-dots"></td><td class="toc-page">5</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">Calendar</span><span class="toc-title-line">May events across the North Shore</span></td><td class="toc-dots"></td><td class="toc-page">6</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">Teachings</span><span class="toc-title-line">Ziigwan: the time of new growth</span></td><td class="toc-dots"></td><td class="toc-page">7</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">Language Corner</span><span class="toc-title-line">A word to carry: <em>Ziigwan</em></span></td><td class="toc-dots"></td><td class="toc-page">8</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">Our Territory</span><span class="toc-title-line">The twenty-one First Nations of Robinson Huron</span></td><td class="toc-dots"></td><td class="toc-page">9</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">Puzzles</span><span class="toc-title-line">Word search &middot; riddle of the month</span></td><td class="toc-dots"></td><td class="toc-page">10</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">Horoscopes</span><span class="toc-title-line">Clan guidance for the season</span></td><td class="toc-dots"></td><td class="toc-page">11</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">Elder Spotlight</span><span class="toc-title-line">Grace Manitowabi on Minigoziwin</span></td><td class="toc-dots"></td><td class="toc-page">12</td></tr>
                    <tr><td class="toc-label"><span class="toc-kicker">Reader Mail</span><span class="toc-title-line">What we hope you send for Issue 2</span></td><td class="toc-dots"></td><td class="toc-page">14</td></tr>
                </table>
            {% endif %}
        </div>

        <aside class="toc-rail">
            <div class="pullquote toc-pullquote">
                <span class="pullquote-mark">&ldquo;</span>
                <p>I built this newsletter because I wanted Anishinaabe news to arrive
                at the kitchen table, not the algorithm.</p>
                <div class="pullquote-attrib">&mdash; Russell, p.&thinsp;2</div>
            </div>

            <div class="next-issue-teaser">
                <div class="next-issue-kicker">In the next issue</div>
                <ul>
                    <li>Niibin (summer) teachings</li>
                    <li>Powwow trail &mdash; 2026 schedule</li>
                    <li>Riddle of the Month winner</li>
                </ul>
                <div class="next-issue-cta">
                    Send stories, events and photos to<br>
                    <strong>hello@minoo.live</strong> or the address on the back page.
                </div>
            </div>
        </aside>
    </div>
</div>
```

### CSS additions (append to existing `<style>` block)

```css
.page.toc-page h2 { margin-bottom: 0.25rem; }

.toc-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-top: 1rem;
}

.toc-table { width: 100%; border-collapse: collapse; }
.toc-table tr { break-inside: avoid; }
.toc-table td { vertical-align: baseline; padding: 0.45rem 0; border-bottom: 1px dotted #d8d3c4; }
.toc-kicker {
    display: block;
    font-family: var(--font-sans, system-ui);
    font-size: 0.72rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #8a6b3a;
    margin-bottom: 0.1rem;
}
.toc-title-line { font-size: 1.05rem; line-height: 1.3; }
.toc-dots { width: 100%; border-bottom: 2px dotted #c8bfa8; position: relative; top: -0.35em; padding: 0 0.5rem; }
.toc-page { font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; min-width: 2.5ch; }

.toc-rail { border-left: 1px solid #e7e1cf; padding-left: 1.25rem; }
.toc-pullquote {
    font-family: var(--font-serif, Georgia, serif);
    font-style: italic;
    font-size: 1.05rem;
    line-height: 1.45;
    color: #3a352b;
    margin-bottom: 1.5rem;
}
.toc-pullquote .pullquote-mark { font-size: 3rem; line-height: 0; color: #b68c3c; display: block; margin-bottom: 0.5rem; }
.toc-pullquote .pullquote-attrib { font-style: normal; font-size: 0.8rem; color: #6a6458; margin-top: 0.5rem; }

.next-issue-teaser { font-size: 0.9rem; }
.next-issue-kicker {
    font-family: var(--font-sans, system-ui);
    font-size: 0.72rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #6a4a1a;
    border-top: 2px solid #6a4a1a;
    padding-top: 0.4rem;
    margin-bottom: 0.5rem;
}
.next-issue-teaser ul { margin: 0 0 0.9rem 1rem; padding: 0; }
.next-issue-teaser li { margin-bottom: 0.25rem; }
.next-issue-cta { font-size: 0.85rem; line-height: 1.35; color: #3a352b; }
```

---

## 2. PAGE 16 — Back Cover (colophon + mailing panel + teaser)

### Editorial approach

The back cover of a saddle-stitch newsletter does four jobs at once:

1. **Colophon** — who made it, with what, under what terms (this is the `About This Newsletter` content, trimmed).
2. **Mailing panel** — address block, postage indicia. Canada Post Publications Mail requires this bottom-third zone for bulk mail; even if you hand-distribute Issue 1, designing it in now saves a later redesign.
3. **Teaser** — one photo/hook for the next issue so the back cover is a reason to keep the paper on the table.
4. **Partners/funders** — logo strip at the base.

### Drop-in Twig (replaces existing PAGE 16 block at lines 1425–1467)

```twig
{# ============================================================
   PAGE 16 — Back Cover
   ============================================================ #}
<div class="page back-cover">

    <div class="back-cover-teaser">
        <div class="teaser-kicker">Issue 2 &middot; Niibin / Summer 2026</div>
        <h3 class="teaser-head">The powwow trail, a summer of markets, and what Grace planted.</h3>
        <p class="teaser-sub">Send us your photos, stories and events by June&nbsp;15.</p>
    </div>

    <div class="back-cover-grid">
        <section class="colophon-block">
            <h4>About this newsletter</h4>
            {% if items_by_section['back_page'] is defined %}
                {% for item in items_by_section['back_page'] %}
                    {% if item.get('included') %}
                        {% set k = item.get('kind') %}
                        {% if k == 'back_page_box' %}
                            <div class="back-page-box">
                                {% if item.get('inline_title') %}<h5>{{ item.get('inline_title') }}</h5>{% endif %}
                                {{ item.get('inline_body')|raw }}
                            </div>
                        {% elseif k == 'colophon' %}
                            <div class="colophon">{{ item.get('inline_body')|raw }}</div>
                        {% elseif k != 'partners' %}
                            <div class="prose">{{ item.get('inline_body')|raw }}</div>
                        {% endif %}
                    {% endif %}
                {% endfor %}
            {% else %}
                <p>This newsletter is generated by <strong>Minoo Live</strong>, a community
                platform built by Anishinaabe people for Indigenous communities.
                Stories and events are contributed at <strong>minoo.live</strong> and
                curated by the Keeper.</p>
                <p>Printed in Espanola, Ontario. Free to our communities.</p>
            {% endif %}

            <dl class="colophon-meta">
                <dt>Editor &amp; Keeper</dt><dd>Russell Jones, Sagamok Anishnawbek</dd>
                <dt>Published</dt><dd>{{ edition.get('publish_date') }}</dd>
                <dt>Issue</dt><dd>Vol. {{ edition.get('volume') }} &middot; No. {{ edition.get('issue_number') }}</dd>
                <dt>Contact</dt><dd>hello@minoo.live &middot; minoo.live</dd>
            </dl>
        </section>

        <section class="mailing-panel" aria-label="Mailing panel">
            <div class="indicia">
                <div class="indicia-box">
                    <div class="indicia-line">Canada Post</div>
                    <div class="indicia-line">Publications Mail</div>
                    <div class="indicia-line">Agreement No.</div>
                    <div class="indicia-line indicia-number">[pending]</div>
                </div>
                <div class="return-address">
                    <strong>Return undeliverable to:</strong><br>
                    Minoo Live<br>
                    [Street address]<br>
                    Sagamok, ON&nbsp; [P.C.]
                </div>
            </div>
            <div class="address-window" aria-hidden="true">
                {# Blank zone sized to fit a #10 window or wafer-seal label. #}
            </div>
        </section>
    </div>

    {% if items_by_section['back_page'] is defined %}
        {% for item in items_by_section['back_page'] %}
            {% if item.get('included') and item.get('kind') == 'partners' %}
                {% set ps = item.get('structured') %}
                <div class="partners back-cover-partners">
                    <div class="partners-label">With thanks to</div>
                    <div class="partners-logos">
                        {% for p in (ps.partners ?? []) %}
                            <div class="partner-logo">
                                {% if p.svg %}{{ p.svg|raw }}{% endif %}
                                <div class="partner-name">
                                    {% if p.url %}<a href="{{ p.url }}">{{ p.name }}</a>{% else %}{{ p.name }}{% endif %}
                                </div>
                            </div>
                        {% endfor %}
                    </div>
                </div>
            {% endif %}
        {% endfor %}
    {% endif %}

    <div class="back-cover-footer">
        <span class="wordmark">Minoo</span>
        <span class="footer-tag">For Communities &middot; <em>minoo.live</em></span>
    </div>
</div>
```

### CSS additions

```css
.page.back-cover {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.back-cover-teaser {
    border-top: 4px solid #6a4a1a;
    border-bottom: 1px solid #d8d3c4;
    padding: 0.75rem 0 1rem;
}
.teaser-kicker {
    font-size: 0.75rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #6a4a1a;
}
.teaser-head {
    font-family: var(--font-serif, Georgia, serif);
    font-size: 1.55rem;
    line-height: 1.2;
    margin: 0.4rem 0 0.4rem;
    color: #2a2620;
}
.teaser-sub { font-style: italic; color: #4a4437; margin: 0; }

.back-cover-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 1.5rem;
    flex: 1;
}
.colophon-block h4 {
    font-size: 0.85rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #6a4a1a;
    margin: 0 0 0.6rem;
}
.colophon-block p { font-size: 0.92rem; line-height: 1.45; margin: 0 0 0.75rem; }

.colophon-meta {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 0.25rem 0.9rem;
    margin-top: 0.9rem;
    font-size: 0.82rem;
}
.colophon-meta dt { color: #8a6b3a; font-weight: 600; }
.colophon-meta dd { margin: 0; color: #2a2620; }

.mailing-panel {
    border: 1px dashed #b8b09a; /* visible in proof, set to transparent for print-ready */
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-height: 3.25in; /* Canada Post admail zone */
    background: #fbf8ef;
}
.indicia { display: flex; justify-content: space-between; gap: 1rem; }
.indicia-box {
    border: 1.5px solid #2a2620;
    padding: 0.4rem 0.55rem;
    font-size: 0.7rem;
    line-height: 1.25;
    text-align: center;
    min-width: 1.6in;
    font-family: var(--font-sans, system-ui);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.indicia-number { font-weight: 700; letter-spacing: 0.1em; }
.return-address { font-size: 0.72rem; line-height: 1.3; color: #3a352b; }
.address-window { flex: 1; min-height: 1.75in; } /* intentionally empty */

.back-cover-partners { margin-top: 0.5rem; }

.back-cover-footer {
    border-top: 1px solid #d8d3c4;
    padding-top: 0.6rem;
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}
.wordmark {
    font-family: var(--font-serif, Georgia, serif);
    font-size: 1.4rem;
    font-weight: 700;
    color: #2a2620;
    letter-spacing: 0.02em;
}
.footer-tag { font-size: 0.8rem; color: #6a6458; }
```

---

## What still needs doing (not in this draft)

- **Reorder existing pages** to match the spread table above (moves the existing Jokes page away from facing the Territory page; gives Elder Spotlight a true 12–13 spread).
- **Add page 1 hero artwork + teasers** — covers should never be text-only.
- **Merge Reader Mail + Jokes** onto one page (or merge Jokes into the Puzzles spread) to free up a content page — this is how you actually land on 16pp without padding.
- **Decide on Canada Post admail agreement number** before going to press; until then, leave the indicia placeholder as `[pending]`.
- **Store mailing-panel addresses as structured data** on the `back_page` section (new `kind: 'mailing_panel'` with `structured: { return_address, indicia_number }`) so the colophon/admail data isn't hard-coded per issue. Follow-up issue recommended.
