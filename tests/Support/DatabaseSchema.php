<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

final class DatabaseSchema
{
    public static function applyCanonicalSchema(): void
    {
        $sql = file_get_contents(__DIR__.'/../../CANONICAL_SCHEMA.sql');
        if ($sql === false) {
            throw new \RuntimeException('Failed to read CANONICAL_SCHEMA.sql');
        }

        foreach (self::splitSqlStatements($sql) as $statement) {
            \Illuminate\Support\Facades\DB::unprepared($statement);
        }
    }

    /**
     * Borrowed conceptually from the migration's statement splitter.
     *
     * @return list<string>
     */
    private static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';

            if ($ch === "'" && $prev !== '\\' && ! $inDouble) {
                $inSingle = ! $inSingle;
            } elseif ($ch === '"' && $prev !== '\\' && ! $inSingle) {
                $inDouble = ! $inDouble;
            }

            $buffer .= $ch;
            if ($ch === ';' && ! $inSingle && ! $inDouble) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return array_values(array_filter($statements, static function (string $stmt): bool {
            $clean = ltrim($stmt);
            return $clean !== '' && ! str_starts_with($clean, '--');
        }));
    }
}

