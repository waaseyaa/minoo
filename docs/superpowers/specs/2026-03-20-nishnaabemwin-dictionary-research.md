# Nishnaabemwin Online Dictionary — Research Assessment

> **Issue:** waaseyaa/minoo#276
> **Phase:** 1 — Discovery & Assessment
> **Date:** 2026-03-20
> **Status:** Complete — **data-mining prohibited, permission required before any ingestion**

---

## 1. Resource Overview

| Field | Value |
|-------|-------|
| **URL** | https://dictionary.nishnaabemwin.atlas-ling.ca |
| **Name** | Nishnaabemwin Online Dictionary |
| **Subtitle** | Odawa & Eastern Ojibwe online dictionary |
| **Editors** | Mary Ann Naokwegijig-Corbiere and Rand Valentine |
| **Web development** | Marie-Odile Junker and Rand Valentine |
| **Programming** | Delasie Torkornoo |
| **Funding** | SSHRC (grant # 435-2014-1199) |
| **ISBN** | 9780770905835 |
| **Corpus size** | 12,000+ words |
| **Interface version** | 3.2018.07.04 |
| **Data version** | 2017.04 |
| **Developed by** | Algonquian Dictionaries Project (resources.atlas-ling.ca) |

## 2. Licensing & Terms

### Hard Constraint

The site footer states in English and French:

> **"Data-mining and scraping strictly prohibited."**
> *"L'extraction des données est strictement interdite."*

This is an explicit prohibition. No automated harvesting, scraping, or bulk data extraction is permitted without written permission from the editors.

### Implications

- The `indigenous-harvesters` framework **cannot be used** against this source without a data-sharing agreement
- Manual reference use (looking up individual words) appears permitted as normal dictionary use
- The data represents 20 years of documentary research with Elders and speakers — it is Indigenous intellectual property deserving of the highest respect
- **Action required:** Contact editors Dr. Mary Ann Naokwegijig-Corbiere and Prof. Rand Valentine to discuss potential data-sharing partnership

### Community Connection

Contributors to this dictionary include speakers from **Sagamok Anishnawbek** — Russell's own community. Notable Sagamok contributors: Isabel Abitong, Mary Assinewe, Dan Fox, Irene Makadebin, Grace Manitowabi, Alice Moses, Georgina Toulouse, Ida R. Toulouse, Madonna Toulouse, Martha Toulouse, Pauline Toulouse, Mary Ann Trudeau, Mary E. Wemigwans. This personal connection may facilitate a respectful dialogue about data sharing.

## 3. Site Structure & Technology

### Architecture

- **Frontend:** AngularJS single-page application (SPA)
- **Routing:** Hash-based (`#/help`, `#/browse`, `#/results`, `#/entry/{id}`)
- **Search modes:** English → Nishnaabemwin, Nishnaabemwin → English, Advanced Options
- **Browse tabs:** Entries (A-Z paginated), Keywords, Word components
- **Entry IDs:** Hash-based identifiers (e.g., `n71429751029n`, `n58fa1bd57d9c419b887023320a01dd02n`)

### No Public API Detected

- No visible REST API endpoints
- No JSON-LD, RDF, or structured data exports
- No CSV/data download functionality
- No robots.txt or sitemap.xml with data endpoints
- Admin panel referenced in entry links (`admin/words/wizard/{id}`) suggests a CMS backend, but not publicly accessible

## 4. Data Model

### Entry Fields (observed from browse and search results)

| Field | Example (`mkwa`) | Notes |
|-------|-------------------|-------|
| **Headword** | `mkwa` | Short form (consonant clusters) |
| **Full vowel form** | `makwa` | Long form with vowels spelled out |
| **Part of speech** | `na` (noun, animate) | Abbreviated codes: na, ni, vti, vta, av, pv, pt, expr, nad, nid, nadp, nidp |
| **Definition** | `bear` | English gloss, may have multiple numbered senses |
| **Inflections** | pl: `mkwak` (CL), `mkoog` (R); dim: `mkoons` | Plural forms, diminutives with dialect tags |
| **Dialect tags** | CL, R, SO, LH, Wp, MC, comm | Community/dialect abbreviations |
| **Example sentences** | `Aaniish maaba ngwiiwzensim enji-mwishit? MC` | Nishnaabemwin text with speaker/community attribution |
| **Example translations** | `Why is my poor little boy crying?` | English translation |
| **Entry ID** | `n71429751029n` | Internal hash identifier |

### Part of Speech Codes (observed)

| Code | Full form | Example |
|------|-----------|---------|
| `na` | noun, animate | mkwa (bear) |
| `ni` | noun, inanimate | aabaabka'gan (key) |
| `vti` | verb, transitive inanimate | aab'aan (untie sth.) |
| `vta` | verb, transitive animate | aab'amwaa (undo something for smb.) |
| `av` | adverb | aabda (continuously) |
| `pv` | preverb | a- (where/when/which) |
| `pt` | particle | aa (well; let's!) |
| `expr` | expression | aa gaawii! (no way!) |
| `nad` | noun, animate dependent | nda'iim (my possession) |
| `nid` | noun, inanimate dependent | nda'iim (my possession) |
| `nadp` | noun, animate dependent plural | nda'iimak (my belongings) |
| `nidp` | noun, inanimate dependent plural | nda'iiman (my belongings) |
| `verb` | verb (general) | -shi (poor, expressing pity) |

### Dialect/Community Abbreviations (observed)

| Code | Likely meaning |
|------|---------------|
| `comm` | Common across dialects |
| `CL` | Curve Lake |
| `SO` | Southern Ojibwe |
| `LH` | Lake Huron |
| `Wp` | Walpole (Walpole Island) |
| `MC` | M'Chigeeng |
| `R` | Regional variant |

### Browse Structure

- **Entries:** 786 pages, alphabetical A-Z, ~15 entries per page (≈12,000 entries)
- **Keywords:** English keyword index (not fully explored due to scraping prohibition)
- **Word components:** Morphological units (initials, medials, finals)

### Multi-sense Entries

Some entries have numbered senses:
- `a-` (pv): 1. where, 2. when, 3. which/that which
- `aab'aan` (vti): 1. untie sth., 2. undo sth. [with an instrument]
- `aa` (pt): 1. well; let's!, come on!, 2. oh!, well!

### Example Sentences

- Appear under entries with `example` heading
- Include Nishnaabemwin text in **bold** with dialect/speaker attribution suffix
- Followed by English translation
- "COMING SOON: Analyzed example sentences" — morphological analysis planned

## 5. Mapping to Minoo Entity Model

### DictionaryEntry Compatibility

| Nishnaabemwin field | Minoo DictionaryEntry field | Notes |
|---------------------|----------------------------|-------|
| Headword (short form) | `word` | Direct map — use short form as primary |
| Full vowel form | *new field needed* | `full_vowel_form` — important for learning |
| Part of speech code | `part_of_speech` | Map codes to full names |
| Definition | `definition` | May need multi-sense handling |
| Entry ID | `source_url` | Build URL from hash ID |
| — | `stem` | Not directly available; could derive from word components |
| — | `language_code` | `oj` (Ojibwe) |
| Inflections | `inflected_forms` | JSON: `{plural: [{form, dialect}], diminutive: [{form}]}` |
| — | `attribution_source` | "Nishnaabemwin Online Dictionary" |
| — | `attribution_url` | Entry URL |
| — | `consent_public` | Requires explicit permission |

### ExampleSentence Compatibility

| Nishnaabemwin field | Minoo ExampleSentence field | Notes |
|---------------------|----------------------------|-------|
| Nishnaabemwin text | `ojibwe_text` | Strip dialect attribution suffix |
| English translation | `english_text` | Direct map |
| Dialect tag | *new field or metadata* | `dialect_code` — maps to dialect_region entity |
| — | `dictionary_entry_id` | Link to parent entry |
| — | `speaker_id` | Attribution unclear — may be editor-created |

### WordPart Compatibility

The "Word components" browse tab maps directly to Minoo's WordPart entity (initial/medial/final morphological roles). Not fully explored due to scraping prohibition.

### New Fields Recommended

If a data-sharing agreement is reached:
1. `full_vowel_form` on DictionaryEntry — pedagogically important
2. `dialect_tags` on DictionaryEntry — array of community codes where word is used
3. `sense_number` support — for multi-sense entries

## 6. Recommended Next Steps

### Immediate (no data access needed)

1. **Draft outreach letter** to Dr. Mary Ann Naokwegijig-Corbiere and Prof. Rand Valentine
   - Explain Minoo's purpose (Indigenous community platform from Sagamok)
   - Describe how data would be used (display with full attribution, never re-exported)
   - Propose terms: read-only display, attribution on every entry, opt-out capability
   - Emphasize `consent_public` governance already built into Minoo's entity model
   - Mention Sagamok community connection

2. **Research the Algonquian Dictionaries Project** at resources.atlas-ling.ca
   - May have a standard data-sharing process for Indigenous language projects
   - Other dictionaries in the network may also be available

### If Permission Granted

3. **Request a data export** (CSV/JSON) rather than scraping
   - The editors likely have database access and can provide a clean export
   - This is more respectful and produces higher-quality data

4. **Build a manual import harvester** in `indigenous-harvesters`
   - Reads from a local file (provided by editors), not from the website
   - Maps to Minoo's DictionaryEntry + ExampleSentence entities
   - Sets `consent_public` based on agreement terms
   - Includes full attribution metadata

5. **Add `full_vowel_form` field** to DictionaryEntry entity
   - Migration + entity update in Minoo
   - Update dictionary-entry-card template to display both forms

### Alternative M1.2 Target

If the outreach takes time, **pivot M1.2 to OPD** (Ojibwe People's Dictionary at ojibwe.lib.umn.edu) which was the original Phase 1 plan target and has clearer open-access terms. Build the Nishnaabemwin harvester as M1.3 or later once a data-sharing agreement is in place.

## 7. Summary

The Nishnaabemwin Online Dictionary is an exceptional resource — 12,000+ words, 20 years of documentary research, Odawa and Eastern Ojibwe coverage including Sagamok speakers. Its data model maps well to Minoo's existing entity structure. However, **automated harvesting is explicitly prohibited**. The path forward is a respectful outreach to the editors, leveraging the Sagamok community connection, to establish a data-sharing partnership that honors Indigenous data sovereignty.
