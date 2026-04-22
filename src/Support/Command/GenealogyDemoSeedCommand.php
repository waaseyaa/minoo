<?php

declare(strict_types=1);

namespace App\Support\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Genealogy\GenealogyLocalDemoMarkers;
use Waaseyaa\Genealogy\GenealogyRelationshipType;
use Waaseyaa\User\User;

#[AsCommand(name: 'genealogy:demo-seed', description: 'Create demo genealogy tree, persons, family, and edges for local SSR testing')]
final class GenealogyDemoSeedCommand extends Command
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $personStorage = $this->entityTypeManager->getStorage('genealogy_person');
        $existing = $personStorage->getQuery()
            ->condition('display_name', GenealogyLocalDemoMarkers::CHILD_PERSON_DISPLAY)
            ->accessCheck(false)
            ->range(0, 1)
            ->execute();

        $familyStorage = $this->entityTypeManager->getStorage('genealogy_family');

        if ($existing !== []) {
            $childId = (string) $existing[0];
            $output->writeln('<info>Demo data already present.</info>');
            $this->ensureOwnerGenealogyOptIn();
            $this->ensureDemoTreePublishedForAnonymousSsr();
            $familyIds = $familyStorage->getQuery()
                ->condition('display_name', GenealogyLocalDemoMarkers::FAMILY_DISPLAY)
                ->accessCheck(false)
                ->range(0, 1)
                ->execute();
            $familyId = isset($familyIds[0]) ? (string) $familyIds[0] : null;
            $this->printUrls($output, $childId, $familyId);

            return self::SUCCESS;
        }

        $relStorage = $this->entityTypeManager->getStorage('relationship');
        $treeStorage = $this->entityTypeManager->getStorage('genealogy_tree');

        $tree = $treeStorage->create([
            'display_name' => GenealogyLocalDemoMarkers::TREE_DISPLAY,
            'owner_uid' => 1,
            // Published so anonymous SSR (and CDN-style caches) can exercise the demo URLs.
            'status' => 1,
        ]);
        $treeStorage->save($tree);
        $treeId = (int) $tree->id();

        $family = $familyStorage->create([
            'display_name' => GenealogyLocalDemoMarkers::FAMILY_DISPLAY,
            'tree_id' => $treeId,
            'status' => 1,
        ]);
        $familyStorage->save($family);

        $parent = $personStorage->create([
            'display_name' => GenealogyLocalDemoMarkers::PARENT_PERSON_DISPLAY,
            'tree_id' => $treeId,
            'status' => 1,
            'is_living' => true,
        ]);
        $personStorage->save($parent);
        $child = $personStorage->create([
            'display_name' => GenealogyLocalDemoMarkers::CHILD_PERSON_DISPLAY,
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
        $output->writeln('<info>Genealogy demo data created.</info>');
        $this->printUrls($output, $childId, (string) $family->id());

        return self::SUCCESS;
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

    /**
     * Older seeds created the demo tree as draft; anonymous SSR requires a published tree.
     */
    private function ensureDemoTreePublishedForAnonymousSsr(): void
    {
        $treeStorage = $this->entityTypeManager->getStorage('genealogy_tree');
        $ids = $treeStorage->getQuery()
            ->condition('display_name', GenealogyLocalDemoMarkers::TREE_DISPLAY)
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

    private function printUrls(OutputInterface $output, string $childPersonId, ?string $familyId = null): void
    {
        $output->writeln(sprintf('Person: /genealogy/person/%s', $childPersonId));
        $output->writeln(sprintf('Ancestors: /genealogy/person/%s/ancestors', $childPersonId));
        if ($familyId !== null) {
            $output->writeln(sprintf('Family: /genealogy/family/%s', $familyId));
        }
    }
}
