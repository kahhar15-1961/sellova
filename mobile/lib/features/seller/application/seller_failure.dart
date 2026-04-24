import '../../../core/errors/api_exception.dart';

class SellerFailure {
  const SellerFailure({
    required this.message,
    this.type,
    this.code,
    this.retryable = false,
  });

  final String message;
  final ApiExceptionType? type;
  final String? code;
  final bool retryable;

  factory SellerFailure.from(Object error) {
    if (error is ApiException) {
      final retryable = error.type == ApiExceptionType.network || error.type == ApiExceptionType.internalError;
      return SellerFailure(
        message: _messageForApi(error),
        type: error.type,
        code: error.errorCode,
        retryable: retryable,
      );
    }
    return SellerFailure(message: error.toString(), retryable: false);
  }

  static String _messageForApi(ApiException e) {
    switch (e.type) {
      case ApiExceptionType.unauthenticated:
        return 'Your session expired. Please sign in again.';
      case ApiExceptionType.forbidden:
        return 'You do not have permission to perform this action.';
      case ApiExceptionType.notFound:
        return 'The requested resource was not found.';
      case ApiExceptionType.validationFailed:
        return e.message.isNotEmpty ? e.message : 'Please check your input and try again.';
      case ApiExceptionType.conflict:
      case ApiExceptionType.invalidStateTransition:
        return e.message.isNotEmpty ? e.message : 'This action could not be completed in the current state.';
      case ApiExceptionType.network:
        return 'Network issue. Check your connection and try again.';
      case ApiExceptionType.internalError:
        return 'Server error. Please try again shortly.';
      case ApiExceptionType.unknown:
        return e.message.isNotEmpty ? e.message : 'Something went wrong.';
    }
  }
}
