<?php

declare(strict_types=1);

$rawEmergency = getenv('MINOO_SAGAMOK_FLOOD_EMERGENCY_OG');
if ($rawEmergency === false || trim((string) $rawEmergency) === '') {
    $emergencyOpenGraph = true;
} else {
    $filtered = filter_var($rawEmergency, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $emergencyOpenGraph = $filtered ?? false;
}

return [
    'emergency_open_graph' => $emergencyOpenGraph,
    'og_image_revision' => (int) (getenv('MINOO_SAGAMOK_FLOOD_OG_REVISION') ?: '1'),
    'last_verified_date' => getenv('MINOO_SAGAMOK_FLOOD_VERIFIED') ?: '2026-04-22',
    'og_image_path' => '/og/crisis/sagamok-spanish-river-flood.png',
    'gallery_base_path' => '/img/crisis/sagamok-spanish-river-flood',
    'page_theme' => 'flood',
    'carousel_id' => 'sagamok-flood-gallery',
    'translation_pending_lang' => 'oj',
    'translation_pending_key' => 'sagamok_flood.translation_pending',

    'notice_url' => 'https://www.sagamokanishnawbek.com/sagamok-news/sudbury-district-has-issued-an-updated-flood-warning-notice-for-the-spanish-river',
    'official_feed_url' => 'https://www.sagamokanishnawbek.com/sagamok-news',

    'title_key' => 'sagamok_flood.title',
    'og_subtitle_key' => 'sagamok_flood.og_subtitle',
    'og_image_cta_key' => 'sagamok_flood.og_image_cta',
    'meta_description_key' => 'sagamok_flood.meta_description',
    'breadcrumb_key' => 'sagamok_flood.breadcrumb',
    'gallery_heading_key' => 'sagamok_flood.gallery_h',

    'soe_eyebrow_key' => 'sagamok_flood.soe_eyebrow',
    'soe_title_key' => 'sagamok_flood.soe_title',
    'soe_meta_key' => 'sagamok_flood.soe_meta',

    'official_label_key' => 'sagamok_flood.official_label',
    'official_text_before_key' => 'sagamok_flood.official_text_before',
    'official_link_key' => 'sagamok_flood.official_link',

    'timeline_heading_key' => 'sagamok_flood.timeline_h',
    'timeline' => [
        ['datetime' => '2026-04-22', 'date_key' => 'sagamok_flood.t_22_date', 'body_key' => 'sagamok_flood.t_22_body'],
        ['datetime' => '2026-04-21', 'date_key' => 'sagamok_flood.t_21_date', 'body_key' => 'sagamok_flood.t_21_body'],
        ['datetime' => '2026-04-19', 'date_key' => 'sagamok_flood.t_19_date', 'body_key' => 'sagamok_flood.t_19_body'],
        ['datetime' => '2026-04-18', 'date_key' => 'sagamok_flood.t_18_date', 'body_key' => 'sagamok_flood.t_18_body'],
        ['datetime' => '2026-04-14', 'date_key' => 'sagamok_flood.t_14_date', 'body_key' => 'sagamok_flood.t_14_body'],
    ],

    'tiles_heading_key' => 'sagamok_flood.glance_h',
    'tiles' => [
        ['label_key' => 'sagamok_flood.tile_river_label', 'pill_key' => 'sagamok_flood.tile_river_pill', 'pill_tone' => 'emergency', 'note_key' => 'sagamok_flood.tile_river_note'],
        ['label_key' => 'sagamok_flood.tile_hwy_label', 'pill_key' => 'sagamok_flood.tile_hwy_pill', 'pill_tone' => 'warn', 'note_key' => 'sagamok_flood.tile_hwy_note'],
        ['label_key' => 'sagamok_flood.tile_bwa_label', 'pill_key' => 'sagamok_flood.tile_bwa_pill', 'pill_tone' => 'emergency', 'note_key' => 'sagamok_flood.tile_bwa_note'],
        ['label_key' => 'sagamok_flood.tile_roads_label', 'pill_key' => 'sagamok_flood.tile_roads_pill', 'pill_tone' => 'emergency', 'note_key' => 'sagamok_flood.tile_roads_note'],
    ],

    'contacts_heading_key' => 'sagamok_flood.contacts_h',
    'contacts_verified_key' => 'sagamok_flood.contacts_verified',
    'contacts_notice_link_key' => 'sagamok_flood.contacts_notice_link',
    'contacts_note_key' => 'sagamok_flood.contacts_note',
    'contacts_notice_link_short_key' => 'sagamok_flood.contacts_notice_link_short',
    'contacts' => [
        ['name_key' => 'sagamok_flood.c_911_name', 'role_key' => 'sagamok_flood.c_911_role', 'tel_href' => 'tel:911', 'tel_display' => '911', 'emergency' => true],
        ['name_key' => 'sagamok_flood.c_crisis_name', 'role_key' => 'sagamok_flood.c_crisis_role', 'tel_href' => 'tel:+18448640523', 'tel_display' => '844-864-0523', 'emergency' => false],
        ['name_key' => 'sagamok_flood.c_victim_name', 'role_key' => 'sagamok_flood.c_victim_role', 'tel_href' => 'tel:+17053703378', 'tel_display' => '705-370-3378', 'emergency' => false],
        ['name_key' => 'sagamok_flood.c_admin_name', 'role_key' => 'sagamok_flood.c_admin_role', 'tel_href' => 'tel:+18005672896', 'tel_display' => '1-800-567-2896', 'emergency' => false],
    ],

    'info_cards' => [
        [
            'section_id' => 'crisis-bwa-h',
            'heading_key' => 'sagamok_flood.bwa_h',
            'tone' => 'emergency',
            'tag_key' => 'sagamok_flood.bwa_tag',
            'subsections' => [
                ['title_key' => 'sagamok_flood.bwa_how_h', 'body_key' => 'sagamok_flood.bwa_how_p'],
                ['title_key' => 'sagamok_flood.bwa_when_h', 'body_key' => 'sagamok_flood.bwa_when_p'],
            ],
        ],
        [
            'section_id' => 'crisis-roads-h',
            'heading_key' => 'sagamok_flood.roads_h',
            'tone' => 'warn',
            'tag_key' => 'sagamok_flood.roads_tag',
            'subsections' => [
                ['title_key' => 'sagamok_flood.roads_7300_h', 'body_key' => 'sagamok_flood.roads_7300_p'],
                ['title_key' => 'sagamok_flood.roads_river_h', 'body_key' => 'sagamok_flood.roads_river_p'],
            ],
        ],
    ],

    'prep_heading_key' => 'sagamok_flood.prep_h',
    'prep_checklist_keys' => [
        'sagamok_flood.prep_1',
        'sagamok_flood.prep_2',
        'sagamok_flood.prep_3',
        'sagamok_flood.prep_4',
        'sagamok_flood.prep_5',
        'sagamok_flood.prep_6',
        'sagamok_flood.prep_7',
    ],

    'disclaimer_keys' => [
        'sagamok_flood.disclaimer_1',
        'sagamok_flood.disclaimer_2',
    ],
    'footer_updated_key' => 'sagamok_flood.footer_updated',
    'back_top_key' => 'sagamok_flood.back_top',

    /**
     * @var list<array{file: string, width: positive-int, height: positive-int, alt_key: string, caption_key: string}>
     */
    'gallery' => [
        [
            'file' => 'flood-01.jpg',
            'width' => 1428,
            'height' => 1071,
            'alt_key' => 'sagamok_flood.gallery_alt_1',
            'caption_key' => 'sagamok_flood.gallery_cap_1',
        ],
        [
            'file' => 'flood-02.jpg',
            'width' => 1523,
            'height' => 1142,
            'alt_key' => 'sagamok_flood.gallery_alt_2',
            'caption_key' => 'sagamok_flood.gallery_cap_2',
        ],
        [
            'file' => 'flood-03.jpg',
            'width' => 1187,
            'height' => 891,
            'alt_key' => 'sagamok_flood.gallery_alt_3',
            'caption_key' => 'sagamok_flood.gallery_cap_3',
        ],
        [
            'file' => 'flood-04.jpg',
            'width' => 1300,
            'height' => 975,
            'alt_key' => 'sagamok_flood.gallery_alt_4',
            'caption_key' => 'sagamok_flood.gallery_cap_4',
        ],
    ],
];
