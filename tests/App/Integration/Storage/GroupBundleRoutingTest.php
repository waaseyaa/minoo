<?php

declare(strict_types=1);

namespace App\Tests\Integration\Storage;

use App\Entity\Group;
use App\Provider\AppBootServiceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

/**
 * Asserts that business-bundle Group values persist to the framework's
 * bundle-scoped `group__business` subtable and that the base `group._data`
 * JSON column does NOT carry those values.
 *
 * This is the structural contract of the groups-extraction refactor:
 *
 *   - `addBundleFields('group', 'business', [...])` populates the registry;
 *   - SqlSchemaHandler::ensureTable() materializes `group__business` columns;
 *   - SqlEntityStorage::partitionBundleValues() routes registered fields to
 *     the subtable on save;
 *   - Base `_data` JSON holds only unregistered values.
 *
 * If any of the 19 business-bundle fields ever regresses to `_data`, this
 * test fails — cheap guard against silent reversion of the subtable routing.
 */
#[CoversNothing]
final class GroupBundleRoutingTest extends TestCase
{
    private static HttpKernel $kernel;

    /**
     * Derived from `AppBootServiceProvider::groupBusinessBundleFields()` so adding
     * a new bundle field to the provider auto-extends the routing assertion.
     *
     * @return list<string>
     */
    private function expectedBundleFields(): array
    {
        return array_map(
            static fn (FieldDefinition $field): string => $field->getName(),
            AppBootServiceProvider::groupBusinessBundleFields(),
        );
    }

    public static function setUpBeforeClass(): void
    {
        $projectRoot = dirname(__DIR__, 4);

        $cachePath = $projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel($projectRoot);
        (new \ReflectionMethod(AbstractKernel::class, 'boot'))->invoke(self::$kernel);
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $projectRoot = dirname(__DIR__, 4);
        $cachePath = $projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    #[Test]
    public function businessBundleFieldsLandInSubtableNotBaseData(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $storage = $etm->getStorage('group');

        $group = new Group([
            'name' => 'Structural Test Business',
            'slug' => 'structural-test-business',
            'type' => 'business',
            'status' => 1,
            'consent_public' => 1,
            'consent_ai_training' => 0,
            'description' => 'Verifies bundle routing.',
            'url' => 'https://example.test',
            'region' => 'Manitoulin',
            'community_id' => 42,
            'phone' => '+17055550000',
            'email' => 'test@example.test',
            'address' => '1 Structural Way',
            'booking_url' => 'https://book.example.test',
            'media_id' => 7,
            'copyright_status' => 'community_owned',
            'source' => 'manual:test:2026-04-19',
            'verified_at' => '2026-04-19T00:00:00Z',
            'social_posts' => '[]',
            'latitude' => 45.78,
            'longitude' => -81.74,
            'coordinate_source' => 'address',
        ]);
        $storage->save($group);
        $gid = $group->id();

        self::assertNotNull($gid, 'Saved group should have an assigned id.');

        $database = self::$kernel->getDatabase();
        self::assertInstanceOf(DBALDatabase::class, $database);
        $connection = $database->getConnection();

        $expectedFields = $this->expectedBundleFields();

        $subtableRow = $connection->fetchAssociative(
            'SELECT * FROM group__business WHERE gid = :gid',
            ['gid' => $gid],
        );
        self::assertIsArray($subtableRow, 'group__business row should exist after save.');

        foreach ($expectedFields as $field) {
            self::assertArrayHasKey(
                $field,
                $subtableRow,
                sprintf('Bundle field "%s" should be a column on group__business.', $field),
            );
        }

        $baseDataJson = (string) $connection->fetchOne(
            'SELECT _data FROM "group" WHERE gid = :gid',
            ['gid' => $gid],
        );
        $baseData = $baseDataJson === '' ? [] : json_decode($baseDataJson, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($baseData);

        foreach ($expectedFields as $field) {
            self::assertArrayNotHasKey(
                $field,
                $baseData,
                sprintf('Bundle field "%s" must not leak into base _data JSON.', $field),
            );
        }
    }
}
