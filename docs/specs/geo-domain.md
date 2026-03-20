# Geo Domain Specification

## File Map

| File | Purpose |
|------|---------|
| `src/Domain/Geo/ValueObject/LocationContext.php` | Readonly VO encapsulating user's resolved geographic location |
| `src/Domain/Geo/ValueObject/RankedVolunteer.php` | Readonly VO pairing a volunteer entity with distance data |
| `src/Domain/Geo/Service/CommunityFinder.php` | Stateless service finding nearest/nearby communities by haversine |
| `src/Domain/Geo/Service/LocationService.php` | Multi-source location resolver (session → cookie → IP → coordinates) |
| `src/Domain/Geo/Service/VolunteerRanker.php` | Ranks Elder Support volunteers by proximity in 3 tiers |
| `src/Support/GeoDistance.php` | Haversine distance calculation (static utility) |
| `src/Support/CommunityLookup.php` | Builds community ID → name/slug lookup maps |
| `src/Support/NorthCloudClient.php` | HTTP client for North Cloud APIs (dictionary, people, band office) |
| `src/Support/NorthCloudCache.php` | SQLite-backed response cache for NC API calls (default 1h TTL) |

## Interface Signatures

### LocationContext (Value Object)

```php
final readonly class LocationContext
{
    public function __construct(
        public ?int $communityId,
        public ?string $communityName,
        public ?float $latitude,
        public ?float $longitude,
        public string $source,
    );

    public static function none(): self;          // Empty context (source='none')
    public function hasLocation(): bool;           // True if communityId > 0
    public function toArray(): array;              // Serialize to associative array
    public static function fromArray(array $data): self;  // Deserialize (validates communityId)
}
```

### RankedVolunteer (Value Object)

```php
final readonly class RankedVolunteer
{
    public function __construct(
        public ContentEntityBase $volunteer,
        public ?float $distanceKm,
        public bool $exceedsMaxTravel = false,
    );

    public function hasDistance(): bool;            // True if distanceKm is not null
    public function formattedDistance(): string;    // "< 1 km" or rounded km string
}
```

### CommunityFinder (Service — stateless)

```php
final class CommunityFinder
{
    // Returns nearest community with distance, or null
    public function findNearest(float $lat, float $lon, array $communities): ?array;
    // Returns up to $limit communities sorted by distance ASC
    public function findNearby(float $lat, float $lon, array $communities, int $limit = 5): array;
}
```

### LocationService (Service — requires EntityTypeManager + config)

```php
final class LocationService
{
    public function __construct(EntityTypeManager $entityTypeManager, array $config);

    // Multi-step fallback: session → cookie → IP geolocation
    public function fromRequest(HttpRequest $request): LocationContext;
    // Resolves nearest community from coordinates
    public function resolveFromCoordinates(float $lat, float $lon, string $source = 'browser'): LocationContext;
    // Loads community by ID from storage
    public function resolveFromCommunityId(int|string $communityId): LocationContext;
    // Persists location to $_SESSION['minoo_location']
    public function storeInSession(LocationContext $ctx): void;
    // Sets HTTP cookie (TTL from config, default 30 days)
    public function setCookie(LocationContext $ctx): void;
}
```

### VolunteerRanker (Service — requires EntityTypeManager)

```php
final class VolunteerRanker
{
    public function __construct(EntityTypeManager $entityTypeManager);

    // Ranks volunteers in 3 tiers: same community → distance ASC → name ASC
    public function rank(array $volunteers, ContentEntityBase $request): RankedVolunteer[];
}
```

### NorthCloudClient (Support)

```php
final class NorthCloudClient
{
    public function __construct(
        string $baseUrl,
        string $apiToken = '',
        ?NorthCloudCache $cache = null,
        ?\Closure $httpClient = null,
        int $timeout = 10,
    );

    public function searchDictionary(string $query, int $limit = 25, int $offset = 0): ?array;
    public function getPeople(string $ncId): ?array;       // Community leadership
    public function getBandOffice(string $ncId): ?array;   // Band office contact info
    public function linkSources(bool $dryRun = true): ?array;
    public function createLeadershipScrapeJob(string $ncId): ?array;
}
```

### NorthCloudCache (Support)

```php
final class NorthCloudCache
{
    public function __construct(\PDO $pdo, int $ttl = 3600);

    public function get(string $key): ?string;    // Null if expired or missing
    public function set(string $key, string $value): void;
    public function clear(): void;                // Clears nc_api_cache table
}
```

## Data Flow

### Location Resolution (fromRequest)

```
HttpRequest
  → check $_SESSION['minoo_location'] → if valid, return LocationContext
  → check $_COOKIE['minoo_location'] → if valid, decode + return
  → resolveFromIp() → geolocate IP → findNearest() → return
  → LocationContext::none() (fallback)
```

### Volunteer Ranking (Elder Support)

```
ElderSupportController receives request entity
  → VolunteerRanker::rank($volunteers, $request)
    → resolve request community coordinates
    → for each volunteer:
      → resolve volunteer community coordinates (cached per community)
      → compute haversine distance
    → sort: same_community first, then distance ASC, then name ASC
    → wrap each in RankedVolunteer VO
  → template renders ranked list with distance badges
```

### North Cloud API Calls

```
Controller/Service needs NC data
  → NorthCloudClient::getPeople($ncId) (or searchDictionary, getBandOffice)
    → NorthCloudCache::get(cacheKey) → if hit, return cached
    → doAuthenticatedRequest(url, apiToken)
    → on success: cache response, return parsed JSON
    → on failure: return null (caller handles gracefully)
```

## Configuration

| Key | Source | Default | Purpose |
|-----|--------|---------|---------|
| `northcloud.base_url` | config/app.php | — | NC API base URL |
| `northcloud.api_token` | config/app.php | `''` | NC API auth token |
| `northcloud.cache_ttl` | config/app.php | `3600` | Cache TTL in seconds |
| `location.cookie_ttl` | config/app.php | `2592000` | Location cookie TTL (30 days) |
| `WAASEYAA_DB` | env var | `storage/waaseyaa.sqlite` | Database path (affects cache table) |

## Edge Cases

- **Private IPs**: `LocationService::isPrivateIp()` skips geolocation for RFC 1918 addresses — returns `LocationContext::none()`
- **No communities loaded**: If `loadAllCommunities()` returns empty, `findNearest()` returns null and location falls back to `none()`
- **NULL community status**: Most imported communities have `status=NULL` (not `1`). The homepage `buildNearbyMixed()` requires `status=1`, so nearby results are often empty — `buildRecentMixed()` provides the fallback
- **Volunteer without community**: If a volunteer entity has no `community_id`, `resolveCoords()` returns null and the volunteer sorts to the end (name-only tier)
- **NC API timeout**: `NorthCloudClient` uses a configurable timeout (default 10s). On timeout, returns null — callers must handle gracefully
- **NC cache table creation**: `NorthCloudCache::ensureTable()` auto-creates `nc_api_cache` on first use — no migration needed
