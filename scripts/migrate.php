<?php

declare(strict_types=1);

/**
 * Backward-compatible entrypoint: forwards all arguments to `php artisan …`.
 *
 * Examples:
 *   php scripts/migrate.php
 *   php scripts/migrate.php migrate --force
 *   php scripts/migrate.php migrate:status
 *
 * Canonical: `php artisan migrate` or `composer migrate`
 *
 * @see docs/MIGRATIONS.md
 */

$artisan = realpath(__DIR__.'/../artisan');
if ($artisan === false) {
    fwrite(STDERR, "artisan not found at project root.\n");
    exit(1);
}

$php = PHP_BINARY;
$forward = array_slice($argv, 1);
if ($forward === []) {
    $forward = ['migrate'];
}

$cmd = escapeshellarg($php).' '.escapeshellarg($artisan);
foreach ($forward as $arg) {
    $cmd .= ' '.escapeshellarg($arg);
}

passthru($cmd, $code);
exit($code);
