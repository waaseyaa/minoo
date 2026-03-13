<?php

declare(strict_types=1);

namespace Minoo\Domain\Geo\Service;

use GeoIp2\Database\Reader;
use Minoo\Domain\Geo\ValueObject\LocationContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;

final class LocationService
{
    private CommunityFinder $communityFinder;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly array $config,
    ) {
        $this->communityFinder = new CommunityFinder();
    }

    public function fromRequest(HttpRequest $request): LocationContext
    {
        // 1. Check session.
        $session = $request->attributes->get('_session') ?? ($_SESSION ?? []);
        if (isset($session['minoo_location']) && is_array($session['minoo_location'])) {
            $ctx = LocationContext::fromArray($session['minoo_location']);
            if ($ctx->hasLocation()) {
                return $ctx;
            }
            // Invalid session data — clear it.
            unset($_SESSION['minoo_location']);
        }

        // 2. Check cookie.
        $cookieName = $this->config['cookie_name'] ?? 'minoo_location';
        $cookieValue = $request->cookies->get($cookieName);
        if ($cookieValue !== null) {
            try {
                $data = json_decode($cookieValue, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data) && isset($data['communityId'])) {
                    $ctx = LocationContext::fromArray($data);
                    if ($ctx->hasLocation()) {
                        return $ctx;
                    }
                }
            } catch (\Throwable) {
                // Corrupted cookie — fall through to IP resolution.
            }
            // Invalid or corrupted cookie — clear it so the user isn't stuck.
            $this->clearCookie($cookieName);
        }

        // 3. Resolve from IP.
        return $this->resolveFromIp($request);
    }

    public function resolveFromCoordinates(float $lat, float $lon, string $source = 'browser'): LocationContext
    {
        $communities = $this->loadAllCommunities();
        $result = $this->communityFinder->findNearest($lat, $lon, $communities);

        if ($result === null) {
            return LocationContext::none();
        }

        /** @var ContentEntityBase $community */
        $community = $result['community'];

        return new LocationContext(
            communityId: $community->id(),
            communityName: $community->get('name'),
            latitude: $lat,
            longitude: $lon,
            source: $source,
        );
    }

    public function resolveFromCommunityId(int|string $communityId): LocationContext
    {
        $storage = $this->entityTypeManager->getStorage('community');

        if (is_int($communityId)) {
            $community = $storage->load($communityId);
        } else {
            $ids = $storage->getQuery()->condition('uuid', $communityId)->execute();
            $community = $ids !== [] ? $storage->load(reset($ids)) : null;
        }

        if ($community === null) {
            return LocationContext::none();
        }

        return new LocationContext(
            communityId: $community->id(),
            communityName: $community->get('name'),
            latitude: $community->get('latitude') !== null ? (float) $community->get('latitude') : null,
            longitude: $community->get('longitude') !== null ? (float) $community->get('longitude') : null,
            source: 'manual',
        );
    }

    public function storeInSession(LocationContext $ctx): void
    {
        $_SESSION['minoo_location'] = $ctx->toArray();
    }

    public function setCookie(LocationContext $ctx): void
    {
        $cookieName = $this->config['cookie_name'] ?? 'minoo_location';
        $cookieTtl = $this->config['cookie_ttl'] ?? 86400 * 30;

        setcookie($cookieName, json_encode($ctx->toArray(), JSON_THROW_ON_ERROR), [
            'expires' => time() + $cookieTtl,
            'path' => '/',
            'httponly' => false, // JS reads it
            'samesite' => 'Lax',
        ]);
    }

    private function resolveFromIp(HttpRequest $request): LocationContext
    {
        $ip = $request->getClientIp() ?? '127.0.0.1';

        if ($this->isPrivateIp($ip)) {
            $defaults = $this->config['default_coordinates'] ?? null;
            if ($defaults === null) {
                return LocationContext::none();
            }

            return $this->resolveFromCoordinates($defaults[0], $defaults[1], 'ip');
        }

        // Try GeoIP2 lookup.
        $dbPath = $this->config['geoip_db'] ?? '';
        if (!file_exists($dbPath)) {
            return LocationContext::none();
        }

        try {
            $reader = new Reader($dbPath);
            $record = $reader->city($ip);

            $lat = $record->location->latitude;
            $lon = $record->location->longitude;

            if ($lat === null || $lon === null) {
                return LocationContext::none();
            }

            return $this->resolveFromCoordinates($lat, $lon, 'ip');
        } catch (\Exception) {
            return LocationContext::none();
        }
    }

    private function clearCookie(string $cookieName): void
    {
        setcookie($cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * @return array<ContentEntityBase>
     */
    private function loadAllCommunities(): array
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $query = $storage->getQuery();
        $query->condition('status', 1);
        $ids = $query->execute();

        if (empty($ids)) {
            return [];
        }

        return $storage->loadMultiple($ids);
    }
}
