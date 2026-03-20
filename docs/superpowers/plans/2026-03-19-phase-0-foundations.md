# Phase 0: Foundations & Governance — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the shared taxonomy contract, extend NC's source registry, codify boundary governance, and add dialect region config entities — unblocking all Phase 1+ work.

**Architecture:** Four independent milestones that can execute in parallel. M0.1 creates `jonesrussell/indigenous-taxonomy` with canonical YAML schemas and a Python generator producing Go/PHP/Python packages. M0.2 extends NC's existing source-manager `type` field with `structured`/`api` values and metadata for non-crawled sources. M0.3 adds boundary rules to each repo's CLAUDE.md and drift checks. M0.4 adds a `dialect_region` config entity to Minoo following the existing ConfigEntityBase pattern.

**Tech Stack:** Python 3.12+ (taxonomy generator/tests), Go 1.24+ (NC source-manager), PHP 8.4+ (Minoo), YAML, JSON Schema, GitHub Actions

**Spec:** `docs/superpowers/specs/2026-03-19-minoo-platform-vision-design.md`

**Parallelization:** M0.1, M0.2, M0.3, and M0.4 have no cross-dependencies. Dispatch as parallel subagents.

---

## File Structure

### M0.1 — indigenous-taxonomy (new repo at `~/dev/indigenous-taxonomy`)

```
indigenous-taxonomy/
├── schema/
│   ├── categories.yaml
│   ├── regions.yaml
│   ├── dialect-codes.yaml
│   └── validation/
│       ├── categories.schema.json
│       ├── regions.schema.json
│       └── dialect-codes.schema.json
├── scripts/
│   └── generate.py
├── generated/
│   ├── go/taxonomy/
│   │   ├── go.mod
│   │   ├── categories.go
│   │   ├── regions.go
│   │   ├── dialects.go
│   │   └── version.go
│   ├── php/
│   │   ├── composer.json
│   │   └── src/
│   │       ├── Category.php
│   │       ├── Region.php
│   │       ├── DialectCode.php
│   │       └── TaxonomyVersion.php
│   └── python/
│       ├── pyproject.toml
│       └── indigenous_taxonomy/
│           ├── __init__.py
│           ├── categories.py
│           ├── regions.py
│           ├── dialects.py
│           └── version.py
├── tests/
│   ├── test_schema_valid.py
│   ├── test_slugs_unique.py
│   └── test_generated_consistent.py
├── Taskfile.yml
├── .github/workflows/
│   ├── validate.yml
│   └── release.yml
├── .gitignore
├── CLAUDE.md
├── CHANGELOG.md
└── README.md
```

### M0.2 — north-cloud source-manager changes

```
source-manager/
├── internal/models/source.go          # Modify: add SourceType constants, metadata fields
├── migrations/
│   ├── 017_add_structured_source_fields.up.sql    # Create
│   └── 017_add_structured_source_fields.down.sql  # Create
├── internal/handlers/source.go        # Modify: validate new type values, accept metadata
└── internal/handlers/source_test.go   # Modify: test structured source creation
```

### M0.3 — Boundary governance (all repos)

```
minoo/CLAUDE.md                        # Modify: add Boundary Rules section
north-cloud/CLAUDE.md                  # Modify: add Boundary Rules section
waaseyaa/CLAUDE.md                     # Modify: add Boundary Rules section
minoo/bin/check-milestones             # Modify: add boundary drift check
```

### M0.4 — Minoo dialect region entity

```
minoo/
├── src/Entity/DialectRegion.php                    # Create
├── src/Provider/DialectRegionServiceProvider.php   # Create
├── src/Seed/ConfigSeeder.php                       # Modify: add dialectRegions()
├── src/Access/LanguageAccessPolicy.php             # Modify: add dialect_region
├── tests/Minoo/Unit/Entity/DialectRegionTest.php   # Create
├── tests/Minoo/Unit/Seed/ConfigSeederTest.php      # Modify: add dialect region test
└── tests/Minoo/Integration/BootTest.php            # Modify: add to type list
```

---

## M0.1 — Shared Taxonomy Contract

### Task 1: Repo scaffold and canonical YAML schemas

**Files:**
- Create: `~/dev/indigenous-taxonomy/` (entire repo scaffold)
- Create: `schema/categories.yaml`
- Create: `schema/regions.yaml`
- Create: `schema/dialect-codes.yaml`
- Create: `.gitignore`
- Create: `CLAUDE.md`
- Create: `CHANGELOG.md`

- [ ] **Step 1: Create repo and initialize**

```bash
cd ~/dev
mkdir indigenous-taxonomy && cd indigenous-taxonomy
git init
mkdir -p schema/validation scripts generated/{go/taxonomy,php/src,python/indigenous_taxonomy} tests .github/workflows
```

- [ ] **Step 2: Create .gitignore**

```gitignore
__pycache__/
*.pyc
.venv/
dist/
*.egg-info/
.pytest_cache/
```

- [ ] **Step 3: Write categories.yaml**

Content is defined in the spec (Section 4.3). Write the 10 categories (culture, language, land-rights, environment, sovereignty, education, health, justice, history, community) with slug, name, description, and nc_routing fields.

```yaml
version: 1

categories:
  - slug: culture
    name: Culture
    description: Cultural practices, ceremonies, traditions, art, music
    nc_routing: true

  - slug: language
    name: Language
    description: Language revitalization, dictionaries, dialects, immersion programs
    nc_routing: true

  - slug: land-rights
    name: Land Rights
    description: Treaty rights, land claims, territorial sovereignty
    nc_routing: true

  - slug: environment
    name: Environment
    description: Environmental stewardship, traditional ecological knowledge, climate
    nc_routing: true

  - slug: sovereignty
    name: Sovereignty
    description: Self-governance, nation-to-nation relations, self-determination
    nc_routing: true

  - slug: education
    name: Education
    description: Indigenous education, curriculum, schools, knowledge transmission
    nc_routing: true

  - slug: health
    name: Health
    description: Indigenous health, traditional medicine, wellness, mental health
    nc_routing: true

  - slug: justice
    name: Justice
    description: Legal rights, MMIWG, policing, incarceration, restorative justice
    nc_routing: true

  - slug: history
    name: History
    description: Historical events, residential schools, treaties, reconciliation
    nc_routing: true

  - slug: community
    name: Community
    description: Community news, events, leadership, services, economic development
    nc_routing: true
```

- [ ] **Step 4: Write regions.yaml**

Hierarchical regions with colon-delimited slugs. Full content as specified in design spec Section 4.3 — Canada with provinces, sub-regions, and northern territories.

```yaml
version: 1

regions:
  - slug: canada
    name: Canada
    children:
      - slug: canada:british-columbia
        name: British Columbia
      - slug: canada:alberta
        name: Alberta
      - slug: canada:saskatchewan
        name: Saskatchewan
      - slug: canada:manitoba
        name: Manitoba
        children:
          - slug: canada:manitoba:southern
            name: Southern Manitoba
      - slug: canada:ontario
        name: Ontario
        children:
          - slug: canada:ontario:northern
            name: Northern Ontario
          - slug: canada:ontario:north-shore-huron
            name: North Shore of Lake Huron
          - slug: canada:ontario:southern
            name: Southern Ontario
      - slug: canada:quebec
        name: Quebec
      - slug: canada:atlantic
        name: Atlantic Canada
      - slug: canada:north
        name: Northern Canada
        children:
          - slug: canada:north:yukon
            name: Yukon
          - slug: canada:north:nwt
            name: Northwest Territories
          - slug: canada:north:nunavut
            name: Nunavut
```

