<?php

declare(strict_types=1);

namespace App\Support\Cli;

use App\Support\MessageDigestCommand;
use Waaseyaa\CLI\CliIO;

final class MessagingDigestHandler
{
    public function __construct(
        private readonly MessageDigestCommand $digest,
    ) {}

    public function execute(CliIO $io): int
    {
        $this->digest->execute();

        return 0;
    }
}
