<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Splits {@see base_path('CANONICAL_SCHEMA.sql')} into executable statements.
 *
 * Handles `--` line comments so semicolons inside comments do not terminate statements.
 */
final class CanonicalSchemaSql
{
    /**
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
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
