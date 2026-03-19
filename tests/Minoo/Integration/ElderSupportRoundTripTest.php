<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class ElderSupportRoundTripTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        // tests/Minoo/Integration/ → 3 levels up to project root.
        self::$projectRoot = dirname(__DIR__, 3);

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    #[Test]
    public function elder_support_request_can_be_created_and_loaded(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getStorage('elder_support_request');

        $entity = $storage->create([
            'name'       => 'Elder Rose',
            'phone'      => '705-555-0001',
            'type'       => 'ride',
            'status'     => 'open',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($entity);

        $id = $entity->id();
        $this->assertNotNull($id, 'Request should have an ID after save.');

        $loaded = $storage->load($id);
        $this->assertNotNull($loaded);
        $this->assertSame('Elder Rose', $loaded->get('name'));
        $this->assertSame('open', $loaded->get('status'));
    }

    #[Test]
    public function volunteer_can_be_created_and_linked_by_account_id(): void
    {
        $this->markTestSkipped('account_id stored in _data blob; condition() cannot query JSON fields yet');
        $storage = self::$kernel->getEntityTypeManager()->getStorage('volunteer');

        $entity = $storage->create([
            'name'       => 'John Birchbark',
            'phone'      => '705-555-0002',
            'status'     => 'active',
            'account_id' => 42,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($entity);

        $ids = $storage->getQuery()->condition('account_id', 42)->execute();
        $this->assertCount(1, $ids, 'Volunteer should be findable by account_id.');

        $loaded = $storage->load(reset($ids));
        $this->assertNotNull($loaded);
        $this->assertSame('John Birchbark', $loaded->get('name'));
        $this->assertSame(42, $loaded->get('account_id'));
    }

    #[Test]
    public function full_request_assign_complete_confirm_round_trip(): void
    {
        $this->markTestSkipped('account_id/volunteer_id stored in _data blob; condition() cannot query JSON fields yet');
        $etm = self::$kernel->getEntityTypeManager();
        $requestStorage  = $etm->getStorage('elder_support_request');
        $volunteerStorage = $etm->getStorage('volunteer');

        // 1. Create an open request.
        $request = $requestStorage->create([
            'name'       => 'Elder Mary',
            'phone'      => '705-555-0010',
            'type'       => 'groceries',
            'status'     => 'open',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $requestStorage->save($request);
        $requestId = $request->id();
        $this->assertSame('open', $request->get('status'));

        // 2. Create an active volunteer.
        $volunteer = $volunteerStorage->create([
            'name'       => 'Sarah Cedar',
            'phone'      => '705-555-0011',
            'status'     => 'active',
            'account_id' => 55,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $volunteerStorage->save($volunteer);
        $volunteerId = $volunteer->id();

        // 3. Assign the volunteer to the request.
        $request->set('assigned_volunteer', $volunteerId);
        $request->set('status', 'assigned');
        $request->set('updated_at', time());
        $requestStorage->save($request);

        // 4. Reload and verify assignment persisted.
        $reloaded = $requestStorage->load($requestId);
        $this->assertNotNull($reloaded);
        $this->assertSame('assigned', $reloaded->get('status'));
        $this->assertSame($volunteerId, $reloaded->get('assigned_volunteer'));

        // 5. Volunteer dashboard query: finds the request by assigned_volunteer account.
        // The dashboard queries by account_id=55 (the volunteer's user account).
        // assigned_volunteer stores the volunteer *entity* ID, not the account ID.
        // The dashboard controller loads the volunteer by account_id, then uses the
        // volunteer entity's ID as the assigned_volunteer lookup value.
        $volIds = $volunteerStorage->getQuery()->condition('account_id', 55)->execute();
        $this->assertCount(1, $volIds);
        $vol = $volunteerStorage->load(reset($volIds));
        $this->assertNotNull($vol);

        $assignedIds = $requestStorage->getQuery()
            ->condition('assigned_volunteer', $vol->id())
            ->execute();
        $this->assertCount(1, $assignedIds, 'Volunteer dashboard query should find the assigned request.');

        // 6. Volunteer marks in progress.
        $reloaded->set('status', 'in_progress');
        $reloaded->set('updated_at', time());
        $requestStorage->save($reloaded);

        $inProgress = $requestStorage->load($requestId);
        $this->assertSame('in_progress', $inProgress->get('status'));

        // 7. Volunteer marks complete.
        $inProgress->set('status', 'completed');
        $inProgress->set('updated_at', time());
        $requestStorage->save($inProgress);

        $completed = $requestStorage->load($requestId);
        $this->assertSame('completed', $completed->get('status'));

        // 8. Coordinator confirms.
        $completed->set('status', 'confirmed');
        $completed->set('updated_at', time());
        $requestStorage->save($completed);

        $final = $requestStorage->load($requestId);
        $this->assertSame('confirmed', $final->get('status'));
    }

    #[Test]
    public function volunteer_dashboard_query_returns_only_own_assignments(): void
    {
        $this->markTestSkipped('account_id stored in _data blob; condition() cannot query JSON fields yet');
        $etm = self::$kernel->getEntityTypeManager();
        $requestStorage  = $etm->getStorage('elder_support_request');
        $volunteerStorage = $etm->getStorage('volunteer');

        // Create two volunteers with different account IDs.
        $vol1 = $volunteerStorage->create([
            'name' => 'Vol One', 'phone' => '705-555-0020',
            'status' => 'active', 'account_id' => 101,
            'created_at' => time(), 'updated_at' => time(),
        ]);
        $volunteerStorage->save($vol1);

        $vol2 = $volunteerStorage->create([
            'name' => 'Vol Two', 'phone' => '705-555-0021',
            'status' => 'active', 'account_id' => 102,
            'created_at' => time(), 'updated_at' => time(),
        ]);
        $volunteerStorage->save($vol2);

        // Assign one request to vol1 and one to vol2.
        $req1 = $requestStorage->create([
            'name' => 'Elder A', 'phone' => '555-001', 'type' => 'ride',
            'status' => 'assigned', 'assigned_volunteer' => $vol1->id(),
            'created_at' => time(), 'updated_at' => time(),
        ]);
        $requestStorage->save($req1);

        $req2 = $requestStorage->create([
            'name' => 'Elder B', 'phone' => '555-002', 'type' => 'visit',
            'status' => 'assigned', 'assigned_volunteer' => $vol2->id(),
            'created_at' => time(), 'updated_at' => time(),
        ]);
        $requestStorage->save($req2);

        // Vol1's dashboard query should only return req1.
        $vol1Ids = $requestStorage->getQuery()
            ->condition('assigned_volunteer', $vol1->id())
            ->execute();
        $this->assertCount(1, $vol1Ids);
        $this->assertContains($req1->id(), $vol1Ids);
        $this->assertNotContains($req2->id(), $vol1Ids);

        // Vol2's dashboard query should only return req2.
        $vol2Ids = $requestStorage->getQuery()
            ->condition('assigned_volunteer', $vol2->id())
            ->execute();
        $this->assertCount(1, $vol2Ids);
        $this->assertContains($req2->id(), $vol2Ids);
        $this->assertNotContains($req1->id(), $vol2Ids);
    }
}
