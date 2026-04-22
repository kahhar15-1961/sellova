import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class DisputeDto {
  const DisputeDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten dispute fields once payload schema is fully frozen.
}

class DisputeRepository {
  DisputeRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<PaginatedResult<DisputeDto>> list({
    int page = 1,
    int perPage = 10,
  }) async {
    final json = await _apiClient.get(
      '/api/v1/disputes',
      queryParameters: <String, dynamic>{'page': page, 'per_page': perPage},
    );
    final result = parsePaginatedObjectList(json);
    return PaginatedResult<DisputeDto>(
      items: result.items.map(DisputeDto.new).toList(),
      meta: result.meta,
    );
  }

  Future<DisputeDto> getById(int disputeCaseId) async {
    final json = await _apiClient.get('/api/v1/disputes/$disputeCaseId');
    return DisputeDto(parseObjectEnvelope(json).data);
  }

  Future<DisputeDto> openForOrder({
    required int orderId,
    required Map<String, dynamic> request,
  }) async {
    final json = await _apiClient.post('/api/v1/orders/$orderId/disputes', data: request);
    return DisputeDto(parseObjectEnvelope(json).data);
  }

  Future<DisputeDto> submitEvidence({
    required int disputeCaseId,
    required List<Map<String, dynamic>> evidence,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/disputes/$disputeCaseId/evidence',
      data: <String, dynamic>{'evidence': evidence},
    );
    return DisputeDto(parseObjectEnvelope(json).data);
  }

  Future<DisputeDto> moveToReview(int disputeCaseId) async {
    final json = await _apiClient.post('/api/v1/disputes/$disputeCaseId/move-to-review');
    return DisputeDto(parseObjectEnvelope(json).data);
  }

  Future<DisputeDto> escalate(int disputeCaseId) async {
    final json = await _apiClient.post('/api/v1/disputes/$disputeCaseId/escalate');
    return DisputeDto(parseObjectEnvelope(json).data);
  }

  Future<DisputeDto> resolveRefund({
    required int disputeCaseId,
    required Map<String, dynamic> request,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/disputes/$disputeCaseId/resolve/refund',
      data: request,
    );
    return DisputeDto(parseObjectEnvelope(json).data);
  }

  Future<DisputeDto> resolveRelease({
    required int disputeCaseId,
    required Map<String, dynamic> request,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/disputes/$disputeCaseId/resolve/release',
      data: request,
    );
    return DisputeDto(parseObjectEnvelope(json).data);
  }

  Future<DisputeDto> resolveSplit({
    required int disputeCaseId,
    required Map<String, dynamic> request,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/disputes/$disputeCaseId/resolve/split',
      data: request,
    );
    return DisputeDto(parseObjectEnvelope(json).data);
  }
}