- [ ] **Step 5: Write dialect-codes.yaml**

Language families with dialect codes, ISO 639-3 references, and region mappings. Full content as specified in design spec Section 4.3 — Algonquian (7 dialects), Eskimo-Aleut (2), Iroquoian (1).

```yaml
version: 1

language_families:
  - slug: algonquian
    name: Algonquian
    dialects:
      - code: oji-east
        name: Nishnaabemwin
        display_name: Eastern Ojibwe
        iso_639_3: ojg
        regions:
          - canada:ontario:north-shore-huron
          - canada:ontario:southern
      - code: oji-northwest
        name: Anishinaabemowin
        display_name: Northwestern Ojibwe
        iso_639_3: ojb
        regions:
          - canada:ontario:northern
      - code: oji-plains
        name: "Nakaw\u0113mowin"
        display_name: Saulteaux / Plains Ojibwe
        iso_639_3: ojs
        regions:
          - canada:manitoba:southern
          - canada:saskatchewan
      - code: oji-ottawa
        name: Odaawaa
        display_name: Ottawa / Odawa
        iso_639_3: otw
        regions:
          - canada:ontario:southern
      - code: cree-plains
        name: "n\u0113hiyaw\u0113win"
        display_name: Plains Cree
        iso_639_3: crk
        regions:
          - canada:saskatchewan
          - canada:alberta
      - code: cree-swampy
        name: "Inin\u012Bmowin"
        display_name: Swampy Cree
        iso_639_3: csw
        regions:
          - canada:manitoba
          - canada:ontario:northern
      - code: innu
        name: Innu-aimun
        display_name: Innu
        iso_639_3: moe
        regions:
          - canada:quebec
          - canada:atlantic
  - slug: eskimo-aleut
    name: Eskimo-Aleut
    dialects:
      - code: inuktitut
        name: "\u1403\u14C4\u1483\u144E\u1450\u1466"
        display_name: Inuktitut
        iso_639_3: iku
        regions:
          - canada:north:nunavut
          - canada:quebec
      - code: inuvialuktun
        name: Inuvialuktun
        display_name: Inuvialuktun
        iso_639_3: ikt
        regions:
          - canada:north:nwt
  - slug: iroquoian
    name: Iroquoian
    dialects:
      - code: mohawk
        name: "Kanien\u2019k\u00E9ha"
        display_name: Mohawk
        iso_639_3: moh
        regions:
          - canada:ontario:southern
          - canada:quebec
```

- [ ] **Step 6: Write CLAUDE.md**

```markdown
# Indigenous Taxonomy

Canonical taxonomy contract for Indigenous content classification. Consumed by North Cloud (Go), Minoo (PHP), and indigenous-harvesters (Python).

## Commands

task generate       # Regenerate all packages from YAML
task validate       # Validate YAML against JSON Schema
task test           # Run all tests
task release        # Tag + push (CI publishes packages)

## Architecture

- schema/*.yaml — canonical source of truth (human-edited)
- schema/validation/*.schema.json — JSON Schema for YAML validation
- scripts/generate.py — reads YAML, writes Go/PHP/Python packages
- generated/ — output of generate.py (committed, not hand-edited)

## Rules

- NEVER hand-edit files in generated/ — always edit YAML then run task generate
- Adding a category/region/dialect = minor version bump
- Renaming/removing a slug = major version bump with deprecation window
- All slugs must be unique within their file
- Region slugs use colon-delimited hierarchy (canada:ontario:northern)
```

- [ ] **Step 7: Write CHANGELOG.md**

```markdown
# Changelog

## [1.0.0] — 2026-03-19

### Added
- Initial taxonomy: 10 categories, 13 Canadian regions, 10 dialect codes
- JSON Schema validation for all YAML files
- Python generator producing Go, PHP, and Python packages
- CI workflows for validation and release
```

- [ ] **Step 8: Commit scaffold**

```bash
git add -A
git commit -m "chore: scaffold indigenous-taxonomy repo with canonical YAML schemas"
```

---

### Task 2: JSON Schema validation files

**Files:**
- Create: `schema/validation/categories.schema.json`
- Create: `schema/validation/regions.schema.json`
- Create: `schema/validation/dialect-codes.schema.json`

- [ ] **Step 1: Write categories schema**

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "required": ["version", "categories"],
  "additionalProperties": false,
  "properties": {
    "version": { "type": "integer", "minimum": 1 },
    "categories": {
      "type": "array",
      "minItems": 1,
      "items": {
        "type": "object",
        "required": ["slug", "name", "description", "nc_routing"],
        "additionalProperties": false,
        "properties": {
          "slug": { "type": "string", "pattern": "^[a-z][a-z0-9-]*$" },
          "name": { "type": "string", "minLength": 1 },
          "description": { "type": "string", "minLength": 1 },
          "nc_routing": { "type": "boolean" },
          "deprecated": { "type": "boolean" },
          "replaced_by": { "type": "string" }
        }
      }
    }
  }
}
```

- [ ] **Step 2: Write regions schema**

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "required": ["version", "regions"],
  "additionalProperties": false,
  "properties": {
    "version": { "type": "integer", "minimum": 1 },
    "regions": {
      "type": "array",
      "minItems": 1,
      "items": { "$ref": "#/$defs/region" }
    }
  },
  "$defs": {
    "region": {
      "type": "object",
      "required": ["slug", "name"],
      "additionalProperties": false,
      "properties": {
        "slug": { "type": "string", "pattern": "^[a-z][a-z0-9:-]*$" },
        "name": { "type": "string", "minLength": 1 },
        "children": {
          "type": "array",
          "items": { "$ref": "#/$defs/region" }
        }
      }
    }
  }
}
```

- [ ] **Step 3: Write dialect-codes schema**

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "required": ["version", "language_families"],
  "additionalProperties": false,
  "properties": {
    "version": { "type": "integer", "minimum": 1 },
    "language_families": {
      "type": "array",
      "minItems": 1,
      "items": {
        "type": "object",
        "required": ["slug", "name", "dialects"],
        "additionalProperties": false,
        "properties": {
          "slug": { "type": "string", "pattern": "^[a-z][a-z0-9-]*$" },
          "name": { "type": "string", "minLength": 1 },
          "dialects": {
            "type": "array",
            "minItems": 1,
            "items": {
              "type": "object",
              "required": ["code", "name", "display_name", "iso_639_3", "regions"],
              "additionalProperties": false,
              "properties": {
                "code": { "type": "string", "pattern": "^[a-z][a-z0-9-]*$" },
                "name": { "type": "string", "minLength": 1 },
                "display_name": { "type": "string", "minLength": 1 },
                "iso_639_3": { "type": "string", "pattern": "^[a-z]{3}$" },
                "regions": {
                  "type": "array",
                  "minItems": 1,
                  "items": { "type": "string" }
                }
              }
            }
          }
        }
      }
    }
  }
}
```

- [ ] **Step 4: Commit**

```bash
git add schema/validation/
git commit -m "chore: add JSON Schema validation for taxonomy YAML files"
```

---

### Task 3: Python generator script

**Files:**
- Create: `scripts/generate.py`

The generator reads YAML files and produces type-safe code in Go, PHP, and Python.

- [ ] **Step 1: Write generate.py**

```python
#!/usr/bin/env python3
"""Generate Go, PHP, and Python packages from canonical YAML taxonomy files."""

