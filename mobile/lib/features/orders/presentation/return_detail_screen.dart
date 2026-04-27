import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/returns_repository.dart';

class ReturnDetailScreen extends ConsumerStatefulWidget {
  const ReturnDetailScreen({super.key, required this.returnId});

  final int returnId;

  @override
  ConsumerState<ReturnDetailScreen> createState() => _ReturnDetailScreenState();
}

class _ReturnDetailScreenState extends ConsumerState<ReturnDetailScreen> {
  late Future<ReturnRequestDto> _future = _load();

  Future<ReturnRequestDto> _load() =>
      ref.read(returnsRepositoryProvider).getReturnDetail(widget.returnId);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Return #${widget.returnId}')),
      body: FutureBuilder<ReturnRequestDto>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return Center(
                child: Text('Failed to load return: ${snapshot.error}'));
          }
          final data = snapshot.data!;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: <Widget>[
              Card(
                child: ListTile(
                  title: Text(
                      'Status: ${data.status.toUpperCase()} • ${data.slaStatus}'),
                  subtitle: Text(
                    'RMA: ${data.rmaCode ?? '-'}\n'
                    'Reverse logistics: ${data.reverseLogisticsStatus}\n'
                    'Refund: ${data.refundStatus}${data.refundAmount != null ? ' (${data.refundAmount})' : ''}\n'
                    '${data.notes ?? 'No notes'}',
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  if (data.status == 'approved')
                    OutlinedButton(
                      onPressed: () async {
                        await ref
                            .read(returnsRepositoryProvider)
                            .markShippedBack(
                              returnId: widget.returnId,
                              trackingUrl: data.returnTrackingUrl,
                              carrier: data.returnCarrier,
                            );
                        if (!mounted) return;
                        setState(() => _future = _load());
                      },
                      child: const Text('I shipped item back'),
                    ),
                  if (data.status == 'approved' || data.status == 'escalated')
                    OutlinedButton(
                      onPressed: () async {
                        await ref.read(returnsRepositoryProvider).submitRefund(
                              widget.returnId,
                              amount: data.refundAmount,
                            );
                        if (!mounted) return;
                        setState(() => _future = _load());
                      },
                      child: const Text('Submit refund'),
                    ),
                  if (data.refundStatus == 'submitted')
                    FilledButton.tonal(
                      onPressed: () async {
                        await ref
                            .read(returnsRepositoryProvider)
                            .confirmRefund(widget.returnId);
                        if (!mounted) return;
                        setState(() => _future = _load());
                      },
                      child: const Text('Confirm refund'),
                    ),
                ],
              ),
              const SizedBox(height: 12),
              Text('Timeline',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              ...data.timeline.map(
                (event) => Card(
                  child: ListTile(
                    leading: const Icon(Icons.timelapse_rounded),
                    title: Text(_label(event.eventCode)),
                    subtitle: Text(event.createdAt ?? ''),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  String _label(String code) {
    switch (code) {
      case 'requested':
        return 'Return requested by buyer';
      case 'approved':
        return 'Return approved by seller';
      case 'rejected':
        return 'Return rejected by seller';
      case 'buyer_shipped_back':
        return 'Buyer marked return as shipped back';
      case 'seller_received_return':
        return 'Seller received returned item';
      case 'refund_submitted':
        return 'Refund submitted';
      case 'refund_confirmed':
        return 'Refund confirmed';
      case 'sla_auto_escalated':
        return 'SLA overdue and auto-escalated';
      default:
        return code;
    }
  }
}
