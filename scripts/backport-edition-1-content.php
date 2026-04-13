#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Backport all real content from the HTML newsletter draft into newsletter_item entities.
 *
 * This script extracts every piece of text from the Vol. 1, Issue 1 HTML draft
 * (storage/tmp/minoo-newsletter-vol1-issue1.html) and creates inline
 * newsletter_item rows so the Twig print template can render them.
 *
 * Steps:
 *   1. Boot the Waaseyaa kernel
 *   2. Find or create newsletter edition 1 (reset to "draft" if it exists)
 *   3. Delete ALL existing newsletter_item rows for this edition
 *   4. Create inline newsletter_item rows with real content from the HTML draft
 *   5. Transition edition to "curating" status
 *
 * Idempotent: safe to run multiple times. All previous items are wiped first.
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

// Helper to add an item
$addItem = function (string $section, string $title, string $body, string $blurb = '') use (&$items, &$position, $editionId): void {
    $items[] = [
        'edition_id' => $editionId,
        'position' => ++$position,
        'section' => $section,
        'source_type' => 'inline',
        'source_id' => 0,
        'inline_title' => $title,
        'inline_body' => $body,
        'editor_blurb' => $blurb ?: $title,
        'included' => 1,
    ];
};

// ── COVER (Page 1) ──────────────────────────────────────────────────────────

$addItem('cover', 'Minoo Elder Newsletter — Vol. 1, Issue 1', <<<'HTML'
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
HTML);

// ── EDITOR'S NOTE / KEEPER'S NOTE (Page 2) ──────────────────────────────────

$addItem('editors_note', "Keeper's Note", <<<'HTML'
<p>Aanii! My name is Russell Jones. I am a software developer from Sagamok Anishnawbek and I build technology for Indigenous communities. For a long time I have been trying to figure out how to put those skills to work closer to home. This is one answer.</p>

<p>I built this newsletter with Minoo Live, a community platform I created for Anishinaabe communities. Minoo Live is not owned by a corporation in California or Toronto. It was built here, by one of us, and it stays here. The data belongs to the communities it serves. Nobody else.</p>

<p>Think about what happens when we rely on platforms we do not control. In 2023 Meta blocked news from Facebook and Instagram across Canada. Communities that depended on Facebook to share updates lost that overnight. Your photos, your event pages, your community group, all of it sitting on a server in California where someone else decides the rules. They can change those rules any time they want. They already did.</p>

<p>Our people should control our own technology the same way we control our own land, our own water, and our own stories. Minoo Live keeps community data in community hands. The newsletter you are holding is generated from the same system. What appears online also arrives in your hands in print.</p>

<p>This first issue is a beginning. It will grow with your ideas and your words. If you have a teaching to pass along or a joke that makes you laugh every time, send it our way. Contact information is on the back page.</p>

<p>Miigwech for picking this up.</p>

<p><em>Russell Jones<br>Sagamok Anishnawbek</em></p>
HTML);

// ── NEWS (Page 3) — 4 articles ──────────────────────────────────────────────

$addItem('news', 'Treaty Chiefs Say No to Herbicide Spraying', <<<'HTML'
<p>Leaders of the 21 Robinson Huron Treaty First Nations issued a public notice: forestry companies do not have consent to conduct aerial or ground-based herbicide spraying in treaty territory. Interfor confirmed it will not spray in 2026, but chiefs want a permanent moratorium on glyphosate. The herbicide kills sage, sweetgrass, and cedar. Hunters have reported moose with signs of cancer in sprayed areas.</p>
<p class="source-line"><em>April 8, 2026. Source: Turtle Island News, Anishinabek News</em></p>
HTML);

$addItem('news', 'Over 1,000 Gather for Anishinaabemowin', <<<'HTML'
<p>The annual Anishinaabemowin Teg gathering brought more than 1,000 people together in London, Ontario from March 26 to 28. The conference focused on helping youth learn the language through workshops, speakers, and community activities. Anishinaabemowin is spoken by less than one per cent of our population. Gatherings like this are how we change that.</p>
<p class="source-line"><em>March 26–28, 2026. Source: Anishinaabemowin Teg</em></p>
HTML);

