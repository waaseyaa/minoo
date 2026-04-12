<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\CulturalCollectionServiceProvider;
use App\Provider\CulturalGroupServiceProvider;
use App\Provider\EventServiceProvider;
use App\Provider\GroupServiceProvider;
use App\Provider\LanguageServiceProvider;
use App\Provider\LeaderServiceProvider;
use App\Provider\PeopleServiceProvider;
use App\Provider\TeachingServiceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

#[CoversNothing]
final class ConsentFieldsTest extends TestCase
{
    /**
     * @return array<string, array{ServiceProvider, string}>
     */
    public static function contentProviderDataProvider(): array
    {
        return [
            'teaching' => [new TeachingServiceProvider(), 'teaching'],
            'event' => [new EventServiceProvider(), 'event'],
            'group' => [new GroupServiceProvider(), 'group'],
            'cultural_group' => [new CulturalGroupServiceProvider(), 'cultural_group'],
            'cultural_collection' => [new CulturalCollectionServiceProvider(), 'cultural_collection'],
            'resource_person' => [new PeopleServiceProvider(), 'resource_person'],
            'leader' => [new LeaderServiceProvider(), 'leader'],
            'dictionary_entry' => [new LanguageServiceProvider(), 'dictionary_entry'],
        ];
    }

    #[Test]
    #[DataProvider('contentProviderDataProvider')]
    public function all_content_providers_define_consent_public(ServiceProvider $provider, string $entityTypeId): void
    {
        $provider->register();

        $fields = $this->getFieldDefinitions($provider, $entityTypeId);

        self::assertArrayHasKey('consent_public', $fields, sprintf(
            'Entity type "%s" is missing the consent_public field definition.',
            $entityTypeId,
        ));
        self::assertSame('boolean', $fields['consent_public']['type']);
    }

    #[Test]
    #[DataProvider('contentProviderDataProvider')]
    public function all_content_providers_define_consent_ai_training(ServiceProvider $provider, string $entityTypeId): void
    {
        $provider->register();

        $fields = $this->getFieldDefinitions($provider, $entityTypeId);

        self::assertArrayHasKey('consent_ai_training', $fields, sprintf(
            'Entity type "%s" is missing the consent_ai_training field definition.',
            $entityTypeId,
        ));
        self::assertSame('boolean', $fields['consent_ai_training']['type']);
    }

    #[Test]
    #[DataProvider('contentProviderDataProvider')]
    public function consent_public_defaults_to_true(ServiceProvider $provider, string $entityTypeId): void
    {
        $provider->register();

        $fields = $this->getFieldDefinitions($provider, $entityTypeId);

        self::assertSame(1, $fields['consent_public']['default'], sprintf(
            'Entity type "%s" consent_public should default to 1 (public).',
            $entityTypeId,
        ));
    }

    #[Test]
    #[DataProvider('contentProviderDataProvider')]
    public function consent_ai_training_defaults_to_false(ServiceProvider $provider, string $entityTypeId): void
    {
        $provider->register();

        $fields = $this->getFieldDefinitions($provider, $entityTypeId);

        self::assertSame(0, $fields['consent_ai_training']['default'], sprintf(
            'Entity type "%s" consent_ai_training should default to 0 (opt-in only).',
            $entityTypeId,
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFieldDefinitions(ServiceProvider $provider, string $entityTypeId): array
    {
        $types = $provider->getEntityTypes();

        foreach ($types as $type) {
            if ($type->id() === $entityTypeId) {
                return $type->getFieldDefinitions();
            }
        }

        self::fail(sprintf('Entity type "%s" not found in provider.', $entityTypeId));
    }
}
