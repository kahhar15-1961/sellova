import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class WithdrawalDto {
  const WithdrawalDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten withdrawal fields once payload schema is fully frozen.

  int? get id => (raw['id'] as num?)?.toInt() ?? (raw['withdrawal_request_id'] as num?)?.toInt();

  String get status => (raw['status'] ?? 'unknown').toString();

  String get currency => (raw['currency'] ?? '').toString().toUpperCase();

  String get amountLabel {
    final amount = raw['requested_amount'] ?? raw['amount'];
    if (amount == null) {
      return currency.isEmpty ? 'Amount unavailable' : currency;
    }
    return currency.isEmpty ? amount.toString() : '$currency $amount';
  }

  String get feeLabel {
    final fee = raw['fee_amount'] ?? raw['fee'];
    if (fee == null) {
      return 'N/A';
    }
    return currency.isEmpty ? fee.toString() : '$currency $fee';
  }

  String get netLabel {
    final net = raw['net_payout_amount'] ?? raw['net_amount'] ?? raw['net'];
    if (net == null) {
      return 'N/A';
    }
    return currency.isEmpty ? net.toString() : '$currency $net';
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

  String get payoutMethodLabel {
    final method = raw['payout_method'] ?? raw['destination'] ?? raw['bank_account_masked'];
    if (method == null || method.toString().isEmpty) {
      return 'Payout method unavailable';
    }
    return method.toString();
  }

  String get reviewerLabel {
    final reviewer =
        raw['reviewed_by_name'] ?? raw['reviewer_name'] ?? raw['admin_name'] ?? raw['reviewed_by'];
    if (reviewer == null || reviewer.toString().isEmpty) {
      return 'Reviewer unavailable';
    }
    return reviewer.toString();
  }
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
