import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';

class SellerUpdateOrderStatusScreen extends ConsumerStatefulWidget {
  const SellerUpdateOrderStatusScreen({super.key, required this.orderId});
  final int orderId;

  @override
  ConsumerState<SellerUpdateOrderStatusScreen> createState() => _SellerUpdateOrderStatusScreenState();
}

class _SellerUpdateOrderStatusScreenState extends ConsumerState<SellerUpdateOrderStatusScreen> {
  SellerOrderStatus _selected = SellerOrderStatus.shipped;

  @override
  Widget build(BuildContext context) {
    final order = ref.read(sellerOrdersProvider.notifier).byId(widget.orderId);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    if (order == null) return const Scaffold(body: Center(child: Text('Order not found')));
    return Scaffold(
      appBar: AppBar(title: const Text('Update Order Status')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Text(error, style: const TextStyle(color: Color(0xFF9F1239))),
            ),
          Text(order.orderNumber, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 16),
          const Text('Select new status', style: TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 10),
          ...<SellerOrderStatus>[SellerOrderStatus.processing, SellerOrderStatus.shipped, SellerOrderStatus.delivered, SellerOrderStatus.cancelled].map(
            (s) => _StatusTile(
              selected: _selected == s,
              title: s.label,
              subtitle: switch (s) {
                SellerOrderStatus.processing => 'Order is being prepared',
                SellerOrderStatus.shipped => 'Order has been shipped',
                SellerOrderStatus.delivered => 'Order delivered to buyer',
                SellerOrderStatus.cancelled => 'Cancel this order',
                SellerOrderStatus.toShip => 'Awaiting processing',
              },
              onTap: () => setState(() => _selected = s),
            ),
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: busy
                ? null
                : () async {
              if (_selected == SellerOrderStatus.cancelled) {
                final confirm = await showDialog<bool>(
                  context: context,
                  builder: (context) => AlertDialog(
                    title: const Text('Cancel this order?'),
                    content: const Text('This is a sensitive action and may affect customer trust. Do you want to continue?'),
                    actions: <Widget>[
                      TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('No')),
                      FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Yes, cancel')),
                    ],
                  ),
                );
                if (confirm != true) return;
              }
              if (_selected == SellerOrderStatus.shipped) {
                context.push('/seller/orders/${widget.orderId}/shipping');
                return;
              }
              try {
                await ref.read(sellerOrdersProvider.notifier).updateStatus(widget.orderId, _selected);
                if (context.mounted) {
                  showSellerSuccessToast(context, 'Order status updated successfully.');
                  context.pop();
                }
              } catch (_) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to update status.')));
                }
              }
            },
            child: const Text('Continue'),
          ),
          if (busy) const Padding(padding: EdgeInsets.only(top: 10), child: LinearProgressIndicator()),
        ],
      ),
    );
  }
}

class _StatusTile extends StatelessWidget {
  const _StatusTile({required this.selected, required this.title, required this.subtitle, required this.onTap});
  final bool selected;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: selected ? Theme.of(context).colorScheme.primary : Theme.of(context).colorScheme.outlineVariant),
      ),
      child: ListTile(
        onTap: onTap,
        leading: Icon(selected ? Icons.radio_button_checked : Icons.radio_button_off),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        subtitle: Text(subtitle),
      ),
    );
  }
}
