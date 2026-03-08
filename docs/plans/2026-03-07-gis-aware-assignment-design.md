# GIS-Aware Volunteer Assignment — Design

**Date:** 2026-03-07
**Scope:** Minoo v0.7
**Depends on:** v0.5 (People Directory), v0.6 (Auth, Assignment Workflow, Communities)

## Problem

When a coordinator assigns a volunteer to an elder support request, they see a flat dropdown of all active volunteers with no indication of proximity. In a region spanning hundreds of kilometres — from Sudbury to Sault Ste. Marie, with First Nations communities scattered throughout — a ride request from Sagamok Anishnawbek should surface volunteers from Sagamok, Atikameksheng, and Sudbury before those from Elliot Lake or Thessalon.

Today the coordinator must:
1. Look at the request's community.
2. Mentally recall which volunteers are near that community.
3. Scroll through an alphabetical list to find them.

This doesn't scale. It also introduces assignment errors — a volunteer in Sault Ste. Marie assigned to a chore in Sagamok (3 hours away) is unlikely to follow through.

## Solution

Add proximity-aware volunteer ranking to the coordinator dashboard. When a coordinator views an open request, volunteers are sorted by distance from the request's community, with distance displayed beside each name. The scoring model runs server-side using the `latitude`/`longitude` fields already present on the `community` entity.

### What this is NOT

