#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Backport Vol. 1 / Issue 1 content into structured newsletter_item rows —
 * Anishinaabemowin edition (langcode='oj').
 *
 * Mirrors backport-edition-1-content.php but produces a parallel edition with
 * a best-effort Anishinaabemowin translation. Many phrases are first-draft
 * and need Elder review; uncertain words are marked with [?] in the source.
 *
 * Identification: volume=1, issue_number=1, community_id=0 (not null),
 * langcode='oj'. The English script filters community_id=null so the two
 * editions live side-by-side.
 *
 * Vocabulary sources: Russell's authored content (already bilingual),
 * the local OPD dictionary corpus (21,721 entries from ojibwe.lib.umn.edu),
 * and standard Nishnaabemwin/Anishinaabemowin orthography matching the
 * double-vowel ("Fiero") system Russell uses in the English edition.
 *
 * Usage: php scripts/backport-edition-1-ojibwe.php
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

// ── 1. Find or create Ojibwe edition ─────────────────────────────────────────

$editionStorage = $etm->getStorage('newsletter_edition');
$existing = array_filter(
    $editionStorage->loadMultiple(),
    static fn ($e) => (int) $e->get('volume') === 1
        && (int) $e->get('issue_number') === 1
        && (string) $e->get('langcode') === 'oj',
);

if ($existing !== []) {
    $edition = reset($existing);
    echo "Found existing Ojibwe edition neid={$edition->id()}, resetting to draft.\n";
    $edition->set('status', 'draft');
    $editionStorage->save($edition);
} else {
    echo "Creating new Ojibwe edition: Vol. 1, Issue 1\n";
    $edition = $editionStorage->create([
        'community_id' => 0,
        'volume' => 1,
        'issue_number' => 1,
        'publish_date' => '2026-06-01',
        'langcode' => 'oj',
        'status' => 'draft',
        'created_by' => 0,
        'headline' => "Minoo Mazina'igan — Niibin 2026",
    ]);
    $editionStorage->save($edition);
    echo "Created edition neid={$edition->id()}\n";
}

$editionId = (int) $edition->id();

// ── 2. Delete existing items for this edition ────────────────────────────────

$itemStorage = $etm->getStorage('newsletter_item');
$existingItems = array_filter(
    $itemStorage->loadMultiple(),
    static fn ($i) => (int) $i->get('edition_id') === $editionId,
);

if ($existingItems !== []) {
    $itemStorage->delete(array_values($existingItems));
    echo "Deleted " . count($existingItems) . " existing item(s).\n";
}

$items = [];
$position = 0;

$svgDir = __DIR__ . '/assets/newsletter-vol1-issue1/svgs';
$loadSvg = static function (string $name) use ($svgDir): string {
    $path = $svgDir . '/' . $name . '.svg';
    if (!is_file($path)) {
        return '';
    }
    return trim((string) file_get_contents($path));
};

$addItem = function (string $section, array $payload) use (&$items, &$position, $editionId): void {
    $kind  = $payload['kind']  ?? 'prose';
    $title = $payload['title'] ?? '';
    $body  = $payload['body']  ?? '';
    $structured = $payload['structured'] ?? null;

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
    'title' => "Aanii",
    'body' => <<<'HTML'
<p>Ningii-ozhitoon owe mazina'igan ji-aabajitooyeg dibaajimowinan, gegoo gaa-bi-izhiwebak, gikinoo'amaagewinan, Anishinaabemowin, baapinaagewinan, miinawaa damiwininan endaso-giizis. Niminwendaan giishpin minoseyeg.</p>
HTML,
]);

$addItem('cover', [
    'kind' => 'toc',
    'title' => "Owe Mazina'iganing",
    'structured' => [
        'entries' => [
            ['label' => "Russell odibaajimowin",     'page' => 2],
            ['label' => 'Anishinaabe Dibaajimowin',  'page' => 4],
            ['label' => "Giizisoo-mazina'igan",      'page' => 6],
            ['label' => 'Gikinoo\'amaagewinan',      'page' => 7],
            ['label' => 'Anishinaabemowin',          'page' => 8],
            ['label' => 'Indakiiminaan',             'page' => 9],
            ['label' => 'Baapinaagewinan',           'page' => 10],
            ['label' => 'Damiwininan',               'page' => 11],
            ['label' => "Doodemag Aajimowinan",      'page' => 12],
            ['label' => 'Gichi-Anishinaabe',         'page' => 13],
            ['label' => 'Owiindamaagewinan',         'page' => 15],
            ['label' => 'Aaniin Ezhi-Anokaadeg',     'page' => 16],
        ],
    ],
]);

// ── KEEPER'S NOTE (Page 2) — drop cap ───────────────────────────────────────

