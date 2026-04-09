<?php

declare(strict_types=1);

namespace Minoo\Support\Command;

use Minoo\Support\MessageDigestCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'messaging:digest', description: 'Send email digests for unread thread messages (cron)')]
final class MessagingDigestCommand extends Command
{
    public function __construct(
        private readonly MessageDigestCommand $digest,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->digest->execute();

        return self::SUCCESS;
    }
}
