import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/providers/repository_providers.dart';
import '../../features/auth/application/auth_session_controller.dart';
import '../../features/profile/data/profile_extras_repository.dart';

final pushRegistrationBindingProvider = Provider<void>((ref) {
  final userId = ref.watch(
    authSessionControllerProvider.select((state) => state.session?.userId),
  );
  if (userId == null) {
    return;
  }

  final repo = ref.watch(profileExtrasRepositoryProvider);
  final manager = _PushRegistrationManager(repo: repo);
  unawaited(manager.bind());
  ref.onDispose(manager.dispose);
});

class _PushRegistrationManager {
  _PushRegistrationManager({required this.repo});

  final ProfileExtrasRepository repo;
  StreamSubscription<String>? _tokenRefreshSub;
  bool _bound = false;

  Future<void> bind() async {
    if (_bound) {
      return;
    }
    _bound = true;

    try {
      await Firebase.initializeApp();
    } catch (_) {
      return;
    }

    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission(alert: true, badge: true, sound: true);
    await messaging.setAutoInitEnabled(true);
    await _registerCurrentToken();

    await _tokenRefreshSub?.cancel();
    _tokenRefreshSub = messaging.onTokenRefresh.listen((token) {
      unawaited(_registerToken(token));
    });
  }

  Future<void> _registerCurrentToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.trim().isEmpty) {
        return;
      }
      await _registerToken(token.trim());
    } catch (_) {}
  }

  Future<void> _registerToken(String token) async {
    try {
      await repo.registerPushDevice(
        deviceToken: token,
        platform: defaultTargetPlatform.name,
        deviceName: 'Sellova ${defaultTargetPlatform.name}',
      );
    } catch (_) {}
  }

  Future<void> dispose() async {
    await _tokenRefreshSub?.cancel();
    _tokenRefreshSub = null;
    _bound = false;
  }
}
