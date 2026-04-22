import 'package:dio/dio.dart';

import '../models/api_envelope.dart';
import 'api_exception.dart';

class ApiErrorMapper {
  ApiException map(Object error) {
    if (error is DioException) {
      final response = error.response;
      final status = response?.statusCode;
      final data = response?.data;

      if (data is Map<String, dynamic>) {
        final envelope = ApiErrorEnvelope.fromJson(data);
        return ApiException(
          type: _mapType(status, envelope.error),
          message: envelope.message,
          statusCode: status,
          errorCode: envelope.error,
          context: envelope.context,
        );
      }

      if (error.type == DioExceptionType.connectionError ||
          error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.receiveTimeout ||
          error.type == DioExceptionType.sendTimeout) {
        return ApiException(
          type: ApiExceptionType.network,
          message: error.message ?? 'Network error',
          statusCode: status,
          errorCode: 'network_error',
        );
      }
    }

    return ApiException(
      type: ApiExceptionType.unknown,
      message: error.toString(),
      statusCode: null,
      errorCode: 'unknown_error',
    );
  }

  ApiExceptionType _mapType(int? statusCode, String errorCode) {
    if (statusCode == 401 || errorCode == 'unauthenticated') {
      return ApiExceptionType.unauthenticated;
    }
    if (statusCode == 403 || errorCode == 'forbidden') {
      return ApiExceptionType.forbidden;
    }
    if (statusCode == 404 || errorCode == 'not_found') {
      return ApiExceptionType.notFound;
    }
    if (statusCode == 409 && errorCode == 'invalid_state_transition') {
      return ApiExceptionType.invalidStateTransition;
    }
    if (statusCode == 409 ||
        errorCode == 'conflict' ||
        errorCode == 'idempotency_conflict') {
      return ApiExceptionType.conflict;
    }
    if (statusCode == 422 || errorCode == 'validation_failed') {
      return ApiExceptionType.validationFailed;
    }
    if (statusCode == 500 || errorCode == 'internal_error') {
      return ApiExceptionType.internalError;
    }
    return ApiExceptionType.unknown;
  }
}