$addItem('editors_note', [
    'kind' => 'drop_cap',
    'title' => "Russell odibaajimowin",
    'body' => <<<'HTML'
<p>Aanii kina! Russell Jones nindizhinikaaz. Sagamok Anishnawbek nindoonjibaa. Computer-anokii nindanokii Anishinaabeg ji-mino-aabajitooyaang gikendamowin. Mewinzha nigii-andawendaan ji-aabajitooyaan owe nidanokiiwin omaa endaayaan. Mii owe bezhig nakwedoozaad.</p>

<p>Ningii-ozhitoon owe mazina'igan Minoo Live, Anishinaabeg ozhitooyaang. Gaawiin chi-mookomaan-akiing California, gemaa Toronto, ezhi-dibendamosig. Omaa nibendoonaan, niinawind Anishinaabe ozhitood. Mii Anishinaabeg eyaajig owe gikendamowin gaa-dibendamowaad.</p>

<p>2023 ingoding, Meta gii-gibaakwa'an dibaajimowinan Facebook miinawaa Instagram aki Canada. Mii dash i'iw niibin, ishkode gii-ozaagiseg giiwedinong, miinawaa Anishinaabe-akiing eyaajig gii-naadakiimowaad. Inwewinan, izhi-maajaa-aazhi'igewinan, miinawaa zhinaak-zhitoonigewinan gii-aabita-gibaakwa'aganan.</p>

<p>Niinawind Anishinaabeg gidaa-dibendaanaanaa gidanokiiwininaan, gidaki, gidanibim, miinawaa giziibii'iganinaanan. Minoo Live nibendaan Anishinaabe-bemaadiziwaad odibendamowaad bemaadiziwin gikendamowin.</p>

<p>Owe nitam mazina'igan. Daa-aanji'amagad giishpin aabajitooyeg miinawaa aanind giwiindamageyek. Giishpin awii-aabajitooyek gegoo gikinoo'amaagewin gemaa baapinaagewin, ninga-bi-izhigaade.</p>

<p>Miigwech eyaayek.</p>

<p><em>Russell Jones<br>Sagamok Anishnawbek</em></p>
HTML,
]);

// ── NEWS (Page 4) — 4 news items ────────────────────────────────────────────

$addItem('news', [
    'kind' => 'news_lead',
    'kicker' => 'Gidibendaaganinaan',
    'title' => "Wiindamaagewag Ogimaag: Gaawiin Da-Ziigwebinaasiin Mashkikiwaaboo",
    'body' => <<<'HTML'
<p>Ogimaag igiw 21 Robinson Huron Treaty Anishinaabeg gii-wiindamaagewag: gaawiin mitigokewinii-bemaadiziwaad odibendaagosiiwaag ji-ziigwebinaagewaad mashkikiwaaboo omaa indakiiminaang. Interfor gii-ikido gaawiin da-ziigwebinaaziin 2026, daa kanaage da-aginzoog ji-gibichi-aagonwetawaawaad. Mashkikiwaaboo nisaa wiingashk, giizhik, miinawaa Ziigwaakw. Gegoo-bezhwaad odoonjibinaawaad omayoosan ezhi-aakoziwaad.</p>
<p class="source-line"><em>Aabita-Niibin 8, 2026. Source: Turtle Island News, Anishinabek News</em></p>
HTML,
]);

$addItem('news', [
    'title' => "Daa-Niibawag Niwaak Gaa-Inendamowaad Anishinaabemowin",
    'body' => <<<'HTML'
<p>Onaagaad Anishinaabemowin Teg gii-maawanji'iwag gegaa niwaak London, Ontario apii Aabita-Niibin 26 ji-28. Gii-bagosendamoog oshki-bemaadiziwaad ji-kanawendamowaad inwewin — gikinoo'amaagewinan, gaagiigidowiniinan, miinawaa Anishinaabe-zhi-anokiiwinan. Anishinaabemowin gegaa bezhig dazoshkaa Anishinaabewi-bemaadiziwaad enaabajiitood. Owe ezhi-maawanji'iyaang, mii ezhi-aanjipisiganaadamang.</p>
<p class="source-line"><em>Aabita-Niibin 26–28, 2026. Source: Anishinaabemowin Teg</em></p>
HTML,
]);

$addItem('news', [
    'title' => "22 Anishinaabe-Bemaadiziwaad Odoonjibaadanaawaad Abinoonjiiyensag",
    'body' => <<<'HTML'
<p>22 Anishinabek-bemaadiziwaad gii-onaakonigewaad Anishinabek Nation Child Well-Being Law. Owe inaakonigewin aanjipisige abinoonjiiyensag ezhi-naadamaaganwaad — gaawiin eta dakwetawiyaagwen ji-ozhitoowaad gegoo wenjida-gegoo gabe-bi-izhiwebak. Bemaadiziwaad da-ozhitoonan inendamoji-naadamaagewinan minik onaakonigewiniwaa, gete-anokiiwiniwaa, miinawaa wenji-anokiiwaad. Mii ji-ayaawaad abinoonjiiyensag waasa odenagoog miinawaa odibendaaganiwaag. Gabe-bi-izhi-mazinaabikinigaadeg awewaa-aazhi'igewaad biboon onji-anishinaabe-abinoonjiiyensag, Anishinaabeg odoonjibaagonaawaan.</p>
<p class="source-line"><em>Aabita-Niibin 2026. Source: Anishinabek News, Manitoulin Expositor</em></p>
HTML,
]);

