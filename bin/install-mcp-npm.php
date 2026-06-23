<?php

declare(strict_types=1);

/**
 * Install Node dependencies for the Minoo MCP server (Claude Code).
 * Skips when npm is not on PATH so composer install succeeds in minimal CI images.
 * (Bimaaji's MCP surface went framework-native in Waaseyaa alpha.248 — no Node
 * runtime; its tools are exposed via the framework's /mcp endpoint instead.)
 */

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

$finder = new ExecutableFinder();
$npm = $finder->find('npm', null, [dirname(PHP_BINARY), '/usr/local/bin', '/usr/bin']);
if ($npm === null) {
    fwrite(STDERR, "[minoo] npm not found — skipping MCP npm install. For local dev: composer bimaaji-mcp-install\n");

    exit(0);
}

$targets = [
    $root . '/mcp',
];

foreach ($targets as $dir) {
    if (!is_file($dir . '/package.json')) {
        continue;
    }

    $process = new Process([$npm, 'install'], $dir);
    $process->setTimeout(null);
    $process->run(static function (string $type, string $buffer): void {
        echo $buffer;
    });

    if (!$process->isSuccessful()) {
        fwrite(STDERR, $process->getErrorOutput());
        exit($process->getExitCode() ?? 1);
    }
}

exit(0);
