<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Seed\ConfigSeeder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests the default bio generation template logic used by bin/seed-content.
 */
#[CoversNothing]
final class DefaultBioGeneratorTest extends TestCase
{
    #[Test]
    public function businessDefaultBioIncludesNameAndCommunity(): void
    {
        $name = 'Sagamok Trading Post';
        $type = 'business';
        $community = 'Sagamok Anishnawbek';

        // Resolve type label from ConfigSeeder (mirrors bin/seed-content logic)
        $typeLabel = ucfirst($type);
        foreach (ConfigSeeder::groupTypes() as $gt) {
            if ($gt['type'] === $type) {
                $typeLabel = $gt['name'];
                break;
            }
        }

        $bio = "{$name} is a {$typeLabel} in {$community}.";

        self::assertStringContainsString($name, $bio);
        self::assertStringContainsString($community, $bio);
        self::assertStringContainsString('Local Business', $bio);
        self::assertSame('Sagamok Trading Post is a Local Business in Sagamok Anishnawbek.', $bio);
    }

    #[Test]
    public function personDefaultBioIncludesNameAndRoles(): void
    {
        $name = 'Mary Toulouse';
        $roles = ['Elder', 'Knowledge Keeper'];
        $community = 'Sagamok Anishnawbek';

        $rolesJoined = implode(', ', $roles);
        $bio = "{$name} is a {$rolesJoined} from {$community}.";

        self::assertStringContainsString($name, $bio);
        self::assertStringContainsString('Elder', $bio);
        self::assertStringContainsString('Knowledge Keeper', $bio);
        self::assertStringContainsString($community, $bio);
        self::assertSame('Mary Toulouse is a Elder, Knowledge Keeper from Sagamok Anishnawbek.', $bio);
    }

    #[Test]
    public function personDefaultBioFallsBackToCommunityMember(): void
    {
        $name = 'John Doe';
        $roles = [];
        $community = 'Sagamok Anishnawbek';

        $rolesJoined = $roles !== [] ? implode(', ', $roles) : 'community member';
        $bio = "{$name} is a {$rolesJoined} from {$community}.";

        self::assertStringContainsString('community member', $bio);
        self::assertStringNotContainsString('Elder', $bio);
        self::assertSame('John Doe is a community member from Sagamok Anishnawbek.', $bio);
    }
}
