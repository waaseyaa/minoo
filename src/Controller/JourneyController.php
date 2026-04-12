<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\JourneyEngine;
use App\Support\LayoutTwigContext;
use App\Support\GameStatsCalculator;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Api\JsonResponseTrait;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * JourneyController — Routes for Minoo's Journey hidden-object game.
 *
 * Page:   GET  /games/journey
 * API:    GET  /api/games/journey/scenes
 *         GET  /api/games/journey/scene/{slug}
 *         POST /api/games/journey/tap
 *         POST /api/games/journey/hint
 *         POST /api/games/journey/complete
 *         GET  /api/games/journey/stats
 */
class JourneyController
{
    use GameControllerTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly GateInterface $gate,
    ) {}

    private function getEntityTypeManager(): EntityTypeManager
    {
        return $this->entityTypeManager;
    }

    // ── Page ──────────────────────────────────────────────────────────────

    /** GET /games/journey */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('journey.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/games/journey',
        ]));
        return new Response($html);
    }

    // ── Scene listing and loading ─────────────────────────────────────────

    /** GET /api/games/journey/scenes — list all scenes (no hotspot coords). */
    public function scenes(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->json(['scenes' => JourneyEngine::listScenes()]);
    }

    /**
     * GET /api/games/journey/scene/{slug} — load a scene and open a session.
     *
     * Returns scene data safe for the client (no hotspot coordinates) plus
     * a session token used for all subsequent tap/hint/complete calls.
     */
    public function scene(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        if ($slug === '') {
            return $this->json(['error' => 'Missing scene slug'], 422);
        }

        $sceneData = JourneyEngine::getClientScene($slug);
        if ($sceneData === null) {
            return $this->json(['error' => 'Scene not found'], 404);
        }

        $session = $this->createSession($slug, $account);

        return $this->json([
            'session_token'  => $session->get('uuid'),
            'scene'          => $sceneData,
            'hints_remaining' => 3,
        ]);
    }

    // ── Tap validation ────────────────────────────────────────────────────

    /**
     * POST /api/games/journey/tap
     *
     * Body: { session_token, x, y }
     * x and y are 0.0–1.0 fractions of the rendered scene image dimensions.
     *
     * The client never receives hotspot coordinates. It sends where the
     * player tapped; the server decides if it hit anything.
     */
    public function tap(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data  = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $x     = isset($data['x']) ? (float) $data['x'] : null;
        $y     = isset($data['y']) ? (float) $data['y'] : null;

        if ($token === '' || $x === null || $y === null) {
            return $this->json(['error' => 'Missing session_token, x, or y'], 422);
        }

        if ($x < 0.0 || $x > 1.0 || $y < 0.0 || $y > 1.0) {
            return $this->json(['error' => 'Coordinates out of range'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        if ($session->get('status') !== 'in_progress') {
            return $this->json(['error' => 'Scene already finished'], 400);
        }

        $slug    = (string) $session->get('puzzle_id');
        $objects = JourneyEngine::getSceneObjects($slug);
        if ($objects === null) {
            return $this->json(['error' => 'Scene data missing'], 500);
        }

        $foundIds = json_decode((string) $session->get('found_objects'), true) ?: [];
        $hit      = JourneyEngine::checkTap($objects, $foundIds, $x, $y);

        if ($hit === null) {
            return $this->json(['found' => false]);
        }

        $foundIds[] = $hit['id'];
        $session->set('found_objects', json_encode($foundIds));
        $this->entityTypeManager->getStorage('game_session')->save($session);

        $sceneTotal = count($objects);
        $foundCount = count($foundIds);

        return $this->json([
            'found'          => true,
            'object_id'      => $hit['id'],
            'object_key'     => $hit['key'],
            'label_en'       => $hit['label_en'],
            'label_oj'       => $hit['label_oj'],
            'found_count'    => $foundCount,
            'total_objects'  => $sceneTotal,
            'scene_complete' => $foundCount >= $sceneTotal,
        ]);
    }

    // ── Hint ──────────────────────────────────────────────────────────────

    /**
     * POST /api/games/journey/hint
     *
     * Body: { session_token }
     *
     * Returns the Ojibwe label of the next unfound object plus a quadrant
     * hint ("top-left", "bottom-right", etc.) — enough for the player to
     * narrow the search without revealing the exact location.
     */
    public function hint(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data  = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';

        if ($token === '') {
            return $this->json(['error' => 'Missing session_token'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        if ($session->get('status') !== 'in_progress') {
            return $this->json(['error' => 'Scene already finished'], 400);
        }

        $maxHints  = 3;
        $hintsUsed = (int) $session->get('hints_used');
        if ($hintsUsed >= $maxHints) {
            return $this->json(['error' => 'No hints remaining'], 400);
        }

        $slug    = (string) $session->get('puzzle_id');
        $objects = JourneyEngine::getSceneObjects($slug);
        if ($objects === null) {
            return $this->json(['error' => 'Scene data missing'], 500);
        }

        $foundIds = json_decode((string) $session->get('found_objects'), true) ?: [];
        $hintObj  = JourneyEngine::nextHint($objects, $foundIds);

        if ($hintObj === null) {
            return $this->json(['error' => 'All objects already found'], 400);
        }

        $session->set('hints_used', $hintsUsed + 1);
        $this->entityTypeManager->getStorage('game_session')->save($session);

        return $this->json([
            'label_en'        => $hintObj['label_en'],
            'label_oj'        => $hintObj['label_oj'],
            'quadrant'        => JourneyEngine::hotspotQuadrant($hintObj['hotspot']['x'], $hintObj['hotspot']['y']),
            'hints_remaining' => $maxHints - ($hintsUsed + 1),
        ]);
    }

    // ── Scene completion ──────────────────────────────────────────────────

    /**
     * POST /api/games/journey/complete
     *
     * Body: { session_token }
     *
     * Called when the client detects scene_complete in a tap response.
     * Returns star rating, narrative card, and homestead unlock (if any).
     */
    public function complete(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data  = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';

        if ($token === '') {
            return $this->json(['error' => 'Missing session_token'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        if ($session->get('status') === 'completed') {
            return $this->json(['error' => 'Already completed'], 400);
        }

        $session->set('status', 'completed');
        $this->entityTypeManager->getStorage('game_session')->save($session);

        $slug         = (string) $session->get('puzzle_id');
        $timeSeconds  = time() - (int) $session->get('created_at');
        $hintsUsed    = (int) $session->get('hints_used');
        $stars        = JourneyEngine::calculateStars($timeSeconds, $hintsUsed);
        $narrative    = JourneyEngine::getNarrativeCard($slug);
        $homestead    = JourneyEngine::getHomesteadItem($slug);
        $stats        = GameStatsCalculator::build($this->entityTypeManager, $account, 'journey', ['abandoned'], ['completed']);

        return $this->json([
            'completed'      => true,
            'stars'          => $stars,
            'time_seconds'   => $timeSeconds,
            'hints_used'     => $hintsUsed,
            'narrative_card' => $narrative,
            'homestead_item' => $homestead,
            'stats'          => $stats,
        ]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    /** GET /api/games/journey/stats */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->json(GameStatsCalculator::build($this->entityTypeManager, $account, 'journey', ['abandoned'], ['completed']));
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function createSession(string $slug, AccountInterface $account): object
    {
        $storage = $this->entityTypeManager->getStorage('game_session');
        $session = $storage->create([
            'game_type'    => 'journey',
            'mode'         => 'chapter',
            'puzzle_id'    => $slug,
            'user_id'      => $account->isAuthenticated() ? $account->id() : null,
            'found_objects' => '[]',
            'hints_used'   => 0,
        ]);
        $storage->save($session);
        return $session;
    }

    private function jsonBody(HttpRequest $request): array
    {
        $body = json_decode($request->getContent(), true);
        return is_array($body) ? $body : [];
    }
}
