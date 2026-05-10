<?php

declare(strict_types=1);

namespace App\Console;

use Waaseyaa\CLI\CliIO;

final class MessagingDigestHandler
{
    public function __construct(
        private readonly MessageDigestCommand $digest,
    ) {
    }

    public function execute(CliIO $io): int
    {
        $this->digest->execute();

        return 0;
    }
}
