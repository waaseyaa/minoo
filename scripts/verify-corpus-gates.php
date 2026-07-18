<?php

declare(strict_types=1);

/**
 * Phase 4 gate verification. Asserts that every imported corpus row is:
 *   - consent flags OFF, status 0 (DB level)
 *   - invisible to anonymous entity queries (access level)
 *   - absent from the FTS search index and embeddings (search/AI level)
 * Exit 1 on any violation.
 */

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$violations = 0;

function check(bool $ok, string $label): void
{
    global $violations;
    echo($ok ? '[PASS] ' : '[VIOLATION] ') . $label . "\n";
    if (!$ok) {
        $violations++;
    }
}

// --- DB level ----------------------------------------------------------------

$db = new PDO('sqlite:' . $projectRoot . '/storage/waaseyaa.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = $db->query("SELECT _data FROM example_sentence WHERE json_extract(_data, '$.source_sentence_id') LIKE 'corpus:%'")->fetchAll(PDO::FETCH_COLUMN);
check(count($rows) === 27, 'corpus rows present: ' . count($rows) . '/27');

$bad = 0;
foreach ($rows as $raw) {
    $d = json_decode($raw, true);
    if ((int) ($d['consent_public'] ?? 1) !== 0
        || (int) ($d['consent_ai_training'] ?? 1) !== 0
        || (int) ($d['status'] ?? 1) !== 0) {
        $bad++;
    }
}
check($bad === 0, "all corpus rows consent OFF + unpublished (bad: $bad)");

$speakers = $db->query("SELECT _data FROM speaker")->fetchAll(PDO::FETCH_COLUMN);
$badSpeaker = 0;
foreach ($speakers as $raw) {
    $d = json_decode($raw, true);
    if ((int) ($d['consent_public_display'] ?? 1) !== 0
        || (int) ($d['consent_ai_training'] ?? 1) !== 0
        || (int) ($d['status'] ?? 1) !== 0) {
        $badSpeaker++;
    }
}
check(count($speakers) > 0 && $badSpeaker === 0, 'all speakers consent OFF + unpublished (bad: ' . $badSpeaker . ' of ' . count($speakers) . ')');

// --- Search / AI level --------------------------------------------------------

$ftsHits = (int) $db->query("SELECT COUNT(*) FROM search_index_content WHERE c1 LIKE '%Shoogan%' OR c2 LIKE '%Shoogan%' OR c0 LIKE '%Shoogan%'")->fetchColumn();
check($ftsHits === 0, 'corpus text absent from FTS search index');

$embHits = (int) $db->query("SELECT COUNT(*) FROM embeddings WHERE entity_type IN ('example_sentence', 'speaker')")->fetchColumn();
check($embHits === 0, 'no embeddings for corpus entity types');

// --- Access level (anonymous kernel queries) ----------------------------------

$kernel = new HttpKernel($projectRoot);
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);
$etm = $kernel->getEntityTypeManager();

// Anonymous view of a corpus sentence must be denied by the gate.
$repository = $etm->getRepository('example_sentence');
$ids = $repository->getQuery()->accessCheck(false)
    ->condition('source_sentence_id', 'corpus:sb-001')
    ->execute();
check($ids !== [], 'corpus sentence sb-001 exists for admin tooling');

if ($ids !== []) {
    $entity = $repository->find((string) reset($ids));
    $handler = $kernel->getAccessHandler();
    $anonymous = new class () implements Waaseyaa\Access\AccountInterface {
        public function id(): int|string
        {
            return 0;
        }

        public function hasPermission(string $permission): bool
        {
            return false;
        }

        public function getRoles(): array
        {
            return [];
        }

        public function isAuthenticated(): bool
        {
            return false;
        }
    };
    $result = $handler->check($entity, 'view', $anonymous);
    check(!$result->isAllowed(), 'anonymous view of corpus sentence is denied');

    $speakerIds = $etm->getRepository('speaker')->getQuery()->accessCheck(false)->execute();
    $speaker = $etm->getRepository('speaker')->find((string) reset($speakerIds));
    $sResult = $handler->check($speaker, 'view', $anonymous);
    check(!$sResult->isAllowed(), 'anonymous view of speaker is denied');
}

// Public dictionary search must not surface corpus content.
$entries = $etm->getRepository('dictionary_entry')->getQuery()->accessCheck(false)
    ->condition('word', '%Shoogan%', 'LIKE')
    ->execute();
check($entries === [], 'corpus text never entered dictionary_entry');

echo "\n" . ($violations === 0 ? 'ALL GATES HOLD' : "$violations VIOLATION(S)") . "\n";
exit($violations === 0 ? 0 : 1);