$addItem('news', [
    'title' => "Sagamok Ogimaakaan Ogii-Gikinoo'amaaged Zhiibaahaasing",
    'body' => <<<'HTML'
<p>Sagamok Anishnawbek Ogimaakaan Leroy Bennett gii-witaakim nookomis Martina Osawamick wenji-Zhiibaahaasing Cultural Speaker Series, Naabido-Giizis 23. Owe gikinoo'amaagewin, "Stages of Life and Ceremonies," gii-ayaa Zhiibaahaasing Complex Wiikwemkoong Unceded Territory. Owe gikinoo'amaagewin odachi-aanji'idiwinaawaa bemaadiziwin ji-zhi-mino-bi-izhiwebak miinawaa giginimowin gakina aabita-bemaadiziwin ezhi-anokaadamiyek.</p>
<p class="source-line"><em>Naabido-Giizis 23, 2026. Source: Anishinabek News</em></p>
HTML,
]);

// ── EVENTS (Page 6) — 1 calendar + events + weekly sidebar ─────────────────

$addItem('events', [
    'kind' => 'calendar',
    'title' => 'Niibin-Giizis 2026',
    'structured' => [
        'month' => 'Niibin-Giizis 2026',
        'weekdays' => ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
        'weeks' => [
            [ ['day' => 1,  'event' => null], ['day' => 2,  'event' => null], ['day' => 3,  'event' => null], ['day' => 4,  'event' => null], ['day' => 5,  'event' => 'OVC'], ['day' => 6,  'event' => "Mothers' Grief"], ['day' => 7,  'event' => null] ],
            [ ['day' => 8,  'event' => null], ['day' => 9,  'event' => null], ['day' => 10, 'event' => 'YWHO'], ['day' => 11, 'event' => null], ['day' => 12, 'event' => 'ID Clinic'], ['day' => 13, 'event' => null], ['day' => 14, 'event' => null] ],
            [ ['day' => 15, 'event' => null], ['day' => 16, 'event' => null], ['day' => 17, 'event' => null], ['day' => 18, 'event' => null], ['day' => 19, 'event' => 'CFAU Golf'], ['day' => 20, 'event' => null], ['day' => 21, 'event' => 'NIPD'] ],
            [ ['day' => 22, 'event' => null], ['day' => 23, 'event' => null], ['day' => 24, 'event' => null], ['day' => 25, 'event' => null], ['day' => 26, 'event' => null], ['day' => 27, 'event' => null], ['day' => 28, 'event' => null] ],
            [ ['day' => 29, 'event' => null], ['day' => 30, 'event' => null], null, null, null, null, null ],
        ],
        'weekly_heading' => "Endaso-Anama'e-Giizhik Sagamok",
        'weekly' => [
            "Waawaasnoode Adult Learning Centre, Monday ji-Friday, 9:00 AM",
            "Hapkido Karate, gegaa 6+, Monday-giizhig, 6:00 PM",
            "Young Warriors Drum Group, 12–24, Tuesday-giizhig, 6:00 PM",
            "Oshki-Bemaadiziwaad Gym Nights, Tuesday miinawaa Thursday, 6:00 PM",
            "Family Playgroup, naadakii-Wednesday-giizhig, 12:00 PM",
            "Oshki-Bemaadiziwaad Basketball, 12–24, Saturday-giizhig, 6:00 PM",
        ],
    ],
]);

$addItem('events', [
    'title' => 'Niibin OVC Animwa\'igewin Maazhi-Zaagibinige Naadamaagewin Clinic',
    'body' => <<<'HTML'
<p class="event-detail"><strong>Aapane:</strong> Niibin-Giizis 5–10, maajaayaa 8:00 AM</p>
<p class="event-detail"><strong>Endi:</strong> Sagamok</p>
<p>Ontario Veterinary College gewiimooshkad endaso-niibin animwa'igewin, maazhi-zaagibinige, miinawaa naadamaagewin. Mii reserve-anishinaabe ji-naadamaageyek. Bii-aabajitoon band office.</p>
HTML,
]);

$addItem('events', [
    'title' => "Anokiiwininii-Mama Maazhi-Wiizhinde Program",
    'body' => <<<'HTML'
<p class="event-detail"><strong>Aapane:</strong> Saturday, Niibin-Giizis 6, 11:00 AM</p>
<p class="event-detail"><strong>Endi:</strong> Sagamok</p>
<p>Mamag wenji-ezhi-waawiinde ezhi-maawanji'idiwaad. Bemaadiziwaad weweni-bezhig daa-bi-izhiwaad.</p>
HTML,
]);

