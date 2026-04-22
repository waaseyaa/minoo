<?php

declare(strict_types=1);

/**
 * Registered community crisis incidents (whitelist for /communities/{slug}/{incident}).
 *
 * Optional OG automation (requires MINOO_CRISIS_OG_AUTO=1 and a managed `og_image_path` in the incident config):
 * - og_generate: when true, automation may build/write PNGs under `/og/crisis/*.png` (never overwrites arbitrary paths).
 * - og_generate_mode: `both` (default) | `build` (batch only, static-or-404 on HTTP) | `fallback` (HTTP/background only).
 *
 * @return list<array{
 *     community_slug: string,
 *     incident_slug: string,
 *     config_path: string,
 *     show_on_community_hub?: bool,
 *     draft?: bool,
 *     hub_title_key?: string,
 *     hub_body_key?: string,
 *     hub_cta_key?: string,
 *     og_generate?: bool,
 *     og_generate_mode?: 'both'|'build'|'fallback'
 * }>
 */
return [
    [
        'community_slug' => 'sagamok-anishnawbek',
        'incident_slug' => 'spanish-river-flood',
        'config_path' => __DIR__ . '/crisis/sagamok_spanish_river_flood.php',
        'og_generate' => true,
        'og_generate_mode' => 'both',
        'show_on_community_hub' => true,
        'draft' => false,
        'hub_title_key' => 'sagamok_flood.community_callout_title',
        'hub_body_key' => 'sagamok_flood.community_callout_body',
        'hub_cta_key' => 'sagamok_flood.community_callout_cta',
    ],
    [
        'community_slug' => 'sudbury',
        'incident_slug' => 'state-of-emergency',
        'config_path' => __DIR__ . '/crisis/sudbury_state_of_emergency.php',
        'show_on_community_hub' => true,
        'draft' => false,
        'hub_title_key' => 'sudbury_soe.community_callout_title',
        'hub_body_key' => 'sudbury_soe.community_callout_body',
        'hub_cta_key' => 'sudbury_soe.community_callout_cta',
    ],
];
