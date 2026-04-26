import 'package:dio/dio.dart';

import '../auth/auth_session_manager.dart';
import '../auth/token_store.dart';
import '../errors/api_error_mapper.dart';
import 'auth_interceptor.dart';

class ApiClient {
  ApiClient({
    required String baseUrl,
    required TokenStore tokenStore,
    required AuthSessionManager sessionManager,
    required ApiErrorMapper errorMapper,
    Dio? dio,
  })  : _errorMapper = errorMapper,
        _dio = dio ??
            Dio(
              BaseOptions(
                baseUrl: baseUrl,
                headers: const <String, dynamic>{
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                },
              ),
            ) {
    _dio.interceptors.add(
      AuthInterceptor(
        tokenStore: tokenStore,
        sessionManager: sessionManager,
      ),
    );
  }

  final Dio _dio;
  final ApiErrorMapper _errorMapper;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        path,
        queryParameters: queryParameters,
      );
      return response.data ?? <String, dynamic>{};
    } catch (error) {
      throw _errorMapper.map(error);
    }
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? data,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        path,
        data: data,
        queryParameters: queryParameters,
      );
      return response.data ?? <String, dynamic>{};
    } catch (error) {
      throw _errorMapper.map(error);
    }
  }

  Future<Map<String, dynamic>> patch(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        path,
        data: data,
      );
      return response.data ?? <String, dynamic>{};
    } catch (error) {
      throw _errorMapper.map(error);
    }
  }

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await _dio.delete<Map<String, dynamic>>(
        path,
        data: data,
      );
      return response.data ?? <String, dynamic>{};
    } catch (error) {
      throw _errorMapper.map(error);
    }
  }

  Future<Map<String, dynamic>> postMultipart(
    String path, {
    required FormData data,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: Options(contentType: 'multipart/form-data'),
      );
      return response.data ?? <String, dynamic>{};
    } catch (error) {
      throw _errorMapper.map(error);
    }
  }
}
