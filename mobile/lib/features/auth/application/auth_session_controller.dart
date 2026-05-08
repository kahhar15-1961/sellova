import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../profile/application/my_profile_controller.dart';
import '../../profile/application/seller_profile_controller.dart';
import '../../auth/data/auth_repository.dart';
import '../../seller/application/seller_business_controller.dart';
import '../../seller/application/seller_product_controller.dart';
import '../../seller/application/seller_demo_controller.dart';

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
    NotifierProvider<AuthSessionController, AuthSessionState>(
        AuthSessionController.new);

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
      await _resetScopedStateForFreshSession();
      await _saveLandingRouteForCurrentAccount();
    } catch (_) {
      await tokenStore.clear();
      state = const AuthSessionState(status: AuthStatus.unauthenticated);
    }
  }

  Future<void> login(Map<String, dynamic> request) async {
    final authRepository = ref.read(authRepositoryProvider);
    final session = await authRepository.login(request);
    state =
        AuthSessionState(status: AuthStatus.authenticated, session: session);
    await _resetScopedStateForFreshSession();
    await _saveLandingRouteForCurrentAccount();
  }

  Future<void> loginWithGoogleIdToken({required String idToken}) async {
    final authRepository = ref.read(authRepositoryProvider);
    final session = await authRepository.loginWithGoogle(idToken: idToken);
    state =
        AuthSessionState(status: AuthStatus.authenticated, session: session);
    await _resetScopedStateForFreshSession();
    await _saveLandingRouteForCurrentAccount();
  }

  Future<void> loginWithApple({
    required String identityToken,
    String? email,
  }) async {
    final authRepository = ref.read(authRepositoryProvider);
    final session = await authRepository.loginWithApple(
        identityToken: identityToken, email: email);
    state =
        AuthSessionState(status: AuthStatus.authenticated, session: session);
    await _resetScopedStateForFreshSession();
    await _saveLandingRouteForCurrentAccount();
  }

  Future<void> register(Map<String, dynamic> request) async {
    final authRepository = ref.read(authRepositoryProvider);
    final session = await authRepository.register(request);
    state =
        AuthSessionState(status: AuthStatus.authenticated, session: session);
    await _resetScopedStateForFreshSession();
    await _saveLandingRouteForCurrentAccount();
  }

  Future<void> logout() async {
    final authRepository = ref.read(authRepositoryProvider);
    try {
      await authRepository.logout();
    } finally {
      await _resetScopedStateForFreshSession();
      await ref.read(navigationStatePersistenceProvider).resetToHome();
      state = const AuthSessionState(status: AuthStatus.unauthenticated);
    }
  }

  Future<void> _resetScopedStateForFreshSession() async {
    await ref.read(listStatePersistenceProvider).clearAllBrowsingState();
    ref.invalidate(sellerBusinessControllerProvider);
    ref.invalidate(sellerProductsProvider);
    ref.invalidate(sellerOrdersProvider);
    ref.invalidate(sellerReviewsProvider);
    ref.invalidate(sellerProfileControllerProvider);
    ref.invalidate(myProfileControllerProvider);
  }

  Future<void> _saveLandingRouteForCurrentAccount() async {
    final session = state.session;
    if (session == null) {
      return;
    }

    final nav = ref.read(navigationStatePersistenceProvider);
    try {
      await ref.read(profileRepositoryProvider).getMeSeller();
      await nav.saveLastRoute('/seller/dashboard');
    } on ApiException catch (error) {
      if (error.type == ApiExceptionType.notFound) {
        await nav.saveLastRoute('/home');
        return;
      }
      await nav.saveLastRoute('/home');
    } catch (_) {
      await nav.saveLastRoute('/home');
    }
  }
}
