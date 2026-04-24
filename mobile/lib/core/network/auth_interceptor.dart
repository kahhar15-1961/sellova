import 'package:dio/dio.dart';

import '../auth/auth_session_manager.dart';
import '../auth/token_store.dart';

class AuthInterceptor extends Interceptor {
  AuthInterceptor({
    required this.tokenStore,
    required this.sessionManager,
  });

  final TokenStore tokenStore;
  final AuthSessionManager sessionManager;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    // Never send a stale access token on anonymous auth calls: the server may
    // resolve an actor and eager-load relations (e.g. roles) before login runs,
    // which can surface as 500 if the DB schema is incomplete.
    final path = options.uri.path;
    final isAnonymousAuth = path.contains('/api/v1/auth/login') ||
        path.contains('/api/v1/auth/register') ||
        path.contains('/api/v1/auth/refresh') ||
        path.contains('/api/v1/auth/google') ||
        path.contains('/api/v1/auth/apple');
    if (!isAnonymousAuth) {
      final accessToken = await tokenStore.readAccessToken();
      if (accessToken != null && accessToken.isNotEmpty) {
        options.headers['Authorization'] = 'Bearer $accessToken';
      }
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final response = err.response;
    final requestOptions = err.requestOptions;
    final isUnauthenticated = response?.statusCode == 401;
    final alreadyRetried = requestOptions.extra['__retried401__'] == true;
    final isAuthEndpoint = requestOptions.path.contains('/api/v1/auth/');

    if (!isUnauthenticated || alreadyRetried || isAuthEndpoint) {
      handler.next(err);
      return;
    }

    final refreshed = await sessionManager.refreshIfNeeded();
    if (!refreshed) {
      await tokenStore.clear();
      handler.next(err);
      return;
    }

    final newToken = await tokenStore.readAccessToken();
    if (newToken == null || newToken.isEmpty) {
      await tokenStore.clear();
      handler.next(err);
      return;
    }

    final retryOptions = requestOptions.copyWith(
      headers: <String, dynamic>{
        ...requestOptions.headers,
        'Authorization': 'Bearer $newToken',
      },
      extra: <String, dynamic>{
        ...requestOptions.extra,
        '__retried401__': true,
      },
    );

    try {
      final retryDio = Dio(BaseOptions(baseUrl: sessionManager.baseUrl));
      final retryResponse = await retryDio.fetch<dynamic>(retryOptions);
      handler.resolve(retryResponse);
    } catch (retryError) {
      handler.next(err);
    }
  }
}
