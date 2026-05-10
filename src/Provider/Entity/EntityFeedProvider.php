<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Domain\Feed\EntityLoaderService;
use App\Domain\Feed\FeedAssembler;
use App\Domain\Feed\FeedAssemblerInterface;
use App\Domain\Feed\FeedItemFactory;
use App\Domain\Feed\Scoring\AffinityCache;
use App\Domain\Feed\Scoring\AffinityCalculator;
use App\Domain\Feed\Scoring\DecayCalculator;
use App\Domain\Feed\Scoring\DiversityReranker;
use App\Domain\Feed\Scoring\EngagementCalculator;
use App\Domain\Feed\Scoring\FeedScorer;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Entity\EntityTypeManager;

final class EntityFeedProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
        // =====================================================================
        // --- Feed ---
        // =====================================================================

        $this->singleton(EntityLoaderService::class, fn (): EntityLoaderService => new EntityLoaderService(
            $this->resolve(EntityTypeManager::class),
        ));

        $this->singleton(FeedItemFactory::class, fn (): FeedItemFactory => new FeedItemFactory());

        $this->singleton(FeedAssemblerInterface::class, fn (): FeedAssemblerInterface => new FeedAssembler(
            $this->resolve(EntityLoaderService::class),
            $this->resolve(FeedItemFactory::class),
            null,
            $this->resolve(FeedScorer::class),
        ));

        // =====================================================================
        // --- Feed Scoring ---
        // =====================================================================

        $feedScoringConfig = is_file(dirname(__DIR__, 3) . '/config/feed_scoring.php')
            ? require dirname(__DIR__, 3) . '/config/feed_scoring.php'
            : [];

        $this->singleton(DecayCalculator::class, fn (): DecayCalculator => new DecayCalculator(
            halfLifeHours: (float) ($feedScoringConfig['decay_half_life_hours'] ?? 96),
        ));

        $this->singleton(AffinityCache::class, fn (): AffinityCache => new AffinityCache(
            new MemoryBackend(),
        ));

        $interactionWeights = $feedScoringConfig['interaction_weights'] ?? [];
        $this->singleton(EngagementCalculator::class, fn (): EngagementCalculator => new EngagementCalculator(
            entityTypeManager: $this->resolve(EntityTypeManager::class),
            reactionWeight: (float) ($interactionWeights['reaction'] ?? 1.0),
            commentWeight: (float) ($interactionWeights['comment'] ?? 3.0),
        ));

        $affinityConfig = $feedScoringConfig['affinity_signals'] ?? [];
        $this->singleton(AffinityCalculator::class, fn (): AffinityCalculator => new AffinityCalculator(
            entityTypeManager: $this->resolve(EntityTypeManager::class),
            cache: $this->resolve(AffinityCache::class),
            baseAffinity: (float) ($feedScoringConfig['base_affinity'] ?? 1.0),
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
            lookbackDays: (int) ($feedScoringConfig['lookback_days'] ?? 30),
        ));

        $diversity = $feedScoringConfig['diversity'] ?? [];
        $this->singleton(DiversityReranker::class, fn (): DiversityReranker => new DiversityReranker(
            maxConsecutiveType: (int) ($diversity['max_consecutive_type'] ?? 2),
            maxConsecutiveCommunity: (int) ($diversity['max_consecutive_community'] ?? 2),
            postGuaranteeSlot: (int) ($diversity['post_guarantee_slot'] ?? 3),
        ));

        $this->singleton(FeedScorer::class, fn (): FeedScorer => new FeedScorer(
            affinity: $this->resolve(AffinityCalculator::class),
            engagement: $this->resolve(EngagementCalculator::class),
            decay: $this->resolve(DecayCalculator::class),
            reranker: $this->resolve(DiversityReranker::class),
            featuredBoost: (float) ($feedScoringConfig['featured_boost'] ?? 100.0),
        ));
    }
}
