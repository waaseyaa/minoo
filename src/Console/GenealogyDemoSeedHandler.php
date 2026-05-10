<?php

declare(strict_types=1);

namespace App\Console;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Genealogy\GenealogyRelationshipType;
use Waaseyaa\User\User;

final class GenealogyDemoSeedHandler
{
    private const string DEMO_CHILD_DISPLAY = '[Genealogy demo] Child';
    private const string DEMO_PARENT_DISPLAY = '[Genealogy demo] Parent';
    private const string DEMO_FAMILY_DISPLAY = '[Genealogy demo] Family';
    private const string DEMO_TREE_DISPLAY = '[Genealogy demo] Tree';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    public function execute(CliIO $io): int
    {
        $personStorage = $this->entityTypeManager->getStorage('genealogy_person');
        $existing = $personStorage->getQuery()
            ->condition('display_name', self::DEMO_CHILD_DISPLAY)
            ->accessCheck(false)
            ->range(0, 1)
            ->execute();

        $familyStorage = $this->entityTypeManager->getStorage('genealogy_family');

        if ($existing !== []) {
            $childId = (string) $existing[0];
            $io->writeln('Demo data already present.');
            $this->ensureOwnerGenealogyOptIn();
            $this->ensureDemoTreePublishedForAnonymousSsr();
            $familyIds = $familyStorage->getQuery()
                ->condition('display_name', self::DEMO_FAMILY_DISPLAY)
                ->accessCheck(false)
                ->range(0, 1)
                ->execute();
            $familyId = isset($familyIds[0]) ? (string) $familyIds[0] : null;
            $this->printUrls($io, $childId, $familyId);

            return 0;
        }

        $relStorage = $this->entityTypeManager->getStorage('relationship');
        $treeStorage = $this->entityTypeManager->getStorage('genealogy_tree');

        $tree = $treeStorage->create([
            'display_name' => self::DEMO_TREE_DISPLAY,
            'owner_uid' => 1,
            'status' => 1,
        ]);
        $treeStorage->save($tree);
        $treeId = (int) $tree->id();

        $family = $familyStorage->create([
            'display_name' => self::DEMO_FAMILY_DISPLAY,
            'tree_id' => $treeId,
            'status' => 1,
        ]);
        $familyStorage->save($family);

        $parent = $personStorage->create([
            'display_name' => self::DEMO_PARENT_DISPLAY,
            'tree_id' => $treeId,
            'status' => 1,
            'is_living' => true,
        ]);
        $personStorage->save($parent);
        $child = $personStorage->create([
            'display_name' => self::DEMO_CHILD_DISPLAY,
            'tree_id' => $treeId,
            'status' => 1,
            'is_living' => true,
        ]);
        $personStorage->save($child);

        $parentEdge = $relStorage->create([
            'relationship_type' => GenealogyRelationshipType::PARENT_OF,
            'from_entity_type' => 'genealogy_person',
            'from_entity_id' => (string) $parent->id(),
            'to_entity_type' => 'genealogy_person',
            'to_entity_id' => (string) $child->id(),
            'directionality' => 'directed',
            'status' => 1,
        ]);
        $relStorage->save($parentEdge);

        foreach ([$parent, $child] as $member) {
            $m = $relStorage->create([
                'relationship_type' => GenealogyRelationshipType::MEMBER_OF_FAMILY,
                'from_entity_type' => 'genealogy_person',
                'from_entity_id' => (string) $member->id(),
                'to_entity_type' => 'genealogy_family',
                'to_entity_id' => (string) $family->id(),
                'directionality' => 'directed',
                'status' => 1,
            ]);
            $relStorage->save($m);
        }

        $this->ensureOwnerGenealogyOptIn();

        $childId = (string) $child->id();
        $io->writeln('Genealogy demo data created.');
        $this->printUrls($io, $childId, (string) $family->id());

        return 0;
    }

    private function ensureOwnerGenealogyOptIn(): void
    {
        $userStorage = $this->entityTypeManager->getStorage('user');
        $owner = $userStorage->load('1');
        if ($owner instanceof User) {
            $owner->set('genealogy_product_enabled', true);
            $userStorage->save($owner);
        }
    }

    private function ensureDemoTreePublishedForAnonymousSsr(): void
    {
        $treeStorage = $this->entityTypeManager->getStorage('genealogy_tree');
        $ids = $treeStorage->getQuery()
            ->condition('display_name', self::DEMO_TREE_DISPLAY)
            ->accessCheck(false)
            ->range(0, 1)
            ->execute();
        if ($ids === []) {
            return;
        }
        $tree = $treeStorage->load((string) $ids[0]);
        if ($tree === null) {
            return;
        }
        $status = $tree->get('status');
        if ((int) $status === 1) {
            return;
        }
        $tree->set('status', 1);
        $treeStorage->save($tree);
    }

    private function printUrls(CliIO $io, string $childPersonId, ?string $familyId = null): void
    {
        $io->writeln(sprintf('Person: /genealogy/person/%s', $childPersonId));
        $io->writeln(sprintf('Ancestors: /genealogy/person/%s/ancestors', $childPersonId));
        if ($familyId !== null) {
            $io->writeln(sprintf('Family: /genealogy/family/%s', $familyId));
        }
    }
}
