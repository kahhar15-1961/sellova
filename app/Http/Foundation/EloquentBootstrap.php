<?php

declare(strict_types=1);

namespace App\Http\Foundation;

use Dotenv\Dotenv;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

/**
 * Boots Eloquent for the HTTP entrypoint (mirrors {@see Tests\TestBootstrap}).
 *
 * Connection env (first match): {@code DB_*}, then {@code TEST_DB_*}.
 *
 * Registers the {@code db} manager on a minimal container and sets the facade
 * application so {@code Illuminate\Support\Facades\DB::transaction()} works
 * in services (Auth, Order, Wallet, etc.).
 */
final class EloquentBootstrap
{
    public static function bootFromEnvironment(): bool
    {
        self::loadEnvironmentFileIfPresent();

        $driver = self::env('DB_CONNECTION') ?? self::env('TEST_DB_CONNECTION') ?? 'mysql';
        if ($driver !== 'mysql') {
            return false;
        }

        $host = self::env('DB_HOST') ?? self::env('TEST_DB_HOST');
        $database = self::env('DB_DATABASE') ?? self::env('TEST_DB_DATABASE');
        $username = self::env('DB_USERNAME') ?? self::env('TEST_DB_USERNAME');
        $port = self::env('DB_PORT') ?? self::env('TEST_DB_PORT') ?? '3306';
        $password = self::env('DB_PASSWORD') ?? self::env('TEST_DB_PASSWORD') ?? '';

        if ($host === null || $database === null || $username === null) {
            return false;
        }

        $app = new Container;

        $capsule = new Capsule;
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
        $app->singleton('files', static fn (): Filesystem => new Filesystem);
        $app->bind('db.schema', static function () use ($capsule) {
            return $capsule->getConnection()->getSchemaBuilder();
        });
        Facade::setFacadeApplication($app);

        return true;
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * CLI scripts do not load .env automatically; mirror {@code artisan} behaviour.
     */
    private static function loadEnvironmentFileIfPresent(): void
    {
        $root = self::projectRoot();
        $path = $root.DIRECTORY_SEPARATOR.'.env';
        if (! is_file($path) || ! class_exists(Dotenv::class)) {
            return;
        }

        Dotenv::createImmutable($root)->safeLoad();
    }

    /**
     * Reads a variable from the environment (Dotenv populates {@code $_ENV} / {@code $_SERVER}).
     */
    private static function env(string $key): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            $v = $_ENV[$key];

            return $v === null ? null : (string) $v;
        }
        if (array_key_exists($key, $_SERVER)) {
            $v = $_SERVER[$key];

            return $v === null ? null : (string) $v;
        }
        $g = getenv($key);

        return $g === false ? null : (string) $g;
    }
}
