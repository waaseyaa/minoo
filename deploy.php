<?php

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

desc('Run pending schema migrations against the shared SQLite database');
task('minoo:migrate', function (): void {
    // WAASEYAA_DB is defined in shared/.env and is not present in the deploy
    // shell environment. Source the file before invoking bin/migrate so the
    // script can locate the database. deploy:shared must run first so the
    // symlink {{release_path}}/.env → shared/.env is already in place.
    run('set -a && . {{deploy_path}}/shared/.env && set +a && php {{release_path}}/bin/migrate');
});

desc('Clear Waaseyaa framework manifest cache');
task('minoo:clear-manifest', function (): void {
    // packages.php is compiled from composer.json on first boot.
    // Remove any stale cache so the new release discovers providers fresh.
    run('rm -f {{release_path}}/storage/framework/packages.php');
});

desc('Reload PHP-FPM to pick up new release');
task('php-fpm:reload', function (): void {
    // deployer must have passwordless sudo for this command.
    // Add to /etc/sudoers.d/deployer on the server:
    //   deployer ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.4-fpm
    run('sudo systemctl reload php8.4-fpm');
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
    'minoo:migrate',
    'minoo:clear-manifest',
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
        ['url' => 'https://minoo.live/admin/surface/session', 'expect' => 401],
    ];

    foreach ($checks as $check) {
        $url = $check['url'];
        $expect = $check['expect'];

        // Use curl on the remote host to avoid local DNS/network differences.
        $httpCode = (int) run("curl -s -o /dev/null -w '%{http_code}' --max-time 10 " . escapeshellarg($url));

        if ($httpCode !== $expect) {
            writeln("<error>Health check failed: {$url} returned {$httpCode}, expected {$expect}</error>");
            invoke('deploy:rollback');

            throw new \RuntimeException("Post-deploy health check failed — rolled back.");
        }

        writeln("<info>Health check passed: {$url} → {$httpCode}</info>");
    }
});

desc('Log health-check failure notification');
task('deploy:test:notify', function (): void {
    writeln("<error>[ALERT] Deploy failed health check — rollback was attempted. Check production immediately.</error>");
});

// Roll back automatically if deploy fails after release symlink is set
after('deploy:failed', 'deploy:unlock');
after('deploy:failed', 'deploy:test:notify');
