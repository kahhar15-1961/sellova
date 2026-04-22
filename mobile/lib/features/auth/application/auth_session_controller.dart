import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../auth/data/auth_repository.dart';

enum AuthStatus {
  unknown,
  authenticated,
  unauthenticated,
}

class AuthSessionState {
  const AuthSessionState({
    required this.status,
    this.session,
  });

  final AuthStatus status;
  final AuthSessionDto? session;

  bool get isAuthenticated => status == AuthStatus.authenticated;

  AuthSessionState copyWith({
    AuthStatus? status,
    AuthSessionDto? session,
  }) {
    return AuthSessionState(
      status: status ?? this.status,
      session: session ?? this.session,
    );
  }
}

final authSessionControllerProvider =
    NotifierProvider<AuthSessionController, AuthSessionState>(AuthSessionController.new);

class AuthSessionController extends Notifier<AuthSessionState> {
  @override
  AuthSessionState build() {
    return const AuthSessionState(status: AuthStatus.unknown);
  }

  Future<void> restore() async {
    final tokenStore = ref.read(tokenStoreProvider);
    final refreshToken = await tokenStore.readRefreshToken();
    if (refreshToken == null || refreshToken.isEmpty) {
      state = const AuthSessionState(status: AuthStatus.unauthenticated);
      return;
    }

    try {
      final authRepository = ref.read(authRepositoryProvider);
      final refreshed = await authRepository.refresh(refreshToken);
      state = AuthSessionState(
        status: AuthStatus.authenticated,
        session: refreshed,
      );
    } catch (_) {
      await tokenStore.clear();
      state = const AuthSessionState(status: AuthStatus.unauthenticated);
    }
  }

  Future<void> login(Map<String, dynamic> request) async {
    final authRepository = ref.read(authRepositoryProvider);
    final session = await authRepository.login(request);
    state = AuthSessionState(status: AuthStatus.authenticated, session: session);
  }

  Future<void> register(Map<String, dynamic> request) async {
    final authRepository = ref.read(authRepositoryProvider);
    final session = await authRepository.register(request);
    state = AuthSessionState(status: AuthStatus.authenticated, session: session);
  }

  Future<void> logout() async {
    final authRepository = ref.read(authRepositoryProvider);
    try {
      await authRepository.logout();
    } finally {
      state = const AuthSessionState(status: AuthStatus.unauthenticated);
    }
  }
}
