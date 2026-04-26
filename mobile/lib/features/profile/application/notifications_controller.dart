import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/profile_extras_repository.dart';

class NotificationsState {
  const NotificationsState({
    required this.items,
    required this.unreadCount,
    required this.preferences,
    this.loading = false,
    this.error,
  });

  final List<UserNotificationItem> items;
  final int unreadCount;
  final NotificationPreference preferences;
  final bool loading;
  final String? error;

  NotificationsState copyWith({
    List<UserNotificationItem>? items,
    int? unreadCount,
    NotificationPreference? preferences,
    bool? loading,
    String? error,
  }) {
    return NotificationsState(
      items: items ?? this.items,
      unreadCount: unreadCount ?? this.unreadCount,
      preferences: preferences ?? this.preferences,
      loading: loading ?? this.loading,
      error: error,
    );
  }
}

final notificationsControllerProvider = NotifierProvider<NotificationsController, NotificationsState>(NotificationsController.new);

class NotificationsController extends Notifier<NotificationsState> {
  @override
  NotificationsState build() {
    Future<void>.microtask(load);
    return const NotificationsState(
      items: <UserNotificationItem>[],
      unreadCount: 0,
      preferences: NotificationPreference(
        inAppEnabled: true,
        emailEnabled: true,
        orderUpdatesEnabled: true,
        promotionEnabled: true,
      ),
      loading: true,
    );
  }

  Future<void> load() async {
    state = state.copyWith(loading: true, error: null);
    try {
      final repo = ref.read(profileExtrasRepositoryProvider);
      final result = await repo.loadNotifications();
      final pref = await repo.loadNotificationPreferences();
      state = state.copyWith(
        items: result.items,
        unreadCount: result.unreadCount,
        preferences: pref,
        loading: false,
      );
    } catch (e) {
      state = state.copyWith(loading: false, error: e.toString());
    }
  }

  Future<void> markRead(int id) async {
    await ref.read(profileExtrasRepositoryProvider).markNotificationRead(id);
    final nextItems = state.items.map((e) => e.id == id ? UserNotificationItem(
      id: e.id,
      title: e.title,
      body: e.body,
      channel: e.channel,
      isRead: true,
      createdAt: e.createdAt,
    ) : e).toList();
    final unread = nextItems.where((e) => !e.isRead).length;
    state = state.copyWith(items: nextItems, unreadCount: unread);
  }

  Future<void> markAllRead() async {
    await ref.read(profileExtrasRepositoryProvider).markAllNotificationsRead();
    final nextItems = state.items
        .map((e) => UserNotificationItem(
              id: e.id,
              title: e.title,
              body: e.body,
              channel: e.channel,
              isRead: true,
              createdAt: e.createdAt,
            ))
        .toList();
    state = state.copyWith(items: nextItems, unreadCount: 0);
  }

  Future<void> updatePreferences(NotificationPreference next) async {
    final saved = await ref.read(profileExtrasRepositoryProvider).updateNotificationPreferences(next);
    state = state.copyWith(preferences: saved);
  }
}

