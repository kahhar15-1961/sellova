import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_ui.dart';

class SellerOrderDetailScreen extends ConsumerWidget {
  const SellerOrderDetailScreen({super.key, required this.orderId});
  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final order = ref.watch(sellerOrdersProvider.notifier).byId(orderId);
    if (order == null) return const Scaffold(body: Center(child: Text('Order not found')));
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(title: const Text('Order Details')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Row(children: <Widget>[
            Expanded(child: Text(order.orderNumber, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900))),
            _status(order.status),
          ]),
          const SizedBox(height: 10),
          _card(context, Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
            _r('Customer', order.customerName),
            _r('Order Date', sellerNiceDate(order.orderDate)),
            _r('Payment Method', order.paymentMethod),
            _r('Total Amount', order.totalLabel),
          ])),
          const SizedBox(height: 10),
          _card(context, Row(children: <Widget>[
            const CircleAvatar(child: Icon(Icons.headphones)),
            const SizedBox(width: 10),
            Expanded(child: Text(order.productTitle, style: const TextStyle(fontWeight: FontWeight.w700))),
            Text(order.totalLabel, style: const TextStyle(fontWeight: FontWeight.w900)),
          ])),
          const SizedBox(height: 10),
          _card(context, Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
            const Text('Shipping Address', style: TextStyle(fontWeight: FontWeight.w900)),
            const SizedBox(height: 6),
            Text(order.shippingAddress),
            if (order.trackingId != null) ...<Widget>[
              const SizedBox(height: 10),
              Row(children: <Widget>[
                const Text('Tracking Number', style: TextStyle(fontWeight: FontWeight.w800)),
                const Spacer(),
                Text(order.trackingId!, style: const TextStyle(fontWeight: FontWeight.w800)),
                TextButton(
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: order.trackingId!));
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Tracking ID copied')));
                  },
                  child: const Text('Copy'),
                ),
              ]),
            ],
            if (order.deliveredOn != null) _r('Delivered On', sellerNiceDate(order.deliveredOn!)),
          ])),
          const SizedBox(height: 14),
          OutlinedButton(
            onPressed: () {},
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
            child: const Text('Print Invoice'),
          ),
          const SizedBox(height: 8),
          if (order.status == SellerOrderStatus.toShip)
            FilledButton(
              onPressed: () => context.push('/seller/orders/$orderId/update-status'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(50)),
              child: const Text('Mark as Shipped'),
            ),
          if (order.status == SellerOrderStatus.shipped)
            OutlinedButton(
              onPressed: () async {
                final confirm = await showDialog<bool>(
                  context: context,
                  builder: (context) => AlertDialog(
                    title: const Text('Mark as delivered?'),
                    content: const Text('Only do this after confirming delivery handoff to the customer.'),
                    actions: <Widget>[
                      TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('No')),
                      FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Yes, confirm')),
                    ],
                  ),
                );
                if (confirm != true) return;
                try {
                  await ref.read(sellerOrdersProvider.notifier).updateStatus(orderId, SellerOrderStatus.delivered);
                  if (context.mounted) {
                    showSellerSuccessToast(context, 'Order marked as delivered.');
                  }
                } catch (_) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to update status. Please retry.')));
                  }
                }
              },
              style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
              child: const Text('Mark as Delivered'),
            ),
          if (order.status == SellerOrderStatus.delivered)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: const Color(0xFFECFDF5), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFBBF7D0))),
              child: const Text('Payment has been released to your available balance.', style: TextStyle(color: Color(0xFF166534), fontWeight: FontWeight.w700)),
            ),
          const SizedBox(height: 8),
          FilledButton.tonal(
            onPressed: () => context.push('/seller/orders/$orderId/chat'),
            style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(50)),
            child: const Text('Chat with customer'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () => context.push('/seller/orders/$orderId/timeline'),
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
            child: const Text('View Order Timeline'),
          ),
        ],
      ),
    );
  }

  Widget _card(BuildContext context, Widget child) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: sellerCardDecoration(Theme.of(context).colorScheme),
        child: child,
      );
  Widget _r(String k, String v) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(children: <Widget>[Expanded(child: Text(k, style: const TextStyle(color: kSellerMuted))), Text(v, style: const TextStyle(fontWeight: FontWeight.w700))]),
      );
  Widget _status(SellerOrderStatus status) {
    final (bg, fg) = switch (status) {
      SellerOrderStatus.toShip => (const Color(0xFFFFF7ED), const Color(0xFFEA580C)),
      SellerOrderStatus.processing => (const Color(0xFFEFF6FF), const Color(0xFF1D4ED8)),
      SellerOrderStatus.shipped => (const Color(0xFFEFF6FF), const Color(0xFF2563EB)),
      SellerOrderStatus.delivered => (const Color(0xFFECFDF5), const Color(0xFF16A34A)),
      SellerOrderStatus.cancelled => (const Color(0xFFF1F5F9), const Color(0xFF475569)),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(status.label, style: TextStyle(color: fg, fontWeight: FontWeight.w800)),
    );
  }
}
