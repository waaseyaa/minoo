<?php

declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Exception;

final class RenderException extends \RuntimeException
{
    public static function processFailure(int $exitCode, string $stderr): self
    {
        return new self(sprintf('PDF render process failed (exit %d): %s', $exitCode, trim($stderr)));
    }

    public static function timeout(int $seconds): self
    {
        return new self(sprintf('PDF render timed out after %ds', $seconds));
    }

    public static function zeroByteOutput(string $path): self
    {
        return new self("PDF render produced zero-byte file: {$path}");
    }
}
