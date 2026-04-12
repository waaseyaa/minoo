<?php

declare(strict_types=1);

namespace App\Feed;

interface FeedAssemblerInterface
{
    public function assemble(FeedContext $ctx): FeedResponse;
}
