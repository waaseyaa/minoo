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
    // bin/migrate reads WAASEYAA_DB (set in shared/.env) and applies any SQL
    // files in migrations/ that have not yet been recorded in schema_migrations.
    // Runs before deploy:symlink so the schema is ready before traffic switches.
    run('php {{release_path}}/bin/migrate');
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
    'deploy:cleanup',
    'php-fpm:reload',
]);

// Roll back automatically if deploy fails after release symlink is set
after('deploy:failed', 'deploy:unlock');
