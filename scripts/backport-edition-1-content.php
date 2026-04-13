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
        'editor_blurb'  => $payload['blurb'] ?? ($title ?: $kind),
        'included'      => 1,
    ];
};

// ── COVER (Page 1) ──────────────────────────────────────────────────────────

$addItem('cover', [
    'kind' => 'prose',
    'title' => 'Minoo Elder Newsletter — Vol. 1, Issue 1',
    'body' => <<<'HTML'
<p>I created this newsletter to bring you local news, upcoming events, teachings, language, humour, and puzzles every month. I hope you enjoy it.</p>

<p><strong>Inside This Issue</strong></p>
<p>
Keeper's Note — 2<br>
Community News — 3<br>
Events Calendar — 4<br>
Teachings: Spring on the Land — 5<br>
Language Corner — 6<br>
Remember When — 7<br>
Jokes &amp; Humour — 8<br>
Puzzles — 9<br>
Clan Horoscopes — 10<br>
Elder Spotlight — 11<br>
About &amp; Contact — 12
</p>
HTML,
]);

// ── KEEPER'S NOTE (Page 2) — drop cap ───────────────────────────────────────

$addItem('editors_note', [
    'kind' => 'drop_cap',
    'title' => "Keeper's Note",
    'body' => <<<'HTML'
<p>Aanii! My name is Russell Jones. I am a software developer from Sagamok Anishnawbek and I build technology for Indigenous communities. For a long time I have been trying to figure out how to put those skills to work closer to home. This is one answer.</p>

<p>I built this newsletter with Minoo Live, a community platform I created for Anishinaabe communities. Minoo Live is not owned by a corporation in California or Toronto. It was built here, by one of us, and it stays here. The data belongs to the communities it serves. Nobody else.</p>

<p>Think about what happens when we rely on platforms we do not control. In 2023 Meta blocked news from Facebook and Instagram across Canada. Communities that depended on Facebook to share updates lost that overnight. Your photos, your event pages, your community group, all of it sitting on a server in California where someone else decides the rules. They can change those rules any time they want. They already did.</p>

<p>Our people should control our own technology the same way we control our own land, our own water, and our own stories. Minoo Live keeps community data in community hands. The newsletter you are holding is generated from the same system. What appears online also arrives in your hands in print.</p>

<p>This first issue is a beginning. It will grow with your ideas and your words. If you have a teaching to pass along or a joke that makes you laugh every time, send it our way. Contact information is on the back page.</p>

<p>Miigwech for picking this up.</p>

<p><em>Russell Jones<br>Sagamok Anishnawbek</em></p>
HTML,
]);

// ── NEWS (Page 3) — 4 prose articles ────────────────────────────────────────

$addItem('news', [
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
<p>Anna is hosting weekly adult education sessions covering a range of topics. Whether you are looking to build new skills or just want to spend an evening learning with your neighbours, everyone is welcome.</p>
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
<p>Hosted by the Manitoulin Service Provider Network. Development screening, car seat clinic, refreshments, and activities for the whole family.</p>
HTML,
]);

$addItem('events', [
    'title' => 'Coming Up: Wiikwemkoong Traditional Pow Wow',
    'body' => <<<'HTML'
<p class="event-detail"><strong>When:</strong> Third weekend of June</p>
<p class="event-detail"><strong>Where:</strong> Thunderbird Park, Wiikwemkoong</p>
<p>The annual Traditional Pow Wow returns. Hosted in rotation by one of Wiikwemkoong's satellite communities. Details in next month's issue.</p>
HTML,
]);

// ── TEACHINGS (Page 5) — 3 prose ────────────────────────────────────────────

$addItem('teachings', [
    'title' => 'Ziigwan: The Time of New Growth',
    'body' => <<<'HTML'
<p>Spring is known as Ziigwan in Anishinaabemowin. The earth wakes up and the cycle of life begins again. The snow melts, the sap runs, the birds return from the south, and the medicines start to come up through the ground.</p>
<p>Our ancestors knew this season by its signs, not by a calendar date. When the crows returned, it meant the cold was breaking. When the frogs started singing at night, it was time to prepare the sugar bush. These teachings connect us to the land. They remind us we are part of something much older than ourselves.</p>
HTML,
]);

$addItem('teachings', [
    'title' => 'Iskigamizigan: The Sugar Bush',
    'body' => <<<'HTML'
<p>One of the most important spring activities is making maple syrup. The Anishinaabe have been harvesting maple sap and making sugar since time immemorial. The sugar bush, iskigamizigan, is a place of gathering, hard work, and teaching.</p>
<p>Elders have always said the sugar bush is where young people learn patience. You cannot rush the sap. You tend the fire, you watch the boil, and you wait. That lesson applies to much more than syrup.</p>
<p>If you have memories of the sugar bush from your childhood, the smell of the fire, the taste of fresh syrup on snow, the sound of the bush coming alive, share them. We want to hear your story for next month's issue.</p>
HTML,
]);

$addItem('teachings', [
    'title' => 'Spring Medicines',
    'body' => <<<'HTML'
<p>As the snow recedes, the first medicines begin to appear. Wiigwaas (birch bark) can be carefully harvested in spring for teas and remedies. The young shoots of nettles, the early greens. These are gifts from the land that our grandparents relied on.</p>
<p>If you were taught about spring medicines and would like to share that knowledge with the community, please reach out. We want this section to grow with real teachings from real people in our community.</p>
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
            ['word' => 'Mshkiki',      'pronunciation' => 'mush-KIH-kih',             'meaning' => 'Medicine',             'example' => null, 'svg' => $loadSvg('medicine')],
            ['word' => 'Ziigwan',      'pronunciation' => 'ZEE-gwun',                 'meaning' => 'Spring',               'example' => 'Ziigwan bi-dgoshin. — Spring is arriving.', 'svg' => ''],
        ],
    ],
]);

