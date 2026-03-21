<?php

declare(strict_types=1);

namespace Minoo\Feed;

final class FeedCursor
{
    public static function encode(string $lastSortKey, string $lastType, string $lastId): string
    {
        return base64_encode(json_encode([
            'lastSortKey' => $lastSortKey,
            'lastType' => $lastType,
            'lastId' => $lastId,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{lastSortKey: string, lastType: string, lastId: string}|null
     */
    public static function decode(string $cursor): ?array
    {
        if ($cursor === '') {
            return null;
        }

        $json = base64_decode($cursor, true);
        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (
            !is_array($data)
            || !isset($data['lastSortKey'], $data['lastType'], $data['lastId'])
            || !is_string($data['lastSortKey'])
            || !is_string($data['lastType'])
            || !is_string($data['lastId'])
        ) {
            return null;
        }

        return [
            'lastSortKey' => $data['lastSortKey'],
            'lastType' => $data['lastType'],
            'lastId' => $data['lastId'],
        ];
    }
}
