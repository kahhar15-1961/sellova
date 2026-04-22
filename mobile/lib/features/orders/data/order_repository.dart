import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class OrderDto {
  const OrderDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten order fields once payload schema is fully frozen.

  int? get id => (raw['id'] as num?)?.toInt() ?? (raw['order_id'] as num?)?.toInt();

  String get orderNumber => (raw['order_number'] ?? '#${id ?? 'unknown'}').toString();

  String get status => (raw['status'] ?? 'unknown').toString();

  String get totalLabel {
    final currency = (raw['currency'] ?? '').toString().toUpperCase();
    final total = raw['total_amount'] ?? raw['gross_amount'] ?? raw['net_amount'] ?? raw['total'];
    if (total == null) {
      return currency.isEmpty ? 'Total unavailable' : currency;
    }
    return currency.isEmpty ? total.toString() : '$currency $total';
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

  String get itemSummary {
    final items = raw['items'];
    if (items is List && items.isNotEmpty) {
      return '${items.length} item${items.length == 1 ? '' : 's'}';
    }
    final count = (raw['item_count'] as num?)?.toInt();
    if (count != null) {
      return '$count item${count == 1 ? '' : 's'}';
    }
    final firstName = raw['product_name'] ?? raw['item_name'];
    if (firstName != null && firstName.toString().isNotEmpty) {
      return firstName.toString();
    }
    return 'No item summary';
  }

  String get escrowStatus => (raw['escrow_state'] ?? raw['escrow_status'] ?? 'unavailable').toString();

  String get paymentStatus =>
      (raw['payment_status'] ?? raw['payment_state'] ?? 'unavailable').toString();

  String get sellerLabel {
    final seller =
        raw['seller_name'] ?? raw['store_name'] ?? raw['seller'] ?? raw['seller_profile_name'];
    if (seller == null || seller.toString().isEmpty) {
      return 'Seller unavailable';
    }
    return seller.toString();
  }

  List<Map<String, dynamic>> get items {
    final rawItems = raw['items'];
    if (rawItems is List) {
      return rawItems.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    return const <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> get timeline {
    final events = raw['timeline'] ?? raw['state_transitions'] ?? raw['events'];
    if (events is List) {
      return events.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    return const <Map<String, dynamic>>[];
  }
}

class OrderRepository {
  OrderRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<PaginatedResult<OrderDto>> list({
    int page = 1,
    int perPage = 10,
  }) async {
    final json = await _apiClient.get(
      '/api/v1/orders',
      queryParameters: <String, dynamic>{'page': page, 'per_page': perPage},
    );
    final result = parsePaginatedObjectList(json);
    return PaginatedResult<OrderDto>(
      items: result.items.map(OrderDto.new).toList(),
      meta: result.meta,
    );
  }

  Future<OrderDto> getById(int orderId) async {
    final json = await _apiClient.get('/api/v1/orders/$orderId');
    return OrderDto(parseObjectEnvelope(json).data);
  }

  Future<OrderDto> markPendingPayment({
    required int orderId,
    Map<String, dynamic>? request,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/orders/$orderId/mark-pending-payment',
      data: request,
    );
    return OrderDto(parseObjectEnvelope(json).data);
  }

  Future<OrderDto> markPaid({
    required int orderId,
    required int paymentTransactionId,
    String? correlationId,
  }) async {
    final body = <String, dynamic>{
      'payment_transaction_id': paymentTransactionId,
      if (correlationId != null) 'correlation_id': correlationId,
    };
    final json = await _apiClient.post('/api/v1/orders/$orderId/mark-paid', data: body);
    return OrderDto(parseObjectEnvelope(json).data);
  }
}
