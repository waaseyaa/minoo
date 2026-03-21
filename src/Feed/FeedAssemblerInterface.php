<?php

declare(strict_types=1);

namespace Minoo\Feed;

interface FeedAssemblerInterface
{
    public function assemble(FeedContext $ctx): FeedResponse;
}
