<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Wallet;
use App\Models\WalletBalanceSnapshot;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WalletShowController extends AdminPageController
{
    public function __invoke(Request $request, Wallet $wallet): Response
    {
        $wallet->load(['user:id,email']);

        $entries = WalletLedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(static fn ($e): array => [
                'id' => $e->id,
                'side' => $e->entry_side->value,
                'type' => $e->entry_type->value,
                'amount' => (string) $e->amount,
                'running_balance_after' => (string) $e->running_balance_after,
                'reference' => ($e->reference_type ?? '—').' #'.((string) ($e->reference_id ?? '—')),
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'description' => $e->description,
            ]);

        $holds = WalletHold::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(static fn ($h): array => [
                'id' => $h->id,
                'status' => $h->status->value,
                'type' => $h->hold_type->value,
                'amount' => (string) $h->amount,
                'currency' => $h->currency,
                'reference' => ($h->reference_type ?? '—').' #'.((string) ($h->reference_id ?? '—')),
                'created_at' => $h->created_at?->toIso8601String(),
                'expires_at' => $h->expires_at?->toIso8601String(),
            ]);

        $snapshots = WalletBalanceSnapshot::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(static fn ($s): array => [
                'as_of' => $s->as_of?->toIso8601String(),
                'available_balance' => (string) $s->available_balance,
                'held_balance' => (string) $s->held_balance,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Wallets/Show', [
            'header' => $this->pageHeader(
                'Wallet #'.$wallet->id,
                'Ledger, holds, and balance snapshots for this wallet.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Wallets', 'href' => route('admin.wallets.index')],
                    ['label' => '#'.$wallet->id],
                ],
            ),
            'wallet' => [
                'id' => $wallet->id,
                'user_email' => $wallet->user?->email,
                'wallet_type' => $wallet->wallet_type->value,
                'currency' => $wallet->currency,
                'status' => $wallet->status->value,
                'version' => $wallet->version,
                'created_at' => $wallet->created_at?->toIso8601String(),
            ],
            'entries' => $entries,
            'holds' => $holds,
            'snapshots' => $snapshots,
            'list_href' => route('admin.wallets.index'),
            'export_url' => route('admin.wallets.ledger-export', $wallet),
        ]);
    }
}
