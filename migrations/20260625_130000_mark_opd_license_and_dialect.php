<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Mark the Ojibwe People's Dictionary rows with their licence and dialect (#914).
 *
 * The 21,721 dictionary_entry rows imported from the Ojibwe People's Dictionary
 * (University of Minnesota) are licensed CC BY-NC-SA 3.0 and are Southwestern
 * (Minnesota) Ojibwe. They were imported tagged plain 'oj' and with no licence
 * field. This stamps:
 *   - license       = "CC BY-NC-SA 3.0"  (so the ShareAlike obligation is
 *                       recorded in the data and attaches only to OPD rows)
 *   - language_code = "oj-sw"            (Southwestern Ojibwe; the UI then
 *                       distinguishes them from Sagamok Nishnaabemwin)
 *
 * The match is the OPD fingerprint established in the audit: attribution_source
 * begins "Ojibwe People" OR the attribution_url points at ojibwe.lib.umn.edu.
 * Steven Bennett's Sagamok community corpus (attribution_source = 'corpus') is
 * never touched: it is not OPD content, so it must NOT carry the BY-NC-SA
 * licence or the Southwestern label. The corpus exclusion is stated explicitly
 * as a belt-and-suspenders guard even though the corpus has no umn.edu URL.
 *
 * Values live in the _data JSON blob, so json_set is used. Idempotent: re-running
 * sets the same values. No row is created or deleted.
 */
return new class () extends Migration {
    private const OPD_MATCH =
        "(json_extract(_data, '\$.attribution_source') LIKE 'Ojibwe People%'
          OR json_extract(_data, '\$.attribution_url') LIKE '%ojibwe.lib.umn.edu%')
         AND json_extract(_data, '\$.attribution_source') != 'corpus'";

    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement(
            "UPDATE dictionary_entry
             SET _data = json_set(_data, '\$.license', 'CC BY-NC-SA 3.0', '\$.language_code', 'oj-sw')
             WHERE " . self::OPD_MATCH,
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        // Drop the licence marker and restore the original plain 'oj' tag.
        $schema->getConnection()->executeStatement(
            "UPDATE dictionary_entry
             SET _data = json_set(json_remove(_data, '\$.license'), '\$.language_code', 'oj')
             WHERE " . self::OPD_MATCH,
        );
    }
};
