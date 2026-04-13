<?php
declare(strict_types=1);

return [
    'enabled' => filter_var($_ENV['NEWSLETTER_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),

    'mode' => 'regional',

    'default_community' => 'manitoulin-regional',

    'regional_cover_communities' => ['wiikwemkoong', 'sheguiandah', 'aundeck'],

    'sections' => [
        'news'      => ['quota' => 3, 'sources' => ['post']],
        'events'    => ['quota' => 5, 'sources' => ['event']],
        'teachings' => ['quota' => 2, 'sources' => ['teaching']],
        'language'  => ['quota' => 1, 'sources' => ['dictionary_entry']],
        'community' => ['quota' => 4, 'sources' => ['newsletter_submission']],
    ],

    // Inline sections are hand-authored, not assembled from entities.
    // Seeded via scripts/seed-inline-sections.php as newsletter_item rows
    // with inline_title + inline_body (no source_type/source_id).
    // Order here = print order within the edition.
    'inline_sections' => [
        'cover'           => ['label' => 'Cover'],
        'editors_note'    => ['label' => "Editor's Note"],
        'language_corner' => ['label' => 'Anishinaabemowin Corner'],
        'jokes'           => ['label' => 'Jokes & Humour'],
        'puzzles'         => ['label' => 'Puzzles'],
        'horoscope'       => ['label' => 'Anishinaabe Horoscope'],
        'elder_spotlight'  => ['label' => 'Elder Spotlight'],
        'back_page'       => ['label' => 'Back Page'],
    ],

    'communities' => [
        'manitoulin-regional' => [
            'mode' => 'regional',
            'printer_email' => 'sales@ojgraphix.com',
            'printer_name' => 'OJ Graphix',
            'printer_phone' => '(705) 869-0199',
            'printer_address' => '7 Panache Lake Road, Espanola, ON P5E 1H9',
            'printer_notes' => 'Hightail uplink fallback: spaces.hightail.com/uplink/OJUpload. Confirm PDF/X requirements before first job.',
            'editor_emails' => [],
        ],
    ],

    'pdf' => [
        'format' => 'Letter',
        'margins' => ['top' => '0.5in', 'right' => '0.5in', 'bottom' => '0.5in', 'left' => '0.5in'],
        'timeout_seconds' => 60,
    ],

    'printer_email' => $_ENV['NEWSLETTER_PRINTER_EMAIL'] ?? 'sales@ojgraphix.com',

    'storage_dir' => 'storage/newsletter',
];
