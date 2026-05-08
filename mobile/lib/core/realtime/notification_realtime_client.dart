import 'dart:convert';

import 'package:pusher_client/pusher_client.dart';

import '../auth/token_store.dart';

class NotificationRealtimeClient {
  NotificationRealtimeClient({
    required String baseUrl,
    required TokenStore tokenStore,
  })  : _baseUrl = baseUrl,
        _tokenStore = tokenStore;

  final String _baseUrl;
  final TokenStore _tokenStore;
  PusherClient? _pusher;
  final Map<String, Channel> _channels = <String, Channel>{};
  final Set<String> _boundEvents = <String>{};
  bool _initialized = false;

  Future<void> _ensureInitialized() async {
    if (_initialized) {
      return;
    }
    const key = String.fromEnvironment('REVERB_APP_KEY', defaultValue: '');
    if (key.trim().isEmpty) {
      return;
    }
    final access = await _tokenStore.readAccessToken();
    if (access == null || access.isEmpty) {
      return;
    }
    final uri = Uri.parse(_baseUrl);
    final host = const String.fromEnvironment('REVERB_HOST', defaultValue: '').trim().isEmpty
        ? uri.host
        : const String.fromEnvironment('REVERB_HOST');
    final scheme = const String.fromEnvironment('REVERB_SCHEME', defaultValue: '').trim().isEmpty
        ? (uri.scheme == 'https' ? 'https' : 'http')
        : const String.fromEnvironment('REVERB_SCHEME');
    final portRaw = const String.fromEnvironment('REVERB_PORT', defaultValue: '').trim();
    final port = int.tryParse(portRaw) ?? (scheme == 'https' ? 443 : 80);
    final options = PusherOptions(
      host: host,
      wsPort: port,
      encrypted: scheme == 'https',
      auth: PusherAuth(
        '$_baseUrl/api/v1/realtime/auth',
        headers: <String, String>{
          'Accept': 'application/json',
          'Authorization': 'Bearer $access',
        },
      ),
    );
    _pusher = PusherClient(key, options, enableLogging: false);
    _pusher?.connect();
    _initialized = true;
  }

  Future<void> subscribeUserNotifications({
    required int userId,
    required void Function(Map<String, dynamic> notification, int unreadCount)
        onNotificationCreated,
  }) async {
    await _ensureInitialized();
    final channelName = 'private-App.Models.User.$userId';
    if (!_initialized || _pusher == null) {
      return;
    }
    var channel = _channels[channelName];
    channel ??= _pusher!.subscribe(channelName);
    _channels[channelName] = channel;

    final eventKey = '$channelName:notification.created';
    if (!_boundEvents.contains(eventKey)) {
      channel.bind('notification.created', (event) {
        final dataRaw = event?.data;
        if (dataRaw == null) {
          return;
        }
        try {
          final payload = Map<String, dynamic>.from(jsonDecode(dataRaw) as Map);
          final notification = Map<String, dynamic>.from(
            payload['notification'] as Map? ?? const <String, dynamic>{},
          );
          final unreadCount = (payload['unread_count'] as num?)?.toInt() ?? 0;
          onNotificationCreated(notification, unreadCount);
        } catch (_) {}
      });
      _boundEvents.add(eventKey);
    }
  }

  Future<void> unsubscribeUserNotifications(int userId) async {
    final channelName = 'private-App.Models.User.$userId';
    if (_pusher == null || !_channels.containsKey(channelName)) {
      return;
    }
    _pusher!.unsubscribe(channelName);
    _channels.remove(channelName);
  }
}
