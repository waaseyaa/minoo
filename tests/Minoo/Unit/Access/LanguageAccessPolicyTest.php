<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\LanguageAccessPolicy;
use Minoo\Entity\DictionaryEntry;
use Minoo\Entity\Speaker;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageAccessPolicy::class)]
final class LanguageAccessPolicyTest extends TestCase
{
    #[Test]
    public function it_applies_to_all_language_entity_types(): void
    {
        $policy = new LanguageAccessPolicy();

        $this->assertTrue($policy->appliesTo('dictionary_entry'));
        $this->assertTrue($policy->appliesTo('example_sentence'));
        $this->assertTrue($policy->appliesTo('word_part'));
        $this->assertTrue($policy->appliesTo('speaker'));
        $this->assertFalse($policy->appliesTo('node'));
    }

    #[Test]
    public function anonymous_can_view_published_dictionary_entry(): void
    {
        $policy = new LanguageAccessPolicy();
        $entry = new DictionaryEntry(['word' => 'makwa', 'definition' => 'bear', 'part_of_speech' => 'na', 'status' => 1]);

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return $p === 'access content'; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->access($entry, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_speaker(): void
    {
        $policy = new LanguageAccessPolicy();

        $account = new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $p): bool { return $p === 'access content'; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };

        $result = $policy->createAccess('speaker', '', $account);
        $this->assertFalse($result->isAllowed());
    }
}
