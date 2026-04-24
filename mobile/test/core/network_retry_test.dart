import 'package:flutter_test/flutter_test.dart';

import 'package:sellova_mobile/core/errors/api_exception.dart';
import 'package:sellova_mobile/core/network/network_retry.dart';

void main() {
  group('runWithRetry', () {
    test('retries on network ApiException then succeeds', () async {
      var calls = 0;
      final result = await runWithRetry(
        () async {
          calls++;
          if (calls < 2) {
            throw const ApiException(
              type: ApiExceptionType.network,
              message: 'offline',
              statusCode: null,
              errorCode: 'network',
            );
          }
          return 'done';
        },
        baseDelay: const Duration(milliseconds: 1),
        maxAttempts: 4,
      );
      expect(result, 'done');
      expect(calls, 2);
    });

    test('does not retry validation errors', () async {
      var calls = 0;
      await expectLater(
        () => runWithRetry(
          () async {
            calls++;
            throw const ApiException(
              type: ApiExceptionType.validationFailed,
              message: 'bad',
              statusCode: 422,
              errorCode: 'validation',
            );
          },
          baseDelay: const Duration(milliseconds: 1),
          maxAttempts: 3,
        ),
        throwsA(isA<ApiException>()),
      );
      expect(calls, 1);
    });
  });
}
