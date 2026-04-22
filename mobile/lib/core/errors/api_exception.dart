class ApiException implements Exception {
  const ApiException({
    required this.type,
    required this.message,
    required this.statusCode,
    required this.errorCode,
    this.context = const <String, dynamic>{},
  });

  final ApiExceptionType type;
  final String message;
  final int? statusCode;
  final String errorCode;
  final Map<String, dynamic> context;

  @override
  String toString() {
    return 'ApiException(type: $type, status: $statusCode, code: $errorCode, message: $message)';
  }
}

enum ApiExceptionType {
  unauthenticated,
  forbidden,
  notFound,
  conflict,
  validationFailed,
  invalidStateTransition,
  internalError,
  network,
  unknown,
}
