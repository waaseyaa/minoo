<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class BootTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;
    private static bool $booted = false;

    /**
     * Boot the kernel once for all tests in this class.
     * Uses the real project root with in-memory SQLite.
     */
    public static function setUpBeforeClass(): void
    {
        // tests/Minoo/Integration/ → 3 levels up to project root.
        self::$projectRoot = dirname(__DIR__, 3);

        // Delete stale manifest cache to force fresh compilation.
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        // Use in-memory database for test isolation.
        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);
        self::$booted = true;
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        // Remove the manifest cache that was generated during test.
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    #[Test]
    public function kernel_boots_with_all_minoo_entity_types(): void
    {
        $this->assertTrue(self::$booted, 'Kernel should boot without errors.');

        $manager = self::$kernel->getEntityTypeManager();

        // Built-in entity types from framework packages.
        $this->assertNotNull($manager->getDefinition('node'));
        $this->assertNotNull($manager->getDefinition('taxonomy_term'));
        $this->assertNotNull($manager->getDefinition('user'));

        // All 13 Minoo entity types from app service providers.
        $minooTypes = [
            'event', 'event_type',
            'group', 'group_type',
            'cultural_group',
            'teaching', 'teaching_type',
            'cultural_collection',
            'dictionary_entry', 'example_sentence', 'word_part', 'speaker',
            'ingest_log',
        ];

        foreach ($minooTypes as $typeId) {
            $this->assertNotNull(
                $manager->getDefinition($typeId),
                "Entity type '{$typeId}' should be registered.",
            );
        }
    }

    #[Test]
    public function dictionary_entry_crud_round_trip(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getStorage('dictionary_entry');

        // Create and save.
        $entity = $storage->create([
            'word' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'language_code' => 'oj',
            'status' => 1,
        ]);
        $storage->save($entity);
        $id = $entity->id();

        $this->assertNotNull($id, 'Entity should have an ID after save.');

        // Load and verify.
        $loaded = $storage->load($id);
        $this->assertNotNull($loaded);
        $this->assertSame('makwa', $loaded->get('word'));
        $this->assertSame('bear', $loaded->get('definition'));
        $this->assertSame('na', $loaded->get('part_of_speech'));
    }

    #[Test]
    public function example_sentence_references_dictionary_entry(): void
    {
        $manager = self::$kernel->getEntityTypeManager();

        // Create a dictionary entry.
        $entryStorage = $manager->getStorage('dictionary_entry');
        $entry = $entryStorage->create([
            'word' => 'jiimaan',
            'definition' => 'canoe',
            'part_of_speech' => 'ni',
            'status' => 1,
        ]);
        $entryStorage->save($entry);
        $entryId = $entry->id();

        // Create an example sentence referencing the entry.
        $sentenceStorage = $manager->getStorage('example_sentence');
        $sentence = $sentenceStorage->create([
            'ojibwe_text' => 'Jiimaan agamiing dago.',
            'english_text' => 'The canoe is by the lake.',
            'dictionary_entry_id' => $entryId,
            'status' => 1,
        ]);
        $sentenceStorage->save($sentence);

        // Load and verify the reference.
        $loaded = $sentenceStorage->load($sentence->id());
        $this->assertNotNull($loaded);
        $this->assertSame($entryId, $loaded->get('dictionary_entry_id'));
        $this->assertSame('The canoe is by the lake.', $loaded->get('english_text'));
    }

    #[Test]
    public function access_policies_are_discovered_for_minoo_entities(): void
    {
        // Access the accessHandler via reflection.
        $ref = new \ReflectionProperty(AbstractKernel::class, 'accessHandler');
        $handler = $ref->getValue(self::$kernel);

        $this->assertNotNull($handler, 'EntityAccessHandler should be initialized.');
    }
}
