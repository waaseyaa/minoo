<?php

declare(strict_types=1);

return [
    'decay_half_life_hours' => 96,
    'featured_boost' => 15.0,
    'affinity_cache_ttl' => 900,
    'interaction_weights' => [
        'reaction' => 1.0,
        'comment' => 3.0,
        'follow' => 2.0,
    ],
    'affinity_signals' => [
        'same_community' => 3.0,
        'follows_source' => 4.0,
        'reaction_points' => 1.0,
        'reaction_max' => 5.0,
        'comment_points' => 2.0,
        'comment_max' => 6.0,
        'geo_close_km' => 50,
        'geo_close_points' => 2.0,
        'geo_mid_km' => 150,
        'geo_mid_points' => 1.0,
    ],
    'base_affinity' => 1.0,
    'diversity' => [
        'max_consecutive_type' => 2,
        'max_consecutive_community' => 2,
        'post_guarantee_slot' => 3,
    ],
    'lookback_days' => 30,
];
