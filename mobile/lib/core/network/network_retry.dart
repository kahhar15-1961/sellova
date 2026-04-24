import 'dart:math';

import '../errors/api_exception.dart';

typedef ShouldRetry = bool Function(Object error);

bool defaultShouldRetry(Object error) {
  if (error is ApiException) {
    return error.type == ApiExceptionType.network || error.type == ApiExceptionType.internalError;
  }
  return false;
}

/// Runs [action] with bounded retries and exponential backoff (enterprise-safe defaults).
Future<T> runWithRetry<T>(
  Future<T> Function() action, {
  int maxAttempts = 3,
  Duration baseDelay = const Duration(milliseconds: 220),
  ShouldRetry? shouldRetry,
}) async {
  final retry = shouldRetry ?? defaultShouldRetry;
  Object? lastError;
  for (var attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      return await action();
    } catch (e) {
      lastError = e;
      final canRetry = retry(e) && attempt < maxAttempts;
      if (!canRetry) {
        rethrow;
      }
      final jitterMs = Random().nextInt(120);
      final delay = baseDelay * attempt + Duration(milliseconds: jitterMs);
      await Future<void>.delayed(delay);
    }
  }
  Error.throwWithStackTrace(lastError ?? StateError('runWithRetry exhausted'), StackTrace.current);
}
