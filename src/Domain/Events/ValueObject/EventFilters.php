<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObject;

use Symfony\Component\HttpFoundation\Request;

final class EventFilters
{
    public const ALLOWED_TYPES  = ['powwow', 'gathering', 'ceremony', 'tournament'];
    public const ALLOWED_WHEN   = ['all', 'week', 'month', '3mo', 'past', 'day'];
    public const ALLOWED_VIEWS  = ['feed', 'list', 'calendar'];
    public const ALLOWED_SORTS  = ['soonest', 'latest'];

    /**
     * @param list<string> $types
     */
    public function __construct(
        public readonly array $types,
        public readonly ?string $communityId,
        public readonly string $when,
        public readonly bool $near,
        public readonly ?string $q,
        public readonly string $view,
        public readonly ?string $month,
        public readonly ?string $date,
        public readonly string $sort,
        public readonly int $page,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $rawTypes = (array) $request->query->all('type');
        $types = array_values(array_filter(
            array_map('strval', $rawTypes),
            static fn (string $t): bool => in_array($t, self::ALLOWED_TYPES, true),
        ));

        $communityId = $request->query->get('community_id');
        $communityId = is_string($communityId) && $communityId !== '' ? $communityId : null;

        $when = (string) $request->query->get('when', 'all');
        if (!in_array($when, self::ALLOWED_WHEN, true)) {
            $when = 'all';
        }

        $near = $request->query->getBoolean('near', false);

        $q = $request->query->get('q');
        $q = is_string($q) ? trim($q) : '';
        $q = $q === '' ? null : $q;

        $view = (string) $request->query->get('view', 'feed');
        if (!in_array($view, self::ALLOWED_VIEWS, true)) {
            $view = 'feed';
        }

        $month = $request->query->get('month');
        $month = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;

        $date = $request->query->get('date');
        $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;

        $sort = (string) $request->query->get('sort', 'soonest');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'soonest';
        }

        $page = (int) $request->query->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        return new self($types, $communityId, $when, $near, $q, $view, $month, $date, $sort, $page);
    }

    public function isActive(): bool
    {
        return $this->types !== []
            || $this->communityId !== null
            || $this->when !== 'all'
            || $this->near
            || $this->q !== null
            || $this->date !== null;
    }

    public function without(string $key, ?string $value = null): self
    {
        return new self(
            types:       $key === 'type'         ? array_values(array_filter($this->types, fn ($t) => $t !== $value)) : $this->types,
            communityId: $key === 'community_id' ? null : $this->communityId,
            when:        $key === 'when'         ? 'all' : $this->when,
            near:        $key === 'near'         ? false : $this->near,
            q:           $key === 'q'            ? null : $this->q,
            view:        $this->view,
            month:       $key === 'month'        ? null : $this->month,
            date:        $key === 'date'         ? null : $this->date,
            sort:        $this->sort,
            page:        1,
        );
    }
}
