<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Service;

use Closure;
use App\Domain\Newsletter\Exception\RenderException;
use App\Domain\Newsletter\ValueObject\PdfArtifact;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Waaseyaa\Entity\EntityInterface;

final class NewsletterRenderer
{
    /**
     * @param Closure(list<string>): Process|null $processFactory
     */
    public function __construct(
        private readonly RenderTokenStore $tokenStore,
        private readonly string $storageDir,
        private readonly string $baseUrl,
        private readonly string $nodeBinary,
        private readonly string $scriptPath,
        private readonly int $timeoutSeconds,
        private readonly ?Closure $processFactory = null,
    ) {
        if (! is_dir($storageDir) && ! @mkdir($storageDir, 0775, true) && ! is_dir($storageDir)) {
            throw new \RuntimeException("Renderer cannot create storage dir: {$storageDir}");
        }
    }

    public function render(EntityInterface $edition): PdfArtifact
    {
        $editionId = (int) $edition->id();
        $community = (string) ($edition->get('community_id') ?: 'regional');
        $community = preg_replace('/[^a-z0-9_\-]/i', '', $community);
        if ($community === '') {
            $community = 'regional';
        }
        $vol = (int) $edition->get('volume');
        $issue = (int) $edition->get('issue_number');

        $outDir = $this->storageDir . '/' . $community;
        if (! is_dir($outDir) && ! @mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new \RuntimeException("Renderer cannot create output dir: {$outDir}");
        }
        $outPath = sprintf('%s/%d-%d.pdf', $outDir, $vol, $issue);
        $tmpPath = $outPath . '.tmp.' . bin2hex(random_bytes(4));

        // Paranoid guard: clear any pre-existing tmp at this exact path.
        // Leave the existing outPath in place until the new render is verified.
        if (is_file($tmpPath)) {
            @unlink($tmpPath);
        }

        $token = $this->tokenStore->issue($editionId);
        $url = sprintf('%s/newsletter/_internal/%d/print?token=%s', $this->baseUrl, $editionId, $token);

        $cmd = [$this->nodeBinary, $this->scriptPath, '--url=' . $url, '--out=' . $tmpPath];

        $process = $this->processFactory !== null
            ? ($this->processFactory)($cmd)
            : new Process($cmd);
        $process->setTimeout($this->timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            @unlink($tmpPath);
            throw RenderException::timeout($this->timeoutSeconds);
        }

        if (! $process->isSuccessful()) {
            @unlink($tmpPath);
            throw RenderException::processFailure($process->getExitCode() ?? -1, $process->getErrorOutput());
        }

        if (! is_file($tmpPath)) {
            throw RenderException::zeroByteOutput($outPath);
        }

        $bytes = (int) filesize($tmpPath);
        if ($bytes === 0) {
            @unlink($tmpPath);
            throw RenderException::zeroByteOutput($outPath);
        }

        if (! @rename($tmpPath, $outPath)) {
            @unlink($tmpPath);
            throw RenderException::processFailure(-1, 'Atomic rename failed');
        }

        return new PdfArtifact(
            path: $outPath,
            bytes: $bytes,
            sha256: hash_file('sha256', $outPath),
        );
    }
}
