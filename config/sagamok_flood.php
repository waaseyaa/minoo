<?php

declare(strict_types=1);

$rawEmergency = getenv('MINOO_SAGAMOK_FLOOD_EMERGENCY_OG');
if ($rawEmergency === false || trim((string) $rawEmergency) === '') {
    $emergencyOpenGraph = true;
} else {
    $filtered = filter_var($rawEmergency, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $emergencyOpenGraph = $filtered ?? false;
}

return [
    'emergency_open_graph' => $emergencyOpenGraph,
    'og_image_revision' => (int) (getenv('MINOO_SAGAMOK_FLOOD_OG_REVISION') ?: '1'),
    'last_verified_date' => getenv('MINOO_SAGAMOK_FLOOD_VERIFIED') ?: '2026-04-21',
];