import hashlib
import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent
SCHEMA_DIR = ROOT / "schema"
GEN_GO = ROOT / "generated" / "go" / "taxonomy"
GEN_PHP = ROOT / "generated" / "php" / "src"
GEN_PY = ROOT / "generated" / "python" / "indigenous_taxonomy"

HEADER_GO = "// Code generated by scripts/generate.py. DO NOT EDIT.\n"
HEADER_PHP = "<?php\n\n// Code generated by scripts/generate.py. DO NOT EDIT.\n\ndeclare(strict_types=1);\n\nnamespace IndigenousTaxonomy;\n"
HEADER_PY = '"""Code generated by scripts/generate.py. DO NOT EDIT."""\n'


def load_yaml(name: str) -> dict:
    with open(SCHEMA_DIR / f"{name}.yaml") as f:
        return yaml.safe_load(f)


def schema_hash() -> str:
    h = hashlib.sha256()
    for name in sorted(SCHEMA_DIR.glob("*.yaml")):
        h.update(name.read_bytes())
    return h.hexdigest()[:16]


def to_pascal(slug: str) -> str:
    return "".join(word.capitalize() for word in re.split(r"[-:]", slug))


def to_upper_snake(slug: str) -> str:
    return re.sub(r"[-:]", "_", slug).upper()


def to_go_const(slug: str) -> str:
    return "Category" + to_pascal(slug)


# --- Go generation ---


def gen_go_categories(cats: list[dict]) -> str:
    lines = [HEADER_GO, "package taxonomy\n", "type Category string\n", "const ("]
    for c in cats:
        lines.append(f'\t{to_go_const(c["slug"])} Category = "{c["slug"]}"')
    lines.append(")\n")
    lines.append("var AllCategories = []Category{")
    lines.append("\t" + ", ".join(to_go_const(c["slug"]) for c in cats) + ",")
    lines.append("}\n")
    lines.append("func IsValidCategory(s string) bool {")
    lines.append("\tfor _, c := range AllCategories {")
    lines.append('\t\tif string(c) == s { return true }')
    lines.append("\t}")
    lines.append("\treturn false")
    lines.append("}\n")
    lines.append("func RoutableCategories() []Category {")
    lines.append("\treturn []Category{")
    for c in cats:
        if c.get("nc_routing"):
            lines.append(f"\t\t{to_go_const(c['slug'])},")
    lines.append("\t}")
    lines.append("}")
    return "\n".join(lines) + "\n"


def flatten_regions(regions: list[dict], out: list[dict] | None = None) -> list[dict]:
    if out is None:
        out = []
    for r in regions:
        out.append({"slug": r["slug"], "name": r["name"]})
        if "children" in r:
            flatten_regions(r["children"], out)
    return out


def gen_go_regions(regions: list[dict]) -> str:
    flat = flatten_regions(regions)
    lines = [HEADER_GO, "package taxonomy\n", "type Region string\n", "const ("]
    for r in flat:
        lines.append(f'\tRegion{to_pascal(r["slug"])} Region = "{r["slug"]}"')
    lines.append(")\n")
    lines.append("var AllRegions = []Region{")
    lines.append("\t" + ", ".join(f'Region{to_pascal(r["slug"])}' for r in flat) + ",")
    lines.append("}\n")
    lines.append("func IsValidRegion(s string) bool {")
    lines.append("\tfor _, r := range AllRegions {")
    lines.append('\t\tif string(r) == s { return true }')
    lines.append("\t}")
    lines.append("\treturn false")
    lines.append("}")
    return "\n".join(lines) + "\n"


def gen_go_dialects(families: list[dict]) -> str:
    lines = [HEADER_GO, "package taxonomy\n"]
    lines.append("type DialectCode struct {")
    lines.append("\tCode       string")
    lines.append("\tName       string")
    lines.append("\tDisplayName string")
    lines.append("\tISO639_3   string")
    lines.append("\tFamily     string")
    lines.append("\tRegions    []Region")
    lines.append("}\n")
    lines.append("var AllDialects = []DialectCode{")
    for fam in families:
        for d in fam["dialects"]:
            regions = ", ".join(f'"{r}"' for r in d["regions"])
            lines.append(f'\t{{Code: "{d["code"]}", Name: "{d["name"]}", DisplayName: "{d["display_name"]}", ISO639_3: "{d["iso_639_3"]}", Family: "{fam["slug"]}", Regions: []Region{{{regions}}}}},')
    lines.append("}\n")
    lines.append("func DialectByCode(code string) (DialectCode, bool) {")
    lines.append("\tfor _, d := range AllDialects {")
    lines.append('\t\tif d.Code == code { return d, true }')
    lines.append("\t}")
    lines.append("\treturn DialectCode{}, false")
    lines.append("}")
    return "\n".join(lines) + "\n"


def gen_go_version(version: str, sha: str) -> str:
    lines = [HEADER_GO, "package taxonomy\n"]
    lines.append(f'const TaxonomyVersion = "{version}"')
    lines.append(f'const SchemaHash = "{sha}"')
    return "\n".join(lines) + "\n"


# --- PHP generation ---


def gen_php_categories(cats: list[dict]) -> str:
    lines = [HEADER_PHP, "enum Category: string\n{"]
    for c in cats:
        lines.append(f"    case {to_pascal(c['slug'])} = '{c['slug']}';")
    lines.append("")
    lines.append("    /** @return list<self> */")
    lines.append("    public static function routable(): array")
    lines.append("    {")
    lines.append("        return [")
    for c in cats:
        if c.get("nc_routing"):
            lines.append(f"            self::{to_pascal(c['slug'])},")
    lines.append("        ];")
    lines.append("    }")
    lines.append("}")
    return "\n".join(lines) + "\n"


def gen_php_regions(regions: list[dict]) -> str:
    flat = flatten_regions(regions)
    lines = [HEADER_PHP, "enum Region: string\n{"]
    for r in flat:
        lines.append(f"    case {to_pascal(r['slug'])} = '{r['slug']}';")
    lines.append("}")
    return "\n".join(lines) + "\n"


def gen_php_dialects(families: list[dict]) -> str:
    lines = [HEADER_PHP]
    lines.append("final class DialectCode\n{")
    lines.append("    /** @param list<string> $regions */")
    lines.append("    public function __construct(")
    lines.append("        public readonly string $code,")
    lines.append("        public readonly string $name,")
    lines.append("        public readonly string $displayName,")
    lines.append("        public readonly string $iso6393,")
    lines.append("        public readonly string $family,")
    lines.append("        public readonly array $regions,")
    lines.append("    ) {}")
    lines.append("")
    lines.append("    /** @return list<self> */")
    lines.append("    public static function all(): array")
    lines.append("    {")
    lines.append("        return [")
    for fam in families:
        for d in fam["dialects"]:
            regions = ", ".join(f"'{r}'" for r in d["regions"])
            lines.append(f"            new self('{d['code']}', '{d['name']}', '{d['display_name']}', '{d['iso_639_3']}', '{fam['slug']}', [{regions}]),")
    lines.append("        ];")
    lines.append("    }")
    lines.append("")
    lines.append("    public static function byCode(string $code): ?self")
    lines.append("    {")
    lines.append("        foreach (self::all() as $d) {")
    lines.append("            if ($d->code === $code) { return $d; }")
    lines.append("        }")
    lines.append("        return null;")
    lines.append("    }")
    lines.append("}")
    return "\n".join(lines) + "\n"


