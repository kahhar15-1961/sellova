import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../disputes/application/dispute_detail_provider.dart';
import '../../disputes/data/dispute_repository.dart';
import 'seller_ui.dart';

class SellerDisputeDetailScreen extends ConsumerWidget {
  const SellerDisputeDetailScreen({super.key, required this.disputeId});
  final int disputeId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(disputeDetailProvider(disputeId));
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Dispute Details'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, _) => Center(child: Padding(padding: const EdgeInsets.all(24), child: Text('$e'))),
        data: (DisputeDto d) {
          final resolved = d.status.toLowerCase().contains('resolv') || d.status.toLowerCase().contains('close');
          return Column(
            children: <Widget>[
              Expanded(child: _DetailScroll(dispute: d)),
              if (!resolved)
                SafeArea(
                  minimum: const EdgeInsets.fromLTRB(20, 0, 20, 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      FilledButton(
                        onPressed: () => context.push('/seller/disputes/$disputeId/respond'),
                        style: FilledButton.styleFrom(backgroundColor: kSellerAccent, minimumSize: const Size.fromHeight(52)),
                        child: const Text('Respond to dispute'),
                      ),
                      const SizedBox(height: 8),
                      OutlinedButton(
                        onPressed: () {
                          final oid = d.orderId;
                          if (oid != null) context.push('/seller/orders/$oid/chat');
                        },
                        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                        child: const Text('Chat with customer'),
                      ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _DetailScroll extends StatelessWidget {
  const _DetailScroll({required this.dispute});
  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    final idLabel = 'OSP-2025-${(dispute.id ?? 0).toString().padLeft(6, '0')}';
    final orderNo = dispute.orderId != null ? 'ORD-2025-${(dispute.orderId!).toString().padLeft(6, '0')}' : '—';
    final customer = (dispute.raw['customer_name'] ?? dispute.raw['buyer_name'] ?? 'Riad Hossain').toString();
    final issue = dispute.summary;
    final desc = (dispute.raw['description'] ?? dispute.raw['details'] ?? 'The product quality is not good as shown in the description.').toString();
    final openStyle = dispute.status.toLowerCase().contains('open') || dispute.status.toLowerCase().contains('new');

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      children: <Widget>[
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: Text(
                'Dispute #$idLabel',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: const Color(0xFF2D3748)),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: openStyle ? const Color(0xFFFFEDD5) : const Color(0xFFE5E7EB),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                openStyle ? 'Open' : dispute.status,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: openStyle ? const Color(0xFFDC2626) : kSellerMuted,
                  fontSize: 12,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 20),
        _kv('Order', orderNo),
        _kv('Customer', customer),
        const Divider(height: 32),
        Text('Issue', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 6),
        Text(issue, style: Theme.of(context).textTheme.bodyLarge),
        const SizedBox(height: 18),
        Text('Description', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 6),
        Text(desc, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kSellerMuted, height: 1.45)),
        const SizedBox(height: 18),
        Text('Attachments', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        SizedBox(
          height: 88,
          child: dispute.evidence.isEmpty
              ? Text('No attachments', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted))
              : ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: dispute.evidence.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 10),
                  itemBuilder: (BuildContext context, int i) {
                    final item = dispute.evidence[i];
                    final path = (item['storage_path'] ?? item['url'] ?? item['file_url'] ?? '').toString();
                    return ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: AspectRatio(
                        aspectRatio: 1,
                        child: path.isNotEmpty
                            ? Image.network(path, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _ph())
                            : _ph(),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _ph() {
    return const ColoredBox(
      color: Color(0xFFF3F4F6),
      child: Icon(Icons.image_outlined, size: 40),
    );
  }

  Widget _kv(String k, String v) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(width: 100, child: Text(k, style: const TextStyle(color: kSellerMuted, fontWeight: FontWeight.w600))),
          Expanded(child: Text(v, style: const TextStyle(fontWeight: FontWeight.w700))),
        ],
      ),
    );
  }
}