$addItem('news', '22 First Nations Taking Over Child Welfare', <<<'HTML'
<p>Twenty-two Anishinabek First Nations have chosen to enact the Anishinabek Nation Child Well-Being Law. The law shifts child welfare from a protection model to a prevention model. Communities will design services based on their own laws, traditions, and priorities. The goal is to keep children with their families and in their communities. After decades of outside agencies making decisions about our kids, First Nations are taking that responsibility back.</p>
<p class="source-line"><em>April 2026. Source: Anishinabek News, Manitoulin Expositor</em></p>
HTML);

$addItem('news', 'Sagamok Councillor Shares Teachings at Zhiibaahaasing', <<<'HTML'
<p>Sagamok Anishnawbek Councillor Leroy Bennett joined Nokomis Martina Osawamick for the Zhiibaahaasing Cultural Speaker Series on March 23. The session, "Stages of Life and Ceremonies," was held at the Zhiibaahaasing Complex in Wiikwemkoong Unceded Territory. These teachings connect the stages of life to ceremony and remind us that every age carries its own responsibilities.</p>
<p class="source-line"><em>March 23, 2026. Source: Anishinabek News</em></p>
HTML);

// ── EVENTS (Page 4) — 7 events ─────────────────────────────────────────────

$addItem('events', 'Wednesday Evenings: Adult Education with Anna', <<<'HTML'
<p><strong>When:</strong> Every Wednesday, 6:00 PM – 8:00 PM</p>
<p><strong>Where:</strong> Sagamok Community Centre</p>
<p>Anna is hosting weekly adult education sessions covering a range of topics. Whether you are looking to build new skills or just want to spend an evening learning with your neighbours, everyone is welcome.</p>
HTML);

$addItem('events', 'Providence Bay Spring Market', <<<'HTML'
<p><strong>When:</strong> Saturday, May 9, 10:00 AM – 3:00 PM</p>
<p><strong>Where:</strong> Providence Bay Exhibition Hall, 10 Firehall Road</p>
<p>Local vendors, crafts, and spring finds. A good reason to get out to Manitoulin.</p>
HTML);

$addItem('events', 'Wiikwemkoong Spring Poker Run', <<<'HTML'
<p><strong>When:</strong> May 12, 10:00 AM</p>
<p><strong>Where:</strong> Departs from South Bay Centre, Wiikwemkoong</p>
<p>Sponsored by Wiikwemkoong Anglers. A fun day out on the water as the season opens up.</p>
HTML);

$addItem('events', "M'Chigeeng First Nation Election", <<<'HTML'
<p><strong>When:</strong> May 16, 2026</p>
<p><strong>Where:</strong> M'Chigeeng First Nation</p>
<p>Community members will go to the polls to elect Chief and Council.</p>
HTML);

$addItem('events', 'Manitoulin Streams Angling Trade Fair', <<<'HTML'
<p><strong>When:</strong> May 18 – 19</p>
<p><strong>Where:</strong> Kagawong, Manitoulin Island</p>
<p>Outdoor gear, fishing demonstrations, and conservation talks. Open to all ages.</p>
HTML);

$addItem('events', 'Family Fun Screening Day', <<<'HTML'
<p><strong>When:</strong> Sunday, May 26, 10:00 AM – 2:00 PM</p>
<p><strong>Where:</strong> Low Island, Little Current</p>
<p>Hosted by the Manitoulin Service Provider Network. Development screening, car seat clinic, refreshments, and activities for the whole family.</p>
HTML);

$addItem('events', 'Coming Up: Wiikwemkoong Traditional Pow Wow', <<<'HTML'
<p><strong>When:</strong> Third weekend of June</p>
<p><strong>Where:</strong> Thunderbird Park, Wiikwemkoong</p>
<p>The annual Traditional Pow Wow returns. Hosted in rotation by one of Wiikwemkoong's satellite communities. Details in next month's issue.</p>
HTML);

