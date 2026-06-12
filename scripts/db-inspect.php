<?php

declare(strict_types=1);

// One-off: inspect the restored database (Phase 3 verification helper).
$db = new PDO('sqlite:' . dirname(__DIR__) . '/storage/waaseyaa.sqlite');
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

echo "TABLES (" . count($tables) . "):\n" . implode(', ', $tables) . "\n\n";

if (in_array('waaseyaa_migrations', $tables, true)) {
    echo "waaseyaa_migrations rows:\n";
    foreach ($db->query('SELECT * FROM waaseyaa_migrations ORDER BY 1') as $row) {
        echo '  ' . implode(' | ', array_map(strval(...), array_filter($row, is_scalar(...)))) . "\n";
    }
    echo "\n";
}
if (in_array('schema_migrations', $tables, true)) {
    echo "schema_migrations rows:\n";
    foreach ($db->query('SELECT * FROM schema_migrations ORDER BY 1') as $row) {
        echo '  ' . implode(' | ', array_map(strval(...), array_filter($row, is_scalar(...)))) . "\n";
    }
    echo "\n";
}

foreach (['dictionary_entry', 'user', 'game_session', 'daily_challenge', 'crossword_puzzle', 'example_sentence', 'word_part', 'contributor', 'featured_item', 'speaker', 'dialect_region', 'migrations'] as $t) {
    if (in_array($t, $tables, true)) {
        $c = $db->query('SELECT COUNT(*) FROM "' . $t . '"')->fetchColumn();
        echo sprintf("%-20s %s\n", $t, $c);
    } else {
        echo sprintf("%-20s MISSING\n", $t);
    }
}
