<?php
declare(strict_types=1);

namespace Minoo\Domain\Newsletter\Service;

use Minoo\Domain\Newsletter\Exception\InvalidStateTransition;
use Minoo\Domain\Newsletter\ValueObject\EditionStatus;
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
        $allowed = $this->allowedTransitions($from);

        if (! in_array($to, $allowed, true)) {
            throw InvalidStateTransition::illegal($from, $to);
        }

        $edition->set('status', $to->value);
    }

    public function approve(EntityInterface $edition, int $approverId): void
    {
        $this->transition($edition, EditionStatus::Approved);
        $edition->set('approved_by', $approverId);
        $edition->set('approved_at', (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM));
    }

    public function markGenerated(EntityInterface $edition, string $pdfPath, string $pdfHash): void
    {
        $this->transition($edition, EditionStatus::Generated);
        $edition->set('pdf_path', $pdfPath);
        $edition->set('pdf_hash', $pdfHash);
    }

    public function markSent(EntityInterface $edition): void
    {
        $this->transition($edition, EditionStatus::Sent);
        $edition->set('sent_at', (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM));
    }
}
