<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Service;

use App\Domain\Newsletter\Exception\DispatchException;
use Waaseyaa\Entity\EntityInterface;

/**
 * Emails a generated newsletter PDF to the configured print partner.
 *
 * The dispatcher is intentionally side-effect light: it loads the per-community
 * printer config, validates that mail is configured, sends the attachment, and
 * returns the recipient address. State transitions on the edition (markSent,
 * sent_at, save) are the controller's responsibility so that error paths can
 * keep the edition in `generated` for retry.
 */
class NewsletterDispatcher
{
    /**
     * @param object $mailService Any object exposing isConfigured(): bool and
     *                            sendWithAttachment(string $to, string $subject,
     *                            string $body, string $path): bool. Loose type
     *                            so test fakes (anonymous classes) and the
     *                            production NewsletterMailer adapter can both
     *                            satisfy the contract without sharing an
     *                            interface.
     * @param array<string, array<string, mixed>> $communityConfig
     *        Map of community_id => config (printer_email, printer_name, …).
     *        A null edition.community_id falls back to $defaultCommunity.
     * @param string $defaultCommunity
     *        Community key used when an edition has a null/empty community_id
     *        (regional issues). Must exist in $communityConfig.
     */
    public function __construct(
        private readonly object $mailService,
        private readonly array $communityConfig,
        private readonly string $defaultCommunity,
    ) {
    }

    /**
     * Send the edition's PDF to the configured printer.
     *
     * @return string Recipient email address on success.
     * @throws DispatchException
     */
    public function dispatch(EntityInterface $edition): string
    {
        // Fail fast: confirm mail is configured before any other work.
        if (!$this->mailService->isConfigured()) {
            throw DispatchException::notConfigured();
        }

        $communityId = (string) ($edition->get('community_id') ?? '');
        $lookupKey = $communityId !== '' ? $communityId : $this->defaultCommunity;

        $config = $this->communityConfig[$lookupKey] ?? null;
        $printerEmail = is_array($config) ? (string) ($config['printer_email'] ?? '') : '';

        if ($printerEmail === '') {
            throw DispatchException::missingPrinterEmail($lookupKey);
        }

        $pdfPath = (string) $edition->get('pdf_path');
        if ($pdfPath === '' || !is_file($pdfPath)) {
            throw DispatchException::mailFailure(sprintf('PDF artifact missing at "%s".', $pdfPath));
        }

        $printerName = is_array($config) ? (string) ($config['printer_name'] ?? 'print partner') : 'print partner';
        $headline = (string) ($edition->get('headline') ?? sprintf('Issue %d', (int) $edition->get('issue_number')));
        $subject = sprintf('Minoo Newsletter — %s (vol %d, issue %d)', $headline, (int) $edition->get('volume'), (int) $edition->get('issue_number'));
        $body = sprintf(
            "Hello %s,\n\nAttached is the print-ready PDF for the latest Minoo Elder newsletter (%s).\n\nMiigwech,\nMinoo Newsroom",
            $printerName,
            $headline,
        );

        try {
            $ok = $this->mailService->sendWithAttachment($printerEmail, $subject, $body, $pdfPath);
        } catch (\Throwable $e) {
            throw DispatchException::mailFailure($e->getMessage());
        }

        if ($ok !== true) {
            throw DispatchException::mailFailure('Mail driver returned a non-success result.');
        }

        return $printerEmail;
    }
}
