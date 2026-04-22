<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\AppBootServiceProvider;
use App\Provider\MinooEntityStackProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;
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
            'teaching' => [$provider, 'teaching'],
            'event' => [$provider, 'event'],
            'cultural_group' => [$provider, 'cultural_group'],
            'cultural_collection' => [$provider, 'cultural_collection'],
            'resource_person' => [$provider, 'resource_person'],
            'leader' => [$provider, 'leader'],
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

    #[Test]
    public function group_business_bundle_defines_consent_public(): void
    {
        self::assertSame('boolean', $this->bundleField('consent_public')->getType());
    }

    #[Test]
    public function group_business_bundle_defines_consent_ai_training(): void
    {
        self::assertSame('boolean', $this->bundleField('consent_ai_training')->getType());
    }

    #[Test]
    public function group_business_consent_public_defaults_to_true(): void
    {
        self::assertSame(1, $this->bundleField('consent_public')->getDefaultValue());
    }

    #[Test]
    public function group_business_consent_ai_training_defaults_to_false(): void
    {
        self::assertSame(0, $this->bundleField('consent_ai_training')->getDefaultValue());
    }

    private function bundleField(string $name): FieldDefinition
    {
        $registry = new FieldDefinitionRegistry();
        $registry->registerBundleFields('group', 'business', AppBootServiceProvider::groupBusinessBundleFields());

        $fields = $registry->bundleFieldsFor('group', 'business');

        self::assertArrayHasKey($name, $fields, sprintf(
            'Bundle field "%s" not registered for group:business.',
            $name,
        ));

        $field = $fields[$name];
        self::assertInstanceOf(FieldDefinition::class, $field);

        return $field;
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
