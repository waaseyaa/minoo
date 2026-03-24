<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add crossword fields to game_session.
 *
 * No-op: game_type, puzzle_id, grid_state, hints_used live in the _data JSON blob.
 * This migration exists for version tracking only.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        // Fields stored in _data JSON blob — no schema change needed.
    }

    public function down(SchemaBuilder $schema): void
    {
        // No-op — nothing to reverse.
    }
};
