<?php

declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Exception;

final class DispatchException extends \RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Mail service is not configured (missing SENDGRID_API_KEY or from address).');
    }

    public static function missingPrinterEmail(string $community): self
    {
        return new self(sprintf('No printer_email configured for community "%s".', $community));
    }

    public static function mailFailure(string $reason): self
    {
        return new self('Failed to send newsletter to printer: ' . $reason);
    }
}
