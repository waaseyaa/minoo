<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\MinooEntityStackProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

#[CoversNothing]
final class ConsentFieldsTest extends TestCase
{
    /**
     * Provider-registered entity types whose consent fields are core (not bundle-scoped).
     *
     * `group` is excluded: it was extracted to `waaseyaa/groups`, which registers
     * only universal core fields. Group's consent fields are bundle-scoped on
     * `group:business` and tested separately below via `bundleFieldsFor()`.
     *
     * @return array<string, array{ServiceProvider, string}>
     */
    public static function contentProviderDataProvider(): array
    {
        $provider = new MinooEntityStackProvider();

        return [
            'dictionary_entry' => [$provider, 'dictionary_entry'],
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
