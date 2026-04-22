import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class DisputeDto {
  const DisputeDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten dispute fields once payload schema is fully frozen.

  int? get id => (raw['id'] as num?)?.toInt() ?? (raw['dispute_case_id'] as num?)?.toInt();

  int? get orderId => (raw['order_id'] as num?)?.toInt();

  String get status => (raw['status'] ?? raw['state'] ?? 'unknown').toString();

  String get summary {
    final reason = raw['reason_code'] ?? raw['reason'] ?? raw['summary'] ?? raw['description'];
    if (reason == null || reason.toString().isEmpty) {
      return 'No reason summary';
    }
    return reason.toString();
  }

  DateTime? get createdAt {
    final rawValue = raw['created_at'] ?? raw['createdAt'];
    if (rawValue is String && rawValue.isNotEmpty) {
      return DateTime.tryParse(rawValue);
    }
    return null;
  }

  String get createdDateLabel {
    final date = createdAt;
    if (date == null) {
      return 'Date unavailable';
    }
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '${date.year}-$month-$day';
  }

  List<Map<String, dynamic>> get timeline {
    final rawTimeline = raw['timeline'] ?? raw['events'] ?? raw['status_history'];
    if (rawTimeline is List) {
      return rawTimeline.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    return const <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> get evidence {
    final rawEvidence = raw['evidence'] ?? raw['evidence_items'];
    if (rawEvidence is List) {
      return rawEvidence.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    return const <Map<String, dynamic>>[];
  }

  String get outcome {
    final value = raw['resolution_outcome'] ?? raw['decision'] ?? raw['final_outcome'];
    if (value == null || value.toString().isEmpty) {
      return 'Outcome unavailable';
    }
    return value.toString();
  }
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
