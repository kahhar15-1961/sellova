import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';

class OrderDto {
  const OrderDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten order fields once payload schema is fully frozen.
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
