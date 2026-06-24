<?php

declare(strict_types=1);

namespace App\Tests\Unit\Access\Language;

use App\Access\Language\LanguageAccessPolicy;
use App\Entity\Language\DictionaryEntry;
use App\Entity\Language\TmGapLog;
use App\Entity\Language\TranslationMemory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

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
        $this->assertTrue($policy->appliesTo('dialect_region'));
        $this->assertTrue($policy->appliesTo('speaker'));
        $this->assertTrue($policy->appliesTo('translation_memory'));
        $this->assertTrue($policy->appliesTo('tm_gap_log'));
        $this->assertFalse($policy->appliesTo('node'));
    }

    #[Test]
    public function anonymous_can_view_published_consented_translation_memory(): void
    {
        $policy = new LanguageAccessPolicy();
        $tm = new TranslationMemory(['source_en' => 'bear', 'translation' => 'makwa', 'status' => 1, 'consent_public' => 1]);

        $this->assertTrue($policy->access($tm, 'view', $this->anonymous())->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_view_consent_gated_translation_memory(): void
    {
        $policy = new LanguageAccessPolicy();
        $tm = new TranslationMemory(['source_en' => 'bear', 'translation' => 'makwa', 'status' => 1, 'consent_public' => 0]);

        $this->assertFalse($policy->access($tm, 'view', $this->anonymous())->isAllowed());
    }

    #[Test]
    public function gap_log_is_admin_only_never_public(): void
    {
        $policy = new LanguageAccessPolicy();
        // A string lifecycle status never satisfies the integer status-1 view gate.
        $gap = new TmGapLog(['source_en' => 'snowmobile', 'status' => 'open']);

        $this->assertFalse($policy->access($gap, 'view', $this->anonymous())->isAllowed());
        $this->assertTrue($policy->access($gap, 'view', $this->admin())->isAllowed());
    }

    private function anonymous(): AccountInterface
    {
        return new class () implements AccountInterface {
            public function id(): int
            {
                return 0;
            }
            public function hasPermission(string $p): bool
            {
                return $p === 'access content';
            }
            public function getRoles(): array
            {
                return ['anonymous'];
            }
            public function isAuthenticated(): bool
            {
                return false;
            }
        };
    }

    private function admin(): AccountInterface
    {
        return new class () implements AccountInterface {
            public function id(): int
            {
                return 1;
            }
            public function hasPermission(string $p): bool
            {
                return $p === 'administer content';
            }
            public function getRoles(): array
            {
                return ['admin'];
            }
            public function isAuthenticated(): bool
            {
                return true;
            }
        };
    }

    #[Test]
    public function anonymous_can_view_published_dictionary_entry(): void
    {
        $policy = new LanguageAccessPolicy();
        $entry = new DictionaryEntry(['word' => 'makwa', 'definition' => 'bear', 'part_of_speech' => 'na', 'status' => 1]);

        $account = new class () implements AccountInterface {
            public function id(): int
            {
                return 0;
            }
            public function hasPermission(string $p): bool
            {
                return $p === 'access content';
            }
            public function getRoles(): array
            {
                return ['anonymous'];
            }
            public function isAuthenticated(): bool
            {
                return false;
            }
        };

        $result = $policy->access($entry, 'view', $account);
        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_dictionary_entry(): void
    {
        $policy = new LanguageAccessPolicy();

        $account = new class () implements AccountInterface {
            public function id(): int
            {
                return 0;
            }
            public function hasPermission(string $p): bool
            {
                return $p === 'access content';
            }
            public function getRoles(): array
            {
                return ['anonymous'];
            }
            public function isAuthenticated(): bool
            {
                return false;
            }
        };

        $result = $policy->createAccess('dictionary_entry', '', $account);
        $this->assertFalse($result->isAllowed());
    }
}
