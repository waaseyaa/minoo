<?php
declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement("
            CREATE TABLE newsletter_edition (
                neid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                headline CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('newsletter_edition')) {
            $schema->getConnection()->executeStatement('DROP TABLE newsletter_edition');
        }
    }
};
