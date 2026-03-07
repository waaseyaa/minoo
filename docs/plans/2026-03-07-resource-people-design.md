# Resource People Directory — Design

**Date:** 2026-03-07
**Milestone:** Minoo v0.4
**Repo:** waaseyaa/minoo

## Problem

Community departments (Education, Health, Culture, Youth, Social Services) need a directory of community members they can call on for programming. Today this lives in informal Facebook posts and word-of-mouth. There is no structured, filterable directory.

## Solution

A new `resource_person` entity type with seeded role and offering taxonomies, an SSR listing+detail page at `/people`, and admin CRUD via the existing Admin SPA.

## Core Concept

A Resource Person is a community member with identity, knowledge, and offerings — which may include small business services. Small business owners are not a separate category; they are one expression of the same underlying concept. Business details are an attribute, not a separate entity type.

## Entity: `resource_person`

**Entity type ID:** `resource_person`
**Primary key:** `rpid`
**Label field:** `name`
**Domain:** People (new `PeopleServiceProvider`, `PeopleAccessPolicy`)

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | yes | Full name |
| `slug` | string | yes | URL slug, derived from name |
| `bio` | text_long | no | Free-form biography |
| `community` | string | no | Community affiliation (free string, e.g. "Sagamok Anishnawbek") |
| `roles` | entity_reference (multi) | yes | References `person_role` taxonomy terms |
| `offerings` | entity_reference (multi) | no | References `person_offering` taxonomy terms |
| `email` | string | no | Contact email |
| `phone` | string | no | Contact phone |
| `business_name` | string | no | Optional business identity |
| `media_id` | entity_reference | no | Photo |
| `status` | boolean | yes | Published/unpublished (default: 1) |
| `created_at` | datetime | auto | |
| `updated_at` | datetime | auto | |

### Why free string for `community`

Community names are proper nouns (Sagamok Anishnawbek, Garden River, Atikameksheng), not categories. A taxonomy would create spelling/scoping debates. Free string lets people type what they identify with.

### Why roles required, offerings optional

Every resource person has at least one role — that's why they're in the directory. Not everyone has a discrete offering. An elder might simply be available for guidance.

## Seeded Vocabularies

### Person Roles (`person_roles`)

Elder, Knowledge Keeper, Dancer, Drummer, Language Speaker, Regalia Maker, Caterer, Crafter, Workshop Facilitator, Small Business Owner, Youth Worker, Cedar Harvester

### Person Offerings (`person_offerings`)

Food, Regalia, Crafts, Teachings, Workshops, Cultural Services, Performances, Cedar Products, Beadwork, Traditional Medicine

## Service Provider

`PeopleServiceProvider` — registers:
- `resource_person` entity type with field definitions
- Person Roles vocabulary via `TaxonomySeeder::personRoles()`
- Person Offerings vocabulary via `TaxonomySeeder::personOfferings()`

## Access Policy

`PeopleAccessPolicy` with `#[PolicyAttribute(entityType: 'resource_person')]`. Public read, admin write. Same pattern as other Minoo content entities.

## SSR Templates

### `templates/people.html.twig`

Listing + detail in one template, following the events/groups/teachings pattern:
- `/people` — card grid with `resource-person-card.html.twig`
- `/people/{slug}` — detail view with back link, role badges, offering tags, bio, contact info

### `templates/components/resource-person-card.html.twig`

Card component showing:
- Name (title)
- Primary role as badge
- Community affiliation as meta
- First 1-2 offerings as tags

### Card badge

New badge variant `.card__badge--person` with a distinct color token (earth or sage palette).

## CSS

- New badge color variant in `@layer components`
- No new layout patterns — existing `card-grid` + `detail` cover it

## Seed Data

4-6 sample resource people in a new `PeopleSeeder` (or added to `TaxonomySeeder`) for development/demo.

## Out of Scope (v0.5+)

- Website field
- Social media links
- Business description
- Dynamic taxonomy management UI
- Photo upload (media_id is the field, but upload UI is framework-level)
- Search/filter UI (v0.4 shows full listing; filtering is v0.5)

## Extension Path

- v0.5: Add website, filter UI, search integration
- Future: Link resource people to events/teachings via entity_reference
- Future: Community affiliation could become a taxonomy if needed
