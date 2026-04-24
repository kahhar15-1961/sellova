<?php

declare(strict_types=1);

namespace App\Http\Foundation;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
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
        $driver = getenv('DB_CONNECTION') ?: getenv('TEST_DB_CONNECTION') ?: 'mysql';
        if ($driver !== 'mysql') {
            return false;
        }

        $host = getenv('DB_HOST') ?: getenv('TEST_DB_HOST') ?: null;
        $database = getenv('DB_DATABASE') ?: getenv('TEST_DB_DATABASE') ?: null;
        $username = getenv('DB_USERNAME') ?: getenv('TEST_DB_USERNAME') ?: null;
        $port = getenv('DB_PORT') ?: getenv('TEST_DB_PORT') ?: '3306';
        $password = getenv('DB_PASSWORD') ?: getenv('TEST_DB_PASSWORD') ?: '';

        if ($host === null || $database === null || $username === null) {
            return false;
        }

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

        return true;
    }
}
