<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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

        // All Minoo entity types from app service providers
        // (language-platform set after the 2026-06 slimming).
        $minooTypes = [
            'dictionary_entry', 'example_sentence', 'word_part', 'speaker', 'contributor',
            'game_session', 'daily_challenge', 'crossword_puzzle',
            'featured_item',
            'ingest_log',
            // Sovereign Social spine: post (#811) + engagement from
            // waaseyaa/engagement (#812).
            'post', 'reaction', 'comment', 'follow',
            // Community graph geo index (#815).
            'community',
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
        $repository = self::$kernel->getEntityTypeManager()->getRepository('dictionary_entry');

        // Create and save.
        $entity = $repository->create([
            'word' => 'makwa',
            'definition' => 'bear',
            'part_of_speech' => 'na',
            'language_code' => 'oj',
            'status' => 1,
        ]);
        $repository->save($entity);
        $id = $entity->id();

        $this->assertNotNull($id, 'Entity should have an ID after save.');

        // Load and verify.
        $loaded = $repository->find((string) $id);
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
        $entryRepository = $manager->getRepository('dictionary_entry');
        $entry = $entryRepository->create([
            'word' => 'jiimaan',
            'definition' => 'canoe',
            'part_of_speech' => 'ni',
            'status' => 1,
        ]);
        $entryRepository->save($entry);
        $entryId = $entry->id();

        // Create an example sentence referencing the entry.
        $sentenceRepository = $manager->getRepository('example_sentence');
        $sentence = $sentenceRepository->create([
            'ojibwe_text' => 'Jiimaan agamiing dago.',
            'english_text' => 'The canoe is by the lake.',
            'dictionary_entry_id' => $entryId,
            'status' => 1,
        ]);
        $sentenceRepository->save($sentence);

        // Load and verify the reference.
        $loaded = $sentenceRepository->find((string) $sentence->id());
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

    /**
     * Regression lock for the community_group rename (#923 spec §7 item 5):
     * the entity type manager must recognize the new type id, and Minoo's
     * pre-rename 'group' registration must stay gone after a real kernel
     * boot. Since alpha.267 the framework's waaseyaa/groups package ships
     * its OWN 'group' entity type (Waaseyaa\Groups\Group), so 'group' may
     * exist again — the lock is that it is never Minoo's class.
     */
    #[Test]
    public function community_group_entity_type_replaces_legacy_group_id(): void
    {
        $manager = self::$kernel->getEntityTypeManager();

        $communityGroup = $manager->getDefinition('community_group');
        $this->assertNotNull(
            $communityGroup,
            "Entity type 'community_group' should be registered after the #923 rename.",
        );
        $this->assertSame(
            \App\Entity\Groups\Group::class,
            $communityGroup->getClass(),
            "Entity type 'community_group' should be backed by Minoo's Group entity class.",
        );

        if ($manager->hasDefinition('group')) {
            $this->assertSame(
                \Waaseyaa\Groups\Group::class,
                $manager->getDefinition('group')?->getClass(),
                "Any 'group' entity type must be the framework's own (waaseyaa/groups), never Minoo's pre-#923 registration.",
            );
        }
    }
}
