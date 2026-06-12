<?php

declare(strict_types=1);

// Phase 3 one-off: remove game_session rows created by local smoke testing
// after the 2026-05-20 production backup was restored. Production data ends
// 2026-05-20; anything stamped June 2026 or later is a local test artifact.
$db = new PDO('sqlite:' . dirname(__DIR__) . '/storage/waaseyaa.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cutoff = strtotime('2026-06-01T00:00:00Z');
$n = $db->exec("DELETE FROM game_session WHERE CAST(json_extract(_data, '$.created_at') AS INTEGER) >= $cutoff");
echo "deleted: $n\n";
echo 'remaining: ' . $db->query('SELECT COUNT(*) FROM game_session')->fetchColumn() . "\n";
