<?php

declare(strict_types=1);

/**
 * Standalone HTTP entrypoint has no full Laravel app bootstrap. Domain services
 * still call the global {@code now()} helper (see {@see tests/bootstrap.php} for PHPUnit).
 *
 * Composer does not register {@code \now()}; Symfony only provides
 * {@see \Symfony\Component\Clock\now()} in its own namespace.
 */
if (! function_exists('base_path')) {
    /**
     * Project root (this file: app/Http/Foundation → three levels up).
     */
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__, 3);
        if ($path === '') {
            return $root;
        }

        return $root.DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}

if (! function_exists('now')) {
    /**
     * @param  \DateTimeZone|string|int|null  $tz
     */
    function now($tz = null): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::now($tz);
    }
}
