<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Entity;

use App\Entity\Account\SavedWord;
use App\Entity\Games\GameSession;
use App\Infrastructure\Entity\TimestampPreSaveListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Event\EntityEvent;

#[CoversClass(TimestampPreSaveListener::class)]
final class TimestampPreSaveListenerTest extends TestCase
{
    /** @var array<int, array{string, mixed}> */
    private array $setCalls = [];

    #[Test]
    public function new_entity_gets_created_at_and_updated_at(): void
    {
        $entity = $this->entityMock('game_session', ['mode' => 'daily']);
        $before = time();

        $this->listenerFor($this->gameSessionType())(new EntityEvent($entity));

        $values = $this->setValuesByField();
        self::assertArrayHasKey('created_at', $values);
        self::assertArrayHasKey('updated_at', $values);
        self::assertIsInt($values['created_at']);
        self::assertIsInt($values['updated_at']);
        self::assertGreaterThanOrEqual($before, $values['created_at']);
        self::assertLessThanOrEqual(time(), $values['created_at']);
    }

    #[Test]
    public function existing_created_at_is_never_overwritten(): void
    {
        $entity = $this->entityMock('game_session', ['created_at' => 1_600_000_000]);

        $this->listenerFor($this->gameSessionType())(new EntityEvent($entity));

        $values = $this->setValuesByField();
        self::assertArrayNotHasKey('created_at', $values);
        self::assertArrayHasKey('updated_at', $values);
    }

    #[Test]
    public function zero_created_at_counts_as_unset_and_is_populated(): void
    {
        $entity = $this->entityMock('game_session', ['created_at' => 0]);

        $this->listenerFor($this->gameSessionType())(new EntityEvent($entity));

        $values = $this->setValuesByField();
        self::assertArrayHasKey('created_at', $values);
        self::assertIsInt($values['created_at']);
        self::assertNotSame(0, $values['created_at']);
    }

    #[Test]
    public function updated_at_is_refreshed_on_every_save(): void
    {
        $entity = $this->entityMock('game_session', [
            'created_at' => 1_600_000_000,
            'updated_at' => 1_600_000_000,
        ]);
        $before = time();

        $this->listenerFor($this->gameSessionType())(new EntityEvent($entity));

        $values = $this->setValuesByField();
        self::assertArrayHasKey('updated_at', $values);
        self::assertIsInt($values['updated_at']);
        self::assertGreaterThanOrEqual($before, $values['updated_at']);
    }

    #[Test]
    public function types_without_timestamp_fields_are_untouched(): void
    {
        $type = new EntityType(
            id: 'daily_challenge',
            label: 'Daily Challenge',
            class: GameSession::class,
            keys: ['id' => 'date', 'label' => 'date'],
            _fieldDefinitions: [
                'date' => ['type' => 'string', 'label' => 'Date', 'weight' => 0],
            ],
        );
        $entity = $this->entityMock('daily_challenge', ['date' => '2026-07-18']);
        $entity->expects(self::never())->method('set');

        $this->listenerFor($type)(new EntityEvent($entity));
    }

    #[Test]
    public function created_at_only_type_never_gets_updated_at(): void
    {
        $type = new EntityType(
            id: 'saved_word',
            label: 'Saved Word',
            class: SavedWord::class,
            keys: ['id' => 'swid', 'uuid' => 'uuid', 'label' => 'word'],
            _fieldDefinitions: [
                'word' => ['type' => 'string', 'label' => 'Word', 'weight' => 0],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
            ],
        );
        $entity = $this->entityMock('saved_word', ['word' => 'makwa']);

        $this->listenerFor($type)(new EntityEvent($entity));

        $values = $this->setValuesByField();
        self::assertArrayHasKey('created_at', $values);
        self::assertArrayNotHasKey('updated_at', $values);
    }

    #[Test]
    public function non_timestamp_created_at_field_is_ignored(): void
    {
        $type = new EntityType(
            id: 'odd_type',
            label: 'Odd Type',
            class: GameSession::class,
            keys: ['id' => 'oid', 'label' => 'name'],
            _fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'created_at' => ['type' => 'integer', 'label' => 'Created', 'weight' => 40],
            ],
        );
        $entity = $this->entityMock('odd_type', ['name' => 'x']);
        $entity->expects(self::never())->method('set');

        $this->listenerFor($type)(new EntityEvent($entity));
    }

    #[Test]
    public function unregistered_entity_type_is_ignored(): void
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('getDefinition')
            ->willThrowException(new \InvalidArgumentException('Entity type "mystery" is not registered.'));

        $entity = $this->entityMock('mystery', []);
        $entity->expects(self::never())->method('set');

        (new TimestampPreSaveListener($manager))(new EntityEvent($entity));
    }

    private function gameSessionType(): EntityType
    {
        return new EntityType(
            id: 'game_session',
            label: 'Game Session',
            class: GameSession::class,
            keys: ['id' => 'gsid', 'uuid' => 'uuid', 'label' => 'mode'],
            _fieldDefinitions: [
                'mode' => ['type' => 'string', 'label' => 'Mode', 'weight' => 0],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        );
    }

    private function listenerFor(EntityType $type): TimestampPreSaveListener
    {
        $manager = $this->createMock(EntityTypeManagerInterface::class);
        $manager->method('getDefinition')->with($type->id())->willReturn($type);

        return new TimestampPreSaveListener($manager);
    }

    /**
     * @param array<string, mixed> $rawValues
     */
    private function entityMock(string $entityTypeId, array $rawValues): ContentEntityBase&MockObject
    {
        $this->setCalls = [];
        $entity = $this->createMock(ContentEntityBase::class);
        $entity->method('getEntityTypeId')->willReturn($entityTypeId);
        $entity->method('toArray')->willReturn($rawValues);
        $entity->method('set')->willReturnCallback(function (string $name, mixed $value) use ($entity) {
            $this->setCalls[] = [$name, $value];

            return $entity;
        });

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    private function setValuesByField(): array
    {
        $values = [];
        foreach ($this->setCalls as [$name, $value]) {
            $values[$name] = $value;
        }

        return $values;
    }
}
