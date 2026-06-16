<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Member home community (Phase 5). A logged-in member may self-select ONE home
 * community; the feed uses it as a community-level vantage (same-community
 * affinity boost). Consent-first: NULL by default, set only when the member
 * chooses. No coordinates are involved; proximity stays dormant.
 */
return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $columns = $schema->getConnection()->fetchAllAssociative('PRAGMA table_info(user)');
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'home_community_id') {
                return;
            }
        }

        $schema->getConnection()->executeStatement(
            'ALTER TABLE user ADD COLUMN home_community_id INTEGER DEFAULT NULL',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite cannot drop a column portably; leave it. NULL is inert.
    }
};
