import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';

final _liveCounterTickProvider = StreamProvider<int>((ref) async* {
  var tick = 0;
  while (true) {
    yield tick++;
    await Future<void>.delayed(const Duration(seconds: 8));
  }
});

final chatUnreadCountProvider = FutureProvider<int>((ref) async {
  ref.watch(_liveCounterTickProvider);
  return ref.read(orderRepositoryProvider).loadChatUnreadCount();
});

final notificationUnreadCountProvider = FutureProvider<int>((ref) async {
  ref.watch(_liveCounterTickProvider);
  final notifications = await ref.read(profileExtrasRepositoryProvider).loadNotifications();
  return notifications.unreadCount;
});