def gen_php_version(version: str, sha: str) -> str:
    lines = [HEADER_PHP, "final class TaxonomyVersion\n{"]
    lines.append(f"    public const VERSION = '{version}';")
    lines.append(f"    public const SCHEMA_HASH = '{sha}';")
    lines.append("}")
    return "\n".join(lines) + "\n"


# --- Python generation ---


def gen_py_categories(cats: list[dict]) -> str:
    lines = [HEADER_PY, "from enum import Enum\n\n"]
    lines.append("class Category(str, Enum):")
    for c in cats:
        lines.append(f'    {to_upper_snake(c["slug"])} = "{c["slug"]}"')
    lines.append("")
    lines.append("    @classmethod")
    lines.append("    def routable(cls) -> list['Category']:")
    lines.append("        return [")
    for c in cats:
        if c.get("nc_routing"):
            lines.append(f"            cls.{to_upper_snake(c['slug'])},")
    lines.append("        ]")
    return "\n".join(lines) + "\n"


def gen_py_regions(regions: list[dict]) -> str:
    flat = flatten_regions(regions)
    lines = [HEADER_PY, "from enum import Enum\n\n"]
    lines.append("class Region(str, Enum):")
    for r in flat:
        lines.append(f'    {to_upper_snake(r["slug"])} = "{r["slug"]}"')
    return "\n".join(lines) + "\n"


def gen_py_dialects(families: list[dict]) -> str:
    lines = [HEADER_PY, "from dataclasses import dataclass, field\n\n"]
    lines.append("@dataclass(frozen=True)")
    lines.append("class DialectCode:")
    lines.append("    code: str")
    lines.append("    name: str")
    lines.append("    display_name: str")
    lines.append("    iso_639_3: str")
    lines.append("    family: str")
    lines.append("    regions: list[str] = field(default_factory=list)")
    lines.append("")
    lines.append("")
    lines.append("ALL_DIALECTS: list[DialectCode] = [")
    for fam in families:
        for d in fam["dialects"]:
            regions = ", ".join(f'"{r}"' for r in d["regions"])
            lines.append(f'    DialectCode("{d["code"]}", "{d["name"]}", "{d["display_name"]}", "{d["iso_639_3"]}", "{fam["slug"]}", [{regions}]),')
    lines.append("]\n")
    lines.append("")
    lines.append("def dialect_by_code(code: str) -> DialectCode | None:")
    lines.append("    return next((d for d in ALL_DIALECTS if d.code == code), None)")
    return "\n".join(lines) + "\n"


def gen_py_version(version: str, sha: str) -> str:
    lines = [HEADER_PY]
    lines.append(f'TAXONOMY_VERSION = "{version}"')
    lines.append(f'SCHEMA_HASH = "{sha}"')
    return "\n".join(lines) + "\n"


def gen_py_init() -> str:
    return '"""Indigenous Taxonomy — generated from canonical YAML schemas."""\n\nfrom .version import TAXONOMY_VERSION, SCHEMA_HASH\nfrom .categories import Category\nfrom .regions import Region\nfrom .dialects import DialectCode, ALL_DIALECTS, dialect_by_code\n\n__all__ = [\n    "TAXONOMY_VERSION",\n    "SCHEMA_HASH",\n    "Category",\n    "Region",\n    "DialectCode",\n    "ALL_DIALECTS",\n    "dialect_by_code",\n]\n'


# --- Main ---


def main() -> None:
    cats_data = load_yaml("categories")
    regions_data = load_yaml("regions")
    dialects_data = load_yaml("dialect-codes")

    sha = schema_hash()
    version = cats_data.get("version", 1)
    version_str = f"{version}.0.0"

    # Go
    GEN_GO.mkdir(parents=True, exist_ok=True)
    (GEN_GO / "categories.go").write_text(gen_go_categories(cats_data["categories"]))
    (GEN_GO / "regions.go").write_text(gen_go_regions(regions_data["regions"]))
    (GEN_GO / "dialects.go").write_text(gen_go_dialects(dialects_data["language_families"]))
    (GEN_GO / "version.go").write_text(gen_go_version(version_str, sha))

    # PHP
    GEN_PHP.mkdir(parents=True, exist_ok=True)
    (GEN_PHP / "Category.php").write_text(gen_php_categories(cats_data["categories"]))
    (GEN_PHP / "Region.php").write_text(gen_php_regions(regions_data["regions"]))
    (GEN_PHP / "DialectCode.php").write_text(gen_php_dialects(dialects_data["language_families"]))
    (GEN_PHP / "TaxonomyVersion.php").write_text(gen_php_version(version_str, sha))

    # Python
    GEN_PY.mkdir(parents=True, exist_ok=True)
    (GEN_PY / "__init__.py").write_text(gen_py_init())
    (GEN_PY / "categories.py").write_text(gen_py_categories(cats_data["categories"]))
    (GEN_PY / "regions.py").write_text(gen_py_regions(regions_data["regions"]))
    (GEN_PY / "dialects.py").write_text(gen_py_dialects(dialects_data["language_families"]))
    (GEN_PY / "version.py").write_text(gen_py_version(version_str, sha))

    print(f"Generated taxonomy v{version_str} (hash: {sha})")
    print(f"  Go:     {GEN_GO}")
    print(f"  PHP:    {GEN_PHP}")
    print(f"  Python: {GEN_PY}")


if __name__ == "__main__":
    main()
```

- [ ] **Step 2: Commit**

```bash
git add scripts/generate.py
git commit -m "feat: add Python generator for Go/PHP/Python taxonomy packages"
```

---

### Task 4: Package scaffolds and first generation

**Files:**
- Create: `generated/go/taxonomy/go.mod`
- Create: `generated/php/composer.json`
- Create: `generated/python/pyproject.toml`

- [ ] **Step 1: Write Go module file**

```go
module github.com/jonesrussell/indigenous-taxonomy/generated/go/taxonomy

go 1.24
```

- [ ] **Step 2: Write PHP composer.json**

```json
{
  "name": "jonesrussell/indigenous-taxonomy",
  "description": "Indigenous content taxonomy — categories, regions, dialect codes",
  "type": "library",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "IndigenousTaxonomy\\": "src/"
    }
  },
  "require": {
    "php": ">=8.4"
  }
}
```

- [ ] **Step 3: Write Python pyproject.toml**

```toml
[project]
name = "indigenous-taxonomy"
version = "1.0.0"
description = "Indigenous content taxonomy — categories, regions, dialect codes"
requires-python = ">=3.12"

[build-system]
requires = ["setuptools>=75.0"]
build-backend = "setuptools.build_meta"

[tool.setuptools.packages.find]
where = ["."]
```

- [ ] **Step 4: Run generator and verify output**

```bash
pip install pyyaml
python scripts/generate.py
```

Expected: "Generated taxonomy v1.0.0 (hash: ...)" and populated files in all three `generated/` directories.

- [ ] **Step 5: Verify Go compiles**

```bash
cd generated/go/taxonomy && go vet ./... && cd ../../..
```

Expected: no errors.

- [ ] **Step 6: Commit generated output**

```bash
git add generated/
git commit -m "feat: generate initial Go/PHP/Python taxonomy packages"
```

---

### Task 5: Validation tests

**Files:**
- Create: `tests/test_schema_valid.py`
- Create: `tests/test_slugs_unique.py`
- Create: `tests/test_generated_consistent.py`

- [ ] **Step 1: Write schema validation test**

```python
"""Validate YAML files against their JSON Schema definitions."""
import json
from pathlib import Path