// ── TEACHINGS (Page 5) — 3 teachings ────────────────────────────────────────

$addItem('teachings', 'Ziigwan: The Time of New Growth', <<<'HTML'
<p>Spring is known as Ziigwan in Anishinaabemowin. The earth wakes up and the cycle of life begins again. The snow melts, the sap runs, the birds return from the south, and the medicines start to come up through the ground.</p>

<p>Our ancestors knew this season by its signs, not by a calendar date. When the crows returned, it meant the cold was breaking. When the frogs started singing at night, it was time to prepare the sugar bush. These teachings connect us to the land. They remind us we are part of something much older than ourselves.</p>
HTML);

$addItem('teachings', 'Iskigamizigan: The Sugar Bush', <<<'HTML'
<p>One of the most important spring activities is making maple syrup. The Anishinaabe have been harvesting maple sap and making sugar since time immemorial. The sugar bush, iskigamizigan, is a place of gathering, hard work, and teaching.</p>

<p>Elders have always said the sugar bush is where young people learn patience. You cannot rush the sap. You tend the fire, you watch the boil, and you wait. That lesson applies to much more than syrup.</p>

<p>If you have memories of the sugar bush from your childhood, the smell of the fire, the taste of fresh syrup on snow, the sound of the bush coming alive, share them. We want to hear your story for next month's issue.</p>
HTML);

$addItem('teachings', 'Spring Medicines', <<<'HTML'
<p>As the snow recedes, the first medicines begin to appear. Wiigwaas (birch bark) can be carefully harvested in spring for teas and remedies. The young shoots of nettles, the early greens. These are gifts from the land that our grandparents relied on.</p>

<p>If you were taught about spring medicines and would like to share that knowledge with the community, please reach out. We want this section to grow with real teachings from real people in our community.</p>
HTML);

// ── LANGUAGE (Page 6) — featured word ───────────────────────────────────────

$addItem('language', 'Ziigwan', <<<'HTML'
<p><strong>Ziigwan</strong> — <em>ZEE-gwun</em></p>
<p><strong>Spring</strong></p>
<p>"Ziigwan bi-dgoshin." – Spring is arriving.</p>
HTML, 'Featured Word: Ziigwan (Spring)');

// ── LANGUAGE CORNER (Page 6) — vocabulary + note ────────────────────────────

$addItem('language_corner', 'Anishinaabemowin Corner', <<<'HTML'
<p>Try using one this week with your family or at the next community gathering.</p>

<p><strong>Iskigamizige</strong> — <em>iss-kih-GAH-mih-zih-gay</em> — To make maple sugar</p>
<p><strong>Namebin</strong> — <em>nah-MEH-bin</em> — Sucker fish</p>
<p><strong>Aandeg</strong> — <em>AHN-deg</em> — Crow</p>
<p><strong>Gimiwan</strong> — <em>gih-MIH-wun</em> — It is raining</p>
<p><strong>Mshkiki</strong> — <em>mush-KIH-kih</em> — Medicine</p>

<p>We want to include more Anishinaabemowin in every issue. If you are a speaker or language learner and would like to contribute words, phrases, or short lessons, this is your page. We especially welcome contributions from our Elders who carry the language.</p>
HTML);

// ── COMMUNITY / REMEMBER WHEN (Page 7) — 2 stories + call for submissions ──

$addItem('community', 'Many Rivers Joining', <<<'HTML'
<p>The name Sagamok comes from the Anishinaabemowin words meaning "many rivers joining." The community sits where the Spanish River, the Sauble River, and several smaller waterways come together before flowing into Lake Huron. Long before the Trans-Canada Highway cut through the territory, these rivers were the roads. Our ancestors navigated by water. The place we live was named for the way the land moves.</p>

<p>People used to travel by canoe from Sagamok to Manitoulin Island and back. The rivers connected communities the same way roads do now. Except quieter. And you could fish on the way.</p>
HTML);

