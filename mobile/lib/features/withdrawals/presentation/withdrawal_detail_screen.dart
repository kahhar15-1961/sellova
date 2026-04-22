import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/withdrawal_detail_provider.dart';
import '../data/withdrawal_repository.dart';

class WithdrawalDetailScreen extends ConsumerWidget {
  const WithdrawalDetailScreen({
    super.key,
    required this.withdrawalId,
  });

  final int withdrawalId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(withdrawalDetailProvider(withdrawalId));
    return Scaffold(
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _WithdrawalDetailError(
          message: error.toString(),
          onRetry: () => ref.refresh(withdrawalDetailProvider(withdrawalId)),
        ),
        data: (withdrawal) => _WithdrawalDetailContent(withdrawal: withdrawal),
      ),
    );
  }
}

class _WithdrawalDetailContent extends StatelessWidget {
  const _WithdrawalDetailContent({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    final timeline = withdrawal.timeline;

    return CustomScrollView(
      slivers: <Widget>[
        SliverAppBar(
          pinned: true,
          title: Text('Withdrawal #${withdrawal.id ?? 'unknown'}'),
        ),
        SliverPadding(
          padding: const EdgeInsets.all(16),
          sliver: SliverList.list(
            children: <Widget>[
              _SummaryCard(withdrawal: withdrawal),
              const SizedBox(height: 12),
              _AmountBreakdownCard(withdrawal: withdrawal),
              const SizedBox(height: 12),
              _StatusCard(withdrawal: withdrawal),
              const SizedBox(height: 12),
              _PayoutMethodCard(withdrawal: withdrawal),
              const SizedBox(height: 12),
              _ReviewerCard(withdrawal: withdrawal),
              const SizedBox(height: 16),
              Text('Timeline / history', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (timeline.isEmpty)
                const _EmptyInfo(text: 'No timeline data available.')
              else
                ...timeline.map((event) => _TimelineTile(event: event)),
            ],
          ),
        ),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Withdrawal summary', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text('Status: ${withdrawal.status}'),
            Text('Created: ${withdrawal.createdDateLabel}'),
          ],
        ),
      ),
    );
  }
}

class _AmountBreakdownCard extends StatelessWidget {
  const _AmountBreakdownCard({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Amount breakdown', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text('Amount: ${withdrawal.amountLabel}'),
            Text('Fee: ${withdrawal.feeLabel}'),
            Text('Net: ${withdrawal.netLabel}'),
          ],
        ),
      ),
    );
  }
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    final notes = (withdrawal.raw['notes'] ?? withdrawal.raw['reason'] ?? '').toString();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Current status', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text(withdrawal.status),
            if (notes.isNotEmpty) ...<Widget>[
              const SizedBox(height: 6),
              Text(notes),
            ],
          ],
        ),
      ),
    );
  }
}

class _PayoutMethodCard extends StatelessWidget {
  const _PayoutMethodCard({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Payout method', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text(withdrawal.payoutMethodLabel),
          ],
        ),
      ),
    );
  }
}

class _ReviewerCard extends StatelessWidget {
  const _ReviewerCard({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Reviewer / admin', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text(withdrawal.reviewerLabel),
          ],
        ),
      ),
    );
  }
}

class _TimelineTile extends StatelessWidget {
  const _TimelineTile({required this.event});

  final Map<String, dynamic> event;

  @override
  Widget build(BuildContext context) {
    final status = (event['status'] ?? event['state'] ?? 'state').toString();
    final at = (event['created_at'] ?? event['at'] ?? '').toString();
    final note = (event['note'] ?? event['reason'] ?? '').toString();
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: const Icon(Icons.timeline),
        title: Text(status),
        subtitle: Text(note.isEmpty ? at : '$at\n$note'),
      ),
    );
  }
}

class _EmptyInfo extends StatelessWidget {
  const _EmptyInfo({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest.withOpacity(0.35),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(text),
    );
  }
}

class _WithdrawalDetailError extends StatelessWidget {
  const _WithdrawalDetailError({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.error_outline, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
