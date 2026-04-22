import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/dispute_detail_provider.dart';
import '../data/dispute_repository.dart';

class DisputeDetailScreen extends ConsumerWidget {
  const DisputeDetailScreen({
    super.key,
    required this.disputeId,
  });

  final int disputeId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(disputeDetailProvider(disputeId));
    return Scaffold(
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _DisputeDetailError(
          message: error.toString(),
          onRetry: () => ref.refresh(disputeDetailProvider(disputeId)),
        ),
        data: (dispute) => _DisputeDetailContent(dispute: dispute),
      ),
    );
  }
}

class _DisputeDetailContent extends StatelessWidget {
  const _DisputeDetailContent({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    final timeline = dispute.timeline;
    final evidence = dispute.evidence;
    final isResolved = dispute.status.toLowerCase().contains('resolved');

    return CustomScrollView(
      slivers: <Widget>[
        SliverAppBar(
          pinned: true,
          title: Text('Dispute #${dispute.id ?? 'unknown'}'),
        ),
        SliverPadding(
          padding: const EdgeInsets.all(16),
          sliver: SliverList.list(
            children: <Widget>[
              _SummaryCard(dispute: dispute),
              const SizedBox(height: 12),
              _RelatedOrderCard(dispute: dispute),
              const SizedBox(height: 12),
              _StatusCard(dispute: dispute),
              const SizedBox(height: 16),
              Text('Status timeline', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (timeline.isEmpty)
                const _EmptyInfo(text: 'No timeline data available.')
              else
                ...timeline.map((event) => _TimelineTile(event: event)),
              const SizedBox(height: 16),
              Text('Evidence', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (evidence.isEmpty)
                const _EmptyInfo(text: 'No evidence available.')
              else
                ...evidence.map((item) => _EvidenceTile(item: item)),
              if (isResolved) ...<Widget>[
                const SizedBox(height: 16),
                _OutcomeCard(dispute: dispute),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Dispute summary', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text('Status: ${dispute.status}'),
            Text('Created: ${dispute.createdDateLabel}'),
            const SizedBox(height: 8),
            Text(dispute.summary),
          ],
        ),
      ),
    );
  }
}

class _RelatedOrderCard extends StatelessWidget {
  const _RelatedOrderCard({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Related order', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text('Order ID: #${dispute.orderId ?? 'unknown'}'),
          ],
        ),
      ),
    );
  }
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    final decision = (dispute.raw['decision'] ?? dispute.raw['resolution'] ?? '').toString();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Current status', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text(dispute.status),
            if (decision.isNotEmpty) ...<Widget>[
              const SizedBox(height: 6),
              Text('Decision: $decision'),
            ],
          ],
        ),
      ),
    );
  }
}

class _OutcomeCard extends StatelessWidget {
  const _OutcomeCard({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Final outcome', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text(dispute.outcome),
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

class _EvidenceTile extends StatelessWidget {
  const _EvidenceTile({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final type = (item['evidence_type'] ?? item['type'] ?? 'evidence').toString();
    final content = (item['content_text'] ?? item['description'] ?? '').toString();
    final path = (item['storage_path'] ?? item['url'] ?? '').toString();
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: const Icon(Icons.attachment_outlined),
        title: Text(type),
        subtitle: Text(
          content.isEmpty ? (path.isEmpty ? 'No details provided' : path) : content,
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
        ),
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

class _DisputeDetailError extends StatelessWidget {
  const _DisputeDetailError({
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