$addItem('events', [
    'title' => "Sagamok YWHO Oshki-Bemaadiziwaad Inendamoo-Committee",
    'body' => <<<'HTML'
<p class="event-detail"><strong>Aapane:</strong> Wednesday, Niibin-Giizis 10, 4:00 PM</p>
<p class="event-detail"><strong>Endi:</strong> Multi-Educational Centre Meeting Room</p>
<p>Naadamaage'idiwag Sagamok Oshki-Bemaadiziwaad Mino-bimaadiziwin Hub. Oshki-Bemaadiziwaad eyaajig daa-aabajitoonaawaa odinwewin.</p>
HTML,
]);

$addItem('events', [
    'title' => 'Anishinaabe-Bemaadiziwaad ID miinawaa Naadamaagewin Clinic',
    'body' => <<<'HTML'
<p class="event-detail"><strong>Aapane:</strong> Friday, Niibin-Giizis 12, 10:00 AM</p>
<p class="event-detail"><strong>Endi:</strong> Sagamok</p>
<p>Naadamaagewin onji izhinikaazowin, mazinaakizon, miinawaa weniji-naadamaagewin.</p>
HTML,
]);

$addItem('events', [
    'title' => "CFAU Bezhig Naadamaagewi-Bagasewichigewin Golf Tournament",
    'body' => <<<'HTML'
<p class="event-detail"><strong>Aapane:</strong> Friday, Niibin-Giizis 19, 9:00 AM – 4:00 PM</p>
<p class="event-detail"><strong>Endi:</strong> Stone Ridge Golf Course, Elliot Lake</p>
<p>Niiwin-anishinaabeg scramble. $600 per-team, nitam 30 paid teams da-ji-ayaawaad. Includes golf cart, wiisiniwin, miinawaa miinigowizi-bibii'aazhi-mazina'igan. Register at sagamokanishnawbek.com/cfau-golf-tournament.</p>
HTML,
]);

$addItem('events', [
    'title' => 'Anishinaabeg Akiing Endaso-Biboon Giizhigad',
    'body' => <<<'HTML'
<p class="event-detail"><strong>Aapane:</strong> Sunday, Niibin-Giizis 21</p>
<p>Bekaa apii bemaadiziwaad wiindamaagewinan.</p>
HTML,
]);

// ── TEACHINGS (Page 7) ──────────────────────────────────────────────────────

$addItem('teachings', [
    'kind' => 'teaching_block',
    'title' => "Niibin: Aakiizigeg Apii",
    'structured' => [
        'icon_svg' => $loadSvg('medicine'),
        'banner_svg' => '',
        'drop_cap' => true,
    ],
    'body' => <<<'HTML'
<p>Niibin gaa-izhinikaadeg awa gizhebaa-akiing. Aki gichi-mooshkinemagad. Zaaga'iganan giizhoodemagad, miinanan gimaadi, mashkikiwan gichi-naadamookwaa.</p>
<p>Indede-Anishinaabeg gaawiin gii-aabajitoosiinaawaa giizisoo-mazina'igan ji-gikendamowaad niibin. Apii ode'iminan gii-bigamiwaad miikanaang, mii apii niibin gii-bii-dagoshing.</p>
HTML,
]);

$addItem('teachings', [
    'kind' => 'teaching_block',
    'title' => 'Miinike: Niigi-Miinike',
    'structured' => [
        'icon_svg' => $loadSvg('medicine'),
    ],
    'body' => <<<'HTML'
<p>Bezhig nitam gegoo niibin: ode'imin. Ode'iminag niigaani bi-dagoshinog — gigichi-inendaanaa ezhi-nitam-min biboon. Miinanag dagoshinog, mii dash makademinag, mii dash bagwajimiinag. Bezhig bezhig mii bi-bagamise.</p>
HTML,
]);

$addItem('teachings', [
    'kind' => 'teaching_block',
    'title' => 'Niibin Mashkikiwan',
    'structured' => [
        'icon_svg' => $loadSvg('medicine'),
    ],
    'body' => <<<'HTML'
<p>Apii niibin maajiised, gichi-mashkikiiyaag endazhinda. Wiingashk, giizhik, miinawaa asema gibi-naadinoog izhi-pagidinigewin, miinawaa eta minik wii-aabajitood.</p>
<p><em>Gikendaman ina niibin mashkikiwin? Wiindamawishin.</em></p>
HTML,
]);

// ── LANGUAGE (Page 8) ───────────────────────────────────────────────────────

$addItem('language', [
    'title' => 'Niibin',
    'body' => <<<'HTML'
<p><strong>Niibin</strong> — <em>NEE-bin</em></p>
<p><strong>Summer / It is summer</strong></p>
<p>"Niibin bi-dgoshin." – Niibin is arriving.</p>
HTML,
    'blurb' => 'Featured Word: Niibin',
    'structured' => [
        'svg' => '',
    ],
]);

