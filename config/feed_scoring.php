<?php

declare(strict_types=1);

return [
    // Time-decay half-life in hours (content loses half its score after this many hours)
    'decay_half_life_hours' => 24.0,

    // Engagement weight multipliers
    'reaction_weight' => 1.0,
    'comment_weight' => 2.0,

    // Affinity signal weights
    'affinity_reaction_weight' => 1.0,
    'affinity_comment_weight' => 2.0,
    'affinity_follow_weight' => 5.0,

    // Featured item score boost
    'featured_boost' => 100.0,

    // Diversity reranker window size
    'diversity_window_size' => 3,
];
