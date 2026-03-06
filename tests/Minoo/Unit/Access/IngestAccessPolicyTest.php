<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\IngestAccessPolicy;
use Minoo\Entity\IngestLog;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestAccessPolicy::class)]
final class IngestAccessPolicyTest extends TestCase
{
    #[Test]
    public function admin_can_view_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();
        $log = new IngestLog(['title' => 'test', 'source' => 'northcloud', 'entity_type_target' => 'node', 'payload_raw' => '{}', 'payload_parsed' => '{}']);
        $account = $this->createAdminAccount();

        $result = $policy->access($log, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();
        $log = new IngestLog(['title' => 'test', 'source' => 'northcloud', 'entity_type_target' => 'node', 'payload_raw' => '{}', 'payload_parsed' => '{}']);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($log, 'view', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_create_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();
        $account = $this->createAdminAccount();

        $result = $policy->createAccess('ingest_log', '', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('ingest_log', '', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function applies_to_ingest_log(): void
    {
        $policy = new IngestAccessPolicy();

        $this->assertTrue($policy->appliesTo('ingest_log'));
        $this->assertFalse($policy->appliesTo('node'));
    }

    private function createAnonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $permission): bool
            {
                return $permission === 'access content';
            }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };
    }

    private function createAdminAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 1; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['administrator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}
