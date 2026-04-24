import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import 'seller_feedback_widgets.dart';

class SellerAddShippingDetailsScreen extends ConsumerStatefulWidget {
  const SellerAddShippingDetailsScreen({super.key, required this.orderId});
  final int orderId;

  @override
  ConsumerState<SellerAddShippingDetailsScreen> createState() => _SellerAddShippingDetailsScreenState();
}

class _SellerAddShippingDetailsScreenState extends ConsumerState<SellerAddShippingDetailsScreen> {
  final TextEditingController _courier = TextEditingController(text: 'Pathao');
  final TextEditingController _tracking = TextEditingController(text: 'PT123456789BD');
  final TextEditingController _date = TextEditingController(text: '26 May 2025');
  final TextEditingController _note = TextEditingController(text: 'Package handed over to courier partner.');

  @override
  void dispose() {
    _courier.dispose();
    _tracking.dispose();
    _date.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final order = ref.read(sellerOrdersProvider.notifier).byId(widget.orderId);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    if (order == null) return const Scaffold(body: Center(child: Text('Order not found')));
    return Scaffold(
      appBar: AppBar(title: const Text('Add Shipping Details')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Text(error, style: const TextStyle(color: Color(0xFF9F1239))),
            ),
          Text(order.orderNumber, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 14),
          const Text('Courier Company'),
          const SizedBox(height: 6),
          TextField(controller: _courier, decoration: const InputDecoration(border: OutlineInputBorder())),
          const SizedBox(height: 12),
          const Text('Tracking ID'),
          const SizedBox(height: 6),
          TextField(controller: _tracking, decoration: const InputDecoration(border: OutlineInputBorder())),
          const SizedBox(height: 12),
          const Text('Shipping Date'),
          const SizedBox(height: 6),
          TextField(controller: _date, decoration: const InputDecoration(border: OutlineInputBorder())),
          const SizedBox(height: 12),
          const Text('Additional Note (Optional)'),
          const SizedBox(height: 6),
          TextField(controller: _note, maxLines: 4, maxLength: 200, decoration: const InputDecoration(border: OutlineInputBorder())),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: busy
                ? null
                : () async {
              try {
                await ref.read(sellerOrdersProvider.notifier).addShippingDetails(
                      orderId: widget.orderId,
                      courierCompany: _courier.text.trim(),
                      trackingId: _tracking.text.trim(),
                      shippingDate: DateTime.now(),
                      note: _note.text.trim(),
                    );
                if (context.mounted) {
                  showSellerSuccessToast(context, 'Shipping details saved and order marked as shipped.');
                  context.go('/seller/orders/${widget.orderId}');
                }
              } catch (_) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to save shipping details.')));
                }
              }
            },
            child: const Text('Mark as Shipped'),
          ),
          if (busy) const Padding(padding: EdgeInsets.only(top: 10), child: LinearProgressIndicator()),
        ],
      ),
    );
  }
}
