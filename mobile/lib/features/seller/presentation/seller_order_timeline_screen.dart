import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerOrderTimelineScreen extends ConsumerWidget {
  const SellerOrderTimelineScreen({super.key, required this.orderId});
  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final order = ref.read(sellerOrdersProvider.notifier).byId(orderId);
    if (order == null) return const Scaffold(body: Center(child: Text('Order not found')));
    final steps = <({String title, String subtitle, bool done})>[
      (title: 'Order Placed', subtitle: 'The buyer placed the order.', done: true),
      (title: 'Paid', subtitle: 'Payment received and held in escrow.', done: true),
      (title: 'Processing', subtitle: 'You have started processing the order.', done: order.status != SellerOrderStatus.toShip),
      (title: 'Shipped', subtitle: 'Order shipped via ${order.courierCompany ?? '-'}\nTracking ID: ${order.trackingId ?? '-'}', done: order.status == SellerOrderStatus.shipped || order.status == SellerOrderStatus.delivered),
      (title: 'Delivered', subtitle: 'Waiting for buyer to confirm delivery.', done: order.status == SellerOrderStatus.delivered),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('Order Timeline')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Row(children: <Widget>[
            Expanded(child: Text(order.orderNumber, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900))),
            Chip(label: Text(order.status.label)),
          ]),
          const SizedBox(height: 10),
          ...List<Widget>.generate(steps.length, (i) {
            final item = steps[i];
            final showLine = i < steps.length - 1;
            return Row(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
              SizedBox(
                width: 28,
                child: Column(
                  children: <Widget>[
                    Icon(item.done ? Icons.check_circle_rounded : Icons.radio_button_unchecked, color: item.done ? const Color(0xFF16A34A) : const Color(0xFF94A3B8)),
                    if (showLine) Container(width: 2, height: 42, color: const Color(0xFFDCFCE7)),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
                    Text(item.title, style: const TextStyle(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    Text(item.subtitle, style: const TextStyle(color: kSellerMuted)),
                  ]),
                ),
              ),
            ]);
          }),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: const Color(0xFFF5F3FF), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE9D5FF))),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
              const Text('Need Help?', style: TextStyle(fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              const Text('Contact support if you face any issue with this order.'),
              const SizedBox(height: 8),
              OutlinedButton(onPressed: () {}, child: const Text('Contact Support')),
            ]),
          ),
        ],
      ),
    );
  }
}
