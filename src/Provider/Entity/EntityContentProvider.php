<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Entity\Account\SavedWord;
use App\Entity\Community\Contributor;
use App\Entity\Events\Event;
use App\Entity\Feed\Post;
use App\Entity\Games\CrosswordPuzzle;
use App\Entity\Games\DailyChallenge;
use App\Entity\Games\GameSession;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityType;

/**
 * Games + contributor attribution.
 *
 * Language-platform slimming (2026-06): oral history and leader entity
 * types de-registered; tables stay dormant. Post (feed) is registered
 * below (social spine, #811). Contributor is kept because
 * example_sentence rows reference it for attribution.
 */
final class EntityContentProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
        // =====================================================================
        // --- Contributors (attribution for example sentences / recordings) ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'contributor',
            label: 'Contributor',
            class: Contributor::class,
            keys: ['id' => 'coid', 'uuid' => 'uuid', 'label' => 'name'],
            tenancy: ['scope' => 'community'],
            group: 'contributor',
            _fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'code' => ['type' => 'string', 'label' => 'Speaker Code', 'description' => 'Abbreviation (e.g., es, nj, gh).', 'weight' => 5],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 10],
                'role' => ['type' => 'string', 'label' => 'Role', 'description' => 'Contributor role: speaker, storyteller, elder, translator.', 'weight' => 12],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 20],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 25,
                ],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this contributor may be shown on public pages.', 'weight' => 28, 'default' => 0],
                'consent_record' => ['type' => 'boolean', 'label' => 'Recording Consent', 'description' => 'Whether this contributor consents to being recorded.', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        // =====================================================================
        // --- Events (#819) ---
        // Community-scoped gatherings. Re-registered for the relaunch; the feed
        // auto-joins events once this type exists (FeedAssembler guards on
        // hasDefinition). Geo ranking stays dormant (Phase 5) — the /events
        // surface lists published events chronologically, no proximity wiring.
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'event',
            label: 'Event',
            class: Event::class,
            keys: ['id' => 'eid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'],
            tenancy: ['scope' => 'community'],
            group: 'events',
            _fieldDefinitions: [
                'title' => ['type' => 'string', 'label' => 'Title', 'weight' => 0],
                'type' => ['type' => 'string', 'label' => 'Type', 'weight' => -1],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'description' => ['type' => 'text_long', 'label' => 'Description', 'description' => 'Rich text event description.', 'weight' => 5],
                'location' => ['type' => 'string', 'label' => 'Location', 'description' => 'Physical location or "online".', 'weight' => 10],
                'community_id' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 12],
                'starts_at' => ['type' => 'datetime', 'label' => 'Starts At', 'weight' => 15],
                'ends_at' => ['type' => 'datetime', 'label' => 'Ends At', 'description' => 'Leave empty for open-ended events.', 'weight' => 16],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Featured Image', 'settings' => ['target_type' => 'media'], 'weight' => 20],
                'copyright_status' => ['type' => 'string', 'label' => 'Copyright Status', 'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.', 'default_value' => 'unknown', 'weight' => 99],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this content may be shown on public pages.', 'weight' => 28, 'default' => 1],
                'consent_ai_training' => ['type' => 'boolean', 'label' => 'AI Training Consent', 'description' => 'Whether this content may be used for AI training. Default: no.', 'weight' => 29, 'default' => 0],
                'source_url' => ['type' => 'string', 'label' => 'Source URL', 'description' => 'Canonical URL of the original content (for NC deduplication).', 'weight' => 50],
                'source' => ['type' => 'string', 'label' => 'Source', 'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).', 'weight' => 95],
                'verified_at' => ['type' => 'datetime', 'label' => 'Verified At', 'description' => 'When this record was last verified.', 'weight' => 96],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        // =====================================================================
        // --- Games ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'game_session',
            label: 'Game Session',
            class: GameSession::class,
            keys: ['id' => 'gsid', 'uuid' => 'uuid', 'label' => 'mode'],
            group: 'games',
            _fieldDefinitions: [
                'mode' => ['type' => 'string', 'label' => 'Mode', 'weight' => 0],
                'direction' => ['type' => 'string', 'label' => 'Direction', 'weight' => 1],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 5],
                'user_id' => ['type' => 'integer', 'label' => 'User', 'weight' => 6],
                'guesses' => ['type' => 'text_long', 'label' => 'Guesses', 'description' => 'JSON array of letters guessed.', 'weight' => 10],
                'wrong_count' => ['type' => 'integer', 'label' => 'Wrong Count', 'weight' => 11, 'default' => 0],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 15, 'default' => 'in_progress'],
                'daily_date' => ['type' => 'string', 'label' => 'Daily Date', 'weight' => 16],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 17, 'default' => 'easy'],
                'game_type' => ['type' => 'string', 'label' => 'Game Type', 'weight' => 18, 'default' => 'shkoda'],
                'puzzle_id' => ['type' => 'string', 'label' => 'Puzzle ID', 'weight' => 19],
                'grid_state' => ['type' => 'text_long', 'label' => 'Grid State', 'description' => 'JSON crossword grid fill state.', 'weight' => 20],
                'hints_used' => ['type' => 'integer', 'label' => 'Hints Used', 'weight' => 21, 'default' => 0],
                'found_objects' => ['type' => 'text_long', 'label' => 'Found Objects', 'description' => 'JSON array of found object IDs (Journey game).', 'weight' => 22, 'default' => '[]'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'daily_challenge',
            label: 'Daily Challenge',
            class: DailyChallenge::class,
            keys: ['id' => 'date', 'label' => 'date'],
            group: 'games',
            _fieldDefinitions: [
                'date' => ['type' => 'string', 'label' => 'Date', 'weight' => 0],
                'dictionary_entry_id' => ['type' => 'entity_reference', 'label' => 'Dictionary Entry', 'settings' => ['target_type' => 'dictionary_entry'], 'weight' => 5],
                'direction' => ['type' => 'string', 'label' => 'Direction', 'weight' => 10, 'default' => 'english_to_ojibwe'],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 15, 'default' => 'easy'],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'crossword_puzzle',
            label: 'Crossword Puzzle',
            class: CrosswordPuzzle::class,
            keys: ['id' => 'id', 'label' => 'id'],
            group: 'games',
            _fieldDefinitions: [
                'grid_size' => ['type' => 'integer', 'label' => 'Grid Size', 'weight' => 0],
                'words' => ['type' => 'text_long', 'label' => 'Words', 'description' => 'JSON array of word placements.', 'weight' => 5],
                'clues' => ['type' => 'text_long', 'label' => 'Clues', 'description' => 'JSON map of word index to clue data.', 'weight' => 10],
                'theme' => ['type' => 'string', 'label' => 'Theme', 'weight' => 15],
                'difficulty_tier' => ['type' => 'string', 'label' => 'Difficulty', 'weight' => 20, 'default' => 'easy'],
            ],
        ));

        // =====================================================================
        // --- Social spine: posts (#811) ---
        // Consent by participation: a member posts for themselves. Belongs to a
        // community (HasCommunityTrait) so it places on the graph by vantage.
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'post',
            label: 'Post',
            class: Post::class,
            keys: ['id' => 'pid', 'uuid' => 'uuid', 'label' => 'body'],
            tenancy: ['scope' => 'community'],
            group: 'engagement',
            _fieldDefinitions: [
                'body' => ['type' => 'text_long', 'label' => 'Body', 'weight' => 0],
                'user_id' => ['type' => 'integer', 'label' => 'User ID', 'weight' => 1],
                'community_id' => ['type' => 'integer', 'label' => 'Community ID', 'weight' => 2],
                'images' => ['type' => 'text_long', 'label' => 'Images', 'weight' => 3],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 5, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 11],
            ],
        ));

        // =====================================================================
        // --- Personal word lists (#806) ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'saved_word',
            label: 'Saved Word',
            class: SavedWord::class,
            keys: ['id' => 'swid', 'uuid' => 'uuid', 'label' => 'word'],
            group: 'account',
            _fieldDefinitions: [
                'word' => ['type' => 'string', 'label' => 'Word', 'weight' => 0],
                'user_id' => ['type' => 'integer', 'label' => 'User', 'weight' => 1],
                'dictionary_entry_id' => ['type' => 'integer', 'label' => 'Dictionary Entry', 'weight' => 2],
                'slug' => ['type' => 'string', 'label' => 'Slug', 'weight' => 3],
                'definition' => ['type' => 'text_long', 'label' => 'Definition', 'weight' => 4],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
            ],
        ));
    }
}
