<?php

declare(strict_types=1);

/**
 * PHP Deployer configuration for minoo.live
 *
 * Deployment strategy: artifact upload
 * Composer uses path repositories (../waaseyaa/packages/*), so vendor is
 * pre-built in CI and uploaded — the server never runs composer directly.
 *
 * Usage:
 *   dep deploy production           # Full deploy
 *   dep rollback production          # Roll back to previous release
 *   dep deploy:unlock production     # Unlock if deploy was interrupted
 */

namespace Deployer;

require 'recipe/common.php';

// ---------------------------------------------------------------------------
// Project
// ---------------------------------------------------------------------------

set('application', 'minoo');
set('keep_releases', 5);
set('allow_anonymous_stats', false);

// ---------------------------------------------------------------------------
// Shared filesystem
// ---------------------------------------------------------------------------

// Directories persisted across releases (symlinked from shared/)
set('shared_dirs', ['storage']);

// Files persisted across releases (symlinked from shared/)
set('shared_files', ['.env']);

// Directories that must be writable by the web server
set('writable_dirs', ['storage', 'storage/framework']);

// PHP-FPM pool user (Debian/Ubuntu default). Required so deploy:writable can
// grant the web server write access to shared storage (uploads, caches).
set('http_user', 'www-data');

// ---------------------------------------------------------------------------
// Hosts
// ---------------------------------------------------------------------------

host('production')
    ->setHostname('minoo.live')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/home/deployer/minoo')
    ->set('labels', ['stage' => 'production']);

// ---------------------------------------------------------------------------
// Tasks
// ---------------------------------------------------------------------------

desc('Upload pre-built release artifact from CI');
task('deploy:upload', function (): void {
    // .build/ is prepared by the GitHub Actions workflow and contains the
    // full application tree (src, config, templates, public, vendor, bin).
    upload('.build/', '{{release_path}}/', [
        'options' => ['--recursive', '--compress'],
    ]);
});

desc('Backup SQLite database before migrations');
task('minoo:backup-db', function (): void {
    $backupDir = '{{deploy_path}}/shared/storage/backups';
    run("mkdir -p {$backupDir}");
    $timestamp = date('Y-m-d_His');
    run("set -a && . {{deploy_path}}/shared/.env && set +a && "
        . "cp \"\${WAASEYAA_DB:-{{deploy_path}}/shared/storage/waaseyaa.sqlite}\" "
        . "{$backupDir}/waaseyaa_{$timestamp}.sqlite");
    // Keep only the last 10 backups
    run("ls -t {$backupDir}/waaseyaa_*.sqlite 2>/dev/null | tail -n +11 | xargs -r rm --");
    writeln('<info>Database backed up.</info>');
});

desc('Run pending schema migrations against the shared SQLite database');
task('minoo:migrate', function (): void {
    // WAASEYAA_DB is defined in shared/.env and is not present in the deploy
    // shell environment. Source the file before invoking bin/migrate so the
    // script can locate the database. deploy:shared must run first so the
    // symlink {{release_path}}/.env → shared/.env is already in place.
    run('set -a && . {{deploy_path}}/shared/.env && set +a && php {{release_path}}/bin/migrate');
});

desc('Clear Waaseyaa framework manifest cache (manual recovery only)');
task('minoo:clear-manifest', function (): void {
    // Prefer `minoo:compile-manifest` on deploy: deleting packages.php forces a
    // first-request recompile as the FPM user, which often cannot write under
    // shared/storage when owned by deployer — boot fails with a generic 500.
    run('rm -f {{release_path}}/storage/framework/packages.php');
});

desc('Compile package manifest into shared storage (runs as deploy user)');
task('minoo:compile-manifest', function (): void {
    // Must run before deploy:symlink so vendor/ matches this release when the
    // fingerprint is computed. Writes through release/storage → shared/storage.
    run('cd {{release_path}} && php bin/waaseyaa optimize:manifest');
});

desc('Reload PHP-FPM to pick up new release');
task('php-fpm:reload', function (): void {
    // deployer must have passwordless sudo for this command.
    // Add to /etc/sudoers.d/deployer on the server:
    //   deployer ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.5-fpm
    run('sudo systemctl reload php8.5-fpm');
});

desc('Restart NC sync worker to pick up new release');
task('nc-sync:restart', function (): void {
    // deployer must have passwordless sudo for this command.
    // Add to /etc/sudoers.d/minoo-nc-sync on the server:
    //   deployer ALL=(ALL) NOPASSWD: /bin/systemctl restart minoo-nc-sync
    $result = run('sudo systemctl restart minoo-nc-sync 2>&1; echo "EXIT:$?"');
    if (str_contains($result, 'EXIT:0')) {
        writeln('<info>NC sync worker restarted.</info>');
    } else {
        writeln('<comment>WARNING: NC sync worker restart failed — worker may not be running.</comment>');
    }
});

// ---------------------------------------------------------------------------
// Deploy flow
//
// We use artifact upload instead of git+composer on the server because
// waaseyaa/* packages are path repositories unavailable on the remote.
// ---------------------------------------------------------------------------

desc('Deploy Minoo to production');
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:upload',
    'deploy:shared',
    'deploy:writable',
    'minoo:backup-db',
    'minoo:migrate',
    'minoo:compile-manifest',
    'deploy:symlink',
    'deploy:unlock',
    'php-fpm:reload',
    'nc-sync:restart',
    'deploy:test',
    'deploy:cleanup',
]);

// ---------------------------------------------------------------------------
// Health check
// ---------------------------------------------------------------------------

