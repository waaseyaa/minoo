<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Ingest\IngestImporter;
use Minoo\Ingest\IngestMaterializer;
use Minoo\Ingest\PayloadValidator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class IngestPipelineTest extends TestCase
{
    private static HttpKernel $kernel;
    private static EntityTypeManager $manager;

    public static function setUpBeforeClass(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        $cachePath = $projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel($projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);

        self::$manager = self::$kernel->getEntityTypeManager();
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = dirname(__DIR__, 3) . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    #[Test]
    public function full_pipeline_creates_entities_from_fixture(): void
    {
        $fixture = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/tests/fixtures/ojibwe_lib/dictionary_entry_makwa.json'),
            true,
        );

        // Phase 1: Import.
        $importer = new IngestImporter(new PayloadValidator());
        $log = $importer->import($fixture);

        $this->assertSame('pending_review', $log->get('status'));
        $this->assertSame('dictionary_entry', $log->get('entity_type_target'));

        // Phase 2: Materialize.
        $materializer = new IngestMaterializer(self::$manager);
        $result = $materializer->materialize($log);

        $this->assertNotNull($result->primaryEntityId);

        // Verify dictionary entry was created.
        $entry = self::$manager->getStorage('dictionary_entry')->load($result->primaryEntityId);
        $this->assertNotNull($entry);
        $this->assertSame('makwa', $entry->get('word'));
        $this->assertSame('bear', $entry->get('definition'));
        $this->assertSame('na', $entry->get('part_of_speech'));

        // Verify child entities were created (speaker + example_sentence + word_part).
        $types = array_column($result->created, 'type');
        $this->assertContains('speaker', $types);
        $this->assertContains('example_sentence', $types);
        $this->assertContains('word_part', $types);
    }

    #[Test]
    public function dry_run_does_not_persist_entities(): void
    {
        $fixture = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/tests/fixtures/ojibwe_lib/speaker_es.json'),
            true,
        );

        $importer = new IngestImporter(new PayloadValidator());
        $log = $importer->import($fixture);

        $materializer = new IngestMaterializer(self::$manager);
        $result = $materializer->materialize($log, dryRun: true);

        $this->assertNotEmpty($result->created);
        $this->assertNull($result->primaryEntityId);
    }
}
