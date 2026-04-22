<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;

/**
 * Laravel helpers like {@code now()} live in the full framework; the domain package
 * still calls them from services. Provide a minimal global for PHPUnit runs.
 */
if (! function_exists('now')) {
    /**
     * @param  \DateTimeZone|string|int|null  $tz
     */
    function now($tz = null): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::now($tz);
    }
}

/**
 * Minimal Eloquent bootstrap for integration tests.
 *
 * These tests are designed to run against a real MySQL database because
 * `CANONICAL_SCHEMA.sql` uses MySQL-specific types (ENUM, CHECK, etc.).
 *
 * Resolution order for each setting: {@code TEST_DB_*} first, then {@code DB_*},
 * then safe defaults. PHPUnit sets {@code TEST_DB_*} in {@code phpunit.xml}; the
 * {@code DB_*} mirror matches {@see \App\Http\Foundation\EloquentBootstrap} conventions.
 */
final class TestBootstrap
{
    public static function bootEloquent(): void
    {
        $driver = self::envFirst(['TEST_DB_CONNECTION', 'DB_CONNECTION']) ?: 'mysql';
        if ($driver !== 'mysql') {
            define('TEST_DB_AVAILABLE', false);

            return;
        }

        $host = self::envFirst(['TEST_DB_HOST', 'DB_HOST']);
        $database = self::envFirst(['TEST_DB_DATABASE', 'DB_DATABASE']);
        $username = self::envFirst(['TEST_DB_USERNAME', 'DB_USERNAME']);
        $port = self::envFirst(['TEST_DB_PORT', 'DB_PORT']) ?: '3306';

        if ($host === null || $database === null || $username === null) {
            define('TEST_DB_AVAILABLE', false);

            return;
        }

        $password = self::password();

        $app = new Container();

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ], 'default');

        $capsule->setEventDispatcher(new Dispatcher($app));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $app->instance('db', $capsule->getDatabaseManager());
        Facade::setFacadeApplication($app);

        define('TEST_DB_AVAILABLE', true);
    }

    /**
     * @param  list<string>  $names
     */
    private static function envFirst(array $names): ?string
    {
        foreach ($names as $name) {
            $v = getenv($name);
            if ($v !== false && $v !== '') {
                return (string) $v;
            }
        }

        return null;
    }

    private static function password(): string
    {
        $testPw = getenv('TEST_DB_PASSWORD');
        if ($testPw !== false) {
            return (string) $testPw;
        }

        $dbPw = getenv('DB_PASSWORD');

        return $dbPw !== false ? (string) $dbPw : '';
    }
}

TestBootstrap::bootEloquent();
