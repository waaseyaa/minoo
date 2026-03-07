<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\PeopleAccessPolicy;
use Minoo\Entity\ResourcePerson;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PeopleAccessPolicy::class)]
final class PeopleAccessPolicyTest extends TestCase
{
    #[Test]
    public function it_applies_to_resource_person(): void
    {
        $policy = new PeopleAccessPolicy();

        $this->assertTrue($policy->appliesTo('resource_person'));
        $this->assertFalse($policy->appliesTo('event'));
        $this->assertFalse($policy->appliesTo('speaker'));
    }

    #[Test]
    public function anonymous_can_view_published_resource_person(): void
    {
        $policy = new PeopleAccessPolicy();
        $person = new ResourcePerson(['name' => 'Mary Trudeau', 'slug' => 'mary-trudeau', 'status' => 1]);

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return $p === 'access content'; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->access($person, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_resource_person(): void
    {
        $policy = new PeopleAccessPolicy();

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return $p === 'access content'; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->createAccess('resource_person', '', $account);
        $this->assertFalse($result->isAllowed());
    }
}
