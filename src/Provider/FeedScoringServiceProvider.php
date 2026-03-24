<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Feed\EngagementCounter;
use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use Minoo\Feed\Scoring\DecayCalculator;
use Minoo\Feed\Scoring\DiversityReranker;
use Minoo\Feed\Scoring\EngagementCalculator;
use Minoo\Feed\Scoring\FeedScorer;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class FeedScoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->loadConfig();

        $this->singleton(AffinityCache::class, fn(): AffinityCache => new AffinityCache());

        $this->singleton(DecayCalculator::class, fn(): DecayCalculator => new DecayCalculator(
            halfLifeHours: (float) $config['decay_half_life_hours'],
        ));

        $this->singleton(EngagementCalculator::class, fn(): EngagementCalculator => new EngagementCalculator(
            reactionWeight: (float) $config['reaction_weight'],
            commentWeight: (float) $config['comment_weight'],
        ));

        $this->singleton(AffinityCalculator::class, fn(): AffinityCalculator => new AffinityCalculator(
            entityTypeManager: $this->resolve(EntityTypeManager::class),
            cache: $this->resolve(AffinityCache::class),
            reactionAffinity: (float) $config['affinity_reaction_weight'],
            commentAffinity: (float) $config['affinity_comment_weight'],
            followAffinity: (float) $config['affinity_follow_weight'],
        ));

        $this->singleton(DiversityReranker::class, fn(): DiversityReranker => new DiversityReranker(
            windowSize: (int) $config['diversity_window_size'],
        ));

        $this->singleton(FeedScorer::class, fn(): FeedScorer => new FeedScorer(
            affinity: $this->resolve(AffinityCalculator::class),
            engagement: $this->resolve(EngagementCalculator::class),
            decay: $this->resolve(DecayCalculator::class),
            reranker: $this->resolve(DiversityReranker::class),
            engagementCounter: $this->resolve(EngagementCounter::class),
            featuredBoost: (float) $config['featured_boost'],
        ));
    }

    public function boot(): void
    {
        $dispatcher = $this->resolveEventDispatcher();
        if ($dispatcher === null) {
            return;
        }

        $cache = $this->resolve(AffinityCache::class);
        $invalidateTypes = ['reaction', 'comment', 'follow'];

        // Shared handler: invalidate affinity cache when engagement entities change
        $invalidator = static function (object $event) use ($cache, $invalidateTypes): void {
            $entity = $event->entity ?? null;
            if ($entity === null) {
                return;
            }

            $type = method_exists($entity, 'getEntityTypeId') ? $entity->getEntityTypeId() : '';
            if (!in_array($type, $invalidateTypes, true)) {
                return;
            }

            $userId = $entity->get('user_id');
            if ($userId !== null) {
                $cache->invalidate((int) $userId);
            }
        };

        $dispatcher->addListener('entity.post_save', $invalidator);
        $dispatcher->addListener('entity.post_delete', $invalidator);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/feed_scoring.php';
        if (is_file($configPath)) {
            return require $configPath;
        }

        return [
            'decay_half_life_hours' => 24.0,
            'reaction_weight' => 1.0,
            'comment_weight' => 2.0,
            'affinity_reaction_weight' => 1.0,
            'affinity_comment_weight' => 2.0,
            'affinity_follow_weight' => 5.0,
            'featured_boost' => 100.0,
            'diversity_window_size' => 3,
        ];
    }

    private function resolveEventDispatcher(): ?object
    {
        try {
            return $this->resolve(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
