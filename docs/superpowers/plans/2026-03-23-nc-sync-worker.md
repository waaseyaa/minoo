# NC Content Sync Worker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Long-running PHP worker that polls NC Search API every 30 minutes and creates Minoo teachings/events automatically.

**Architecture:** PHP loop script managed by systemd. Boots HttpKernel, calls existing `NcContentSyncService`, writes status JSON, handles SIGTERM gracefully. Deployer restarts it on deploy.

**Tech Stack:** PHP 8.4, pcntl signals, systemd, Deployer

**Spec:** `docs/superpowers/specs/2026-03-23-nc-sync-worker-design.md`

---

### Task 1: Create `NcSyncWorkerLoop` class

Extract the loop logic into a testable class so the worker script is a thin boot wrapper.

**Pre-step:** Remove `final` from `NcContentSyncService` — it needs mocking. CLAUDE.md: "Don't use `final` on services that need mocking."

**Files:**
- Modify: `src/Ingestion/NcContentSyncService.php` (remove `final`)
- Create: `src/Ingestion/NcSyncWorkerLoop.php`
- Test: `tests/Minoo/Unit/Ingestion/NcSyncWorkerLoopTest.php`

- [ ] **Step 1: Write failing test — single cycle executes sync and writes status**

```php
<?php
declare(strict_types=1);
namespace Minoo\Tests\Unit\Ingestion;

use Minoo\Ingestion\NcContentSyncService;
use Minoo\Ingestion\NcSyncResult;
use Minoo\Ingestion\NcSyncWorkerLoop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NcSyncWorkerLoop::class)]
final class NcSyncWorkerLoopTest extends TestCase
{
    #[Test]
    public function single_cycle_calls_sync_and_writes_status(): void
    {
        $syncService = $this->createMock(NcContentSyncService::class);
        $syncService->method('sync')->willReturn(new NcSyncResult(created: 3, skipped: 7));

        $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

        $loop = new NcSyncWorkerLoop(
            syncService: $syncService,
            statusPath: $statusPath,
            intervalSeconds: 1,
            maxCycles: 1,
        );
        $loop->run();

        $this->assertFileExists($statusPath);
        $status = json_decode(file_get_contents($statusPath), true);
        $this->assertSame(3, $status['created']);
        $this->assertSame(7, $status['skipped']);
        $this->assertSame(0, $status['failed']);
        $this->assertFalse($status['fetch_failed']);
        $this->assertSame(1, $status['cycles']);
        $this->assertArrayHasKey('last_sync', $status);

        @unlink($statusPath);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingestion/NcSyncWorkerLoopTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement `NcSyncWorkerLoop`**

```php
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
        private readonly int $maxCycles = 48,
        private readonly int $limit = 20,
    ) {}

    public function run(): void
    {
        while ($this->running && $this->cycleCount < $this->maxCycles) {
            try {
                $result = $this->syncService->sync(limit: $this->limit);
            } catch (\Throwable $e) {
                $result = new NcSyncResult(fetchFailed: true);
                fprintf(STDERR, "[%s] Sync exception: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
            }
            ++$this->cycleCount;

            $this->writeStatus($result);
            $this->log($result);

            if (!$this->running || $this->cycleCount >= $this->maxCycles) {
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
        $data = json_encode([
            'last_sync' => date('c'),
            'created' => $result->created,
            'skipped' => $result->skipped,
            'failed' => $result->failed,
            'fetch_failed' => $result->fetchFailed,
            'cycles' => $this->cycleCount,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $tmp = $this->statusPath . '.tmp';
        file_put_contents($tmp, $data);
        rename($tmp, $this->statusPath);
    }

    private function log(NcSyncResult $result): void
    {
        $ts = date('Y-m-d H:i:s');
        if ($result->fetchFailed) {
            fprintf(STDERR, "[%s] Sync FAILED: could not reach NorthCloud\n", $ts);
            return;
        }
        fprintf(STDOUT, "[%s] Sync: created=%d skipped=%d failed=%d (cycle %d/%d)\n",
            $ts, $result->created, $result->skipped, $result->failed,
            $this->cycleCount, $this->maxCycles);
    }

    private function sleep(): void
    {
        for ($i = 0; $i < $this->intervalSeconds && $this->running; $i++) {
            sleep(1);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingestion/NcSyncWorkerLoopTest.php`
Expected: PASS

- [ ] **Step 5: Add more test cases**

Add to the same test file:

```php
#[Test]
public function max_cycles_stops_loop(): void
{
    $syncService = $this->createMock(NcContentSyncService::class);
    $syncService->expects($this->exactly(3))->method('sync')
        ->willReturn(new NcSyncResult(created: 1));

    $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

    $loop = new NcSyncWorkerLoop(
        syncService: $syncService,
        statusPath: $statusPath,
        intervalSeconds: 0,
        maxCycles: 3,
    );
    $loop->run();

    $status = json_decode(file_get_contents($statusPath), true);
    $this->assertSame(3, $status['cycles']);

    @unlink($statusPath);
}

#[Test]
public function stop_terminates_loop(): void
{
    $callCount = 0;
    $syncService = $this->createMock(NcContentSyncService::class);
    $syncService->method('sync')->willReturnCallback(function () use (&$callCount, &$loop) {
        $callCount++;
        if ($callCount >= 2) {
            $loop->stop();
        }
        return new NcSyncResult(created: 1);
    });

    $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

    $loop = new NcSyncWorkerLoop(
        syncService: $syncService,
        statusPath: $statusPath,
        intervalSeconds: 0,
        maxCycles: 100,
    );
    $loop->run();

    $this->assertSame(2, $callCount);

    @unlink($statusPath);
}

#[Test]
public function fetch_failed_is_recorded_in_status(): void
{
    $syncService = $this->createMock(NcContentSyncService::class);
    $syncService->method('sync')
        ->willReturn(new NcSyncResult(fetchFailed: true));

    $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

    $loop = new NcSyncWorkerLoop(
        syncService: $syncService,
        statusPath: $statusPath,
        intervalSeconds: 1,
        maxCycles: 1,
    );
    $loop->run();

    $status = json_decode(file_get_contents($statusPath), true);
    $this->assertTrue($status['fetch_failed']);

    @unlink($statusPath);
}
```

```php
#[Test]
public function sync_exception_is_caught_and_loop_continues(): void
{
    $callCount = 0;
    $syncService = $this->createMock(NcContentSyncService::class);
    $syncService->method('sync')->willReturnCallback(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            throw new \RuntimeException('Connection refused');
        }
        return new NcSyncResult(created: 1);
    });

    $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

    $loop = new NcSyncWorkerLoop(
        syncService: $syncService,
        statusPath: $statusPath,
        intervalSeconds: 0,
        maxCycles: 2,
    );
    $loop->run();

    $status = json_decode(file_get_contents($statusPath), true);
    $this->assertSame(2, $status['cycles']);
    $this->assertSame(1, $status['created']);

    @unlink($statusPath);
}
```

- [ ] **Step 6: Run all tests**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Ingestion/NcSyncWorkerLoopTest.php`
Expected: 5 tests, all PASS

- [ ] **Step 7: Commit**

```bash
git add src/Ingestion/NcSyncWorkerLoop.php tests/Minoo/Unit/Ingestion/NcSyncWorkerLoopTest.php
git commit -m "feat(#495): add NcSyncWorkerLoop with tests"
```

---

### Task 2: Create worker script

Thin boot wrapper that wires the loop to the real kernel and signal handlers.

**Files:**
- Create: `scripts/nc-sync-worker.php`

- [ ] **Step 1: Write the worker script**

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * NC Content Sync Worker — polls NorthCloud Search API every 30 minutes.
 * Managed by systemd: minoo-nc-sync.service
 * Run: php scripts/nc-sync-worker.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Minoo\Ingestion\NcContentSyncService;
use Minoo\Ingestion\NcSyncWorkerLoop;
use Minoo\Support\NorthCloudClient;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

// Boot kernel
$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

// Validate config
$config = require dirname(__DIR__) . '/config/waaseyaa.php';
$baseUrl = $config['northcloud']['base_url'] ?? '';
if ($baseUrl === '') {
    fprintf(STDERR, "FATAL: northcloud.base_url is not configured. Set NORTHCLOUD_BASE_URL.\n");
    exit(1);
}

// Build services
$searchTimeout = (int) ($config['search']['timeout'] ?? 15);
$client = new NorthCloudClient(baseUrl: $baseUrl, timeout: $searchTimeout);
$syncService = new NcContentSyncService($client, $kernel->getEntityTypeManager());

$statusPath = dirname(__DIR__) . '/storage/nc-sync-status.json';

$loop = new NcSyncWorkerLoop(
    syncService: $syncService,
    statusPath: $statusPath,
    intervalSeconds: 1800,
    maxCycles: 48,
);

// Signal handling
pcntl_async_signals(true);
$shutdown = static function () use ($loop): void {
    fprintf(STDOUT, "Received shutdown signal, finishing current cycle...\n");
    $loop->stop();
};
pcntl_signal(SIGTERM, $shutdown);
pcntl_signal(SIGINT, $shutdown);

fprintf(STDOUT, "NC Sync Worker started (interval=30m, max_cycles=48)\n");
$loop->run();
fprintf(STDOUT, "NC Sync Worker stopped.\n");
```

- [ ] **Step 2: Verify script boots locally**

Run: `php scripts/nc-sync-worker.php &` then `kill %1` after first cycle.
Expected: Logs one sync line, shuts down gracefully on SIGTERM.

- [ ] **Step 3: Commit**

```bash
git add scripts/nc-sync-worker.php
git commit -m "feat(#495): add nc-sync-worker.php boot script"
```

---

### Task 3: Create systemd service and deploy hook

**Files:**
- Create: `deploy/minoo-nc-sync.service`
- Modify: `deploy.php`

- [ ] **Step 1: Create systemd unit file**

```ini
# deploy/minoo-nc-sync.service
# Install: sudo cp deploy/minoo-nc-sync.service /etc/systemd/system/
#          sudo systemctl daemon-reload
#          sudo systemctl enable --now minoo-nc-sync

[Unit]
Description=Minoo NC Content Sync
After=network.target

[Service]
Type=simple
User=deployer
WorkingDirectory=/home/deployer/minoo/current
ExecStart=/usr/bin/php scripts/nc-sync-worker.php
Restart=on-failure
RestartSec=30
TimeoutStopSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

- [ ] **Step 2: Add deploy hook to `deploy.php`**

Add after the `php-fpm:reload` task definition (before the deploy task list):

```php
desc('Restart NC sync worker to pick up new release');
task('nc-sync:restart', function (): void {
    run('sudo systemctl restart minoo-nc-sync || true');
});
```

Add `'nc-sync:restart'` to the deploy task list after `'php-fpm:reload'`:

```php
task('deploy', [
    // ... existing tasks ...
    'php-fpm:reload',
    'nc-sync:restart',   // <-- add this line
    'deploy:test',
    'deploy:cleanup',
]);
```

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All 684+ tests pass (new tests from Task 1 included)

- [ ] **Step 4: Commit**

```bash
git add deploy/minoo-nc-sync.service deploy.php
git commit -m "feat(#495): add systemd service + deploy restart hook"
```

---

### Task 4: Deploy to production

- [ ] **Step 1: Push and create PR**

```bash
git push -u origin feat/495-nc-sync-worker
gh pr create --title "feat(#495): NC content sync queue worker" --body "..."
```

- [ ] **Step 2: Merge after CI passes**

- [ ] **Step 3: Add sudoers entry BEFORE first deploy with the hook**

```bash
ssh deployer@minoo.live "echo 'deployer ALL=(ALL) NOPASSWD: /bin/systemctl restart minoo-nc-sync' | sudo tee /etc/sudoers.d/minoo-nc-sync"
```

- [ ] **Step 4: Install systemd service on production**

```bash
ssh deployer@minoo.live "sudo cp /home/deployer/minoo/current/deploy/minoo-nc-sync.service /etc/systemd/system/"
ssh deployer@minoo.live "sudo systemctl daemon-reload"
ssh deployer@minoo.live "sudo systemctl enable --now minoo-nc-sync"
```

- [ ] **Step 5: Verify worker is running**

```bash
ssh deployer@minoo.live "systemctl status minoo-nc-sync"
ssh deployer@minoo.live "journalctl -u minoo-nc-sync --no-pager -n 5"
ssh deployer@minoo.live "cat /home/deployer/minoo/current/storage/nc-sync-status.json"
```

---

## Verification

After all tasks complete:

1. `journalctl -u minoo-nc-sync -f` — watch live sync cycles
2. `cat storage/nc-sync-status.json` — verify status updates every 30 min
3. Browse minoo.live/feed — new NC teachings/events appearing
4. `sudo systemctl stop minoo-nc-sync` — verify graceful shutdown in logs
5. Deploy a no-op change — verify worker restarts after deploy
