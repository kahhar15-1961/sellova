<?php

declare(strict_types=1);

/**
 * Run database migrations without Laravel `artisan` (this repo is not a full Laravel app).
 *
 * Requires MySQL and the same env as the API:
 *
 *   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=db_sellova DB_USERNAME=root DB_PASSWORD=secret
 *
 * From project root:
 *
 *   composer migrate
 *   php scripts/migrate.php
 */

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../app/Http/Foundation/global_helpers.php';

use App\Http\Foundation\EloquentBootstrap;
use Illuminate\Container\Container;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

if (! EloquentBootstrap::bootFromEnvironment()) {
    fwrite(STDERR, "Database bootstrap failed. Set DB_CONNECTION=mysql, DB_HOST, DB_DATABASE, DB_USERNAME (and DB_PASSWORD if needed).\n");
    exit(1);
}

/** @var Container $app */
$app = Facade::getFacadeApplication();
$db = $app['db'];

$repository = new DatabaseMigrationRepository($db, 'migrations');
if (! $repository->repositoryExists()) {
    $repository->createRepository();
}

$migrationsPath = realpath(__DIR__.'/../database/migrations');
if ($migrationsPath === false) {
    fwrite(STDERR, "Migrations directory not found.\n");
    exit(1);
}

$dispatcher = new Dispatcher($app);
$migrator = new Migrator($repository, $db, new Filesystem(), $dispatcher);

$ran = $migrator->run([$migrationsPath]);

if (count($ran) === 0) {
    echo "Nothing to migrate.\n";
} else {
    echo 'Ran '.count($ran)." migration(s):\n";
    foreach ($ran as $name) {
        echo "  - {$name}\n";
    }
}
