<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

/**
 * Minimal Eloquent bootstrap for integration tests.
 *
 * These tests are designed to run against a real MySQL database because
 * `CANONICAL_SCHEMA.sql` uses MySQL-specific types (ENUM, CHECK, etc.).
 */
final class TestBootstrap
{
    public static function bootEloquent(): void
    {
        $driver = getenv('TEST_DB_CONNECTION') ?: 'mysql';
        if ($driver !== 'mysql') {
            define('TEST_DB_AVAILABLE', false);
            return;
        }

        $host = getenv('TEST_DB_HOST') ?: null;
        $database = getenv('TEST_DB_DATABASE') ?: null;
        $username = getenv('TEST_DB_USERNAME') ?: null;
        $port = getenv('TEST_DB_PORT') ?: '3306';

        if ($host === null || $database === null || $username === null) {
            define('TEST_DB_AVAILABLE', false);
            return;
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => getenv('TEST_DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ], 'default');

        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        define('TEST_DB_AVAILABLE', true);
    }
}

TestBootstrap::bootEloquent();

