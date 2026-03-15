<?php

declare(strict_types=1);

namespace Minoo\Support;

use Waaseyaa\Entity\EntityTypeManagerInterface;

final class FixtureResolver
{
    /** @var array<string, int|null> */
    private array $communityCache = [];

    /** @var array<string, int|null> */
    private array $groupSlugCache = [];

    public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

    public function resolveCommunity(string $name): ?int
    {
        if (array_key_exists($name, $this->communityCache)) {
            return $this->communityCache[$name];
        }

        $storage = $this->entityTypeManager->getStorage('community');

        // Exact match first
        $ids = $storage->getQuery()->condition('name', $name)->execute();

        if ($ids === []) {
            // Case-insensitive fallback — query all and match
            $allIds = $storage->getQuery()->execute();
            foreach ($allIds as $id) {
                $entity = $storage->load($id);
                if ($entity !== null && strcasecmp($entity->get('name'), $name) === 0) {
                    $this->communityCache[$name] = $id;
                    return $id;
                }
            }
            $this->communityCache[$name] = null;
            return null;
        }

        $id = reset($ids);
        $this->communityCache[$name] = $id;
        return $id;
    }

    public function resolveGroupSlug(string $slug): ?int
    {
        if (array_key_exists($slug, $this->groupSlugCache)) {
            return $this->groupSlugCache[$slug];
        }

        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()->condition('slug', $slug)->execute();

        $id = $ids !== [] ? reset($ids) : null;
        $this->groupSlugCache[$slug] = $id;
        return $id;
    }

    /**
     * @param list<string> $names
     * @param list<string> $warnings
     * @return list<int> Resolved term IDs (unresolved names are skipped)
     */
    public function resolveTaxonomyTerms(array $names, string $vocabulary, array &$warnings = []): array
    {
        $storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $ids = [];

        foreach ($names as $name) {
            $termIds = $storage->getQuery()
                ->condition('vid', $vocabulary)
                ->condition('name', $name)
                ->execute();

            if ($termIds !== []) {
                $ids[] = reset($termIds);
            } else {
                $warnings[] = "Taxonomy term '{$name}' not found in vocabulary '{$vocabulary}'";
            }
        }

        return $ids;
    }
}
