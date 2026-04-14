#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Backport Vol. 1 / Issue 1 content into structured newsletter_item rows.
 *
 * Each item carries a `kind` discriminator plus either `inline_body` prose
 * or a `structured` payload. The Twig template (templates/newsletter/edition.html.twig)
 * branches on `kind` to render the richer v1 PDF styling (drop caps, vocab
 * grid, word-search tables, calendars, back-page boxes, colophon, partners,
 * elder spotlight photo).
 *
 * Idempotent: clears edition 1 items first.
 *
 * Usage: php scripts/backport-edition-1-content.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\ValueObject\EditionStatus;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
(new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$lifecycle = new EditionLifecycle();

// ── 1. Find or create edition ────────────────────────────────────────────────

$editionStorage = $etm->getStorage('newsletter_edition');
$existing = array_filter(
    $editionStorage->loadMultiple(),
    static fn($e) => (int) $e->get('volume') === 1 && (int) $e->get('issue_number') === 1,
);

if ($existing !== []) {
    $edition = reset($existing);
    echo "Found existing edition neid={$edition->id()}, resetting to draft.\n";
    $edition->set('status', 'draft');
    $editionStorage->save($edition);
} else {
    echo "Creating new edition: Vol. 1, Issue 1\n";
    $edition = $editionStorage->create([
        'community_id' => null,
        'volume' => 1,
        'issue_number' => 1,
        'publish_date' => 'Spring 2026',
        'status' => 'draft',
        'created_by' => 0,
        'headline' => 'Minoo Elder Newsletter — Vol. 1, Issue 1',
    ]);
    $editionStorage->save($edition);
    echo "Created edition neid={$edition->id()}\n";
}

$editionId = (int) $edition->id();

// ── 2. Delete ALL existing items for this edition ────────────────────────────

$itemStorage = $etm->getStorage('newsletter_item');
$existingItems = array_filter(
    $itemStorage->loadMultiple(),
    static fn($i) => (int) $i->get('edition_id') === $editionId,
);

if ($existingItems !== []) {
    $itemStorage->delete(array_values($existingItems));
    echo "Deleted " . count($existingItems) . " existing item(s).\n";
}

// ── 3. Content definitions ───────────────────────────────────────────────────

$items = [];
$position = 0;

// ── SVG loader ───────────────────────────────────────────────────────────────
// Inline SVGs live in scripts/assets/newsletter-vol1-issue1/svgs/ (extracted
// from original draft HTML). They are print-ready (black strokes, no colour
// leaks) and must be embedded verbatim in the rendered HTML so the PDF
// renderer sees them.
$svgDir = __DIR__ . '/assets/newsletter-vol1-issue1/svgs';
$loadSvg = static function (string $name) use ($svgDir): string {
    $path = $svgDir . '/' . $name . '.svg';
    if (!is_file($path)) {
        return '';
    }
    return trim((string) file_get_contents($path));
};

/**
 * Add a newsletter item. $payload keys:
 *   kind           (string)  — 'prose' (default), 'drop_cap', 'vocab_grid',
 *                              'word_search', 'calendar', 'back_page_box',
 *                              'colophon', 'partners', 'spotlight'
 *   title          (string)  — inline_title
 *   body           (string)  — inline_body HTML (prose/drop_cap/back_page_box/colophon/spotlight)
 *   structured     (array)   — JSON payload for vocab_grid/word_search/calendar/partners
 *   media_url      (string)
 *   media_alt      (string)
 *   media_caption  (string)
 *   blurb          (string)  — editor_blurb (defaults to title)
 */
$addItem = function (string $section, array $payload) use (&$items, &$position, $editionId): void {
    $kind  = $payload['kind']  ?? 'prose';
    $title = $payload['title'] ?? '';
    $body  = $payload['body']  ?? '';
    $structured = $payload['structured'] ?? null;

    // Split "<p class="source-line"><em>{DATE}. Source: {SRC}</em></p>" so the
    // date lifts to the top of the article and the source stays at the bottom.
    $dateLine = '';
    if ($body !== '' && preg_match(
        '#<p class="source-line"><em>([^<]+?)\.\s*Source:\s*([^<]+)</em></p>#',
        $body,
        $m,
    )) {
        $dateLine = trim($m[1]);
        $body = preg_replace(
            '#<p class="source-line"><em>[^<]+?\.\s*Source:\s*([^<]+)</em></p>#',
            '<p class="source-line"><em>Source: $1</em></p>',
            $body,
        );
    }

    $items[] = [
        'edition_id'    => $editionId,
        'position'      => ++$position,
        'section'       => $section,
        'source_type'   => 'inline',
        'source_id'     => 0,
        'kind'          => $kind,
        'inline_title'  => $title,
        'inline_body'   => $body,
        'structured'    => $structured,
        'media_url'     => $payload['media_url']     ?? '',
        'media_alt'     => $payload['media_alt']     ?? '',
        'media_caption' => $payload['media_caption'] ?? '',
        'date_line'     => $dateLine,
        'kicker'        => $payload['kicker'] ?? '',
        'editor_blurb'  => $payload['blurb'] ?? ($title ?: $kind),
        'included'      => 1,
    ];
};

// ── COVER (Page 1) ──────────────────────────────────────────────────────────

$addItem('cover', [
    'kind' => 'welcome',
    'title' => 'Welcome',
    'body' => <<<'HTML'
<p>I created this newsletter to bring you local news, upcoming events, teachings, language, humour, and puzzles every month. I hope you enjoy it.</p>
HTML,
]);

$addItem('cover', [
    'kind' => 'toc',
    'title' => 'Inside This Issue',
    'structured' => [
        'entries' => [
            ['label' => "Keeper's Note",       'page' => 2],
            ['label' => 'Community News',      'page' => 3],
            ['label' => 'Events Calendar',     'page' => 5],
            ['label' => 'Teachings: Ziigwan',  'page' => 6],
            ['label' => 'Language Corner',     'page' => 7],
            ['label' => 'Our Territory',       'page' => 8],
            ['label' => 'Jokes & Humour',      'page' => 9],
            ['label' => 'Puzzles',             'page' => 10],
            ['label' => 'Clan Horoscopes',     'page' => 11],
            ['label' => 'Elder Spotlight',     'page' => 12],
            ['label' => 'Reader Mail',         'page' => 15],
            ['label' => 'About & Contact',     'page' => 16],
        ],
    ],
]);

// ── KEEPER'S NOTE (Page 2) — drop cap ───────────────────────────────────────

$addItem('editors_note', [
    'kind' => 'drop_cap',
    'title' => "Keeper's Note",
    'body' => <<<'HTML'
<p>Aanii! My name is Russell Jones. I am a software developer from Sagamok Anishnawbek and I build technology for Indigenous communities. For a long time I have been trying to figure out how to put those skills to work closer to home. This is one answer.</p>

<p>I built this newsletter with Minoo Live, a community platform I created for Anishinaabe communities. Minoo Live is not owned by a corporation in California or Toronto. It was built here, by one of us, and it stays here. The data belongs to the communities it serves. Nobody else.</p>

<p>Think about what happens when we rely on platforms we do not control. In 2023, Meta blocked news from Facebook and Instagram across Canada. That same summer, wildfires tore through the north and forced remote Indigenous communities to evacuate — and the emergency updates, evacuation notices, and shelter information that our people share through Facebook groups were blocked along with everything else. Your photos, your event pages, your community group, all of it sits on a server in California where someone else decides the rules. They can change those rules any time they want. They already did.</p>

<p>Our people should control our own technology the same way we control our own land, our own water, and our own stories. Minoo Live keeps community data in community hands, and what appears online also arrives in your hands in print.</p>

<p>This first issue is a beginning. It will grow with your ideas and your words. If you have a teaching to pass along or a joke that makes you laugh every time, send it our way. Contact information is on the back page.</p>

<p>Miigwech for picking this up.</p>

<p><em>Russell Jones<br>Sagamok Anishnawbek</em></p>
HTML,
]);

// ── NEWS (Page 3) — 4 prose articles ────────────────────────────────────────

$addItem('news', [
    'kind' => 'news_lead',
    'kicker' => 'Treaty Rights',
    'title' => 'Treaty Chiefs Say No to Herbicide Spraying',
    'body' => <<<'HTML'
<p>Leaders of the 21 Robinson Huron Treaty First Nations issued a public notice: forestry companies do not have consent to conduct aerial or ground-based herbicide spraying in treaty territory. Interfor confirmed it will not spray in 2026, but chiefs want a permanent moratorium on glyphosate. The herbicide kills sage, sweetgrass, and cedar. Hunters have reported moose with signs of cancer in sprayed areas.</p>
<p class="source-line"><em>April 8, 2026. Source: Turtle Island News, Anishinabek News</em></p>
HTML,
]);

$addItem('news', [
    'title' => 'Over 1,000 Gather for Anishinaabemowin',
    'body' => <<<'HTML'
<p>The annual Anishinaabemowin Teg gathering brought more than 1,000 people together in London, Ontario from March 26 to 28. The conference focused on helping youth learn the language through workshops, speakers, and community activities. Anishinaabemowin is spoken by less than one per cent of our population. Gatherings like this are how we change that.</p>
<p class="source-line"><em>March 26–28, 2026. Source: Anishinaabemowin Teg</em></p>
HTML,
]);

$addItem('news', [
    'title' => '22 First Nations Taking Over Child Welfare',
    'body' => <<<'HTML'
<p>Twenty-two Anishinabek First Nations have chosen to enact the Anishinabek Nation Child Well-Being Law. The law shifts child welfare from a protection model to a prevention model. Communities will design services based on their own laws, traditions, and priorities. The goal is to keep children with their families and in their communities. After decades of outside agencies making decisions about our kids, First Nations are taking that responsibility back.</p>
<p class="source-line"><em>April 2026. Source: Anishinabek News, Manitoulin Expositor</em></p>
HTML,
]);

$addItem('news', [
    'title' => 'Sagamok Councillor Shares Teachings at Zhiibaahaasing',
    'body' => <<<'HTML'
<p>Sagamok Anishnawbek Councillor Leroy Bennett joined Nokomis Martina Osawamick for the Zhiibaahaasing Cultural Speaker Series on March 23. The session, "Stages of Life and Ceremonies," was held at the Zhiibaahaasing Complex in Wiikwemkoong Unceded Territory. These teachings connect the stages of life to ceremony and remind us that every age carries its own responsibilities.</p>
<p class="source-line"><em>March 23, 2026. Source: Anishinabek News</em></p>
HTML,
]);

// ── EVENTS (Page 4) — 1 calendar + 7 prose ──────────────────────────────────

// May 2026 mini calendar. Week rows are Mo–Su. First cell is empty because
// May 1 2026 is a Friday. has-event days carry a hint label.
$addItem('events', [
    'kind' => 'calendar',
    'title' => 'May 2026',
    'structured' => [
        'month' => 'May 2026',
        'weekdays' => ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
        'weeks' => [
            [ null,                          null,                          null,                          null,                          ['day' => 1,  'event' => null],                ['day' => 2,  'event' => null],                ['day' => 3,  'event' => null] ],
            [ ['day' => 4,  'event' => null], ['day' => 5,  'event' => null], ['day' => 6,  'event' => 'Adult Ed'], ['day' => 7,  'event' => null], ['day' => 8,  'event' => null], ['day' => 9,  'event' => 'Spring Market'], ['day' => 10, 'event' => null] ],
            [ ['day' => 11, 'event' => null], ['day' => 12, 'event' => 'Poker Run'], ['day' => 13, 'event' => 'Adult Ed'], ['day' => 14, 'event' => null], ['day' => 15, 'event' => null], ['day' => 16, 'event' => "M'Chigeeng Election"], ['day' => 17, 'event' => null] ],
            [ ['day' => 18, 'event' => 'Angling Fair'], ['day' => 19, 'event' => 'Angling Fair'], ['day' => 20, 'event' => 'Adult Ed'], ['day' => 21, 'event' => null], ['day' => 22, 'event' => null], ['day' => 23, 'event' => null], ['day' => 24, 'event' => null] ],
            [ ['day' => 25, 'event' => null], ['day' => 26, 'event' => 'Family Fun'], ['day' => 27, 'event' => 'Adult Ed'], ['day' => 28, 'event' => null], ['day' => 29, 'event' => null], ['day' => 30, 'event' => null], ['day' => 31, 'event' => null] ],
        ],
    ],
]);

$addItem('events', [
    'title' => 'Wednesday Evenings: Adult Education with Anna',
    'body' => <<<'HTML'
<p class="event-detail"><strong>When:</strong> Every Wednesday, 6:00 PM – 8:00 PM</p>
<p class="event-detail"><strong>Where:</strong> Sagamok Community Centre</p>
<p>Weekly adult ed sessions with Anna — a mix of practical skills and good company. Everyone is welcome.</p>
HTML,
]);

$addItem('events', [
    'title' => 'Providence Bay Spring Market',
    'body' => <<<'HTML'
<p class="event-detail"><strong>When:</strong> Saturday, May 9, 10:00 AM – 3:00 PM</p>
<p class="event-detail"><strong>Where:</strong> Providence Bay Exhibition Hall, 10 Firehall Road</p>
<p>Local vendors, crafts, and spring finds. A good reason to get out to Manitoulin.</p>
HTML,
]);

$addItem('events', [
    'title' => 'Wiikwemkoong Spring Poker Run',
    'body' => <<<'HTML'
<p class="event-detail"><strong>When:</strong> May 12, 10:00 AM</p>
<p class="event-detail"><strong>Where:</strong> Departs from South Bay Centre, Wiikwemkoong</p>
<p>Sponsored by Wiikwemkoong Anglers. A fun day out on the water as the season opens up.</p>
HTML,
]);

$addItem('events', [
    'title' => "M'Chigeeng First Nation Election",
    'body' => <<<'HTML'
<p class="event-detail"><strong>When:</strong> May 16, 2026</p>
<p class="event-detail"><strong>Where:</strong> M'Chigeeng First Nation</p>
<p>Community members will go to the polls to elect Chief and Council.</p>
HTML,
]);

$addItem('events', [
    'title' => 'Manitoulin Streams Angling Trade Fair',
    'body' => <<<'HTML'
<p class="event-detail"><strong>When:</strong> May 18 – 19</p>
<p class="event-detail"><strong>Where:</strong> Kagawong, Manitoulin Island</p>
<p>Outdoor gear, fishing demonstrations, and conservation talks. Open to all ages.</p>
HTML,
]);

$addItem('events', [
    'title' => 'Family Fun Screening Day',
    'body' => <<<'HTML'
<p class="event-detail"><strong>When:</strong> Sunday, May 26, 10:00 AM – 2:00 PM</p>
<p class="event-detail"><strong>Where:</strong> Low Island, Little Current</p>
<p>Hosted by the Manitoulin Service Provider Network — development screening, car seat clinic, refreshments, and family activities.</p>
HTML,
]);

// ── TEACHINGS (Page 5) — 3 prose ────────────────────────────────────────────

$addItem('teachings', [
    'kind' => 'teaching_block',
    'title' => 'Ziigwan: The Time of New Growth',
    'structured' => [
        'icon_svg' => $loadSvg('crow'),
        'banner_svg' => $loadSvg('spring_scene'),
        'drop_cap' => true,
    ],
    'body' => <<<'HTML'
<p>Spring is known as Ziigwan in Anishinaabemowin. The earth wakes up and the cycle of life begins again. The snow melts, the sap runs, the birds return from the south, and the medicines start to come up through the ground.</p>
<p>Our ancestors knew this season by its signs, not by a calendar date. When the crows returned, it meant the cold was breaking. When the frogs started singing at night, it was time to prepare the sugar bush. These teachings connect us to the land.</p>
HTML,
]);

$addItem('teachings', [
    'kind' => 'teaching_block',
    'title' => 'Iskigamizigan: The Sugar Bush',
    'structured' => [
        'icon_svg' => $loadSvg('maple'),
    ],
    'body' => <<<'HTML'
<p>One of the most important spring activities is making maple syrup. The Anishinaabe have been harvesting maple sap and making sugar since time immemorial. The sugar bush, iskigamizigan, is a place of gathering, hard work, and teaching.</p>
<p>Elders have always said the sugar bush is where young people learn patience. You cannot rush the sap. You tend the fire, you watch the boil, and you wait. That lesson applies to much more than syrup.</p>
HTML,
]);

$addItem('teachings', [
    'kind' => 'teaching_block',
    'title' => 'Spring Medicines',
    'structured' => [
        'icon_svg' => $loadSvg('medicine'),
    ],
    'body' => <<<'HTML'
<p>As the snow recedes, the first medicines begin to appear. Wiigwaas (birch bark) can be carefully harvested in spring for teas and remedies. The young shoots of nettles, the early greens. These are gifts from the land that our grandparents relied on.</p>
<p><em>Know a spring medicine teaching? Share it with us — this section grows with your voices.</em></p>
HTML,
]);

// ── LANGUAGE (Page 6) — featured word + vocab grid ──────────────────────────

$addItem('language', [
    'title' => 'Ziigwan',
    'body' => <<<'HTML'
<p><strong>Ziigwan</strong> — <em>ZEE-gwun</em></p>
<p><strong>Spring</strong></p>
<p>"Ziigwan bi-dgoshin." – Spring is arriving.</p>
HTML,
    'blurb' => 'Featured Word: Ziigwan (Spring)',
    'structured' => [
        'svg' => $loadSvg('spring_scene'),
    ],
]);

$addItem('language_corner', [
    'kind' => 'vocab_grid',
    'title' => 'Anishinaabemowin Corner',
    'body' => <<<'HTML'
<p>Try using one this week with your family or at the next community gathering. We want to include more Anishinaabemowin in every issue. If you are a speaker or language learner and would like to contribute words, phrases, or short lessons, this is your page. We especially welcome contributions from our Elders who carry the language.</p>
HTML,
    'structured' => [
        'cards' => [
            ['word' => 'Iskigamizige', 'pronunciation' => 'iss-kih-GAH-mih-zih-gay', 'meaning' => 'To make maple sugar', 'example' => null, 'svg' => $loadSvg('maple')],
            ['word' => 'Namebin',      'pronunciation' => 'nah-MEH-bin',             'meaning' => 'Sucker fish',          'example' => null, 'svg' => $loadSvg('sucker')],
            ['word' => 'Aandeg',       'pronunciation' => 'AHN-deg',                  'meaning' => 'Crow',                 'example' => null, 'svg' => $loadSvg('crow')],
            ['word' => 'Gimiwan',      'pronunciation' => 'gih-MIH-wun',              'meaning' => 'It is raining',        'example' => null, 'svg' => $loadSvg('rain')],
        ],
    ],
]);

// ── OUR TERRITORY (Page 8) — Robinson Huron Treaty orientation ──────────────

$addItem('territory', [
    'title' => 'Our Territory',
    'kind' => 'territory_list',
    'structured' => [
        'epigraph' => 'We are the people of the North Shore. Here is where we belong.',
        'intro' => 'The Robinson Huron Treaty of 1850 is the agreement between our ancestors and the Crown that shapes the North Shore of Lake Huron to this day. Twenty-one First Nations are signatories. These are our relatives — the communities whose names we carry with us.',
        'nations' => [
            ['name' => 'Aamjiwnaang',                 'meaning' => 'at the spawning stream',                 'note' => ''],
            ['name' => 'Atikameksheng Anishnawbek',   'meaning' => 'people of the whitefish',                'note' => 'formerly Whitefish Lake'],
            ['name' => 'Batchewana',                  'meaning' => 'current that turns back on itself',      'note' => ''],
            ['name' => 'Beausoleil',                  'meaning' => '',                                       'note' => 'Christian Island, Georgian Bay'],
            ['name' => 'Dokis',                       'meaning' => '',                                       'note' => 'on the French River'],
            ['name' => 'Garden River',                'meaning' => 'Ketegaunseebee — garden river',          'note' => ''],
            ['name' => 'Henvey Inlet',                'meaning' => '',                                       'note' => ''],
            ['name' => 'Magnetawan',                  'meaning' => 'swiftly flowing river',                  'note' => ''],
            ['name' => 'Mississauga',                 'meaning' => 'river with many mouths',                 'note' => ''],
            ['name' => 'Nipissing',                   'meaning' => 'at the little water',                    'note' => ''],
            ['name' => 'Ojibways of Pic River',       'meaning' => 'Biigtigong Nishnaabeg',                  'note' => ''],
            ['name' => 'Pic Mobert',                  'meaning' => '',                                       'note' => 'north of Lake Superior'],
            ['name' => 'Sagamok Anishnawbek',         'meaning' => 'where the rivers join',                  'note' => ''],
            ['name' => 'Serpent River',               'meaning' => 'Genaabaajing — winding river',           'note' => ''],
            ['name' => 'Sheguiandah',                 'meaning' => '',                                       'note' => 'Manitoulin Island'],
            ['name' => 'Sheshegwaning',               'meaning' => 'place of the rattle',                    'note' => 'Manitoulin Island'],
            ['name' => 'Thessalon',                   'meaning' => '',                                       'note' => ''],
            ['name' => 'Wahnapitae',                  'meaning' => 'shaped like a molar tooth',              'note' => ''],
            ['name' => "Wikwemikong",                 'meaning' => 'bay of the beaver',                      'note' => 'unceded — Manitoulin Island'],
            ['name' => 'Whitefish River',             'meaning' => '',                                       'note' => 'Birch Island'],
            ['name' => 'Zhiibaahaasing',              'meaning' => 'place where the wind blows through',     'note' => 'Manitoulin Island'],
        ],
        'closing' => 'When you read a name on this page, you are reading a piece of the land itself. Every one of these communities is family to us.',
    ],
]);

// ── JOKES (Page 8) — single prose block ─────────────────────────────────────

$addItem('jokes', [
    'title' => 'Jokes & Humour',
    'body' => <<<'HTML'
<div class="joke">
<p><strong>Rez Humour</strong></p>
<p>Why did the moose cross Highway 17?</p>
<p>To prove to the partridge it could be done.</p>
</div>

<div class="joke">
<p>My kookum said the secret to a long life is bannock every day.</p>
<p>The doctor said it's vegetables.</p>
<p>I'm going with kookum.</p>
</div>

<div class="joke">
<p>Elder at the community meeting: "Back in my day we didn't have Wi-Fi. We had to walk to our cousin's house to find out what was for supper."</p>
</div>

<div class="joke">
<p>How do you know spring has arrived on the rez?</p>
<p>The mudroom actually has mud in it.</p>
</div>

<div class="joke">
<p>My cousin said the Band Office finally got back to him.</p>
<p>I asked what they said.</p>
<p>He said, "They told me to come back Tuesday."</p>
<p>I said, "Which Tuesday?"</p>
<p>He said, "Exactly."</p>
</div>

<p><strong>Got a Good One?</strong></p>
<p>Send us your best jokes, funny stories, or rez humour for next month's issue. Keep it clean. Kookum is reading this. Contact details are on the back page.</p>
HTML,
]);

// ── PUZZLES (Page 9) — word_search + riddle/scramble prose ──────────────────

$addItem('puzzles', [
    'kind' => 'word_search',
    'title' => 'Word Search: Signs of Spring',
    'body' => '<p>Find the hidden words in the grid. Words can go across, down, or diagonally.</p>',
    'structured' => [
        'grid' => [
            ['Z','I','I','G','W','A','N','M','S'],
            ['G','I','M','I','W','A','N','A','O'],
            ['A','K','W','E','N','Z','I','I','N'],
            ['N','A','M','E','B','I','N','N','G'],
            ['M','S','H','K','I','K','I','G','B'],
            ['A','K','I','G','O','N','Z','A','I'],
            ['A','A','N','D','E','G','H','N','R'],
            ['W','I','I','G','W','A','A','S','D'],
            ['M','I','G','I','Z','I','K','E','S'],
        ],
        'words' => [
            'ZIIGWAN (Spring)',
            'GIMIWAN (Rain)',
            'NAMEBIN (Sucker)',
            'MSHKIKI (Medicine)',
            'AANDEG (Crow)',
            'WIIGWAAS (Birch)',
            'MIGIZI (Eagle)',
            'SONGBIRD',
            'AKI (Earth)',
        ],
    ],
]);

$addItem('puzzles', [
    'title' => 'Riddle of the Month',
    'body' => <<<'HTML'
<p>I have no mouth, but I carry the community's voice.<br>
I have no feet, but I walk from house to house.<br>
I come back every moon with something new to say,<br>
and I am only worth what the people put in me.</p>
<p>What am I?</p>
<p><em>Answer in next month's issue. Send your guess to the address on the back page — first correct reply gets a mention.</em></p>
HTML,
]);

$addItem('puzzles', [
    'title' => 'Word Scramble',
    'body' => <<<'HTML'
<p>Unscramble these Anishinaabemowin words:</p>
<p>1. <strong>GIIMAA</strong> (hint: a leader)<br>
2. <strong>NIIBNA</strong> (hint: summer)<br>
3. <strong>MKAWDE</strong> (hint: black)</p>
<p><em>(Answers in next month's issue!)</em></p>
HTML,
]);

// ── HOROSCOPES (Page 10) — 7 prose (one per clan) ───────────────────────────

$horoscopes = [
    ['Makwa: Bear Clan',         'Bear is waking up hungry and a little stiff. So are you. This month someone close is going to need your steady presence more than your advice. Sit with them. The medicine you carry is not in what you say.'],
    ['Migizi: Eagle Clan',       'Eagle sees the whole valley from one pine. Pull back and look at the full picture before you answer that message you have been turning over. A decision is coming that is bigger than it looks. Trust the long view.'],
    ["Ma'iingan: Wolf Clan",     "The pack is calling. There is someone you have not spoken to since before freeze-up, and you both know who. Make the first move. Wolves do not wait for the trail to be perfect — they walk it together and the trail becomes clear."],
    ['Waabizheshi: Marten Clan', "Marten is quick, curious, and finishes what it starts. That project you put down in January is ready for the next step, and you already know what the next step is. Stop researching. Begin."],
    ['Ajijaak: Crane Clan',      'Crane speaks for the people, which means Crane also has to listen first. Your words carry weight this month, especially in a hard conversation. Choose them the way you would choose kindling — small, dry, and enough to start a fire that warms the room.'],
    ['Giigoonh: Fish Clan',      'The fish are running and so is your mind. You have been thinking too much. Get outside, put your feet on the ground, and let the water carry some of that weight. The answer you are looking for shows up when you stop looking so hard.'],
    ['Bineshiinh: Bird Clan',    'The songbirds have come back singing something slightly different this year. So will you. If there is a creative thing you set aside — a drum, a beading project, a story you never finished writing — this is the month it comes back up. Do not be precious about it. Start rough.'],
];
foreach ($horoscopes as [$title, $text]) {
    $addItem('horoscope', [
        'title' => $title,
        'body' => '<p>' . $text . '</p>',
    ]);
}

// ── ELDER SPOTLIGHT (Page 11) — spotlight with photo ────────────────────────

$addItem('elder_spotlight', [
    'kind' => 'spotlight',
    'title' => 'Grace Manitowabi: Keeper of the Land',
    'media_url' => '/newsletter/vol1-issue1/grace-manitowabi.jpg',
    'media_alt' => 'Elder Grace Manitowabi',
    'media_caption' => 'Grace Manitowabi, Sagamok Anishnawbek',
    'body' => <<<'HTML'
<p>If you have been to a community gathering in Sagamok in the last few years, you have probably heard Grace Manitowabi's voice before you heard anyone else's. She is usually the one asked to open with prayer, and she is usually the one who — gently, without raising her voice — reminds the room why we are there.</p>

<p>Grace is a Traditional Ecological Knowledge Elder. That phrase sounds bigger than it needs to. What it means, in her hands, is that she knows the plants by their right names, she knows which ones are medicine and which ones are food and which ones to leave alone, and she knows what time of year to pay attention. She did not learn that from a book. She learned it from her grandmothers, and from the bush, and from years of sitting quietly enough that the land could tell her something.</p>

<p>Readers of page 3 will already know that the Robinson Huron Treaty Chiefs have been pushing back against aerial herbicide spraying across our treaty lands. Grace has been one of the steady voices in that work for a long time — not on a podium, but on the phone, at the kitchen table, and beside the highway, organizing a billboard campaign so that every driver passing through treaty territory would see the message: <em>stop spraying our medicines.</em> When hunters came home and said they were seeing things in the moose they had never seen before, Grace made sure that was not dismissed as a story.</p>

<p>Ask people in Sagamok about her and they do not usually lead with any of that. They lead with how she treats the young ones who come to her with questions. They lead with her laugh, which arrives a half-second before you expect it. They lead with the fact that when the new Anishinabek Police Services detachment opened here in 2023, it was Grace who offered the opening prayer — and everyone in the room understood why.</p>

<p class="pull-quote">She does not say it like a political position. She says it like a fact she already knew when she was small.</p>

<p>Her teachings are not only about the land. She speaks often about Minigoziwin, our inherent sovereignty, and about the idea that our communities should be the ones making decisions about our children, our medicines, and our future.</p>

<p>We are grateful for Elders like Grace who do not wait for permission to protect what matters, and who carry themselves so that the rest of us can see what it looks like to stand for something without making noise about it.</p>

<p><em>Miigwech, Grace.</em></p>

<p class="spotlight-cta">Know an Elder who should be featured in a future issue? Write to us through Minoo Live or by email. We will reach out with care, and we only publish with the Elder's permission.</p>
HTML,
]);

// ── READER MAIL & NEXT ISSUE (Page 15) ──────────────────────────────────────

$addItem('reader_mail', [
    'kind' => 'prose',
    'title' => 'Reader Mail & Next Issue',
    'body' => <<<'HTML'
<p>This is a first issue. That means everything in these pages is an opening — an invitation for you to send us what you have. Here is what we are asking for in Issue 2.</p>

<p><strong>The Riddle of the Month.</strong> Send your guess to the address on the back page. The first correct reply gets a mention in the next issue.</p>

<p><strong>Sugar bush memories.</strong> A story, a name, a photograph, a recipe. Whatever you have. We will carry it in the way it was given.</p>

<p><strong>Elder Spotlight nominations.</strong> Know an Elder whose story should be told? Tell us who and why. We reach out with care, and we only publish with their permission.</p>

<p><strong>Spring medicine teachings.</strong> If you carry knowledge about the early plants and you are willing to share what can be shared, this is your page.</p>

<p><strong>Jokes and rez humour.</strong> Clean enough for kookum to read. Original or passed down, doesn't matter.</p>

<p><strong>Anishinaabemowin contributions.</strong> Words, phrases, short lessons, pronunciation recordings — anything a learner could use.</p>

<p><strong>Coming in Issue 2:</strong> The Wiikwemkoong Traditional Pow Wow preview, an expanded Language Corner, answers to this month's riddle and scramble, and the first reader-submitted Sugar Bush memory.</p>

<p class="next-issue-close"><em>Miigwech for reading. See you at the mailbox.</em></p>
HTML,
]);

// ── BACK PAGE (Page 16) — back_page_box + partners + colophon ───────────────

$addItem('back_page', [
    'kind' => 'back_page_box',
    'title' => 'About This Newsletter',
    'body' => <<<'HTML'
<p>This newsletter is generated by Minoo Live, a community platform built by Anishinaabe people for Indigenous communities. Content is produced and curated at <strong>minoo.live</strong>. Community members submit events, stories, and language contributions through the platform. Minoo Live assembles each issue, renders it as a print-ready PDF, and sends it to the printer.</p>

<p>Everything you read in this newsletter started as community data, entered by community members, stored on community infrastructure. No corporate middleman. No data leaving the territory.</p>

<p>This newsletter is free.</p>

<p><strong>Contribute</strong></p>
<p class="contact-line"><strong>Submit content:</strong> minoo.live</p>
<p class="contact-line"><strong>Email:</strong> russell@web.net</p>
<p class="contact-line"><strong>Events, teachings, language, jokes, Elder Spotlight nominations:</strong><br>Submit through Minoo Live or email. We publish what the community sends us.</p>
HTML,
]);

$addItem('back_page', [
    'kind' => 'partners',
    'title' => 'Partners',
    'structured' => [
        'partners' => [
            ['name' => 'Waaseyaa', 'url' => null, 'svg' => $loadSvg('waaseyaa')],
            ['name' => 'OIATC',    'url' => null, 'svg' => $loadSvg('oiatc')],
            ['name' => 'OJ Graphix (Printer)', 'url' => null, 'svg' => ''],
        ],
    ],
]);

$addItem('back_page', [
    'kind' => 'colophon',
    'title' => '',
    'body' => <<<'HTML'
<p><strong>Minoo Newsletter</strong><br>Vol. 1, Issue 1 · Spring 2026<br>Printed by OJ Graphix, Espanola ON</p>
<p><em>Miigwech for reading. See you next month.</em></p>
HTML,
]);

// ── 4. Persist all items ─────────────────────────────────────────────────────

echo "\nCreating " . count($items) . " newsletter items...\n\n";

$bySection = [];
foreach ($items as $data) {
    $item = $itemStorage->create($data);
    $itemStorage->save($item);

    $section = $data['section'];
    $bySection[$section] = ($bySection[$section] ?? 0) + 1;

    echo sprintf(
        "  [%2d] %-18s %-14s %s\n",
        $data['position'],
        $section,
        '(' . $data['kind'] . ')',
        mb_substr($data['inline_title'] !== '' ? $data['inline_title'] : $data['kind'], 0, 60),
    );
}

// ── 5. Transition edition to "curating" ──────────────────────────────────────

$lifecycle->transition($edition, EditionStatus::Curating);
$editionStorage->save($edition);

echo "\n--- SUMMARY ---\n";
echo "Edition neid={$editionId}: {$edition->get('headline')}\n";
echo "Status: {$edition->get('status')}\n";
echo "Total items: " . count($items) . "\n";
echo "By section:\n";
foreach ($bySection as $section => $count) {
    echo sprintf("  %-18s %d item(s)\n", $section, $count);
}
echo "\nDone. Edition is in 'curating' status, ready for PDF generation.\n";
