<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\CanonicalSchemaSql;
use Illuminate\Support\Facades\DB;

final class DatabaseSchema
{
    public static function applyCanonicalSchema(): void
    {
        $sql = file_get_contents(__DIR__.'/../../CANONICAL_SCHEMA.sql');
        if ($sql === false) {
            throw new \RuntimeException('Failed to read CANONICAL_SCHEMA.sql');
        }

        foreach (CanonicalSchemaSql::splitStatements($sql) as $statement) {
            DB::connection()->unprepared($statement);
        }
    }
}
