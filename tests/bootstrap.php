<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap: Composer autoload + legacy helpers only.
 *
 * Database connectivity and schema setup run inside {@see Tests\TestCase} after the
 * Laravel application is bootstrapped via {@see Tests\CreatesApplication}.
 */
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/Http/Foundation/global_helpers.php';
