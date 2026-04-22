import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/order_detail_provider.dart';
import '../data/order_repository.dart';

class OrderDetailScreen extends ConsumerWidget {
  const OrderDetailScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(orderDetailProvider(orderId));
    return Scaffold(
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _OrderDetailError(
          message: error.toString(),
          onRetry: () => ref.refresh(orderDetailProvider(orderId)),
        ),
        data: (order) => _OrderDetailContent(order: order),
      ),
    );
  }
}

class _OrderDetailContent extends StatelessWidget {
  const _OrderDetailContent({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final timeline = order.timeline;
    final items = order.items;

    return CustomScrollView(
      slivers: <Widget>[
        SliverAppBar(
          pinned: true,
          title: Text(order.orderNumber),
        ),
        SliverPadding(
          padding: const EdgeInsets.all(16),
          sliver: SliverList.list(
            children: <Widget>[
              _SummaryCard(order: order),
              const SizedBox(height: 12),
              _TotalsCard(order: order),
              const SizedBox(height: 12),
              _StatusCard(order: order),
              const SizedBox(height: 12),
              _SellerCard(order: order),
              const SizedBox(height: 16),
              Text('Items', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (items.isEmpty)
                const _EmptyInfo(text: 'No item details available.')
              else
                ...items.map((item) => _OrderItemTile(item: item)),
              const SizedBox(height: 16),
              Text('Status timeline', style: Theme.of(context).textTheme.titleMedium),
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
  const _SummaryCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Order summary', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text('Status: ${order.status}'),
            Text('Created: ${order.createdDateLabel}'),
            Text('Items: ${order.itemSummary}'),
          ],
        ),
      ),
    );
  }
}

class _TotalsCard extends StatelessWidget {
  const _TotalsCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final net = order.raw['net_amount'];
    final gross = order.raw['gross_amount'];
    final fee = order.raw['fee_amount'] ?? order.raw['platform_fee'];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Total breakdown', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text('Total: ${order.totalLabel}'),
            if (gross != null) Text('Gross: $gross'),
            if (net != null) Text('Net: $net'),
            if (fee != null) Text('Fee: $fee'),
          ],
        ),
      ),
    );
  }
}

class _StatusCard extends StatelessWidget {
  const _StatusCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Escrow / payment', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text('Payment: ${order.paymentStatus}'),
            Text('Escrow: ${order.escrowStatus}'),
          ],
        ),
      ),
    );
  }
}

class _SellerCard extends StatelessWidget {
  const _SellerCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Seller / store', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            Text(order.sellerLabel),
          ],
        ),
      ),
    );
  }
}

class _OrderItemTile extends StatelessWidget {
  const _OrderItemTile({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final title = (item['title'] ?? item['name'] ?? item['product_name'] ?? 'Untitled item').toString();
    final quantity = (item['quantity'] ?? item['qty'] ?? '-').toString();
    final price = (item['unit_price'] ?? item['price'] ?? '').toString();
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text(title),
        subtitle: Text('Qty: $quantity'),
        trailing: Text(price),
      ),
    );
  }
}

class _TimelineTile extends StatelessWidget {
  const _TimelineTile({required this.event});

  final Map<String, dynamic> event;

  @override
  Widget build(BuildContext context) {
    final state = (event['state'] ?? event['status'] ?? 'state').toString();
    final at = (event['created_at'] ?? event['at'] ?? '').toString();
    final note = (event['note'] ?? event['reason'] ?? '').toString();
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: const Icon(Icons.timeline),
        title: Text(state),
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

class _OrderDetailError extends StatelessWidget {
  const _OrderDetailError({
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
