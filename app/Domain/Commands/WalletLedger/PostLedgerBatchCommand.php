<?php

namespace App\Domain\Commands\WalletLedger;

use App\Domain\Enums\LedgerPostingEventName;
use App\Domain\Value\LedgerPostingLine;

/**
 * Input contract for {@see \App\Services\WalletLedger\WalletLedgerService::postLedgerBatch}.
 *
 * @phpstan-type LineList list<LedgerPostingLine>
 */
final readonly class PostLedgerBatchCommand
{
    /**
     * @param  list<LedgerPostingLine>  $entries
     */
    public function __construct(
        public LedgerPostingEventName $eventName,
        public string $referenceType,
        public int $referenceId,
        public string $idempotencyKey,
        public array $entries,
    ) {
    }

    public static function fromLines(
        LedgerPostingEventName $eventName,
        string $referenceType,
        int $referenceId,
        string $idempotencyKey,
        LedgerPostingLine ...$entries,
    ): self {
        return new self($eventName, $referenceType, $referenceId, $idempotencyKey, array_values($entries));
    }
}
