<?php

declare(strict_types=1);

namespace App\Support;

/**
 * JourneyEngine — Scene data and game logic for Minoo's Journey.
 *
 * Hotspot positions are stored as percentages (0.0–1.0) of the scene
 * image dimensions. This keeps coordinates resolution-independent and
 * means the client never needs to know where objects are — it sends
 * a tap percentage and the server decides if it hit something.
 *
 * Scene data lives here as a static registry for the vertical slice.
 * A future iteration can promote scenes to a Waaseyaa content entity.
 */
class JourneyEngine
{
    public const HIT_RADIUS = 0.06; // 6% of image width/height

    // --- Public API ---

    /**
     * Return metadata for all scenes in chapter 1 (no hotspot coords).
     * @return list<array{slug: string, title_en: string, title_oj: string, chapter: int, order: int}>
     */
    public static function listScenes(): array
    {
        return array_map(
            static fn(array $s) => [
                'slug'     => $s['slug'],
                'title_en' => $s['title_en'],
                'title_oj' => $s['title_oj'],
                'chapter'  => $s['chapter'],
                'order'    => $s['order'],
            ],
            self::scenes(),
        );
    }

    /**
     * Return scene data safe for the client: no hotspot coordinates.
     * @return array|null
     */
    public static function getClientScene(string $slug): ?array
    {
        $scene = self::findScene($slug);
        if ($scene === null) {
            return null;
        }

        return [
            'slug'           => $scene['slug'],
            'title_en'       => $scene['title_en'],
            'title_oj'       => $scene['title_oj'],
            'background_url' => $scene['background_url'],
            'objects'        => self::clientObjects($scene['objects']),
            'total_objects'  => count($scene['objects']),
        ];
    }

    /**
     * Check whether a tap (x, y as 0.0–1.0 percentages) hits any unfound object.
     *
     * @param list<array> $sceneObjects  Full object list from the scene definition.
     * @param list<int>   $foundIds      Object IDs already found this session.
     * @return array|null  The matched object (with labels), or null on miss.
     */
    public static function checkTap(array $sceneObjects, array $foundIds, float $x, float $y): ?array
    {
        foreach ($sceneObjects as $obj) {
            if (in_array($obj['id'], $foundIds, true)) {
                continue;
            }
            $dx = $x - $obj['hotspot']['x'];
            $dy = $y - $obj['hotspot']['y'];
            if (sqrt($dx * $dx + $dy * $dy) <= self::HIT_RADIUS) {
                return $obj;
            }
        }
        return null;
    }

    /**
     * Return the hint object (first unfound object, with its hotspot).
     * The controller strips the hotspot before sending to the client and
     * instead sends a nudge region (quadrant) so the player still has to tap.
     *
     * @param list<array> $sceneObjects
     * @param list<int>   $foundIds
     * @return array|null
     */
    public static function nextHint(array $sceneObjects, array $foundIds): ?array
    {
        foreach ($sceneObjects as $obj) {
            if (!in_array($obj['id'], $foundIds, true)) {
                return $obj;
            }
        }
        return null;
    }

    /**
     * Convert a hotspot position to a quadrant label (e.g. "top-left").
     * Sent to the client instead of the exact position.
     */
    public static function hotspotQuadrant(float $x, float $y): string
    {
        $v = $y < 0.5 ? 'top' : 'bottom';
        $h = $x < 0.5 ? 'left' : 'right';
        return "{$v}-{$h}";
    }

    /**
     * Calculate star rating (1–3) for a completed scene.
     */
    public static function calculateStars(int $timeSeconds, int $hintsUsed): int
    {
        if ($hintsUsed === 0 && $timeSeconds <= 120) {
            return 3;
        }
        if ($hintsUsed <= 1 && $timeSeconds <= 240) {
            return 2;
        }
        return 1;
    }

    /**
     * Return the full scene object list (with hotspot coords) for a slug.
     * Used internally by the controller for tap validation.
     *
     * @return list<array>|null
     */
    public static function getSceneObjects(string $slug): ?array
    {
        $scene = self::findScene($slug);
        return $scene ? $scene['objects'] : null;
    }

    /**
     * Return the narrative card for a scene.
     * @return array{text_en: string, text_oj: string}|null
     */
    public static function getNarrativeCard(string $slug): ?array
    {
        $scene = self::findScene($slug);
        return $scene ? $scene['narrative_card'] : null;
    }

    /**
     * Return the homestead unlock item for a scene (may be null).
     * @return array{key: string, label_en: string, label_oj: string}|null
     */
    public static function getHomesteadItem(string $slug): ?array
    {
        $scene = self::findScene($slug);
        return $scene['homestead_item'] ?? null;
    }

    // --- Private helpers ---

    private static function findScene(string $slug): ?array
    {
        foreach (self::scenes() as $scene) {
            if ($scene['slug'] === $slug) {
                return $scene;
            }
        }
        return null;
    }

    /**
     * Strip hotspot coordinates from objects before sending to client.
     * @param list<array> $objects
     * @return list<array>
     */
    private static function clientObjects(array $objects): array
    {
        return array_values(array_map(static fn(array $o) => [
            'id'       => $o['id'],
            'key'      => $o['key'],
            'label_en' => $o['label_en'],
            'label_oj' => $o['label_oj'],
        ], $objects));
    }

    // --- Scene registry ---

