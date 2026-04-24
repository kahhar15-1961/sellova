import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_business_controller.dart';
import 'seller_ui.dart';

class SellerNotificationsScreen extends ConsumerStatefulWidget {
  const SellerNotificationsScreen({super.key});

  @override
  ConsumerState<SellerNotificationsScreen> createState() => _SellerNotificationsScreenState();
}

class _SellerNotificationsScreenState extends ConsumerState<SellerNotificationsScreen> {

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerBusinessControllerProvider);
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: const Text('Notifications'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
        actions: <Widget>[
          TextButton(
            onPressed: () async {
              await ref.read(sellerBusinessControllerProvider.notifier).markAllNotificationsRead();
              if (!mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('All notifications marked as read.')));
            },
            child: const Text('Mark all as read', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ],
      ),
      body: state.notifications.isEmpty
          ? Center(
              child: Text(
                state.isLoading ? 'Loading notifications...' : 'No notifications yet.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kSellerMuted),
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: state.notifications.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (BuildContext context, int i) {
                final n = state.notifications[i];
                final icon = _iconForKind(n.kind);
                final tint = _tintForKind(n.kind);
                return Material(
                  color: n.read ? Colors.white : const Color(0xFFF3F0FF),
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () => ref.read(sellerBusinessControllerProvider.notifier).markNotificationRead(n.id),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(color: tint, borderRadius: BorderRadius.circular(12)),
                            child: Icon(icon, color: kSellerAccent),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(n.title, style: const TextStyle(fontWeight: FontWeight.w800)),
                                const SizedBox(height: 4),
                                Text(n.body, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted, height: 1.35)),
                              ],
                            ),
                          ),
                          Text(n.timeAgoLabel, style: Theme.of(context).textTheme.labelSmall?.copyWith(color: kSellerMuted)),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
    );
  }

  IconData _iconForKind(String kind) {
    final k = kind.toLowerCase();
    if (k.contains('order')) return Icons.shopping_bag_outlined;
    if (k.contains('ship')) return Icons.local_shipping_outlined;
    if (k.contains('review')) return Icons.star_outline_rounded;
    if (k.contains('withdraw')) return Icons.account_balance_wallet_outlined;
    return Icons.notifications_outlined;
  }

  Color _tintForKind(String kind) {
    final k = kind.toLowerCase();
    if (k.contains('order')) return const Color(0xFFEDE9FE);
    if (k.contains('ship')) return const Color(0xFFE0F2FE);
    if (k.contains('review')) return const Color(0xFFFFF7ED);
    if (k.contains('withdraw')) return const Color(0xFFECFDF5);
    return const Color(0xFFF1F5F9);
  }
}