$addItem('community', 'The Sugar Bush', <<<'HTML'
<p>Before there were grocery stores in Massey or Espanola, families from along the North Shore went to the sugar bush every spring. The whole family went. Grandparents, parents, kids. You tapped the trees, collected the sap in birch bark containers, and boiled it over a fire until it turned to sugar. It took days.</p>

<p>The sugar bush was where young people learned how to work and how to wait. You tended the fire. You watched the boil. You did not rush it. Elders say those lessons applied to a lot more than syrup.</p>

<p>If you have memories of the sugar bush, we want to hear them. The smell of the fire, the taste of fresh syrup on snow, the sound of the bush coming alive. Send us your story for next month's issue.</p>
HTML);

// ── JOKES (Page 8) ──────────────────────────────────────────────────────────

$addItem('jokes', 'Jokes & Humour', <<<'HTML'
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
HTML);

// ── PUZZLES (Page 9) ────────────────────────────────────────────────────────

$addItem('puzzles', 'Puzzles', <<<'HTML'
<p><strong>Word Search: Signs of Spring</strong></p>
<p>Find the hidden words in the grid below. Words can go across, down, or diagonally.</p>

<pre>
Z I I G W A N M S
G I M I W A N A O
A K W E N Z I I N
N A M E B I N N G
M S H K I K I G B
A K I G O N Z A I
A A N D E G H N R
W I I G W A A S D
M I G I Z I K E S
</pre>

<p><strong>Words to find:</strong> ZIIGWAN (Spring) · GIMIWAN (Rain) · NAMEBIN (Sucker) · MSHKIKI (Medicine) · AANDEG (Crow) · WIIGWAAS (Birch) · MIGIZI (Eagle) · SONGBIRD · AKI (Earth)</p>

<p><strong>Riddle of the Month</strong></p>
<p>I have no mouth but I speak for the people. I have no legs but I travel from home to home. I am old but I carry new stories every moon. What am I?</p>
<p><em>(Answer in next month's issue!)</em></p>

<p><strong>Word Scramble</strong></p>
<p>Unscramble these Anishinaabemowin words:</p>
<p>1. <strong>GIIMAA</strong> (hint: a leader)<br>
2. <strong>NIIBNA</strong> (hint: summer)<br>
3. <strong>MKAWDE</strong> (hint: black)</p>
<p><em>(Answers in next month's issue!)</em></p>
HTML);

// ── HOROSCOPE (Page 10) ─────────────────────────────────────────────────────

$addItem('horoscope', 'Clan Horoscopes', <<<'HTML'
<div class="horoscope-item">
<p><strong>Makwa: Bear Clan</strong></p>
<p>The bear is waking from winter rest. This is your time to step into a healing role. Someone close needs your steady presence. Trust the medicine you carry.</p>
</div>

<div class="horoscope-item">
<p><strong>Migizi: Eagle Clan</strong></p>
<p>Eagle sees far. A message is coming from the east. Pay attention to your dreams this week. Your leadership is needed at a gathering. Show up even if you feel unsure.</p>
</div>

<div class="horoscope-item">
<p><strong>Ma'iingan: Wolf Clan</strong></p>
<p>The pack is calling. Reconnect with someone you haven't spoken to since winter. Walk your path with patience. The trail will become clear by the full moon.</p>
</div>

<div class="horoscope-item">
<p><strong>Waabizheshi: Marten Clan</strong></p>
<p>Marten energy is quick and resourceful. A project you started last month is ready for the next step. Don't overthink it. Act with the confidence of spring.</p>
</div>

<div class="horoscope-item">
<p><strong>Ajijaak: Crane Clan</strong></p>
<p>Crane is a speaker and a leader. Your words carry weight this month. Use them to bring people together, not apart. Someone younger is watching how you handle a difficult conversation.</p>
</div>

<div class="horoscope-item">
<p><strong>Giigoonh: Fish Clan</strong></p>
<p>The fish are running and so is your mind. You have been thinking too much. Get outside, put your feet on the ground, and let the water carry some of that weight. Answers come when you stop looking so hard.</p>
</div>

<div class="horoscope-item">
<p><strong>Bineshiinh: Bird Clan</strong></p>
<p>The birds are returning with new songs. This is a month for creativity. If you have been meaning to bead, draw, write, or sing, start now. Your spirit is ready.</p>
</div>
HTML);

// ── ELDER SPOTLIGHT (Page 11) ───────────────────────────────────────────────

$addItem('elder_spotlight', 'Grace Manitowabi: Keeper of the Land', <<<'HTML'
<p>If you have been to a community gathering in Sagamok in recent years, you have probably heard Grace Manitowabi's voice. She is the one who opens with prayer. She is the one who reminds us what we are here for.</p>

<p>Grace is a Traditional Ecological Knowledge Elder who has spent years fighting to protect the medicines that grow on our land. When the province started spraying glyphosate from helicopters over forests in Robinson Huron Treaty territory, Grace was one of the Elders who stood up and said no. The herbicide kills sage, sweetgrass, and cedar. The same medicines our people have relied on since before anyone drew a map of this place.</p>

<p>She did not just speak at meetings. She helped lead a billboard campaign across the treaty territory so that everyone driving through our lands would see the message: stop spraying our medicines. She brought attention to what hunters in the community were already seeing. Moose with signs of cancer, harvested from the same areas being sprayed.</p>

<p>Grace carries knowledge that does not come from a textbook. It comes from the land, from the water, from generations of people who paid attention to what the earth was telling them. Traditional Ecological Knowledge is not a category in a government report. It is how our people survived and thrived for thousands of years. Grace has made it her work to ensure that knowledge is not lost. And not poisoned.</p>

<p>Beyond the land, Grace has been a voice in conversations about Minigoziwin, our inherent sovereignty, and about Anishinaabe approaches to child wellbeing. She believes our communities should be the ones making decisions about our children, our land, and our future. That is not a political position. It is a teaching.</p>

<p>When the new Anishinabek Police Services detachment opened in Sagamok in 2023, it was Grace who offered the opening prayer. That tells you something about the respect she carries in this community.</p>

<p>We are grateful for Elders like Grace who do not wait for someone else to protect what matters. They just do it. And they show the rest of us what it looks like to stand for something.</p>

<p><em>Miigwech, Grace.</em></p>

<p>Know an Elder who should be featured in a future issue? Tell us through Minoo Live or the contact information on the back page. We will reach out to them with care and only feature them with their permission.</p>
HTML);

// ── BACK PAGE (Page 12) ─────────────────────────────────────────────────────

$addItem('back_page', 'About This Newsletter', <<<'HTML'
<p>This newsletter is generated by Minoo Live, a community platform built by Anishinaabe people for Indigenous communities. Content is produced and curated at <strong>minoo.live</strong>. Community members submit events, stories, and language contributions through the platform. Minoo Live assembles each issue, renders it as a print-ready PDF, and sends it to the printer.</p>

<p>Everything you read in this newsletter started as community data, entered by community members, stored on community infrastructure. No corporate middleman. No data leaving the territory.</p>

<p>This newsletter is free.</p>

<p><strong>Contribute</strong></p>
<p><strong>Submit content:</strong> minoo.live</p>
<p><strong>Email:</strong> russell@web.net</p>
<p><strong>Events, teachings, language, jokes, Elder Spotlight nominations:</strong><br>Submit through Minoo Live or email. We publish what the community sends us.</p>

<p><strong>Partners:</strong> Waaseyaa · OIATC</p>

<p><strong>Minoo Newsletter</strong><br>Vol. 1, Issue 1. May 2026<br>Printed by OJ Graphix</p>

<p><em>Miigwech for reading. See you next month.</em></p>
HTML);

// ── 4. Persist all items ─────────────────────────────────────────────────────

echo "\nCreating " . count($items) . " newsletter items...\n\n";

$bySection = [];
foreach ($items as $data) {
    $item = $itemStorage->create($data);
    $itemStorage->save($item);

    $section = $data['section'];
    $bySection[$section] = ($bySection[$section] ?? 0) + 1;

    echo sprintf(
        "  [%2d] %-18s  %s\n",
        $data['position'],
        $section,
        mb_substr($data['inline_title'], 0, 60),
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