// ── COMMUNITY / REMEMBER WHEN (Page 7) — 2 prose ────────────────────────────

$addItem('community', [
    'title' => 'Many Rivers Joining',
    'body' => <<<'HTML'
<p>The name Sagamok comes from the Anishinaabemowin words meaning "many rivers joining." The community sits where the Spanish River, the Sauble River, and several smaller waterways come together before flowing into Lake Huron. Long before the Trans-Canada Highway cut through the territory, these rivers were the roads. Our ancestors navigated by water. The place we live was named for the way the land moves.</p>
<p>People used to travel by canoe from Sagamok to Manitoulin Island and back. The rivers connected communities the same way roads do now. Except quieter. And you could fish on the way.</p>
HTML,
]);

$addItem('community', [
    'title' => 'The Sugar Bush',
    'body' => <<<'HTML'
<p>Before there were grocery stores in Massey or Espanola, families from along the North Shore went to the sugar bush every spring. The whole family went. Grandparents, parents, kids. You tapped the trees, collected the sap in birch bark containers, and boiled it over a fire until it turned to sugar. It took days.</p>
<p>The sugar bush was where young people learned how to work and how to wait. You tended the fire. You watched the boil. You did not rush it. Elders say those lessons applied to a lot more than syrup.</p>
<p>If you have memories of the sugar bush, we want to hear them. The smell of the fire, the taste of fresh syrup on snow, the sound of the bush coming alive. Send us your story for next month's issue.</p>
HTML,
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
<p>You know you're from the rez when your GPS says "turn left at the big rock."</p>
</div>

<div class="joke">
<p>Elder at the community meeting: "Back in my day, we didn't have Wi-Fi. We had to walk to our cousin's house to find out what was for supper."</p>
</div>

<div class="joke">
<p>How do you know spring has arrived on the rez?</p>
<p>The mudroom actually has mud in it.</p>
</div>

<div class="joke">
<p>My uncle said he's been social distancing from the gym since 1987.</p>
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
<p>I have no mouth but I speak for the people. I have no legs but I travel from home to home. I am old but I carry new stories every moon. What am I?</p>
<p><em>(Answer in next month's issue!)</em></p>
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
    ['Makwa: Bear Clan',         'The bear is waking from winter rest. This is your time to step into a healing role. Someone close needs your steady presence. Trust the medicine you carry.'],
    ['Migizi: Eagle Clan',       'Eagle sees far. A message is coming from the east. Pay attention to your dreams this week. Your leadership is needed at a gathering. Show up even if you feel unsure.'],
    ["Ma'iingan: Wolf Clan",     "The pack is calling. Reconnect with someone you haven't spoken to since winter. Walk your path with patience. The trail will become clear by the full moon."],
    ['Waabizheshi: Marten Clan', "Marten energy is quick and resourceful. A project you started last month is ready for the next step. Don't overthink it. Act with the confidence of spring."],
    ['Ajijaak: Crane Clan',      'Crane is a speaker and a leader. Your words carry weight this month. Use them to bring people together, not apart. Someone younger is watching how you handle a difficult conversation.'],
    ['Giigoonh: Fish Clan',      'The fish are running and so is your mind. You have been thinking too much. Get outside, put your feet on the ground, and let the water carry some of that weight. Answers come when you stop looking so hard.'],
    ['Bineshiinh: Bird Clan',    'The birds are returning with new songs. This is a month for creativity. If you have been meaning to bead, draw, write, or sing, start now. Your spirit is ready.'],
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
<p>If you have been to a community gathering in Sagamok in recent years, you have probably heard Grace Manitowabi's voice. She is the one who opens with prayer. She is the one who reminds us what we are here for.</p>

<p>Grace is a Traditional Ecological Knowledge Elder who has spent years fighting to protect the medicines that grow on our land. When the province started spraying glyphosate from helicopters over forests in Robinson Huron Treaty territory, Grace was one of the Elders who stood up and said no. The herbicide kills sage, sweetgrass, and cedar. The same medicines our people have relied on since before anyone drew a map of this place.</p>

<p>She did not just speak at meetings. She helped lead a billboard campaign across the treaty territory so that everyone driving through our lands would see the message: stop spraying our medicines. She brought attention to what hunters in the community were already seeing. Moose with signs of cancer, harvested from the same areas being sprayed.</p>

<p>Grace carries knowledge that does not come from a textbook. It comes from the land, from the water, from generations of people who paid attention to what the earth was telling them. Traditional Ecological Knowledge is not a category in a government report. It is how our people survived and thrived for thousands of years. Grace has made it her work to ensure that knowledge is not lost. And not poisoned.</p>

<p>Beyond the land, Grace has been a voice in conversations about Minigoziwin, our inherent sovereignty, and about Anishinaabe approaches to child wellbeing. She believes our communities should be the ones making decisions about our children, our land, and our future. That is not a political position. It is a teaching.</p>

<p>When the new Anishinabek Police Services detachment opened in Sagamok in 2023, it was Grace who offered the opening prayer. That tells you something about the respect she carries in this community.</p>

<p>We are grateful for Elders like Grace who do not wait for someone else to protect what matters. They just do it. And they show the rest of us what it looks like to stand for something.</p>

<p><em>Miigwech, Grace.</em></p>

<p>Know an Elder who should be featured in a future issue? Tell us through Minoo Live or the contact information on the back page. We will reach out to them with care and only feature them with their permission.</p>
HTML,
]);

// ── BACK PAGE (Page 12) — back_page_box + partners + colophon ───────────────

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
<p><strong>Minoo Newsletter</strong><br>Vol. 1, Issue 1 · May 2026<br>Printed by OJ Graphix</p>
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
