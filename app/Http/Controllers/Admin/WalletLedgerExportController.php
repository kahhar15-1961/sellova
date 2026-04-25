<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WalletLedgerExportController
{
    public function __invoke(Request $request, Wallet $wallet): StreamedResponse
    {
        $limit = max(1, min(100000, (int) $request->query('limit', 5000)));

        $builder = WalletLedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->limit($limit);

        return response()->streamDownload(function () use ($builder): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['id', 'wallet_id', 'occurred_at', 'entry_side', 'entry_type', 'amount', 'currency', 'running_balance_after', 'reference_type', 'reference_id', 'description']);
            foreach ($builder->cursor() as $e) {
                fputcsv($out, [
                    $e->id,
                    $e->wallet_id,
                    $e->occurred_at?->toIso8601String(),
                    $e->entry_side->value,
                    $e->entry_type->value,
                    (string) $e->amount,
                    $e->currency,
                    (string) $e->running_balance_after,
                    $e->reference_type,
                    $e->reference_id,
                    $e->description,
                ]);
            }
            fclose($out);
        }, 'wallet-'.$wallet->id.'-ledger.csv', ['Content-Type' => 'text/csv']);
    }
}
