import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/notifications_controller.dart';
import '../data/profile_extras_repository.dart';

class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(() => ref.read(notificationsControllerProvider.notifier).load());
    _pollTimer = Timer.periodic(const Duration(seconds: 8), (_) {
      if (!mounted) {
        return;
      }
      ref.read(notificationsControllerProvider.notifier).load();
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(notificationsControllerProvider);
    final controller = ref.read(notificationsControllerProvider.notifier);
    final unreadCount = state.unreadCount;

    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/profile'),
        ),
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
        actions: <Widget>[
          TextButton(
            onPressed:
                state.unreadCount == 0 ? null : () => controller.markAllRead(),
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: RefreshIndicator(
          onRefresh: controller.load,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: <Widget>[
              _PreferencesCard(
                value: state.preferences,
                onChanged: (next) => controller.updatePreferences(next),
              ),
              const SizedBox(height: 14),
              if (unreadCount > 0)
                Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFF),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: const Color(0xFFD7E3FF)),
                  ),
                  child: Row(
                    children: <Widget>[
                      Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          color: const Color(0xFFDBEAFE),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: const Icon(Icons.notifications_active_outlined, size: 18, color: Color(0xFF1D4ED8)),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          unreadCount == 1 ? '1 unread notification' : '$unreadCount unread notifications',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              Text('Inbox',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              if (state.loading && state.items.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(top: 24),
                  child: Center(child: CircularProgressIndicator()),
                )
              else if (state.error != null && state.items.isEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 20),
                  child: Text('Load failed: ${state.error}'),
                )
              else if (state.items.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(top: 24),
                  child: Text('No notifications.'),
                )
              else
                ...state.items.map((n) => _NotificationTile(
                      item: n,
                      onTap: () async {
                        await controller.markRead(n.id);
                        if (!context.mounted) {
                          return;
                        }
                        final href = _notificationHref(n);
                        if (href.isNotEmpty) {
                          context.push(href);
                        }
                      },
                    )),
            ],
          ),
        ),
      ),
    );
  }
}

String _notificationHref(UserNotificationItem item) {
  final href = item.href.trim();
  if (href.isNotEmpty) {
    return href;
  }

  final template = item.templateCode.toLowerCase().trim();
  final title = item.title.toLowerCase();
  final body = item.body.toLowerCase();

  if (template.startsWith('wallet.top_up') ||
      title.contains('top-up') ||
      body.contains('wallet funding request') ||
      body.contains('wallet has been credited')) {
    return '/profile/wallet';
  }

  if (template.startsWith('seller.kyc') ||
      title.contains('kyc') ||
      body.contains('verification case')) {
    return '/seller/onboarding';
  }

  if (template.startsWith('seller.profile')) {
    return '/seller/onboarding';
  }

  if (title.contains('support') || body.contains('support')) {
    return '/profile/help';
  }

  return '/profile';
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({
    required this.item,
    required this.onTap,
  });

  final UserNotificationItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Card(
      color: item.isRead ? Colors.white : const Color(0xFFF8FAFF),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        onTap: onTap,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        leading: Icon(
          item.isRead
              ? Icons.notifications_none_outlined
              : Icons.notifications_active_outlined,
          color: item.isRead ? cs.onSurfaceVariant : cs.primary,
        ),
        title: Text(
          item.title,
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        subtitle: Text(item.body),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            if (!item.isRead)
              const _UnreadBadgeBubble(count: 1, compact: true),
            const SizedBox(width: 8),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8)),
          ],
        ),
      ),
    );
  }
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
        gradient: const LinearGradient(
          colors: <Color>[Color(0xFF1D4ED8), Color(0xFF2563EB)],
        ),
        borderRadius: BorderRadius.circular(999),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF1D4ED8).withValues(alpha: 0.18),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
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

class _PreferencesCard extends StatelessWidget {
  const _PreferencesCard({
    required this.value,
    required this.onChanged,
  });

  final NotificationPreference value;
  final ValueChanged<NotificationPreference> onChanged;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 6),
      decoration: BoxDecoration(
        color: cs.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
      ),
      child: Column(
        children: <Widget>[
          SwitchListTile(
            value: value.inAppEnabled,
            title: const Text('In-app notifications'),
            onChanged: (v) => onChanged(
              NotificationPreference(
                inAppEnabled: v,
                emailEnabled: value.emailEnabled,
                orderUpdatesEnabled: value.orderUpdatesEnabled,
                promotionEnabled: value.promotionEnabled,
              ),
            ),
          ),
          SwitchListTile(
            value: value.emailEnabled,
            title: const Text('Email notifications'),
            onChanged: (v) => onChanged(
              NotificationPreference(
                inAppEnabled: value.inAppEnabled,
                emailEnabled: v,
                orderUpdatesEnabled: value.orderUpdatesEnabled,
                promotionEnabled: value.promotionEnabled,
              ),
            ),
          ),
          SwitchListTile(
            value: value.orderUpdatesEnabled,
            title: const Text('Order updates'),
            onChanged: (v) => onChanged(
              NotificationPreference(
                inAppEnabled: value.inAppEnabled,
                emailEnabled: value.emailEnabled,
                orderUpdatesEnabled: v,
                promotionEnabled: value.promotionEnabled,
              ),
            ),
          ),
          SwitchListTile(
            value: value.promotionEnabled,
            title: const Text('Promotions'),
            onChanged: (v) => onChanged(
              NotificationPreference(
                inAppEnabled: value.inAppEnabled,
                emailEnabled: value.emailEnabled,
                orderUpdatesEnabled: value.orderUpdatesEnabled,
                promotionEnabled: v,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