    /**
     * Chapter 1 scene definitions.
     *
     * Hotspot coordinates are placeholders (0.5, 0.5) until real background
     * art is available. Replace x/y with the actual object centre positions
     * measured as fractions of the final image width and height.
     *
     * Ojibwe labels are placeholders — replace with verified translations
     * before community review.
     *
     * @return list<array>
     */
    private static function scenes(): array
    {
        return [
            // ── Scene 1: Lakeshore Gathering ──────────────────────────────
            [
                'slug'           => 'lakeshore-gathering',
                'title_en'       => 'Lakeshore Gathering',
                'title_oj'       => '[Ojibwe title — add verified translation]',
                'chapter'        => 1,
                'order'          => 1,
                'background_url' => '/files/journey/scenes/lakeshore-gathering.jpg',
                'narrative_card' => [
                    'text_en' => 'Minoo remembers the day the lake sang.',
                    'text_oj' => '[Ojibwe line — add verified translation]',
                ],
                'homestead_item' => null,
                'objects'        => [
                    ['id' => 101, 'key' => 'birchbark-basket', 'label_en' => 'birchbark basket',  'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 102, 'key' => 'canoe-paddle',     'label_en' => 'canoe paddle',       'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 103, 'key' => 'feather',          'label_en' => 'feather',            'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 104, 'key' => 'wild-rice-pouch',  'label_en' => 'wild rice pouch',    'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 105, 'key' => 'moccasin',         'label_en' => 'moccasin',           'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 106, 'key' => 'cedar-bundle',     'label_en' => 'cedar bundle',       'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 107, 'key' => 'carved-spoon',     'label_en' => 'carved spoon',       'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 108, 'key' => 'fishing-net',      'label_en' => 'fishing net',        'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 109, 'key' => 'ribbon',           'label_en' => 'ribbon',             'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 110, 'key' => 'small-drum',       'label_en' => 'small drum',         'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 111, 'key' => 'locket',           'label_en' => 'locket',             'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 112, 'key' => 'clay-cup',         'label_en' => 'clay cup',           'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                ],
            ],

            // ── Scene 2: Winter Lodge ──────────────────────────────────────
            [
                'slug'           => 'winter-lodge',
                'title_en'       => 'Winter Lodge',
                'title_oj'       => '[Ojibwe title — add verified translation]',
                'chapter'        => 1,
                'order'          => 2,
                'background_url' => '/files/journey/scenes/winter-lodge.jpg',
                'narrative_card' => [
                    'text_en' => 'Stories are kept warm by the stove.',
                    'text_oj' => '[Ojibwe line — add verified translation]',
                ],
                'homestead_item' => null,
                'objects'        => [
                    ['id' => 201, 'key' => 'wool-blanket',     'label_en' => 'wool blanket',       'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 202, 'key' => 'snowshoes',        'label_en' => 'snowshoes',          'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 203, 'key' => 'kettle',           'label_en' => 'kettle',             'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 204, 'key' => 'beadwork-pouch',   'label_en' => 'beadwork pouch',     'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 205, 'key' => 'tobacco-bundle',   'label_en' => 'tobacco bundle',     'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 206, 'key' => 'carved-bowl',      'label_en' => 'carved bowl',        'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 207, 'key' => 'mitten',           'label_en' => 'mitten',             'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 208, 'key' => 'family-photo',     'label_en' => 'family photo',       'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 209, 'key' => 'sewing-needle',    'label_en' => 'sewing needle',      'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 210, 'key' => 'birch-bark-scroll','label_en' => 'birch bark scroll',  'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 211, 'key' => 'candle',           'label_en' => 'candle',             'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 212, 'key' => 'small-axe',        'label_en' => 'small axe',          'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                ],
            ],

            // ── Scene 3: Sugar Bush Spring ────────────────────────────────
            [
                'slug'           => 'sugar-bush-spring',
                'title_en'       => 'Sugar Bush Spring',
                'title_oj'       => '[Ojibwe title — add verified translation]',
                'chapter'        => 1,
                'order'          => 3,
                'background_url' => '/files/journey/scenes/sugar-bush-spring.jpg',
                'narrative_card' => [
                    'text_en' => 'Sweetness comes from patience and care.',
                    'text_oj' => '[Ojibwe line — add verified translation]',
                ],
                'homestead_item' => [
                    'key'      => 'sugar-shack',
                    'label_en' => 'Sugar Shack',
                    'label_oj' => '[oj]',
                ],
                'objects' => [
                    ['id' => 301, 'key' => 'sap-bucket',       'label_en' => 'sap bucket',         'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 302, 'key' => 'spile',            'label_en' => 'spile',              'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 303, 'key' => 'maple-leaf',       'label_en' => 'maple leaf',         'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 304, 'key' => 'sap-ladle',        'label_en' => 'sap ladle',          'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 305, 'key' => 'wooden-pail',      'label_en' => 'wooden pail',        'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 306, 'key' => 'rope',             'label_en' => 'rope',               'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 307, 'key' => 'carving-knife',    'label_en' => 'carving knife',      'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 308, 'key' => 'measuring-cup',    'label_en' => 'measuring cup',      'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 309, 'key' => 'woven-basket',     'label_en' => 'woven basket',       'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 310, 'key' => 'boiling-pan',      'label_en' => 'sap boiling pan',    'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 311, 'key' => 'small-bell',       'label_en' => 'small bell',         'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                    ['id' => 312, 'key' => 'journal',          'label_en' => 'journal',            'label_oj' => '[oj]', 'hotspot' => ['x' => 0.50, 'y' => 0.50]],
                ],
            ],
        ];
    }
}