import jsonschema
import pytest
import yaml

ROOT = Path(__file__).resolve().parent.parent
SCHEMA_DIR = ROOT / "schema"
VALIDATION_DIR = SCHEMA_DIR / "validation"

YAML_SCHEMAS = [
    ("categories", "categories.schema.json"),
    ("regions", "regions.schema.json"),
    ("dialect-codes", "dialect-codes.schema.json"),
]


@pytest.mark.parametrize("yaml_name,schema_file", YAML_SCHEMAS)
def test_yaml_validates_against_schema(yaml_name: str, schema_file: str) -> None:
    with open(SCHEMA_DIR / f"{yaml_name}.yaml") as f:
        data = yaml.safe_load(f)
    with open(VALIDATION_DIR / schema_file) as f:
        schema = json.load(f)
    jsonschema.validate(data, schema)
```

- [ ] **Step 2: Write slug uniqueness test**

```python
"""Ensure all slugs are unique within each taxonomy file."""
from pathlib import Path

import pytest
import yaml

ROOT = Path(__file__).resolve().parent.parent
SCHEMA_DIR = ROOT / "schema"


def test_category_slugs_unique() -> None:
    with open(SCHEMA_DIR / "categories.yaml") as f:
        data = yaml.safe_load(f)
    slugs = [c["slug"] for c in data["categories"]]
    assert len(slugs) == len(set(slugs)), f"Duplicate slugs: {[s for s in slugs if slugs.count(s) > 1]}"


def _collect_region_slugs(regions: list[dict], out: list[str] | None = None) -> list[str]:
    if out is None:
        out = []
    for r in regions:
        out.append(r["slug"])
        if "children" in r:
            _collect_region_slugs(r["children"], out)
    return out


def test_region_slugs_unique() -> None:
    with open(SCHEMA_DIR / "regions.yaml") as f:
        data = yaml.safe_load(f)
    slugs = _collect_region_slugs(data["regions"])
    assert len(slugs) == len(set(slugs)), f"Duplicate slugs: {[s for s in slugs if slugs.count(s) > 1]}"


def test_dialect_codes_unique() -> None:
    with open(SCHEMA_DIR / "dialect-codes.yaml") as f:
        data = yaml.safe_load(f)
    codes = [d["code"] for fam in data["language_families"] for d in fam["dialects"]]
    assert len(codes) == len(set(codes)), f"Duplicate codes: {[c for c in codes if codes.count(c) > 1]}"


def test_dialect_regions_reference_valid_regions() -> None:
    with open(SCHEMA_DIR / "regions.yaml") as f:
        region_data = yaml.safe_load(f)
    with open(SCHEMA_DIR / "dialect-codes.yaml") as f:
        dialect_data = yaml.safe_load(f)

    valid_slugs = set(_collect_region_slugs(region_data["regions"]))
    for fam in dialect_data["language_families"]:
        for d in fam["dialects"]:
            for region in d["regions"]:
                assert region in valid_slugs, f"Dialect {d['code']} references unknown region: {region}"
```

- [ ] **Step 3: Write generated consistency test**

```python
"""Verify generated code is consistent with YAML source."""
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def test_generated_code_is_up_to_date() -> None:
    """Run generator and check for diffs — generated code should match committed code."""
    result = subprocess.run(
        [sys.executable, "scripts/generate.py"],
        cwd=ROOT,
        capture_output=True,
        text=True,
    )
    assert result.returncode == 0, f"Generator failed: {result.stderr}"

    diff = subprocess.run(
        ["git", "diff", "--stat", "generated/"],
        cwd=ROOT,
        capture_output=True,
        text=True,
    )
    assert diff.stdout.strip() == "", f"Generated code is out of date. Run 'task generate'. Diff:\n{diff.stdout}"
```

- [ ] **Step 4: Run tests**

```bash
pip install pytest jsonschema pyyaml
pytest tests/ -v
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add tests/
git commit -m "test: add schema validation, slug uniqueness, and generation consistency tests"
```

---

### Task 6: Taskfile and CI workflows

**Files:**
- Create: `Taskfile.yml`
- Create: `.github/workflows/validate.yml`
- Create: `.github/workflows/release.yml`

- [ ] **Step 1: Write Taskfile.yml**

```yaml
version: '3'

tasks:
  generate:
    desc: Regenerate all packages from YAML
    cmds:
      - python scripts/generate.py

  validate:
    desc: Validate YAML against JSON Schema
    cmds:
      - pytest tests/test_schema_valid.py tests/test_slugs_unique.py -v

  test:
    desc: Run all tests
    cmds:
      - pytest tests/ -v

  release:
    desc: Tag and push (CI publishes packages)
    cmds:
      - task: test
      - task: generate
      - echo "Ready to tag. Run 'git tag v<X.Y.Z> && git push --tags'"
```

- [ ] **Step 2: Write validate.yml**

```yaml
name: Validate

on:
  pull_request:
    branches: [main]
  push:
    branches: [main]

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-python@v5
        with:
          python-version: '3.12'

      - name: Install dependencies
        run: pip install pyyaml jsonschema pytest

      - name: Validate schemas and run tests
        run: pytest tests/ -v

      - name: Verify generated code is current
        run: |
          python scripts/generate.py
          git diff --exit-code generated/ || (echo "Generated code is stale. Run 'task generate' and commit." && exit 1)

      - name: Verify Go compiles
        uses: actions/setup-go@v5
        with:
          go-version: '1.24'
      - run: cd generated/go/taxonomy && go vet ./...
```

- [ ] **Step 3: Write release.yml**

```yaml
name: Release

on:
  push:
    tags: ['v*']

jobs:
  publish-python:
    runs-on: ubuntu-latest
    permissions:
      id-token: write
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with:
          python-version: '3.12'
      - name: Build package
        run: |
          pip install build
          cd generated/python && python -m build
      - name: Publish to PyPI
        uses: pypa/gh-action-pypi-publish@release/v1
        with:
          packages-dir: generated/python/dist/

  publish-go:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Go module published via git tag
        run: echo "Go module available at github.com/jonesrussell/indigenous-taxonomy/generated/go/taxonomy@${GITHUB_REF_NAME}"
```

- [ ] **Step 4: Commit**

```bash
git add Taskfile.yml .github/
git commit -m "ci: add Taskfile, validate and release workflows"
```

- [ ] **Step 5: Create GitHub repo and push**

```bash
gh repo create jonesrussell/indigenous-taxonomy --public --source=. --push
```

---

## M0.2 — Source Registry Extension

### Task 7: Add structured source fields to source-manager

**Files:**
- Create: `source-manager/migrations/017_add_structured_source_fields.up.sql`
- Create: `source-manager/migrations/017_add_structured_source_fields.down.sql`
- Modify: `source-manager/internal/models/source.go`

The source-manager already has a `type` TEXT column (migration 012) with values: `news`, `indigenous`, `government`, `mining`, `community`. We need to add `structured` and `api` as valid types, plus metadata fields for non-crawled sources.

> **Note:** The spec lists "admin UI update" for M0.2. The NC admin dashboard is a Vue 3 SPA — updating it to display/manage new source types is deferred to a fast-follow task after the API changes land, since the API must be stable first.

- [ ] **Step 1: Write up migration**

File: `source-manager/migrations/017_add_structured_source_fields.up.sql`

```sql
-- Add metadata fields for structured (non-crawled) sources.
-- These fields are nullable because crawled sources don't need them.
ALTER TABLE sources ADD COLUMN data_format TEXT;
ALTER TABLE sources ADD COLUMN update_frequency TEXT;
ALTER TABLE sources ADD COLUMN license_type TEXT;
ALTER TABLE sources ADD COLUMN attribution_text TEXT;

