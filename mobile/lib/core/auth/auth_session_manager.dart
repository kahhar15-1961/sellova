import 'dart:async';

import 'package:dio/dio.dart';

import '../models/api_envelope.dart';
import 'token_store.dart';

class AuthSessionManager {
  AuthSessionManager({
    required this.baseUrl,
    required this.tokenStore,
  });

  final String baseUrl;
  final TokenStore tokenStore;
  Future<bool>? _refreshInFlight;

  Future<bool> refreshIfNeeded() {
    final current = _refreshInFlight;
    if (current != null) {
      return current;
    }

    final completer = Completer<bool>();
    _refreshInFlight = completer.future;
    _refreshInternal().then(completer.complete).catchError(completer.completeError).whenComplete(() {
      _refreshInFlight = null;
    });
    return completer.future;
  }

  Future<bool> _refreshInternal() async {
    final refreshToken = await tokenStore.readRefreshToken();
    if (refreshToken == null || refreshToken.isEmpty) {
      await tokenStore.clear();
      return false;
    }

    final dio = Dio(BaseOptions(baseUrl: baseUrl));
    final response = await dio.post<Map<String, dynamic>>(
      '/api/v1/auth/refresh',
      data: <String, dynamic>{'refresh_token': refreshToken},
    );
    final body = response.data;
    if (body == null) {
      await tokenStore.clear();
      return false;
    }

    final envelope = ApiEnvelope<Map<String, dynamic>>.fromJson(
      body,
      (raw) => Map<String, dynamic>.from(raw as Map),
    );
    final data = envelope.data;
    final newAccessToken = (data['access_token'] ?? '').toString();
    final newRefreshToken = (data['refresh_token'] ?? '').toString();

    if (newAccessToken.isEmpty || newRefreshToken.isEmpty) {
      await tokenStore.clear();
      return false;
    }

    await tokenStore.writeTokens(
      accessToken: newAccessToken,
      refreshToken: newRefreshToken,
    );
    return true;
  }
}
