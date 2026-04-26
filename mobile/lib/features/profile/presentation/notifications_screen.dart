import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/notifications_controller.dart';
import '../data/profile_extras_repository.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsControllerProvider);
    final controller = ref.read(notificationsControllerProvider.notifier);

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('Notifications'),
        actions: <Widget>[
          TextButton(
            onPressed: state.unreadCount == 0 ? null : () => controller.markAllRead(),
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: controller.load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: <Widget>[
            _PreferencesCard(
              value: state.preferences,
              onChanged: (next) => controller.updatePreferences(next),
            ),
            const SizedBox(height: 14),
            Text('Inbox', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            if (state.loading && state.items.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 24),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (state.error != null && state.items.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 20),
                child: Text('Failed to load notifications: ${state.error}'),
              )
            else if (state.items.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 24),
                child: Text('No notifications yet.'),
              )
            else
              ...state.items.map((n) => _NotificationTile(
                    item: n,
                    onTap: () => controller.markRead(n.id),
                  )),
          ],
        ),
      ),
    );
  }
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
    return Card(
      color: item.isRead ? Colors.white : const Color(0xFFF5F3FF),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        onTap: onTap,
        leading: Icon(
          item.isRead ? Icons.notifications_none_outlined : Icons.notifications_active_outlined,
          color: item.isRead ? const Color(0xFF64748B) : const Color(0xFF6D28D9),
        ),
        title: Text(item.title, style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Text(item.body),
        trailing: item.isRead
            ? null
            : Container(
                width: 10,
                height: 10,
                decoration: const BoxDecoration(
                  color: Color(0xFF6D28D9),
                  shape: BoxShape.circle,
                ),
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
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 6),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
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

