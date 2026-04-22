<?php

declare(strict_types=1);

/**
 * Draft municipal emergency page — hidden from public resolver until draft is cleared in registry.
 * Copy and official URLs must be verified against City of Greater Sudbury communications before publish.
 *
 * Open Graph image (`og_image_path`):
 * - Leave empty (`''`) so `crisis-incident.html.twig` uses `{{ parent() }}` for `og:image`, i.e. the same
 *   default as the base layout: `/img/og-default.png` (see `templates/layouts/base.html.twig`).
 * - When you ship a Sudbury-specific card, set `og_image_path` to a public URL such as
 *   `/og/crisis/sudbury-state-of-emergency.png`, register the route or add the static file under `public/`,
 *   and bump `og_image_revision` when the visual or copy on the card changes.
 */
return [
    'emergency_open_graph' => true,
    'og_image_revision' => 1,
    'last_verified_date' => '2026-04-22',
    // '' → Twig `og_image` block calls `parent()` → site default `/img/og-default.png` (not a Sudbury-specific asset).
    'og_image_path' => '',
    'gallery_base_path' => '',
    'page_theme' => 'flood',
    'carousel_id' => 'sudbury-soe-gallery',
    'translation_pending_lang' => 'oj',
    'translation_pending_key' => 'sudbury_soe.translation_pending',

    'notice_url' => 'https://www.greatersudbury.ca/',
    'official_feed_url' => 'https://www.greatersudbury.ca/',

    'title_key' => 'sudbury_soe.title',
    'og_subtitle_key' => 'sudbury_soe.og_subtitle',
    'og_image_cta_key' => 'sudbury_soe.og_image_cta',
    'meta_description_key' => 'sudbury_soe.meta_description',
    'breadcrumb_key' => 'sudbury_soe.breadcrumb',
    'gallery_heading_key' => 'sudbury_soe.gallery_h',

    'soe_eyebrow_key' => 'sudbury_soe.soe_eyebrow',
    'soe_title_key' => 'sudbury_soe.soe_title',
    'soe_meta_key' => 'sudbury_soe.soe_meta',

    'official_label_key' => 'crisis.common.official_label',
    'official_text_before_key' => 'sudbury_soe.official_text_before',
    'official_link_key' => 'sudbury_soe.official_link',

    'timeline_heading_key' => 'sudbury_soe.timeline_h',
    'timeline' => [
        ['datetime' => '2026-04-22', 'date_key' => 'sudbury_soe.t_placeholder_date', 'body_key' => 'sudbury_soe.t_placeholder_body'],
    ],

    'tiles_heading_key' => 'crisis.common.glance_h',
    'tiles' => [
        ['label_key' => 'sudbury_soe.tile_status_label', 'pill_key' => 'sudbury_soe.tile_status_pill', 'pill_tone' => 'emergency', 'note_key' => 'sudbury_soe.tile_status_note'],
    ],

    'contacts_heading_key' => 'crisis.common.contacts_h',
    'contacts_verified_key' => 'sudbury_soe.contacts_verified',
    'contacts_notice_link_key' => 'sudbury_soe.contacts_notice_link',
    'contacts_note_key' => 'sudbury_soe.contacts_note',
    'contacts_notice_link_short_key' => 'sudbury_soe.contacts_notice_link_short',
    'contacts' => [
        ['name_key' => 'sudbury_soe.c_911_name', 'role_key' => 'sudbury_soe.c_911_role', 'tel_href' => 'tel:911', 'tel_display' => '911', 'emergency' => true],
    ],

    'info_cards' => [
        [
            'section_id' => 'crisis-info-h',
            'heading_key' => 'sudbury_soe.info_h',
            'tone' => 'warn',
            'tag_key' => 'sudbury_soe.info_tag',
            'subsections' => [
                ['title_key' => 'sudbury_soe.info_p1_h', 'body_key' => 'sudbury_soe.info_p1_body'],
            ],
        ],
    ],

    'prep_heading_key' => 'crisis.common.prep_h',
    'prep_checklist_keys' => [
        'crisis.common.prep_1',
        'crisis.common.prep_2',
        'crisis.common.prep_3',
    ],

    'disclaimer_keys' => [
        'sudbury_soe.disclaimer_1',
        'sudbury_soe.disclaimer_2',
    ],
    'footer_updated_key' => 'sudbury_soe.footer_updated',
    'back_top_key' => 'sudbury_soe.back_top',

    'gallery' => [],
];
