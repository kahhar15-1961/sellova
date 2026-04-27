import 'dart:convert';

import 'package:pusher_client/pusher_client.dart';

import '../auth/token_store.dart';

class ChatRealtimeClient {
  ChatRealtimeClient({
    required String baseUrl,
    required TokenStore tokenStore,
  })  : _baseUrl = baseUrl,
        _tokenStore = tokenStore;

  final String _baseUrl;
  final TokenStore _tokenStore;
  PusherClient? _pusher;
  final Map<String, Channel> _channels = <String, Channel>{};
  bool _initialized = false;
  final Set<String> _boundEvents = <String>{};

  Future<void> _ensureInitialized() async {
    if (_initialized) return;
    const key = String.fromEnvironment('REVERB_APP_KEY', defaultValue: '');
    if (key.trim().isEmpty) return;
    final access = await _tokenStore.readAccessToken();
    if (access == null || access.isEmpty) return;
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

  Future<void> subscribeThread({
    required int threadId,
    required void Function(Map<String, dynamic> message) onMessageCreated,
    required void Function(Map<String, dynamic> typing) onTypingUpdated,
  }) async {
    await _ensureInitialized();
    final channelName = 'private-chat.thread.$threadId';
    if (!_initialized || _pusher == null) {
      return;
    }
    var channel = _channels[channelName];
    channel ??= _pusher!.subscribe(channelName);
    _channels[channelName] = channel;
    final messageEventKey = '$channelName:chat.message.created';
    if (!_boundEvents.contains(messageEventKey)) {
      channel.bind('chat.message.created', (event) {
        final dataRaw = event?.data;
        if (dataRaw == null) return;
        Map<String, dynamic> payload;
        try {
          payload = Map<String, dynamic>.from(jsonDecode(dataRaw) as Map);
        } catch (_) {
          return;
        }
        final msg = Map<String, dynamic>.from(payload['message'] as Map? ?? const <String, dynamic>{});
        onMessageCreated(msg);
      });
      _boundEvents.add(messageEventKey);
    }
    final typingEventKey = '$channelName:chat.typing.updated';
    if (!_boundEvents.contains(typingEventKey)) {
      channel.bind('chat.typing.updated', (event) {
        final dataRaw = event?.data;
        if (dataRaw == null) return;
        try {
          onTypingUpdated(Map<String, dynamic>.from(jsonDecode(dataRaw) as Map));
        } catch (_) {}
      });
      _boundEvents.add(typingEventKey);
    }
  }

  Future<void> unsubscribeThread(int threadId) async {
    final channelName = 'private-chat.thread.$threadId';
    if (_pusher == null || !_channels.containsKey(channelName)) return;
    _pusher!.unsubscribe(channelName);
    _channels.remove(channelName);
  }
}

