import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerOrdersScreen extends ConsumerStatefulWidget {
  const SellerOrdersScreen({super.key});

  @override
  ConsumerState<SellerOrdersScreen> createState() => _SellerOrdersScreenState();
}

class _SellerOrdersScreenState extends ConsumerState<SellerOrdersScreen> {
  SellerOrderStatus? _tab;

  @override
  Widget build(BuildContext context) {
    final orders = ref.watch(sellerOrdersProvider);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    final filtered = _tab == null ? orders : orders.where((e) => e.status == _tab).toList();
    return SellerScaffold(
      selectedNavIndex: 1,
      appBar: AppBar(title: const Text('Orders')),
      body: Column(
        children: <Widget>[
          if (error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              child: SellerInlineFeedback(
                message: error,
                onRetry: () => ref.read(sellerOrdersProvider.notifier).refresh(),
              ),
            ),
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: <Widget>[
                _tabBtn('All', _tab == null, () => setState(() => _tab = null)),
                _tabBtn('To Ship', _tab == SellerOrderStatus.toShip, () => setState(() => _tab = SellerOrderStatus.toShip)),
                _tabBtn('Shipped', _tab == SellerOrderStatus.shipped, () => setState(() => _tab = SellerOrderStatus.shipped)),
                _tabBtn('Delivered', _tab == SellerOrderStatus.delivered, () => setState(() => _tab = SellerOrderStatus.delivered)),
                _tabBtn('Cancelled', _tab == SellerOrderStatus.cancelled, () => setState(() => _tab = SellerOrderStatus.cancelled)),
              ],
            ),
          ),
          Expanded(
            child: busy && orders.isEmpty
                ? const SellerListSkeleton()
                : filtered.isEmpty
                ? SellerEmptyState(
                    title: 'No seller orders yet',
                    subtitle: 'Orders will appear here as soon as buyers start purchasing your products.',
                    ctaLabel: 'Refresh',
                    onTap: () => ref.read(sellerOrdersProvider.notifier).refresh(),
                  )
                : RefreshIndicator(
                    onRefresh: () => ref.read(sellerOrdersProvider.notifier).refresh(),
                    child: ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
                      itemCount: filtered.length,
                      itemBuilder: (_, i) {
                        final order = filtered[i];
                        return Container(
                          margin: const EdgeInsets.only(bottom: 10),
                          decoration: sellerCardDecoration(Theme.of(context).colorScheme),
                          child: ListTile(
                            onTap: () => context.push('/seller/orders/${order.id}'),
                            leading: const CircleAvatar(child: Icon(Icons.shopping_bag_outlined)),
                            title: Text(order.orderNumber, style: const TextStyle(fontWeight: FontWeight.w800)),
                            subtitle: Text('${order.customerName}\n${sellerNiceDate(order.orderDate)}'),
                            isThreeLine: true,
                            trailing: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: <Widget>[
                                _statusPill(order.status),
                                const SizedBox(height: 6),
                                Text(order.totalLabel, style: const TextStyle(fontWeight: FontWeight.w900)),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
          ),
          if (busy) const LinearProgressIndicator(minHeight: 2),
        ],
      ),
    );
  }

  Widget _tabBtn(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(label: Text(label), selected: selected, onSelected: (_) => onTap()),
    );
  }

  Widget _statusPill(SellerOrderStatus status) {
    final (bg, fg) = switch (status) {
      SellerOrderStatus.toShip => (const Color(0xFFFFF7ED), const Color(0xFFEA580C)),
      SellerOrderStatus.processing => (const Color(0xFFEFF6FF), const Color(0xFF1D4ED8)),
      SellerOrderStatus.shipped => (const Color(0xFFEFF6FF), const Color(0xFF2563EB)),
      SellerOrderStatus.delivered => (const Color(0xFFECFDF5), const Color(0xFF16A34A)),
      SellerOrderStatus.cancelled => (const Color(0xFFF1F5F9), const Color(0xFF475569)),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(status.label, style: TextStyle(color: fg, fontWeight: FontWeight.w700, fontSize: 11)),
    );
  }
}