$addItem('language_corner', [
    'kind' => 'vocab_grid',
    'title' => 'Anishinaabemowin',
    'body' => <<<'HTML'
<p>Aabajitoon bezhig owe anama'e-giizhik weweni endaayan gemaa endazhi-maawanji'iyek. Niwii-bibii'amin Anishinaabemowin gakina mazina'igan. Giishpin gikinoo'amaagewa-bemaadiziyek gemaa gikinoo'amaagosiyeg miinawaa awii-aabajitooyek inwewinan, mii sa owe gimazina'iganinaan. Wii-mino-bii-naadamaageminigowiziyek gete-Anishinaabeg gaa-Anishinaabe-aabajitoojig.</p>
HTML,
    'structured' => [
        'cards' => [
            ['word' => 'Miinike',     'pronunciation' => 'MEE-nih-keh',     'meaning' => 'To pick berries',         'example' => null, 'svg' => ''],
            ['word' => "Ode'imin",    'pronunciation' => 'OH-deh-min',      'meaning' => 'Strawberry (heart berry)', 'example' => null, 'svg' => ''],
            ['word' => 'Aandeg',      'pronunciation' => 'AHN-deg',         'meaning' => 'Crow',                    'example' => null, 'svg' => $loadSvg('crow')],
            ['word' => 'Gimiwan',     'pronunciation' => 'gih-MIH-wun',     'meaning' => 'It is raining',           'example' => null, 'svg' => $loadSvg('rain')],
            ['word' => 'Manoomin',    'pronunciation' => 'mah-NOH-min',     'meaning' => 'Wild rice',               'example' => null, 'svg' => ''],
            ['word' => 'Mashkiki',    'pronunciation' => 'mush-KIH-kih',    'meaning' => 'Medicine',                'example' => null, 'svg' => ''],
        ],
    ],
]);

// ── OUR TERRITORY (Page 9) ──────────────────────────────────────────────────

$addItem('territory', [
    'title' => 'Indakiiminaan',
    'kind' => 'territory_list',
    'structured' => [
        'epigraph' => "Niinawind Giiwedinong Aki Anishinaabeg. Omaa gidayaayang.",
        'intro' => 'Robinson Huron Treaty 1850 mii dazhi-onaakoniged niinawind indede-Anishinaabeg miinawaa gichi-mookomaan-ogimaag. Mii anishinaabe-aki giiwedinong Lake Huron. 21 Anishinaabeg-Bemaadiziwaad mii gaa-zhinikwaadagamesiniyaang. Niinawind gigidoodeminaanan owe — bemaadiziwaad wenji-gidizhinikaazoyaang.',
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
            ['name' => 'Wikwemikong',                 'meaning' => 'bay of the beaver',                      'note' => 'unceded — Manitoulin Island'],
            ['name' => 'Whitefish River',             'meaning' => '',                                       'note' => 'Birch Island'],
            ['name' => 'Zhiibaahaasing',              'meaning' => 'place where the wind blows through',     'note' => 'Manitoulin Island'],
        ],
        'closing' => 'Apii agindaman izhinikaazowin omaa, mii sa aki gaa-agindaman. Bezhig bezhig owe bemaadiziwaad mii gidoodeminaan.',
    ],
]);

// ── JOKES (Page 10) ─────────────────────────────────────────────────────────

$addItem('jokes', [
    'title' => 'Baapinaagewinan',
    'body' => <<<'HTML'
<div class="joke">
<p><strong>Rez Baapinaagewinan</strong></p>
<p>Aaniish wenji-aazhawishkaad omayoos Highway 17?</p>
<p>Ji-wiindamawaaj bineshiinhan da-gashkitood.</p>
</div>

<div class="joke">
<p>Nookomis gii-ikido weweni-ji-ginwaanj-bemaadizid bakwezhigan endaso-giizhik aabajitoon.</p>
<p>Mashkikiiwinini gii-ikido gegoo-zhigwadaadinan.</p>
<p>Ningii-naazhikiwe Nookomis.</p>
</div>

<div class="joke">
<p>Gichi-Anishinaabe gaa-maawanji'aad: "Mewinzha gaawiin Wi-Fi nigii-ayaasinaa. Gigii-pimose ji-zhi-bizindawang nizhiinaaganens enendang nawakwe-wiisiniwin."</p>
</div>

<div class="joke">
<p>Aaniin ezhi-gikenimad ziigwan gii-bi-dagoshing rez?</p>
<p>Mudroom-ing wenji-azhi-zhigad.</p>
</div>

<div class="joke">
<p>Nishiimenh gii-ikido Band Office geyaabi gii-aanjipidoon.</p>
<p>Ningii-gagwedwe gegoo eshpedwaad.</p>
<p>Gii-ikido, "Daagwii Niiwizhitanjiyaang Tuesday."</p>
<p>Ningii-ikido, "Aanish Tuesday?"</p>
<p>Gii-ikido, "Mii sa."</p>
</div>

<p><strong>Daa Gegoo Baapinaaganaak?</strong></p>
<p>Wiindamawiyaang baapinaagewinan, gidaajimowinan, gemaa rez baapinaagewin onji wii-bii-zhiwaak mazina'igan. Wenji-mino-onendaagwadi — nookomis daa-agindaago. Owe ishkweyaang owe mazina'iganing dazhinde ezhi-aazhawibaa'inaaganiyaang.</p>
HTML,
]);

