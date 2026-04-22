import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class WithdrawalDto {
  const WithdrawalDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten withdrawal fields once payload schema is fully frozen.
}

class WithdrawalRepository {
  WithdrawalRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<PaginatedResult<WithdrawalDto>> list({
    int page = 1,
    int perPage = 10,
  }) async {
    final json = await _apiClient.get(
      '/api/v1/withdrawals',
      queryParameters: <String, dynamic>{'page': page, 'per_page': perPage},
    );
    final result = parsePaginatedObjectList(json);
    return PaginatedResult<WithdrawalDto>(
      items: result.items.map(WithdrawalDto.new).toList(),
      meta: result.meta,
    );
  }

  Future<WithdrawalDto> getById(int withdrawalRequestId) async {
    final json = await _apiClient.get('/api/v1/withdrawals/$withdrawalRequestId');
    return WithdrawalDto(parseObjectEnvelope(json).data);
  }

  Future<WithdrawalDto> request(Map<String, dynamic> body) async {
    final json = await _apiClient.post('/api/v1/withdrawals', data: body);
    return WithdrawalDto(parseObjectEnvelope(json).data);
  }

  Future<WithdrawalDto> review({
    required int withdrawalRequestId,
    required Map<String, dynamic> body,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/withdrawals/$withdrawalRequestId/review',
      data: body,
    );
    return WithdrawalDto(parseObjectEnvelope(json).data);
  }

  Future<WithdrawalDto> approve({
    required int withdrawalRequestId,
    required String idempotencyKey,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/withdrawals/$withdrawalRequestId/approve',
      data: <String, dynamic>{'idempotency_key': idempotencyKey},
    );
    return WithdrawalDto(parseObjectEnvelope(json).data);
  }

  Future<WithdrawalDto> reject({
    required int withdrawalRequestId,
    required String idempotencyKey,
    required String reason,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/withdrawals/$withdrawalRequestId/reject',
      data: <String, dynamic>{
        'idempotency_key': idempotencyKey,
        'reason': reason,
      },
    );
    return WithdrawalDto(parseObjectEnvelope(json).data);
  }
}
