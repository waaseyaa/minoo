<?php

declare(strict_types=1);

namespace Minoo\Ingestion;

final class NcSyncWorkerLoop
{
    private bool $running = true;
    private int $cycleCount = 0;

    public function __construct(
        private readonly NcContentSyncService $syncService,
        private readonly string $statusPath,
        private readonly int $intervalSeconds = 1800,
        /** @param int $maxCycles Max sync iterations; 0 or negative = unlimited */
        private readonly int $maxCycles = 0,
        private readonly int $limit = 20,
    ) {}

    public function run(): void
    {
        while ($this->running && ($this->maxCycles <= 0 || $this->cycleCount < $this->maxCycles)) {
            try {
                $result = $this->syncService->sync(limit: $this->limit);
            } catch (\Throwable $e) {
                $result = new NcSyncResult(fetchFailed: true);
                fprintf(STDERR, "[%s] Sync exception (%s): %s\n%s\n",
                    date('Y-m-d H:i:s'), $e::class, $e->getMessage(), $e->getTraceAsString());
            }
            ++$this->cycleCount;

            $this->writeStatus($result);
            $this->log($result);

            if (!$this->running || ($this->maxCycles > 0 && $this->cycleCount >= $this->maxCycles)) {
                break;
            }

            $this->sleep();
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function writeStatus(NcSyncResult $result): void
    {
        try {
            $data = json_encode([
                'last_sync' => date('c'),
                'created' => $result->created,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
                'fetch_failed' => $result->fetchFailed,
                'cycles' => $this->cycleCount,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\JsonException $e) {
            fprintf(STDERR, "[%s] WARNING: failed to encode status JSON: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
            return;
        }

        $tmp = $this->statusPath . '.tmp';
        if (file_put_contents($tmp, $data) === false) {
            fprintf(STDERR, "[%s] WARNING: failed to write status file %s\n", date('Y-m-d H:i:s'), $tmp);
            return;
        }
        if (!rename($tmp, $this->statusPath)) {
            fprintf(STDERR, "[%s] WARNING: failed to rename status file %s -> %s\n", date('Y-m-d H:i:s'), $tmp, $this->statusPath);
        }
    }

    private function log(NcSyncResult $result): void
    {
        $ts = date('Y-m-d H:i:s');
        if ($result->fetchFailed) {
            fprintf(STDERR, "[%s] Sync FAILED: could not reach NorthCloud\n", $ts);
            return;
        }
        $cap = $this->maxCycles <= 0 ? '∞' : (string) $this->maxCycles;
        fprintf(STDOUT, "[%s] Sync: created=%d skipped=%d failed=%d (cycle %d/%s)\n",
            $ts, $result->created, $result->skipped, $result->failed,
            $this->cycleCount, $cap);
    }

    private function sleep(): void
    {
        for ($i = 0; $i < $this->intervalSeconds && $this->running; $i++) {
            sleep(1);
        }
    }
}
