#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Verify-before-prod HTTP check (#783).
 *
 * Hits the public surface of a running deployment and asserts each page returns
 * 200 with a non-trivial body AND the expected <title> — never status alone (a
 * crashing kernel can still emit a 200 with an empty body; see CLAUDE.md). Run
 * it against a staging hostname before promoting, and against prod after.
 *
 * Usage:
 *   php scripts/verify-http.php https://staging.minoo.live
 *   BASE_URL=https://minoo.live php scripts/verify-http.php
 *
 * Exit 0 if every check passes, 1 otherwise.
 */

$base = rtrim((string) ($argv[1] ?? getenv('BASE_URL') ?: ''), '/');
if ($base === '') {
    fwrite(STDERR, "Usage: php scripts/verify-http.php <base-url>  (or set BASE_URL)\n");
    exit(2);
}

/** @var list<array{path: string, minBytes: int, title: string, type?: string}> $checks */
$checks = [
    ['path' => '/', 'minBytes' => 4000, 'title' => 'Minoo'],
    ['path' => '/language', 'minBytes' => 6000, 'title' => 'Language'],
    ['path' => '/language/makwa', 'minBytes' => 4000, 'title' => 'makwa'],
    ['path' => '/communities', 'minBytes' => 4000, 'title' => 'Communities'],
    ['path' => '/communities/sagamok-anishnawbek', 'minBytes' => 3000, 'title' => 'Sagamok'],
    ['path' => '/games', 'minBytes' => 4000, 'title' => 'Games'],
    ['path' => '/games/crossword', 'minBytes' => 3000, 'title' => 'Crossword'],
    ['path' => '/about', 'minBytes' => 2000, 'title' => 'About'],
    ['path' => '/sitemap.xml', 'minBytes' => 500, 'title' => '', 'type' => 'application/xml'],
    ['path' => '/robots.txt', 'minBytes' => 30, 'title' => '', 'type' => 'text/plain'],
];

$failures = 0;
foreach ($checks as $check) {
    $url = $base . $check['path'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'minoo-verify-http/1.0',
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $size = strlen($body);
    $problems = [];
    if ($status !== 200) {
        $problems[] = "status {$status}";
    }
    if ($size < $check['minBytes']) {
        $problems[] = "body {$size}B < {$check['minBytes']}B";
    }
    if ($check['title'] !== '' && stripos($body, '<title>') !== false) {
        if (preg_match('/<title>(.*?)<\/title>/is', $body, $m) !== 1 || stripos($m[1], $check['title']) === false) {
            $problems[] = "title missing '{$check['title']}'";
        }
    } elseif ($check['title'] !== '' && stripos($body, $check['title']) === false) {
        $problems[] = "missing '{$check['title']}'";
    }
    if (isset($check['type']) && stripos($contentType, $check['type']) === false) {
        $problems[] = "content-type '{$contentType}' != '{$check['type']}'";
    }

    if ($problems === []) {
        fprintf(STDOUT, "[PASS] %-40s %d/%dB\n", $check['path'], $status, $size);
    } else {
        $failures++;
        fprintf(STDOUT, "[FAIL] %-40s %s\n", $check['path'], implode('; ', $problems));
    }
}

fprintf(STDOUT, "\n%s — %s\n", $base, $failures === 0 ? 'ALL CHECKS PASS' : "{$failures} FAILURE(S)");
exit($failures === 0 ? 0 : 1);
