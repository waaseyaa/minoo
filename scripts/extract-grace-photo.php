<?php
declare(strict_types=1);

/**
 * One-shot helper: extract Grace Manitowabi's photo + caption from the
 * vol1-issue1 HTML draft into public/newsletter/vol1-issue1/.
 */
$src = dirname(__DIR__) . '/storage/tmp/minoo-newsletter-vol1-issue1.html';
$out = dirname(__DIR__) . '/public/newsletter/vol1-issue1/grace-manitowabi.jpg';

$html = file_get_contents($src);
if ($html === false) {
    fwrite(STDERR, "Cannot read $src\n");
    exit(1);
}

if (!preg_match('#<div class="photo-wrap">\s*<img[^>]*src="data:image/jpeg;base64,([A-Za-z0-9+/=\s]+?)"[^>]*>\s*<p class="photo-caption">(.*?)</p>\s*</div>#s', $html, $m)) {
    fwrite(STDERR, "photo-wrap not matched\n");
    exit(2);
}
$m[1] = preg_replace('/\s+/', '', $m[1]);

$bin = base64_decode($m[1], true);
if ($bin === false) {
    fwrite(STDERR, "base64 decode failed\n");
    exit(3);
}

if (!is_dir(dirname($out))) {
    mkdir(dirname($out), 0755, true);
}
file_put_contents($out, $bin);

printf("wrote %s (%d bytes)\n", $out, strlen($bin));
printf("caption: %s\n", trim(strip_tags($m[2])));
