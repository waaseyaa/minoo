<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use Minoo\Feed\Scoring\DecayCalculator;
use Minoo\Feed\Scoring\DiversityReranker;
use Minoo\Feed\Scoring\EngagementCalculator;
use Minoo\Feed\Scoring\FeedScorer;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class FeedScoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = is_file(dirname(__DIR__, 2) . '/config/feed_scoring.php')
            ? require dirname(__DIR__, 2) . '/config/feed_scoring.php'
            : [];

        $this->singleton(DecayCalculator::class, fn(): DecayCalculator => new DecayCalculator(
            halfLifeHours: (float) ($config['decay_half_life_hours'] ?? 96),
        ));

        $this->singleton(AffinityCache::class, fn(): AffinityCache => new AffinityCache(
            new MemoryBackend(),
        ));

        $interactionWeights = $config['interaction_weights'] ?? [];
        $this->singleton(EngagementCalculator::class, fn(): EngagementCalculator => new EngagementCalculator(
            entityTypeManager: $this->resolve(EntityTypeManager::class),
            reactionWeight: (float) ($interactionWeights['reaction'] ?? 1.0),
            commentWeight: (float) ($interactionWeights['comment'] ?? 3.0),
        ));

        $affinityConfig = $config['affinity_signals'] ?? [];
        $this->singleton(AffinityCalculator::class, fn(): AffinityCalculator => new AffinityCalculator(
            entityTypeManager: $this->resolve(EntityTypeManager::class),
            cache: $this->resolve(AffinityCache::class),
            baseAffinity: (float) ($config['base_affinity'] ?? 1.0),
            followPoints: (float) ($affinityConfig['follows_source'] ?? 4.0),
            sameCommunityPoints: (float) ($affinityConfig['same_community'] ?? 3.0),
            reactionPoints: (float) ($affinityConfig['reaction_points'] ?? 1.0),
            reactionMax: (float) ($affinityConfig['reaction_max'] ?? 5.0),
            commentPoints: (float) ($affinityConfig['comment_points'] ?? 2.0),
            commentMax: (float) ($affinityConfig['comment_max'] ?? 6.0),
            geoCloseKm: (float) ($affinityConfig['geo_close_km'] ?? 50),
            geoClosePoints: (float) ($affinityConfig['geo_close_points'] ?? 2.0),
            geoMidKm: (float) ($affinityConfig['geo_mid_km'] ?? 150),
            geoMidPoints: (float) ($affinityConfig['geo_mid_points'] ?? 1.0),
            lookbackDays: (int) ($config['lookback_days'] ?? 30),
        ));

        $diversity = $config['diversity'] ?? [];
        $this->singleton(DiversityReranker::class, fn(): DiversityReranker => new DiversityReranker(
            maxConsecutiveType: (int) ($diversity['max_consecutive_type'] ?? 3),
            maxConsecutiveCommunity: (int) ($diversity['max_consecutive_community'] ?? 5),
        ));

        $this->singleton(FeedScorer::class, fn(): FeedScorer => new FeedScorer(
            affinity: $this->resolve(AffinityCalculator::class),
            engagement: $this->resolve(EngagementCalculator::class),
            decay: $this->resolve(DecayCalculator::class),
            reranker: $this->resolve(DiversityReranker::class),
            featuredBoost: (float) ($config['featured_boost'] ?? 100.0),
        ));
    }

    public function boot(): void
    {
        $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $affinityCache = $this->resolve(AffinityCache::class);

        $invalidate = static function (EntityEvent $event) use ($affinityCache): void {
            $entity = $event->entity;
            if (in_array($entity->getEntityTypeId(), ['reaction', 'comment', 'follow'], true)) {
                $userId = $entity->get('user_id');
                if ($userId !== null) {
                    $affinityCache->invalidate((int) $userId);
                }
            }
        };

        $dispatcher->addListener(EntityEvents::POST_SAVE->value, $invalidate);
        $dispatcher->addListener(EntityEvents::POST_DELETE->value, $invalidate);
    }
}