- Not a map UI (that's v0.8).
- Not real-time routing or travel-time estimation.
- Not automatic assignment — the coordinator still chooses.
- Not a new API surface — this is a controller + template change.

## Data Sources

### Community coordinates

The `community` entity already has `latitude` (float, weight 15) and `longitude` (float, weight 16) fields, defined in `CommunityServiceProvider`. These are populated via the `bin/sync-communities` import script, which sources coordinates from the community dataset.

**Current coverage:** All communities imported via sync-communities have coordinates. Manual entries may not. The scoring model must handle missing coordinates gracefully.

### Request → Community link

`elder_support_request.community` is an `entity_reference` to `community` (weight 5). This gives us the requester's location.

### Volunteer → Community link

`volunteer.community` is an `entity_reference` to `community` (weight 3). This gives us each volunteer's location.

### Missing data

If either the request or a volunteer has no community, or the community has no coordinates, that volunteer gets a "distance unknown" marker and sorts to the bottom of the list.

## Entity Changes

### No new entity types

The existing entities have all the fields we need. The `community` entity already stores coordinates, and both `elder_support_request` and `volunteer` reference communities.

### New field: `volunteer.max_travel_km` (optional)

| Field | Type | Default | Purpose |
|-------|------|---------|---------|
| `max_travel_km` | integer | null | Maximum distance (km) the volunteer is willing to travel |

This is optional. When set, the scoring model uses it to flag volunteers who are beyond their stated range. The coordinator sees a visual indicator but is not blocked from assigning.

**Provider change:** Add to `ElderSupportServiceProvider` field definitions for `volunteer`:

```php
'max_travel_km' => ['type' => 'integer', 'label' => 'Max Travel Distance (km)', 'weight' => 6],
```

**Form change:** Add optional field to `elders/volunteer.html.twig`:

```html
<label>Maximum travel distance (km) <span class="form__label-optional">(optional)</span></label>
<input type="number" name="max_travel_km" min="1" max="500" placeholder="e.g. 50">
```

## Scoring Algorithm

### Haversine distance

Pure PHP implementation. No PostGIS, no external services. The dataset is small (dozens of volunteers, not thousands) so O(n) distance calculation per request is fine.

```php
final class GeoDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
```

**Location:** `src/Geo/GeoDistance.php`

### Scoring model

For each volunteer, given a request's community coordinates:

```
score(volunteer) = {
    distance_km:    haversine(request.community, volunteer.community)
    same_community: request.community.id == volunteer.community.id
    within_range:   volunteer.max_travel_km == null || distance_km <= volunteer.max_travel_km
    has_coords:     both communities have lat/lon
}
```

**Sort order:**
1. Same community first (distance = 0)
2. Has coordinates, within range — sorted by distance ASC
3. Has coordinates, beyond range — sorted by distance ASC (flagged)
4. Missing coordinates — sorted by name ASC (no distance shown)

### VolunteerRanker

```php
final class VolunteerRanker
{
    /**
     * @param EntityInterface[] $volunteers Active volunteers
     * @param EntityInterface|null $requestCommunity The request's community entity (with lat/lon)
     * @return RankedVolunteer[] Sorted by proximity
     */
    public function rank(array $volunteers, ?EntityInterface $requestCommunity): array;
}
```

**Value object:**

```php
final readonly class RankedVolunteer
{
    public function __construct(
        public EntityInterface $volunteer,
        public ?float $distanceKm,
        public bool $sameCommunity,
        public bool $withinRange,
    ) {}
}
```

**Location:** `src/Geo/VolunteerRanker.php`, `src/Geo/RankedVolunteer.php`

## Controller Changes

### CoordinatorDashboardController

**Current:** Loads all active volunteers as a flat array, passes to template.

**Change:** For each open request, resolve the request's community entity, then rank volunteers using `VolunteerRanker`.

```php
// Current
'volunteers' => $volunteers,

// New
'ranked_volunteers' => $this->volunteerRanker->rank($volunteers, $requestCommunity),
```

The controller needs `VolunteerRanker` injected. Since Waaseyaa controllers receive dependencies via constructor, add it alongside `EntityTypeManager` and `Twig\Environment`.

**Constructor change:**

```php
public function __construct(
    private readonly EntityTypeManager $entityTypeManager,
    private readonly Environment $twig,
    private readonly VolunteerRanker $volunteerRanker,
) {}
```

**Registration:** The `DashboardServiceProvider` will need to construct `VolunteerRanker` and pass it to the controller. Since Waaseyaa uses explicit wiring (not a DI container), this is a straightforward change in the provider.

### Resolving community coordinates

The coordinator dashboard already loads all requests. For each open request:

1. Read `$request->get('community')` — this is a community entity ID.
2. If set, load the community entity via `$entityTypeManager->getStorage('community')->load($id)`.
3. Read `latitude` and `longitude` from the community.
4. Pass the community entity to `VolunteerRanker::rank()`.

For the volunteer pool, each volunteer also references a community. The ranker resolves these internally.

## UI/UX Changes

### Coordinator dashboard — volunteer assignment dropdown

**Current markup:**

```html
<select name="volunteer_id">
  {% for v in volunteers %}
    <option value="{{ v.id() }}">{{ v.get('name') }}</option>
  {% endfor %}
</select>
```

**New markup:**

```html
<select name="volunteer_id" class="form__select">
  <option value="">Select volunteer…</option>
  {% for rv in ranked_volunteers %}
    <option value="{{ rv.volunteer.id() }}"
      {% if not rv.withinRange %} class="volunteer-option--beyond-range"{% endif %}>
      {{ rv.volunteer.get('name') }}
      {% if rv.sameCommunity %}
        (same community)
      {% elseif rv.distanceKm is not null %}
        ({{ rv.distanceKm|number_format(0) }} km)
      {% else %}
        (distance unknown)
      {% endif %}
      {% if not rv.withinRange and rv.distanceKm is not null %}
        — beyond stated range
      {% endif %}
    </option>
  {% endfor %}
</select>
```

**Visual treatment:**
- Same community: no extra styling (natural first position communicates priority)
- Within range: distance in grey text
- Beyond range: distance in amber/warning text
- Unknown: italic grey text

### Volunteer signup form

Add an optional "Maximum travel distance" number input below the availability field. The label should explain: "How far are you willing to travel to help an elder? Leave blank if no limit."

### No map in v0.7

Map visualization is explicitly deferred to v0.8. v0.7 is text-based proximity ranking only. This keeps scope tight and avoids a Leaflet/MapLibre dependency.

## File Map

| Action | File | Purpose |
|--------|------|---------|
| Create | `src/Geo/GeoDistance.php` | Haversine distance calculator |
| Create | `src/Geo/RankedVolunteer.php` | Value object for ranked volunteer |
| Create | `src/Geo/VolunteerRanker.php` | Ranks volunteers by proximity |
| Create | `tests/Minoo/Unit/Geo/GeoDistanceTest.php` | Haversine edge cases |
| Create | `tests/Minoo/Unit/Geo/VolunteerRankerTest.php` | Ranking logic |
| Modify | `src/Provider/ElderSupportServiceProvider.php` | Add `max_travel_km` field to volunteer |
| Modify | `src/Provider/DashboardServiceProvider.php` | Wire `VolunteerRanker` into controller |
| Modify | `src/Controller/CoordinatorDashboardController.php` | Resolve communities, rank volunteers |
| Modify | `src/Controller/VolunteerController.php` | Accept `max_travel_km` in form submission |
| Modify | `templates/dashboard/coordinator.html.twig` | Show distance in volunteer dropdown |
| Modify | `templates/elders/volunteer.html.twig` | Add max travel distance field |
| Modify | `public/css/minoo.css` | Styling for distance indicators |

## Test Plan

### Unit tests

**GeoDistanceTest** (`tests/Minoo/Unit/Geo/GeoDistanceTest.php`):
- Same point returns 0.0
- Known city pair: Sudbury (46.49, -80.99) to Sault Ste. Marie (46.52, -84.35) ≈ 262 km
- Known city pair: Sagamok (46.15, -81.72) to Sudbury (46.49, -80.99) ≈ 68 km
- Antipodal points ≈ 20,015 km
- Equator crossing

**VolunteerRankerTest** (`tests/Minoo/Unit/Geo/VolunteerRankerTest.php`):
- Volunteers sorted by distance ascending
- Same-community volunteer sorts first
- Volunteer with no community sorts last
- Request with no community: all volunteers get null distance, sorted by name
- Volunteer beyond `max_travel_km` flagged but not excluded
- Volunteer with null `max_travel_km` always within range
- Empty volunteer list returns empty array
- Multiple volunteers in same community all get distance 0

### Integration tests

**CoordinatorDashboardControllerTest** (extend existing or create):
- Open request with community: ranked_volunteers in template context sorted by distance
- Open request without community: all volunteers returned, no distance

### Manual verification

- [ ] Create communities with known coordinates (Sagamok, Sudbury, Sault Ste. Marie)
- [ ] Create volunteers in each community
- [ ] Submit elder request from Sagamok
- [ ] Verify coordinator dashboard shows Sagamok volunteer first, then Sudbury, then Sault Ste. Marie
- [ ] Verify distance numbers are reasonable (68 km, 262 km)
- [ ] Verify volunteer with max_travel_km=50 is flagged for Sudbury request
- [ ] Verify volunteer with no community shows "distance unknown"

## Rollout Plan

### Phase 1: Core geo logic (no UI changes)

1. Create `GeoDistance` with haversine implementation
2. Create `RankedVolunteer` value object
3. Create `VolunteerRanker` with ranking logic
4. Write all unit tests
5. Verify: all existing + new tests green

### Phase 2: Provider + form changes

1. Add `max_travel_km` to volunteer entity in `ElderSupportServiceProvider`
2. Update volunteer signup form (`volunteer.html.twig`)
3. Update `VolunteerController::submitSignup()` to accept `max_travel_km`
4. Verify: existing tests still green, forms work

### Phase 3: Dashboard integration

1. Wire `VolunteerRanker` into `DashboardServiceProvider`
2. Update `CoordinatorDashboardController` to resolve communities and rank volunteers
3. Update `coordinator.html.twig` to show distance in dropdown
4. Add CSS for distance indicators
5. Verify: coordinator dashboard shows ranked volunteers

### Phase 4: Polish + edge cases

1. Handle communities with missing coordinates
2. Handle requests with no community set
3. Test with real community data
4. Final test suite run

## Dependencies

- **No new Composer packages.** Haversine is 10 lines of math.
- **No new infrastructure.** No PostGIS, no map tiles, no geocoding APIs.
- **No framework changes.** All changes are in Minoo.
- **Community data must have coordinates.** The `bin/sync-communities` script already imports these.

## Future (v0.8)

- **Map view:** Leaflet/MapLibre map on coordinator dashboard showing request + volunteer locations
- **Driving distance:** Optional integration with OSRM or GraphHopper for actual travel time
- **Auto-suggest:** Automatically suggest the 3 nearest volunteers instead of requiring manual selection
- **Volunteer availability calendar:** Time-based matching alongside geographic matching
