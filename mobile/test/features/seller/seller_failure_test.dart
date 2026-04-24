import 'package:flutter_test/flutter_test.dart';

import 'package:sellova_mobile/core/errors/api_exception.dart';
import 'package:sellova_mobile/features/seller/application/seller_failure.dart';

void main() {
  group('SellerFailure.from', () {
    test('maps network errors to retryable friendly copy', () {
      const e = ApiException(
        type: ApiExceptionType.network,
        message: 'timeout',
        statusCode: null,
        errorCode: 't',
      );
      final f = SellerFailure.from(e);
      expect(f.retryable, isTrue);
      expect(f.message, contains('Network'));
      expect(f.type, ApiExceptionType.network);
    });

    test('maps validation to non-retryable', () {
      const e = ApiException(
        type: ApiExceptionType.validationFailed,
        message: 'Email invalid',
        statusCode: 422,
        errorCode: 'v',
      );
      final f = SellerFailure.from(e);
      expect(f.retryable, isFalse);
      expect(f.message, 'Email invalid');
    });

    test('falls back for unknown objects', () {
      final f = SellerFailure.from(StateError('x'));
      expect(f.message, contains('Bad state'));
    });
  });
}
