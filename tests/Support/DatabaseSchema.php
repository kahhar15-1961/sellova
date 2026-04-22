<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Capsule\Manager as Capsule;

final class DatabaseSchema
{
    public static function applyCanonicalSchema(): void
    {
        $sql = file_get_contents(__DIR__.'/../../CANONICAL_SCHEMA.sql');
        if ($sql === false) {
            throw new \RuntimeException('Failed to read CANONICAL_SCHEMA.sql');
        }

        foreach (self::splitSqlStatements($sql) as $statement) {
            Capsule::connection()->unprepared($statement);
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
        $inLineComment = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inLineComment) {
                $buffer .= $ch;
                if ($ch === "\n" || ($ch === "\r" && $next !== "\n")) {
                    $inLineComment = false;
                }

                continue;
            }

            if (! $inSingle && ! $inDouble && $ch === '-' && $next === '-') {
                $buffer .= '-';
                $buffer .= $next;
                $inLineComment = true;
                $i++;

                continue;
            }

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
            $trimmed = trim($stmt);
            if ($trimmed === '') {
                return false;
            }
            // Keep statements that contain real SQL even if they start with full-line `--` comments
            // (CANONICAL_SCHEMA groups comments immediately before some CREATE blocks).
            foreach (preg_split("/\R/u", $trimmed) as $line) {
                $t = trim($line);
                if ($t === '') {
                    continue;
                }
                if (! str_starts_with($t, '--')) {
                    return true;
                }
            }

            return false;
        }));
    }
}

