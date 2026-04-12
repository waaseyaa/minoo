<?php

declare(strict_types=1);

namespace App\Ingestion;

enum IngestStatus: string
{
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Failed = 'failed';
}
