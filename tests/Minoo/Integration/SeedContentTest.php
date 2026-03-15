<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Entity\Group;
use Minoo\Entity\ResourcePerson;
use Minoo\Support\FixtureLoader;
use Minoo\Support\FixtureResolver;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class SeedContentTest extends TestCase
{
    private static $etm;

    public static function setUpBeforeClass(): void
    {
        $packagesFile = dirname(__DIR__, 3) . '/storage/framework/packages.php';
        if (file_exists($packagesFile)) {
            unlink($packagesFile);
        }

        putenv('WAASEYAA_DB=:memory:');
        $kernel = new HttpKernel(dirname(__DIR__, 3));
        (new \ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);
        self::$etm = $kernel->getEntityTypeManager();
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');
        $packagesFile = dirname(__DIR__, 3) . '/storage/framework/packages.php';
        if (file_exists($packagesFile)) {
            unlink($packagesFile);
        }
    }

    #[Test]
    public function fixtureLoaderValidatesBusinessFixtures(): void
    {
        $loader = new FixtureLoader(dirname(__DIR__, 3) . '/content');
        $records = $loader->load('businesses');

        $this->assertNotEmpty($records, 'businesses.json should contain records');

        $errors = $loader->validate($records, 'businesses');
        $this->assertSame([], $errors, 'Business fixtures should have no validation errors');
    }

    #[Test]
    public function fixtureLoaderValidatesPeopleFixtures(): void
    {
        $loader = new FixtureLoader(dirname(__DIR__, 3) . '/content');
        $records = $loader->load('people');

        $this->assertNotEmpty($records, 'people.json should contain records');

        $errors = $loader->validate($records, 'people');
        $this->assertSame([], $errors, 'People fixtures should have no validation errors');
    }

    #[Test]
    public function fixtureLoaderValidatesEventFixtures(): void
    {
        $loader = new FixtureLoader(dirname(__DIR__, 3) . '/content');
        $records = $loader->load('events');

        $this->assertNotEmpty($records, 'events.json should contain records');

        $errors = $loader->validate($records, 'events');
        $this->assertSame([], $errors, 'Event fixtures should have no validation errors');
    }

    #[Test]
    public function canCreateAndLoadBusinessEntity(): void
    {
        $storage = self::$etm->getStorage('group');

        $group = new Group([
            'name' => 'Test Business',
            'slug' => 'test-business',
            'type' => 'business',
            'description' => 'A test business.',
            'phone' => '+17055551234',
            'email' => 'test@example.com',
            'address' => '123 Main St, Espanola, ON',
            'source' => 'test:unit:2026-03-15',
        ]);

        $storage->save($group);
        $id = $group->id();
        $this->assertNotNull($id);

        $loaded = $storage->load($id);
        $this->assertSame('Test Business', $loaded->get('name'));
        $this->assertSame('+17055551234', $loaded->get('phone'));
        $this->assertSame('test@example.com', $loaded->get('email'));
        $this->assertSame('123 Main St, Espanola, ON', $loaded->get('address'));
        $this->assertSame('test:unit:2026-03-15', $loaded->get('source'));
    }

    #[Test]
    public function canCreateResourcePersonWithLinkedGroup(): void
    {
        $groupStorage = self::$etm->getStorage('group');
        $personStorage = self::$etm->getStorage('resource_person');

        // Create a group first
        $group = new Group([
            'name' => 'Linked Business',
            'slug' => 'linked-business',
            'type' => 'business',
        ]);
        $groupStorage->save($group);
        $groupId = $group->id();

        // Create person linked to group
        $person = new ResourcePerson([
            'name' => 'Test Person',
            'slug' => 'test-person',
            'community' => 'Test Town',
            'linked_group_id' => $groupId,
            'source' => 'test:unit:2026-03-15',
        ]);
        $personStorage->save($person);

        $loaded = $personStorage->load($person->id());
        $this->assertSame($groupId, $loaded->get('linked_group_id'));
        $this->assertSame('test:unit:2026-03-15', $loaded->get('source'));
    }

    #[Test]
    public function upsertBySlugUpdatesExistingRecord(): void
    {
        $storage = self::$etm->getStorage('group');

        // Create initial
        $group = new Group([
            'name' => 'Original Name',
            'slug' => 'upsert-test',
            'type' => 'business',
        ]);
        $storage->save($group);
        $originalId = $group->id();

        // Upsert — find by slug, update
        $ids = $storage->getQuery()->condition('slug', 'upsert-test')->execute();
        $this->assertNotEmpty($ids);

        $existing = $storage->load(reset($ids));
        $existing->set('name', 'Updated Name');
        $existing->set('phone', '+17055559999');
        $storage->save($existing);

        // Verify
        $reloaded = $storage->load($originalId);
        $this->assertSame('Updated Name', $reloaded->get('name'));
        $this->assertSame('+17055559999', $reloaded->get('phone'));
    }
}