desc('Verify production is healthy after deploy');
task('deploy:test', function (): void {
    $checks = [
        ['url' => 'https://minoo.live/', 'expect' => 200],
        // Admin session probe. Verifies the framework `/admin/_surface/session`
        // endpoint is wired and returning the SPA-recognized envelope, not the
        // SPA HTML fallback (which would return 200 + HTML and silently mask a
        // broken route). The body capture below uses a single run() call with
        // a `___STATUS___NNN` trailing sentinel — earlier two-call shapes
        // (write to remote /tmp + second `cat`) intermittently returned an
        // empty body across Deployer's run() boundary.
        [
            'url' => 'https://minoo.live/admin/_surface/session',
            'expect' => 200,
            'expectJson' => ['ok' => false, 'error.status' => 401],
        ],
    ];

    // Retry checks up to 5 times with 2s sleep between attempts. Tolerates the
    // opcache-warmup race on first request after the release symlink flips
    // (the home check has caught real failures since the .183 bump; the admin
    // session probe has hit the race repeatedly even though the endpoint is
    // returning the right envelope when checked seconds later).
    $maxAttempts = 5;
    $sleepSeconds = 2;

    foreach ($checks as $check) {
        $url = $check['url'];
        $expect = $check['expect'];
        $expectBody = $check['expectBody'] ?? [];
        // JSON assertions are pretty-print tolerant: a dotted path → expected
        // scalar value. e.g. ['ok' => false, 'error.status' => 401] decodes
        // the body and walks the path. Beats brittle substring matching when
        // the server toggles JSON_PRETTY_PRINT or whitespace.
        $expectJson = $check['expectJson'] ?? [];

        $lastFailure = '';
        $lastHttpCode = 0;
        $passed = false;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // Single remote run(): curl writes the body to a temp file, the
            // command then `cat`s the body, prints a `___STATUS___NNN`
            // sentinel, and removes the temp file. PHP parses the sentinel to
            // recover the status code without a second run() call. The earlier
            // two-call approach (curl writes /tmp/body, second `cat` reads it)
            // intermittently returned an empty body across Deployer's run()
            // boundary; folding both into one shell pipeline removes that race.
            $remoteBodyPath = '/tmp/minoo-deploy-check-' . posix_getpid() . '-' . $attempt . '.body';
            $combined = (string) run(
                'curl -sS --max-time 10 -o ' . escapeshellarg($remoteBodyPath)
                . ' -w "%{http_code}" ' . escapeshellarg($url)
                . ' > ' . escapeshellarg($remoteBodyPath . '.code')
                . '; cat ' . escapeshellarg($remoteBodyPath)
                . '; printf "\n___STATUS___%s" "$(cat ' . escapeshellarg($remoteBodyPath . '.code') . ')"'
                . '; rm -f ' . escapeshellarg($remoteBodyPath) . ' ' . escapeshellarg($remoteBodyPath . '.code'),
            );

            $sentinelPos = strrpos($combined, "\n___STATUS___");
            if ($sentinelPos === false) {
                $lastHttpCode = 0;
                $body = $combined;
            } else {
                $body = substr($combined, 0, $sentinelPos);
                $lastHttpCode = (int) substr($combined, $sentinelPos + strlen("\n___STATUS___"));
            }

            if ($lastHttpCode !== $expect) {
                $lastFailure = "returned {$lastHttpCode}, expected {$expect}";
            } else {
                $bodyOk = true;

                foreach ($expectBody as $needle) {
                    if (!str_contains($body, $needle)) {
                        $bodyOk = false;
                        $lastFailure = "body missing expected substring '{$needle}'";
                        break;
                    }
                }

                if ($bodyOk && $expectJson !== []) {
                    $decoded = json_decode($body, true);
                    if (!is_array($decoded)) {
                        $bodyOk = false;
                        $lastFailure = 'body is not valid JSON';
                    } else {
                        foreach ($expectJson as $path => $expectedValue) {
                            $cursor = $decoded;
                            $found = true;
                            foreach (explode('.', $path) as $segment) {
                                if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                                    $found = false;
                                    break;
                                }
                                $cursor = $cursor[$segment];
                            }
                            if (!$found || $cursor !== $expectedValue) {
                                $bodyOk = false;
                                $actual = $found ? var_export($cursor, true) : '<missing>';
                                $expectedStr = var_export($expectedValue, true);
                                $lastFailure = "JSON path '{$path}' expected {$expectedStr}, got {$actual}";
                                break;
                            }
                        }
                    }
                }

                if ($bodyOk) {
                    $passed = true;
                    break;
                }
            }

            if ($attempt < $maxAttempts) {
                writeln("<comment>Health check retry {$attempt}/{$maxAttempts} for {$url}: {$lastFailure}</comment>");
                sleep($sleepSeconds);
            }
        }

        if (!$passed) {
            // `deploy:rollback` is not defined in this dep config; the previous
            // invoke() call here only produced a confusing secondary exception.
            // The next push will re-attempt; manual SSH inspection is the
            // operator action when this fires.
            writeln("<error>Health check failed after {$maxAttempts} attempts: {$url} — {$lastFailure}</error>");

            throw new \RuntimeException("Post-deploy health check failed — manual investigation required (no rollback configured).");
        }

        writeln("<info>Health check passed: {$url} → {$lastHttpCode}</info>");
    }
});

desc('Log health-check failure notification');
task('deploy:test:notify', function (): void {
    writeln("<error>[ALERT] Deploy failed health check — rollback was attempted. Check production immediately.</error>");
});

// Roll back automatically if deploy fails after release symlink is set
after('deploy:failed', 'deploy:unlock');
after('deploy:failed', 'deploy:test:notify');
