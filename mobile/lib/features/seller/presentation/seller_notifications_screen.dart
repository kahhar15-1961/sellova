import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_business_controller.dart';
import '../domain/seller_models.dart';
import 'seller_access_gate.dart';
import 'seller_ui.dart';

class SellerNotificationsScreen extends ConsumerStatefulWidget {
  const SellerNotificationsScreen({super.key});

  @override
  ConsumerState<SellerNotificationsScreen> createState() =>
      _SellerNotificationsScreenState();
}

class _SellerNotificationsScreenState
    extends ConsumerState<SellerNotificationsScreen> {
  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerBusinessControllerProvider);
    final controller = ref.read(sellerBusinessControllerProvider.notifier);
    final unreadCount = state.notifications.where((e) => !e.read).length;

    if (!state.sellerAccessChecked || state.isLoading) {
      return const Scaffold(
        backgroundColor: Color(0xFFF8F9FE),
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (state.errorMessage != null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8F9FE),
        appBar: AppBar(title: const Text('Notifications')),
        body: SellerAccessGate(
          title: 'Notifications unavailable',
          message:
              'Seller notifications could not be loaded right now. Try again to refresh the inbox.',
          errorMessage: state.errorMessage,
          primaryActionLabel: 'Try again',
          onPrimaryAction: () => controller.load(),
          secondaryActionLabel: 'Back to profile',
          onSecondaryAction: () => context.go('/profile'),
        ),
      );
    }

    if (!state.hasSellerProfile) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8F9FE),
        appBar: AppBar(title: const Text('Notifications')),
        body: SellerAccessGate(
          title: 'Seller notifications locked',
          message:
              'You need an active seller profile before seller notifications and workflow alerts become available.',
          primaryActionLabel: 'Start onboarding',
          onPrimaryAction: () => context.push('/seller/onboarding'),
          secondaryActionLabel: 'Back to profile',
          onSecondaryAction: () => context.go('/profile'),
        ),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Text('Notifications'),
            if (unreadCount > 0) ...<Widget>[
              const SizedBox(width: 10),
              _UnreadBadgeBubble(count: unreadCount),
            ],
          ],
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/seller/menu'),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () async {
              final messenger = ScaffoldMessenger.of(context);
              await ref
                  .read(sellerBusinessControllerProvider.notifier)
                  .markAllNotificationsRead();
              if (!mounted) return;
              messenger.showSnackBar(
                const SnackBar(
                    content: Text('All notifications marked as read.')),
              );
            },
            child: const Text(
              'Mark all as read',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
      body: state.notifications.isEmpty
          ? Center(
              child: Text(
                state.isLoading
                    ? 'Loading notifications...'
                    : 'No notifications yet.',
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium
                    ?.copyWith(color: kSellerMuted),
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
                    onTap: () async {
                      final controller =
                          ref.read(sellerBusinessControllerProvider.notifier);
                      controller.markNotificationRead(n.id);
                      final href = _notificationHref(n);
                      if (href.isNotEmpty && context.mounted) {
                        context.push(href);
                      }
                    },
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                                color: tint,
                                borderRadius: BorderRadius.circular(12)),
                            child: Icon(icon, color: kSellerAccent),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Row(
                                  children: <Widget>[
                                    Expanded(
                                      child: Text(n.title,
                                          style: const TextStyle(
                                              fontWeight: FontWeight.w800)),
                                    ),
                                    if (!n.read)
                                      const _UnreadBadgeBubble(
                                        count: 1,
                                        compact: true,
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(n.body,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodySmall
                                        ?.copyWith(
                                            color: kSellerMuted, height: 1.35)),
                              ],
                            ),
                          ),
                          Text(n.timeAgoLabel,
                              style: Theme.of(context)
                                  .textTheme
                                  .labelSmall
                                  ?.copyWith(color: kSellerMuted)),
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

String _notificationHref(SellerNotificationItem item) {
  final href = item.href.trim();
  if (href.isNotEmpty) {
    return href;
  }

  final kind = item.kind.toLowerCase();
  final title = item.title.toLowerCase();
  final body = item.body.toLowerCase();

  if (kind.contains('order') ||
      title.contains('order') ||
      body.contains('order')) {
    return '/seller/orders';
  }
  if (kind.contains('ship') ||
      title.contains('shipping') ||
      body.contains('shipping')) {
    return '/seller/orders';
  }
  if (kind.contains('review') || title.contains('review')) {
    return '/seller/reviews';
  }
  if (kind.contains('withdraw') ||
      title.contains('withdraw') ||
      body.contains('withdraw')) {
    return '/seller/withdraw-history';
  }
  if (kind.contains('dispute') ||
      title.contains('dispute') ||
      body.contains('dispute')) {
    return '/seller/disputes';
  }
  return '/seller/menu';
}

class _UnreadBadgeBubble extends StatelessWidget {
  const _UnreadBadgeBubble({
    required this.count,
    this.compact = false,
  });

  final int count;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final label = count > 99 ? '99+' : '$count';
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 8 : 10,
        vertical: compact ? 3 : 5,
      ),
      decoration: BoxDecoration(
        gradient: kSellerPrimaryGradient,
        borderRadius: BorderRadius.circular(999),
        boxShadow: <BoxShadow>[
          sellerGradientShadow(alpha: 0.16),
        ],
      ),
      child: Text(
        compact ? 'NEW' : '$label unread',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.15,
            ),
      ),
    );
  }
}