// ── PUZZLES (Page 11) ───────────────────────────────────────────────────────

$addItem('puzzles', [
    'kind' => 'word_search',
    'title' => 'Inwewin-Nandoone\'igewin: Niibin Inwewinan',
    'body' => '<p>Nandoone\'an inwewinan owe mazinaateng. Daa-aazhawishkaagad mawewig, niisaayi\'iing, gemaa ezhi-zhebakaagad.</p>',
    'structured' => [
        'grid' => [
            ['N','I','I','B','I','N','K','T','B'],
            ['G','O','D','E','I','M','I','N','P'],
            ['I','M','S','H','K','I','K','I','T'],
            ['M','T','A','A','N','D','E','G','K'],
            ['I','W','I','I','G','W','A','A','S'],
            ['W','P','M','I','G','I','Z','I','T'],
            ['A','S','O','N','G','B','I','R','D'],
            ['N','K','B','P','T','M','A','K','I'],
            ['T','M','Z','B','P','N','K','T','M'],
        ],
        'words' => [
            'NIIBIN (Niibin)',
            'GIMIWAN (Gimwan)',
            "ODE'IMIN (Ode'imin)",
            'MSHKIKI (Mashkiki)',
            'AANDEG (Aandeg)',
            'WIIGWAAS (Wiigwaas)',
            'MIGIZI (Migizi)',
            'SONGBIRD (Bineshiinh)',
            'AKI (Aki)',
        ],
    ],
]);

$addItem('puzzles', [
    'title' => 'Owe Giizis Wenji-Gagwedwemagad',
    'body' => <<<'HTML'
<p>Gaawiin nidoonisin, ayaa nina-bemaadiziwaad inwewin.<br>
Gaawiin nizidan, ayaa nibimose endaa-endaa.<br>
Niwiidoosa endaso-giizis gegoo oshki-gaagiigidowin,<br>
miinawaa eta nibendaan inendamowin Anishinaabeg nindaan.</p>
<p>Aaniish niin?</p>
<p><em>Naadinige owe wenji-ozhibii'ige ishkweyaang. Nitam mino-naadinige onji-mooshkinemagad mazinaakizon.</em></p>
HTML,
]);