COMMENT ON COLUMN sources.data_format IS 'Data format: json, csv, rss, html, api';
COMMENT ON COLUMN sources.update_frequency IS 'How often source updates: daily, weekly, monthly, realtime';
COMMENT ON COLUMN sources.license_type IS 'License: open, cc-by, cc-by-sa, restricted, unknown';
COMMENT ON COLUMN sources.attribution_text IS 'Required attribution text for hosted content';
```

- [ ] **Step 2: Write down migration**

File: `source-manager/migrations/017_add_structured_source_fields.down.sql`

```sql
ALTER TABLE sources DROP COLUMN IF EXISTS data_format;
ALTER TABLE sources DROP COLUMN IF EXISTS update_frequency;
ALTER TABLE sources DROP COLUMN IF EXISTS license_type;
ALTER TABLE sources DROP COLUMN IF EXISTS attribution_text;
```

- [ ] **Step 3: Add fields and type constants to Source model**

Modify: `source-manager/internal/models/source.go`

Add constants after the existing `DefaultSourceType`:

```go
const (
	DefaultSourceType = "news"

	SourceTypeCrawled    = "crawled"
	SourceTypeStructured = "structured"
	SourceTypeAPI        = "api"
)

// ValidSourceTypes includes both legacy and new source type values.
var ValidSourceTypes = []string{
	"news", "indigenous", "government", "mining", "community",
	SourceTypeStructured, SourceTypeAPI,
}
```

Add fields to the Source struct (after existing fields):

```go
DataFormat      *string `db:"data_format" json:"data_format,omitempty"`
UpdateFrequency *string `db:"update_frequency" json:"update_frequency,omitempty"`
LicenseType     *string `db:"license_type" json:"license_type,omitempty"`
AttributionText *string `db:"attribution_text" json:"attribution_text,omitempty"`
```

Add validation function:

```go
func IsValidSourceType(t string) bool {
	for _, v := range ValidSourceTypes {
		if v == t {
			return true
		}
	}
	return false
}
```

- [ ] **Step 4: Run migration locally**

```bash
cd ~/dev/north-cloud
task source-manager:migrate:up
```

- [ ] **Step 5: Commit**

```bash
cd ~/dev/north-cloud
git add source-manager/migrations/017_* source-manager/internal/models/source.go
git commit -m "feat(source-manager): add structured source type and metadata fields

Extends the existing type column to support 'structured' and 'api' values
for non-crawled sources. Adds data_format, update_frequency, license_type,
and attribution_text fields for source metadata tracking."
```

---

### Task 8: Validate source type in API handlers

**Files:**
- Modify: `source-manager/internal/handlers/source.go`

- [ ] **Step 1: Add type validation to Create handler**

In the `Create` function, after `c.ShouldBindJSON(&source)` succeeds, add validation:

```go
if source.Type != "" && !models.IsValidSourceType(source.Type) {
    c.JSON(http.StatusBadRequest, gin.H{"error": fmt.Sprintf("invalid source type: %s", source.Type)})
    return
}
```

- [ ] **Step 2: Add type validation to Update handler**

Same validation in the `Update` function after binding.

- [ ] **Step 3: Run existing tests**

```bash
cd ~/dev/north-cloud
task source-manager:test
```

Expected: all existing tests pass.

- [ ] **Step 4: Commit**

```bash
git add source-manager/internal/handlers/source.go
git commit -m "feat(source-manager): validate source type values in Create/Update handlers"
```

---

## M0.3 — Boundary Governance

### Task 9: Add boundary rules to CLAUDE.md files

**Files:**
- Modify: `/home/jones/dev/minoo/CLAUDE.md`
- Modify: `/home/jones/dev/north-cloud/CLAUDE.md`
- Modify: `/home/jones/dev/waaseyaa/CLAUDE.md`

- [ ] **Step 1: Add boundary section to Minoo CLAUDE.md**

Add after the "## Codified Context" section:

```markdown
## Architectural Boundaries

Minoo is the **application layer**. It owns entity types, map-driven UX, dialect-aware content, access policies, templates, and CSS.

