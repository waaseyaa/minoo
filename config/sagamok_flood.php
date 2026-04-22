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
    'last_verified_date' => getenv('MINOO_SAGAMOK_FLOOD_VERIFIED') ?: '2026-04-21',
    /**
     * Public gallery images under public/img/crisis/sagamok-spanish-river-flood/.
     * alt_key / caption_key are translation keys (resources/lang).
     *
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
