<?php

declare(strict_types=1);

namespace Minoo\Support;

use Minoo\Service\FlashMessageService;

/**
 * Static facade for FlashMessageService.
 *
 * Controllers use Flash::success(), Flash::error(), Flash::info() to queue
 * flash messages. The FlashTwigExtension consumes them automatically on
 * the next page render via FlashMessageService::consumeAll().
 */
final class Flash
{
    private static ?FlashMessageService $service = null;

    public static function success(string $message): void
    {
        self::getService()->addSuccess($message);
    }

    public static function error(string $message): void
    {
        self::getService()->addError($message);
    }

    public static function info(string $message): void
    {
        self::getService()->addInfo($message);
    }

    /**
     * @deprecated Use flash_messages() Twig function instead.
     * @return array{type: string, message: string}|null
     */
    public static function consume(): ?array
    {
        $messages = self::getService()->consumeAll();

        return $messages !== [] ? $messages[0] : null;
    }

    /**
     * @deprecated Use Flash::success(), Flash::error(), or Flash::info() instead.
     */
    public static function set(string $type, string $message): void
    {
        match ($type) {
            'error' => self::getService()->addError($message),
            'info' => self::getService()->addInfo($message),
            default => self::getService()->addSuccess($message),
        };
    }

    private static function getService(): FlashMessageService
    {
        if (self::$service === null) {
            self::$service = new FlashMessageService();
        }

        return self::$service;
    }
}