**Minoo does NOT own:**
- Content classification logic (that's North Cloud)
- Crawl scheduling or source fetching (that's North Cloud)
- Entity system internals, storage engine, or ingestion envelope contract (that's Waaseyaa)

**Import rules:**
- Minoo imports from Waaseyaa (framework) — never the reverse
- Minoo consumes the shared taxonomy contract (`jonesrussell/indigenous-taxonomy`) for category/region/dialect constants
- Minoo may call North Cloud APIs (via NorthCloudClient) but must not import NC Go packages
- North Cloud must not contain Minoo-specific entity types or templates

**Shared contracts:**
- `jonesrussell/indigenous-taxonomy` — categories, regions, dialect codes (PHP package)
- Waaseyaa ingestion envelope schema — used by Python harvesters to feed Minoo directly
- NC source-manager API — used by harvesters to register sources
```

- [ ] **Step 2: Add boundary section to North Cloud CLAUDE.md**

Add an appropriate section:

```markdown
## Architectural Boundaries

North Cloud is the **content pipeline layer**. It owns crawling, classification (rules + ML), enrichment, routing, Redis pub/sub, and the source registry.

**North Cloud does NOT own:**
- Entity model, frontend rendering, or dialect/language data (that's Minoo)
- Framework internals, entity storage, or ingestion envelope contract (that's Waaseyaa)
- Content curation or editorial decisions (that's the consuming apps)

**Import rules:**
- NC classifier must import category/region slugs from `jonesrussell/indigenous-taxonomy` Go package — not hardcode them
- NC must not reference Minoo entity types, PHP classes, or templates
- NC source-manager is the single registry for all content sources (crawled + structured + API)

**Shared contracts:**
- `jonesrussell/indigenous-taxonomy` — categories, regions (Go module)
- Redis pub/sub channels follow taxonomy slugs: `indigenous:category:{slug}`, `indigenous:region:{slug}`
```

- [ ] **Step 3: Add boundary section to Waaseyaa CLAUDE.md**

Add an appropriate section:

```markdown
## Architectural Boundaries

Waaseyaa is the **framework layer**. It owns the entity system, storage engine, field types, ingestion envelope contract, GraphQL/REST API, access control, and SSR rendering.

**Waaseyaa does NOT own:**
- Minoo-specific entity types (those belong in Minoo's src/Entity/)
- Content classification or routing (that's North Cloud)
- Map UX, dialect logic, or community-specific features (that's Minoo)

**Import rules:**
- Waaseyaa must not import from Minoo — the dependency flows one way (Minoo → Waaseyaa)
- Waaseyaa must not reference North Cloud services or APIs
- Waaseyaa defines the ingestion envelope contract that external tools (Python harvesters) must follow
```

- [ ] **Step 4: Commit across repos**

```bash
cd ~/dev/minoo
git add CLAUDE.md
git commit -m "docs: add architectural boundary rules to CLAUDE.md"

cd ~/dev/north-cloud
git add CLAUDE.md
git commit -m "docs: add architectural boundary rules to CLAUDE.md"

cd ~/dev/waaseyaa
git add CLAUDE.md
git commit -m "docs: add architectural boundary rules to CLAUDE.md"
```

---

### Task 10: Add boundary drift check to check-milestones

**Files:**
- Modify: `/home/jones/dev/minoo/bin/check-milestones`

- [ ] **Step 1: Add boundary checks after milestone checks**

Append before the final `exit 0` in `bin/check-milestones`:

```bash
# --- Boundary drift checks ---
echo ""
echo "=== Boundary drift check ==="

# Check: no NC classifier logic in Minoo (Go imports, classification constants)
if grep -rq 'indigenous_rules\|classifier.*indigenous\|crime_classifier' src/ 2>/dev/null; then
  echo "WARN: Found North Cloud classifier references in Minoo src/ — this logic belongs in NC"
  PROBLEMS=$((PROBLEMS + 1))
fi

# Check: no Minoo entity types in waaseyaa framework packages
if [ -d "../waaseyaa/packages" ]; then
  if grep -rq 'Minoo\\\\Entity\|minoo_entity\|MinooEntity' ../waaseyaa/packages/ 2>/dev/null; then
    echo "WARN: Found Minoo-specific references in waaseyaa packages/ — entities belong in Minoo"
    PROBLEMS=$((PROBLEMS + 1))
  fi
fi

# Check: taxonomy version (when indigenous-taxonomy PHP package is installed)
if [ -f "vendor/jonesrussell/indigenous-taxonomy/src/TaxonomyVersion.php" ]; then
  echo "OK: indigenous-taxonomy PHP package installed"
else
  echo "INFO: indigenous-taxonomy PHP package not yet installed (expected until Phase 1)"
fi

if [ "$PROBLEMS" -eq 0 ]; then
  echo "OK: No boundary violations detected."
fi
```

- [ ] **Step 2: Test the script**

```bash
cd ~/dev/minoo
bash bin/check-milestones
```

Expected: existing milestone checks run, then "OK: No boundary violations detected."

- [ ] **Step 3: Commit**

```bash
git add bin/check-milestones
git commit -m "feat: add boundary drift checks to session-start hook"
```

---

## M0.4 — Dialect Region Config Entity

### Task 11: Create DialectRegion entity class

**Files:**
- Create: `src/Entity/DialectRegion.php`
- Create: `tests/Minoo/Unit/Entity/DialectRegionTest.php`

- [ ] **Step 1: Write failing test**

File: `tests/Minoo/Unit/Entity/DialectRegionTest.php`

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\DialectRegion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DialectRegion::class)]
final class DialectRegionTest extends TestCase
{
    #[Test]
    public function it_creates_with_code_and_name(): void
    {
        $region = new DialectRegion([
            'code' => 'oji-east',
            'name' => 'Nishnaabemwin',
        ]);

        $this->assertSame('oji-east', $region->id());
        $this->assertSame('Nishnaabemwin', $region->label());
        $this->assertSame('dialect_region', $region->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_optional_fields(): void
    {
        $region = new DialectRegion([
            'code' => 'oji-east',
            'name' => 'Nishnaabemwin',
        ]);

        $values = $region->toArray();
        $this->assertSame('', $values['display_name']);
        $this->assertSame('', $values['language_family']);
        $this->assertSame('', $values['iso_639_3']);
        $this->assertSame([], $values['regions']);
        $this->assertNull($values['boundary_geojson']);
    }

    #[Test]
    public function it_stores_all_fields(): void
    {
        $geojson = '{"type":"Polygon","coordinates":[[[-81.0,46.0],[-80.0,46.0],[-80.0,47.0],[-81.0,47.0],[-81.0,46.0]]]}';
        $region = new DialectRegion([
            'code' => 'oji-east',
            'name' => 'Nishnaabemwin',
            'display_name' => 'Eastern Ojibwe',
            'language_family' => 'algonquian',
            'iso_639_3' => 'ojg',
            'regions' => ['canada:ontario:north-shore-huron', 'canada:ontario:southern'],
            'boundary_geojson' => $geojson,
        ]);

        $this->assertSame('Eastern Ojibwe', $region->get('display_name'));
        $this->assertSame('algonquian', $region->get('language_family'));
        $this->assertSame('ojg', $region->get('iso_639_3'));
        $this->assertSame(['canada:ontario:north-shore-huron', 'canada:ontario:southern'], $region->get('regions'));
        $this->assertSame($geojson, $region->get('boundary_geojson'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd ~/dev/minoo
./vendor/bin/phpunit tests/Minoo/Unit/Entity/DialectRegionTest.php
```

Expected: FAIL — class `Minoo\Entity\DialectRegion` not found.

- [ ] **Step 3: Write implementation**

File: `src/Entity/DialectRegion.php`

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class DialectRegion extends ConfigEntityBase
{
    protected string $entityTypeId = 'dialect_region';

    protected array $entityKeys = ['id' => 'code', 'label' => 'name'];

    public function __construct(array $values = [])
    {
        if (!array_key_exists('display_name', $values)) {
            $values['display_name'] = '';
        }
        if (!array_key_exists('language_family', $values)) {
            $values['language_family'] = '';
        }
        if (!array_key_exists('iso_639_3', $values)) {
            $values['iso_639_3'] = '';
        }
        if (!array_key_exists('regions', $values)) {
            $values['regions'] = [];
        }
        if (!array_key_exists('boundary_geojson', $values)) {
            $values['boundary_geojson'] = null;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Entity/DialectRegionTest.php
```

Expected: 3 tests, 3 passed.

- [ ] **Step 5: Commit**

```bash
git add src/Entity/DialectRegion.php tests/Minoo/Unit/Entity/DialectRegionTest.php
git commit -m "feat: add DialectRegion config entity class with tests"
```

---

### Task 12: Add dialect region seed data

> **Spec deviation:** The spec says `TaxonomySeeder::dialectRegions()` but we use `ConfigSeeder::dialectRegions()`. Rationale: `TaxonomySeeder` handles vocabulary terms (hierarchical parent→child), while `ConfigSeeder` handles typed configs (code+name pairs like event_type, group_type). Dialect regions are config-like (code+name+metadata), matching the ConfigSeeder pattern. `boundary_geojson` is `null` in seed data — actual GeoJSON polygons will be sourced and populated during Phase 2 (M2.2) when the map integration is built.

**Files:**
- Modify: `src/Seed/ConfigSeeder.php`
- Modify: `tests/Minoo/Unit/Seed/ConfigSeederTest.php`

- [ ] **Step 1: Write failing test**

Add to `tests/Minoo/Unit/Seed/ConfigSeederTest.php`:

```php
#[Test]
public function it_provides_dialect_regions(): void
{
    $regions = ConfigSeeder::dialectRegions();

    $this->assertNotEmpty($regions);

    // First entry should be Eastern Ojibwe (home dialect)
    $this->assertSame('oji-east', $regions[0]['code']);
    $this->assertSame('Nishnaabemwin', $regions[0]['name']);
    $this->assertSame('Eastern Ojibwe', $regions[0]['display_name']);
    $this->assertSame('algonquian', $regions[0]['language_family']);
    $this->assertSame('ojg', $regions[0]['iso_639_3']);
    $this->assertIsArray($regions[0]['regions']);
    $this->assertContains('canada:ontario:north-shore-huron', $regions[0]['regions']);

    // All entries must have required keys
    foreach ($regions as $region) {
        $this->assertArrayHasKey('code', $region);
        $this->assertArrayHasKey('name', $region);
        $this->assertArrayHasKey('display_name', $region);
        $this->assertArrayHasKey('language_family', $region);
        $this->assertArrayHasKey('iso_639_3', $region);
        $this->assertArrayHasKey('regions', $region);
        $this->assertArrayHasKey('boundary_geojson', $region);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Seed/ConfigSeederTest.php --filter=dialect
```

Expected: FAIL — method `dialectRegions` not found.

- [ ] **Step 3: Add dialectRegions() to ConfigSeeder**

Add to `src/Seed/ConfigSeeder.php` after the `teachingTypes()` method:

```php
/** @return list<array{code: string, name: string, display_name: string, language_family: string, iso_639_3: string, regions: list<string>, boundary_geojson: ?string}> */
public static function dialectRegions(): array
{
    // boundary_geojson is null in seed data — actual GeoJSON polygons
    // will be sourced during Phase 2 (M2.2) map integration.
    return [
        [
            'code' => 'oji-east',
            'name' => 'Nishnaabemwin',
            'display_name' => 'Eastern Ojibwe',
            'language_family' => 'algonquian',
            'iso_639_3' => 'ojg',
            'regions' => ['canada:ontario:north-shore-huron', 'canada:ontario:southern'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'oji-northwest',
            'name' => 'Anishinaabemowin',
            'display_name' => 'Northwestern Ojibwe',
            'language_family' => 'algonquian',
            'iso_639_3' => 'ojb',
            'regions' => ['canada:ontario:northern'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'oji-plains',
            'name' => 'Nakawēmowin',
            'display_name' => 'Saulteaux / Plains Ojibwe',
            'language_family' => 'algonquian',
            'iso_639_3' => 'ojs',
            'regions' => ['canada:manitoba:southern', 'canada:saskatchewan'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'oji-ottawa',
            'name' => 'Odaawaa',
            'display_name' => 'Ottawa / Odawa',
            'language_family' => 'algonquian',
            'iso_639_3' => 'otw',
            'regions' => ['canada:ontario:southern'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'cree-plains',
            'name' => 'nēhiyawēwin',
            'display_name' => 'Plains Cree',
            'language_family' => 'algonquian',
            'iso_639_3' => 'crk',
            'regions' => ['canada:saskatchewan', 'canada:alberta'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'cree-swampy',
            'name' => 'Ininīmowin',
            'display_name' => 'Swampy Cree',
            'language_family' => 'algonquian',
            'iso_639_3' => 'csw',
            'regions' => ['canada:manitoba', 'canada:ontario:northern'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'innu',
            'name' => 'Innu-aimun',
            'display_name' => 'Innu',
            'language_family' => 'algonquian',
            'iso_639_3' => 'moe',
            'regions' => ['canada:quebec', 'canada:atlantic'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'inuktitut',
            'name' => 'ᐃᓄᒃᑎᑐᑦ',
            'display_name' => 'Inuktitut',
            'language_family' => 'eskimo-aleut',
            'iso_639_3' => 'iku',
            'regions' => ['canada:north:nunavut', 'canada:quebec'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'inuvialuktun',
            'name' => 'Inuvialuktun',
            'display_name' => 'Inuvialuktun',
            'language_family' => 'eskimo-aleut',
            'iso_639_3' => 'ikt',
            'regions' => ['canada:north:nwt'],
            'boundary_geojson' => null,
        ],
        [
            'code' => 'mohawk',
            'name' => "Kanien\u{2019}kéha",
            'display_name' => 'Mohawk',
            'language_family' => 'iroquoian',
            'iso_639_3' => 'moh',
            'regions' => ['canada:ontario:southern', 'canada:quebec'],
            'boundary_geojson' => null,
        ],
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Seed/ConfigSeederTest.php --filter=dialect
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Seed/ConfigSeeder.php tests/Minoo/Unit/Seed/ConfigSeederTest.php
git commit -m "feat: add dialect region seed data (10 Canadian dialects)"
```

---

### Task 13: Register entity type and update access policy

**Files:**
- Create: `src/Provider/DialectRegionServiceProvider.php`
- Modify: `src/Access/LanguageAccessPolicy.php`

- [ ] **Step 1: Create service provider**

File: `src/Provider/DialectRegionServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\DialectRegion;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider;

final class DialectRegionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'dialect_region',
            label: 'Dialect Region',
            class: DialectRegion::class,
            keys: ['id' => 'code', 'label' => 'name'],
            group: 'language',
        ));
    }
}
```

- [ ] **Step 2: Add dialect_region to LanguageAccessPolicy**

Modify `src/Access/LanguageAccessPolicy.php`:

Change the attribute on line 13:
```php
#[PolicyAttribute(entityType: ['dictionary_entry', 'example_sentence', 'word_part', 'speaker', 'dialect_region'])]
```

Change the constant on line 16:
```php
private const ENTITY_TYPES = ['dictionary_entry', 'example_sentence', 'word_part', 'speaker', 'dialect_region'];
```

- [ ] **Step 3: Delete stale manifest cache and run unit tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: all unit tests pass.

- [ ] **Step 4: Commit**

```bash
git add src/Provider/DialectRegionServiceProvider.php src/Access/LanguageAccessPolicy.php
git commit -m "feat: register dialect_region entity type with language access policy"
```

---

### Task 14: Update integration test and verify full boot

**Files:**
- Modify: `tests/Minoo/Integration/BootTest.php`

- [ ] **Step 1: Add dialect_region to the entity type list**

In `BootTest.php` at line 75 (after the `speaker` line), add:

```php
'dialect_region',
```

So the array becomes:
```php
$minooTypes = [
    'event', 'event_type',
    'group', 'group_type',
    'cultural_group',
    'teaching', 'teaching_type',
    'cultural_collection',
    'dictionary_entry', 'example_sentence', 'word_part', 'speaker',
    'dialect_region',
    'ingest_log',
    'resource_person',
    'elder_support_request', 'volunteer',
    'community',
];
```

- [ ] **Step 2: Run integration tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit --testsuite MinooIntegration
```

Expected: kernel boots, discovers `dialect_region`, all integration tests pass.

- [ ] **Step 3: Run full test suite**

```bash
./vendor/bin/phpunit
```

Expected: all tests pass (existing + new).

- [ ] **Step 4: Commit**

```bash
git add tests/Minoo/Integration/BootTest.php
git commit -m "test: add dialect_region to integration boot test entity list"
```

---

### Task 15: Update CLAUDE.md and spec documentation

**Files:**
- Modify: `/home/jones/dev/minoo/CLAUDE.md`

- [ ] **Step 1: Update Entity Domains table**

Add dialect_region to the Language domain row. Update the table to show:

```markdown
| Language | `dictionary_entry`, `example_sentence`, `word_part`, `speaker`, `dialect_region` | `LanguageServiceProvider`, `DialectRegionServiceProvider` | `LanguageAccessPolicy` |
```

- [ ] **Step 2: Update Orchestration table**

Add row for the new provider:

```markdown
| `src/Entity/DialectRegion.php`, `src/Provider/DialectRegionServiceProvider.php` | `minoo:entities` | `docs/specs/entity-model.md` |
```

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: add dialect_region to entity domains and orchestration table"
```

---

## Verification

After all tasks are complete, run full verification across repos:

```bash
# Minoo — full test suite
cd ~/dev/minoo
rm -f storage/framework/packages.php
./vendor/bin/phpunit

# North Cloud — source-manager tests
cd ~/dev/north-cloud
task source-manager:test

# Taxonomy — all tests
cd ~/dev/indigenous-taxonomy
pytest tests/ -v
```

Expected: all green across all three repos.
