import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';
import 'package:dio/dio.dart';

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

  Future<OrderTrackingDto> getTracking(int orderId) async {
    final json = await _apiClient.get('/api/v1/orders/$orderId/tracking');
    return OrderTrackingDto(parseObjectEnvelope(json).data);
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

  Future<int> getOrCreateChatThread(int orderId) async {
    final json = await _apiClient.post('/api/v1/orders/$orderId/chat-thread');
    final data = parseObjectEnvelope(json).data;
    return (data['thread_id'] as num?)?.toInt() ?? 0;
  }

  Future<List<ChatThreadDto>> listChatThreads() async {
    final json = await _apiClient.get('/api/v1/chat/threads');
    final data = parseObjectEnvelope(json).data;
    final items = (data['items'] as List?) ?? const <dynamic>[];
    return items.whereType<Map>().map((e) => ChatThreadDto(Map<String, dynamic>.from(e))).toList();
  }

  Future<int> loadChatUnreadCount() async {
    final json = await _apiClient.get('/api/v1/chat/threads');
    final data = parseObjectEnvelope(json).data;
    return (data['unread_count'] as num?)?.toInt() ?? 0;
  }

  Future<List<ChatMessageDto>> listChatMessages(int threadId) async {
    final json = await _apiClient.get('/api/v1/chat/threads/$threadId/messages');
    final envelope = parseListEnvelope(json);
    return envelope.data.map(ChatMessageDto.new).toList();
  }

  Future<ChatMessageDto> sendChatMessage(int threadId, String body) async {
    final json = await _apiClient.post(
      '/api/v1/chat/threads/$threadId/messages',
      data: <String, dynamic>{'body': body},
    );
    return ChatMessageDto(parseObjectEnvelope(json).data);
  }

  Future<ChatMessageDto> sendChatAttachment({
    required int threadId,
    required String filePath,
    required String fileName,
    String? body,
  }) async {
    final form = FormData.fromMap(<String, dynamic>{
      if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
      'attachment': await MultipartFile.fromFile(filePath, filename: fileName),
    });
    final json = await _apiClient.postMultipart('/api/v1/chat/threads/$threadId/messages', data: form);
    return ChatMessageDto(parseObjectEnvelope(json).data);
  }

  Future<void> markChatThreadRead(int threadId) async {
    await _apiClient.post('/api/v1/chat/threads/$threadId/read');
  }

  Future<void> setTyping(int threadId, {required bool typing}) async {
    await _apiClient.post(
      '/api/v1/chat/threads/$threadId/typing',
      data: <String, dynamic>{'typing': typing},
    );
  }

  Future<List<String>> loadTypingUsers(int threadId) async {
    final json = await _apiClient.get('/api/v1/chat/threads/$threadId/typing');
    final envelope = parseListEnvelope(json);
    return envelope.data
        .map((row) => (row['name'] ?? '').toString())
        .where((name) => name.isNotEmpty)
        .toList();
  }

  Future<int> createSupportTicket({
    required String subject,
    required String message,
  }) async {
    final json = await _apiClient.post(
      '/api/v1/chat/support-tickets',
      data: <String, dynamic>{'subject': subject, 'message': message},
    );
    final data = parseObjectEnvelope(json).data;
    return (data['thread_id'] as num?)?.toInt() ?? 0;
  }
}

class ChatThreadDto {
  const ChatThreadDto(this.raw);
  final Map<String, dynamic> raw;

  int get id => (raw['id'] as num?)?.toInt() ?? 0;
  String get kind => (raw['kind'] ?? 'order').toString();
  String get subject => (raw['subject'] ?? 'Chat').toString();
  String get preview => (raw['last_message_preview'] ?? '').toString();
  bool get hasUnread => raw['has_unread'] == true || raw['has_unread']?.toString() == '1';
  String get counterparty => (raw['counterparty_label'] ?? '').toString();
}

class OrderTrackingDto {
  const OrderTrackingDto(this.raw);
  final Map<String, dynamic> raw;

  String get carrierName => (raw['carrier_name'] ?? '').toString();
  String get trackingId => (raw['tracking_id'] ?? '').toString();
  String get trackingUrl => (raw['tracking_url'] ?? '').toString();
  String get eta => (raw['eta'] ?? '').toString();
  List<Map<String, dynamic>> get timeline {
    final rows = raw['timeline'];
    if (rows is List) {
      return rows.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    return const <Map<String, dynamic>>[];
  }

  Map<String, dynamic> get proofOfDelivery {
    final pod = raw['proof_of_delivery'];
    if (pod is Map) {
      return Map<String, dynamic>.from(pod);
    }
    return const <String, dynamic>{};
  }
}

class ChatMessageDto {
  const ChatMessageDto(this.raw);
  final Map<String, dynamic> raw;

  int get id => (raw['id'] as num?)?.toInt() ?? 0;
  bool get fromMe => raw['from_me'] == true || raw['from_me']?.toString() == '1';
  String get body => (raw['body'] ?? '').toString();
  String get createdAt => (raw['created_at'] ?? '').toString();
  String? get attachmentUrl => raw['attachment_url']?.toString();
  String? get attachmentName => raw['attachment_name']?.toString();
  String get deliveryStatus => (raw['delivery_status'] ?? 'sent').toString();
}
