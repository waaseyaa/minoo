<?php
declare(strict_types=1);

namespace App\Domain\Newsletter\Service;

use App\Domain\Newsletter\Exception\InvalidStateTransition;
use App\Domain\Newsletter\ValueObject\EditionStatus;
use Waaseyaa\Entity\EntityInterface;

final class EditionLifecycle
{
    /**
     * Returns the set of statuses an edition in the given source state may
     * transition into. Adding a new EditionStatus case will fail compilation
     * until this match is updated — that is intentional.
     *
     * @return list<EditionStatus>
     */
    private function allowedTransitions(EditionStatus $from): array
    {
        return match ($from) {
            EditionStatus::Draft     => [EditionStatus::Curating],
            EditionStatus::Curating  => [EditionStatus::Approved, EditionStatus::Draft],
            EditionStatus::Approved  => [EditionStatus::Generated, EditionStatus::Curating],
            EditionStatus::Generated => [EditionStatus::Sent, EditionStatus::Approved],
            EditionStatus::Sent      => [],
        };
    }

    public function transition(EntityInterface $edition, EditionStatus $to): void
    {
        $from = EditionStatus::fromEntity($edition);

        if ($from === $to) {
            return; // idempotent: already in the target state
        }

        $allowed = $this->allowedTransitions($from);

        if (! in_array($to, $allowed, true)) {
            throw InvalidStateTransition::illegal($from, $to);
        }

        $edition->set('status', $to->value);
    }

    public function approve(EntityInterface $edition, int $approverId): void
    {
        if (EditionStatus::fromEntity($edition) === EditionStatus::Approved) {
            return; // idempotent: already approved, preserve existing metadata
        }

        $this->transition($edition, EditionStatus::Approved);
        $edition->set('approved_by', $approverId);
        $edition->set('approved_at', (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM));
    }

    public function markGenerated(EntityInterface $edition, string $pdfPath, string $pdfHash): void
    {
        if (EditionStatus::fromEntity($edition) === EditionStatus::Generated) {
            return; // idempotent: already generated, preserve existing metadata
        }

        $this->transition($edition, EditionStatus::Generated);
        $edition->set('pdf_path', $pdfPath);
        $edition->set('pdf_hash', $pdfHash);
    }

    public function markSent(EntityInterface $edition): void
    {
        if (EditionStatus::fromEntity($edition) === EditionStatus::Sent) {
            return; // idempotent: already sent, preserve existing metadata
        }

        $this->transition($edition, EditionStatus::Sent);
        $edition->set('sent_at', (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM));
    }
}
