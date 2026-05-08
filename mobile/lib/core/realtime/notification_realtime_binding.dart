import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/providers/app_providers.dart';
import '../../features/auth/application/auth_session_controller.dart';
import '../../features/profile/application/notifications_controller.dart';
import '../../features/profile/application/wallet_controller.dart';
import 'notification_realtime_client.dart';

final notificationRealtimeClientProvider = Provider<NotificationRealtimeClient>((ref) {
  return NotificationRealtimeClient(
    baseUrl: ref.watch(baseUrlProvider),
    tokenStore: ref.watch(tokenStoreProvider),
  );
});

final notificationRealtimeBindingProvider = Provider<void>((ref) {
  final userId = ref.watch(
    authSessionControllerProvider.select((state) => state.session?.userId),
  );
  if (userId == null) {
    return;
  }

  final realtime = ref.watch(notificationRealtimeClientProvider);
  unawaited(
    realtime.subscribeUserNotifications(
      userId: userId,
      onNotificationCreated: (notification, unreadCount) {
        ref.read(notificationsControllerProvider.notifier).applyRealtimeNotification(
              notification,
              unreadCount: unreadCount,
            );

        final templateCode = (notification['template_code'] ?? '').toString().toLowerCase();
        if (templateCode.startsWith('wallet.top_up')) {
          ref.read(walletControllerProvider.notifier).load();
        }
      },
    ),
  );

  ref.onDispose(() {
    unawaited(realtime.unsubscribeUserNotifications(userId));
  });
});