$addItem('puzzles', [
    'title' => 'Inwewin-Aanjichigewin',
    'body' => <<<'HTML'
<p>Aanjichigaadan onow Anishinaabemowin inwewinan:</p>
<p>1. <strong>GIIMAA</strong> (hint: gichi-Anishinaabe)<br>
2. <strong>NIIBNA</strong> (hint: niibin)<br>
3. <strong>MKAWDE</strong> (hint: makade)</p>
<p><em>(Aagonwetagewin onji-bi-zhi-mazina'iganing!)</em></p>
HTML,
]);

// ── HOROSCOPES (Page 12) ────────────────────────────────────────────────────

$horoscopes = [
    ['Makwa: Makwa-Doodem',
     'Makwa goji-zaaga\'ode mii bi-bimosed. Giin igo. Owe giizis bezhig gi-pesegineag bemaadid wii-naadamooyaa giin-bii-bezhig-anokiiyan gemaa giin-bii-ininiyan. Bezigi-namadab. Mashkiki gigayaaw gaawiin gegoo gi-ikidooyiin.'],
    ['Migizi: Migizi-Doodem',
     'Migizi gakina aki owaabandaa gichi-mookomaan ininaatigong onjiing. Bezigi-azheke miinawaa waabamen gichi-mazinaazon gegoo ozhibii\'aman. Ningoji-wendendaagwadi onaakonigewin gichi-ji-bi-ayaag. Gi-gwayakwaadi waawaasendamowin.'],
    ['Ma\'iingan: Ma\'iingan-Doodem',
     'Ma\'iinganag obi-noondaagewag. Bezhig gi-bezhwaad gaawiin gigii-gaagiigidoosiin onji nitam-bibooning, miinawaa nesowind giwaabandaanaawa. Niiganishkawii. Ma\'iinganag gaawiin obi-baapizigowaan miikana ji-mino-ayaag — owichiwaag miikana, miinawaa miikana mii gichi-onjipisigaadeg.'],
    ['Waabizheshi: Waabizheshi-Doodem',
     'Waabizheshi mii gigizhe-bemose, gichi-gikendang, miinawaa giichigaag gegoo. Owe gaa-aandanigaadeg January-ing, mii sa gaa-bi-gakidinde, miinawaa gigikendaan aaniin ezhi-anokaadaman. Bekaa-bibii\'igen. Maajitaa.'],
    ['Ajijaak: Ajijaak-Doodem',
     'Ajijaak ogaagiigidoon Anishinaabeg, mii dash nitam ji-bizindam. Gidoonwewinan ozhitoonan owe giizis, wenjida gichi-gaagiigido. Onaakonan dibishkoo ode\'iminan — agaasaa, baateteg, mii minik ji-maajiide ishkode wii-giizhoodemag.'],
    ['Giigoonh: Giigoonh-Doodem',
     'Giigoonyag obi-zhi-bimaadiziwag mii dash gidinendamowin. Gimino-inendam. Zaagajisen, gizidan akiing bi-namadab, miinawaa nibii-gi-bi-azhonigod. Aanikwetamaa gaa-andawaabandaman bi-noozhe apii gi-nakwaa-naabiyan.'],
    ['Bineshiinh: Bineshiinh-Doodem',
     'Bineshiinyag biigizhig bi-onaagosiwag bizhibii\'igaadeg gegoo aanigi-aanji bezhig bibooning. Mii giin igo. Ningoji-ozhibii\'amaagewin gegoo gaa-binichigaadeg — dewe\'igan, mzina\'igewin, gidaa-jiibaakwed mazina\'igan gaawiin bekaa-bii\'agan — mii owe gaa-bi-bi-azhe-bizhibii\'amagad. Gaawiin gichi-inendangen. Maajipisige.'],
];
foreach ($horoscopes as [$title, $text]) {
    $addItem('horoscope', [
        'title' => $title,
        'body' => '<p>' . $text . '</p>',
    ]);
}

// ── ELDER SPOTLIGHT (Page 13–14) ────────────────────────────────────────────

$addItem('elder_spotlight', [
    'kind' => 'spotlight',
    'title' => 'Grace Manitowabi: Aki Gaa-Naadamood',
    'media_url' => '/newsletter/vol1-issue1/grace-manitowabi.jpg',
    'media_alt' => 'Gichi-Anishinaabe Grace Manitowabi',
    'media_caption' => 'Grace Manitowabi, Sagamok Anishnawbek',
    'body' => <<<'HTML'
<p>Giishpin gigii-bi-izhaa Sagamok-maawanji\'idiwining nising-biboon, gigii-noondaaga Grace Manitowabi inwewin niigaan akawe-bemaadiziwaad. Mii sa wenji-gagwed-aabajitoonaagwaad ji-aanikwedid weweni, miinawaa mii sa wenji-bekaa gaagiigidod, gaawiin ji-mishkadinaaning — minik bemaadiziwaad eyaajig gichi-inendamoog wenji-omaa-ayaayaang.</p>

<p>Grace mii Gichi-Anishinaabe-Gete-Aki-Gikendang. Ogikendamowin apaakwadinaaganan mii ezhinikaazowaad, a\'awe-mashkiki, a\'awe-wiisiniwin, miinawaa aaniin ezhi-biboong. Gaawiin mazina\'iganing owe gii-gikendaa — onookomisan, mii i\'iw mitigwaak gegoo gii-wiindamage.</p>

<p>Owe gaa-agindaman page 4 — Robinson Huron Treaty Ogimaag gii-aagonwetamowaad mashkikiwaaboo ziigwebinangaadeg indakiimi-akiiminaan. Grace mii bezhig eya\'omogwaan owe anokiiwin mewinzha — gaawiin niigaan-aakide, jiibaakwed jiibaakwed mawayoog gegoo.</p>

<p class="pull-quote">Gaawiin ikido daabishkoo wenji-anokaadang. Ikido daabishkoo gegoo gaa-gikendang apii i'iw oshkinooshkewinid.</p>

<p>Ogikendamowinan gaawiin eta aki gaagiigidoon. Minigoziwin gaagiigidoon, miinawaa anishinaabe-bemaadiziwaad daa-onaakonigewaad gabe-bi-izhi-aabajitoowaad abinoonjiyensag, mashkikiwan, miinawaa niigaan.</p>

<p><em>Miigwech, Grace.</em></p>

<p class="spotlight-cta">Gigikendaa ina Gichi-Anishinaabe gichi-ji-mizinaakizod? Bii-ozhibii'amawishin Minoo Live miinawaa email.</p>
HTML,
]);

// ── READER MAIL & NEXT ISSUE (Page 15) ──────────────────────────────────────

$addItem('reader_mail', [
    'kind' => 'prose',
    'title' => "Owiindamaagewinan miinawaa Niigaan-Mazina'igan",
    'body' => <<<'HTML'
<p>Owe mii nitam mazina'igan. Mii sa wenji-gagweji-aanikoonigowiyek ji-wiindamageyek gegoo ezhi-ayaayek. Mii sa gaa-andawenimaang ji-bi-bii-zhitooyek Issue 2.</p>

<p><strong>Owe Giizis Wenji-Gagwedwemagad.</strong> Naadinige onji-azhi-mazina'igan ishkweyaang. Nitam mino-naadinige onji-mooshkinemagad mazinaakizon.</p>

<p><strong>Miinike Endaa-Inendamowinan.</strong> Gidaajimowin, gizhinikaazowin, gimizinaakizon — gegoo gi-ayaayan. Niwii-naadamaageminigowizimin daabishkoo gigii-azhi-miinigooyek.</p>

<p><strong>Gichi-Anishinaabeg Wenji-Niigaaniizhid.</strong> Gigikendaa ina Gichi-Anishinaabe gaa-azhi-mizinaakaadeg odaajimowin? Wiindamawishin awenen miinawaa wenji-aaniin.</p>

<p><strong>Niibin Mashkiki Gikinoo'amaagewinan.</strong> Giishpin gikendaman onow mashkikiwan endaayan gabe-Niibin, mii sa gimazina'iganinaan.</p>

<p><strong>Baapinaagewinan.</strong> Wenji-mino-onendaagwadi — nookomis daa-agindaago.</p>

<p><strong>Anishinaabemowin.</strong> Inwewinan, gizhibaakwe-inwewinan, agaasaa-gikinoo'amaagewinan — gegoo gikinoo'amaagosiwin gidaa-naadamaagomi.</p>

<p><strong>Bi-Ayaamagad Issue 2:</strong> Wiikwemkoong Traditional Pow Wow nibowizid, Anishinaabemowin gichi-aanji-zhi-mooshkinemagad, owe giizis aanjichigewin, miinawaa nitam miinike-endaa-inendamowin.</p>

<p class="next-issue-close"><em>Miigwech eyaayek. Gawaabamigon-mazina'igan-bagwaajigaadeyeg.</em></p>
HTML,
]);

// ── BACK PAGE (Page 16) ─────────────────────────────────────────────────────

$addItem('back_page', [
    'kind' => 'back_page_box',
    'title' => "Aaniin Ezhi-Anokaadeg Owe Mazina'igan",
    'body' => <<<'HTML'
<p>Owe mazina'igan ozhitoomagad Minoo Live, anishinaabe-aaniin-ozhibii'iganens-bemaadiziwaad Anishinaabeg gaa-bi-ozhitoojig. Gegoo ozhibii'amaadeg miinawaa giiwitaakaa mii <strong>minoo.live</strong>. Bemaadiziwaad odibendamowaad odazhi-aazhi'igewinan, dibaajimowinan, miinawaa inwewinan owe mazinaate-zhibii'igewin. Minoo Live obi-naagaadawendamoon bezhig bezhig mazina'igan, ozhibii'an mazina'iganing ezhi-anokaazoond, miinawaa ishkwaaj wii-azhe-aabajitood mazinaakizigewinini.</p>

<p>Gakina gaa-agindaman omaa mazina'iganing maamawi bemaadiziwaad-gikendamowin, bemaadiziwaad gaa-bizhibii'igejig, ezhi-ayaag bemaadiziwaad-anokaadan. Gaawiin chi-mookomaan-ningoji-naadamookwadig gegoo ezhi-ayaayaang. Gaawiin gegoo ji-maajaag akiimi-akiin-onji.</p>

<p>Owe mazina'igan gaawiin gegoo gidaa-gizhowinan.</p>

<p><strong>Naadamaage</strong></p>
<p class="contact-line"><strong>Wiindamage gegoo:</strong> minoo.live</p>
<p class="contact-line"><strong>Email:</strong> russell@web.net</p>
<p class="contact-line"><strong>Aazhi'igewinan, gikinoo'amaagewinan, Anishinaabemowin, baapinaagewinan, Gichi-Anishinaabe wenji-niigaaniizhid:</strong><br>Wiindamage Minoo Live gemaa email. Niwii-bizhibii'igeminaag gegoo bemaadiziwaad gaa-bi-naadamaagewaad.</p>
HTML,
]);

$addItem('back_page', [
    'kind' => 'partners',
    'title' => "Niinawind Miigwech",
    'structured' => [
        'partners' => [
            ['name' => 'Waaseyaa', 'url' => null, 'svg' => $loadSvg('waaseyaa')],
            ['name' => 'OIATC',    'url' => null, 'svg' => $loadSvg('oiatc')],
        ],
    ],
]);

$addItem('back_page', [
    'kind' => 'colophon',
    'title' => '',
    'body' => <<<'HTML'
<p><strong>Minoo Mazina'igan</strong><br>Vol. 1, Issue 1 · Niibin 2026</p>
<p><em>Miigwech eyaayek. Gawaabamigon naabido-giizis.</em></p>
HTML,
]);

// ── Persist all items ────────────────────────────────────────────────────────

echo "\nCreating " . count($items) . " Ojibwe newsletter items...\n\n";

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

// ── Transition edition to curating ───────────────────────────────────────────

$lifecycle->transition($edition, EditionStatus::Curating);
$editionStorage->save($edition);

echo "\n--- SUMMARY ---\n";
echo "Edition neid={$editionId}: {$edition->get('headline')}\n";
echo "Langcode: " . $edition->get('langcode') . "\n";
echo "Status: {$edition->get('status')}\n";
echo "Total items: " . count($items) . "\n";
echo "By section:\n";
foreach ($bySection as $section => $count) {
    echo sprintf("  %-18s %d item(s)\n", $section, $count);
}
echo "\nDone. Ojibwe edition is in 'curating' status, ready for PDF generation.\n";
