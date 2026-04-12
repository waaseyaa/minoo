<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access;

use App\Access\ContributorAccessPolicy;
use App\Entity\Contributor;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContributorAccessPolicy::class)]
final class ContributorAccessPolicyTest extends TestCase
{
    #[Test]
    public function it_applies_to_contributor_only(): void
    {
        $policy = new ContributorAccessPolicy();

        $this->assertTrue($policy->appliesTo('contributor'));
        $this->assertFalse($policy->appliesTo('speaker'));
        $this->assertFalse($policy->appliesTo('dictionary_entry'));
    }

    #[Test]
    public function anonymous_can_view_published_contributor_with_consent(): void
    {
        $policy = new ContributorAccessPolicy();
        $contributor = new Contributor(['name' => 'Test', 'status' => 1, 'consent_public' => 1]);

        $result = $policy->access($contributor, 'view', $this->createAnonymousAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_published_contributor_without_consent(): void
    {
        $policy = new ContributorAccessPolicy();
        $contributor = new Contributor(['name' => 'Test', 'status' => 1, 'consent_public' => 0]);

        $result = $policy->access($contributor, 'view', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_contributor(): void
    {
        $policy = new ContributorAccessPolicy();
        $contributor = new Contributor(['name' => 'Test', 'status' => 0, 'consent_public' => 1]);

        $result = $policy->access($contributor, 'view', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_view_contributor_without_consent(): void
    {
        $policy = new ContributorAccessPolicy();
        $contributor = new Contributor(['name' => 'Test', 'status' => 1, 'consent_public' => 0]);

        $result = $policy->access($contributor, 'view', $this->createAdminAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_contributor(): void
    {
        $policy = new ContributorAccessPolicy();
        $contributor = new Contributor(['name' => 'Test']);

        $result = $policy->access($contributor, 'update', $this->createAdminAccount());

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_contributor(): void
    {
        $policy = new ContributorAccessPolicy();

        $result = $policy->createAccess('contributor', '', $this->createAnonymousAccount());

        $this->assertFalse($result->isAllowed());
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
