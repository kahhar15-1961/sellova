import 'dart:convert';
import 'dart:typed_data';

import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';
import '../../cart/domain/cart_line.dart';
import 'package:dio/dio.dart';

class OrderDto {
  const OrderDto(this.raw);
  final Map<String, dynamic> raw;
  // TODO: tighten order fields once payload schema is fully frozen.

  int? get id =>
      (raw['id'] as num?)?.toInt() ?? (raw['order_id'] as num?)?.toInt();

  String get orderNumber =>
      (raw['order_number'] ?? '#${id ?? 'unknown'}').toString();

  String get status => (raw['status'] ?? 'unknown').toString();

  String get productType {
    final direct = (raw['product_type'] ?? '').toString().trim().toLowerCase();
    if (direct.isNotEmpty) return direct;
    for (final item in items) {
      final value =
          (item['product_type'] ?? item['product_type_snapshot'] ?? '')
              .toString()
              .trim()
              .toLowerCase();
      if (value.isNotEmpty) return value;
    }
    return 'physical';
  }

  bool get isPhysical => productType == 'physical';

  bool get usesProofDelivery => !isPhysical;

  Map<String, dynamic> get timeoutState {
    final value = raw['timeout_state'];
    return value is Map
        ? Map<String, dynamic>.from(value)
        : const <String, dynamic>{};
  }

  String get totalLabel {
    final currency = (raw['currency'] ?? '').toString().toUpperCase();
    final total = raw['total_amount'] ??
        raw['gross_amount'] ??
        raw['net_amount'] ??
        raw['total'];
    if (total == null) {
      return currency.isEmpty ? 'Total unavailable' : currency;
    }
    final parsed = num.tryParse(total.toString());
    final formatted =
        parsed == null ? total.toString() : parsed.toStringAsFixed(2);
    return currency.isEmpty ? formatted : '$currency $formatted';
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

  String get escrowStatus =>
      (raw['escrow_state'] ?? raw['escrow_status'] ?? 'unavailable').toString();

  String get paymentStatus =>
      (raw['payment_status'] ?? raw['payment_state'] ?? 'unavailable')
          .toString();

  bool get canCancel {
    final serverValue = raw['can_cancel'];
    if (serverValue is bool) {
      return serverValue;
    }
    if (serverValue != null) {
      final text = serverValue.toString().trim().toLowerCase();
      if (<String>{'1', 'true', 'yes'}.contains(text)) {
        return true;
      }
      if (<String>{'0', 'false', 'no'}.contains(text)) {
        return false;
      }
    }
    return <String>{'pending_payment', 'paid', 'paid_in_escrow'}
        .contains(status.trim().toLowerCase());
  }

  String get sellerLabel {
    final seller = raw['seller_name'] ??
        raw['store_name'] ??
        raw['seller'] ??
        raw['seller_profile_name'];
    if (seller == null || seller.toString().isEmpty) {
      return 'Seller unavailable';
    }
    return seller.toString();
  }

  List<Map<String, dynamic>> get items {
    final rawItems = raw['items'];
    if (rawItems is List) {
      return rawItems
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    return const <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> get timeline {
    final events = raw['timeline'] ?? raw['state_transitions'] ?? raw['events'];
    if (events is List) {
      return events
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    return const <Map<String, dynamic>>[];
  }
}

class PaymentGatewayItem {
  const PaymentGatewayItem({
    required this.id,
    required this.code,
    required this.name,
    required this.method,
    required this.driver,
    required this.isEnabled,
    required this.isDefault,
    required this.priority,
    required this.supportedMethods,
    required this.description,
    required this.extraJson,
  });

  final int id;
  final String code;
  final String name;
  final String method;
  final String driver;
  final bool isEnabled;
  final bool isDefault;
  final int priority;
  final List<String> supportedMethods;
  final String description;
  final Map<String, dynamic> extraJson;

  bool get walletManualTopUpEnabled {
    final value = extraJson['wallet_manual_top_up_enabled'];
    if (value is bool) {
      return value;
    }
    final text = value?.toString().trim().toLowerCase() ?? '';
    return <String>{'1', 'true', 'yes', 'on'}.contains(text);
  }

  String get walletManualTopUpLabel {
    final value =
        extraJson['wallet_manual_top_up_label']?.toString().trim() ?? '';
    return value.isEmpty ? 'Manual review' : value;
  }

  factory PaymentGatewayItem.fromJson(Map<String, dynamic> json) {
    final supported = (json['supported_methods'] as List?) ?? const <dynamic>[];
    return PaymentGatewayItem(
      id: (json['id'] as num?)?.toInt() ?? 0,
      code: (json['code'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      method: (json['method'] ?? 'card').toString(),
      driver: (json['driver'] ?? 'manual').toString(),
      isEnabled:
          json['is_enabled'] == true || json['is_enabled']?.toString() == '1',
      isDefault:
          json['is_default'] == true || json['is_default']?.toString() == '1',
      priority: (json['priority'] as num?)?.toInt() ?? 0,
      supportedMethods: supported.map((e) => e.toString()).toList(),
      description: (json['description'] ?? '').toString(),
      extraJson: json['extra_json'] is Map
          ? Map<String, dynamic>.from(json['extra_json'] as Map)
          : const <String, dynamic>{},
    );
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

  Future<OrderDto> createOrder({
    required List<CartLine> lines,
    required String correlationId,
    String? shippingMethod,
    String? shippingAddressId,
    String? shippingRecipientName,
    String? shippingAddressLine,
    String? shippingPhone,
    String? promoCode,
  }) async {
    final body = <String, dynamic>{
      'lines': lines
          .map(
            (line) => <String, dynamic>{
              'product_id': line.productId,
              'product_variant_id': null,
              'quantity': line.quantity,
              'unit_price': line.unitPriceRaw,
              'currency': line.currency,
            },
          )
          .toList(),
      'correlation_id': correlationId,
      if (shippingMethod != null) 'shipping_method': shippingMethod,
      if (shippingAddressId != null) 'shipping_address_id': shippingAddressId,
      if (shippingRecipientName != null)
        'shipping_recipient_name': shippingRecipientName,
      if (shippingAddressLine != null)
        'shipping_address_line': shippingAddressLine,
      if (shippingPhone != null) 'shipping_phone': shippingPhone,
      if (promoCode != null && promoCode.trim().isNotEmpty)
        'promo_code': promoCode.trim().toUpperCase(),
    };
    final json = await _apiClient.post('/api/v1/orders', data: body);
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
    final json =
        await _apiClient.post('/api/v1/orders/$orderId/mark-paid', data: body);
    return OrderDto(parseObjectEnvelope(json).data);
  }

  Future<OrderDto> payWithWallet({
    required int orderId,
    String? correlationId,
  }) async {
    final body = <String, dynamic>{
      if (correlationId != null) 'correlation_id': correlationId,
    };
    final json =
        await _apiClient.post('/api/v1/orders/$orderId/pay/wallet', data: body);
    return OrderDto(parseObjectEnvelope(json).data);
  }

  Future<List<PaymentGatewayItem>> listPaymentGateways() async {
    final json = await _apiClient.get('/api/v1/payment-gateways');
    final envelope = parseObjectEnvelope(json);
    final items = (envelope.data['items'] as List?) ?? const <dynamic>[];
    return items
        .whereType<Map>()
        .map((e) => PaymentGatewayItem.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<OrderDto> payWithManualMethod({
    required int orderId,
    required String provider,
    required String providerReference,
    String? correlationId,
  }) async {
    final body = <String, dynamic>{
      'provider': provider,
      'provider_reference': providerReference,
      if (correlationId != null) 'correlation_id': correlationId,
    };
    final json =
        await _apiClient.post('/api/v1/orders/$orderId/pay/manual', data: body);
    return OrderDto(parseObjectEnvelope(json).data);
  }

  Future<OrderDto> completeOrder({
    required int orderId,
    String? correlationId,
  }) async {
    final body = <String, dynamic>{
      if (correlationId != null) 'correlation_id': correlationId,
    };
    final json =
        await _apiClient.post('/api/v1/orders/$orderId/complete', data: body);
    return OrderDto(parseObjectEnvelope(json).data);
  }

  Future<OrderDto> cancelOrder({
    required int orderId,
    String? reason,
    String? correlationId,
  }) async {
    final body = <String, dynamic>{
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      if (correlationId != null) 'correlation_id': correlationId,
    };
    final json =
        await _apiClient.post('/api/v1/orders/$orderId/cancel', data: body);
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
    return items
        .whereType<Map>()
        .map((e) => ChatThreadDto(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<int> loadChatUnreadCount() async {
    final json = await _apiClient.get('/api/v1/chat/threads');
    final data = parseObjectEnvelope(json).data;
    return (data['unread_count'] as num?)?.toInt() ?? 0;
  }

  Future<List<ChatMessageDto>> listChatMessages(int threadId) async {
    final json =
        await _apiClient.get('/api/v1/chat/threads/$threadId/messages');
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
    String? filePath,
    Uint8List? fileBytes,
    required String fileName,
    String? body,
    bool isDeliveryProof = false,
    String? artifactType,
    void Function(int sent, int total)? onSendProgress,
  }) async {
    final contentType = _chatAttachmentContentType(fileName);
    if (fileBytes != null) {
      onSendProgress?.call(0, fileBytes.length);
      final json = await _apiClient.post(
        '/api/v1/chat/threads/$threadId/messages',
        data: <String, dynamic>{
          if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
          if (isDeliveryProof) 'is_delivery_proof': '1',
          if (artifactType != null && artifactType.trim().isNotEmpty)
            'artifact_type': artifactType.trim(),
          'attachment_base64': base64Encode(fileBytes),
          'attachment_name': fileName,
          'attachment_mime':
              contentType?.toString() ?? 'application/octet-stream',
          'attachment_size': fileBytes.length,
        },
      );
      onSendProgress?.call(fileBytes.length, fileBytes.length);
      return ChatMessageDto(parseObjectEnvelope(json).data);
    }

    final MultipartFile attachment;
    if (filePath != null && filePath.trim().isNotEmpty) {
      attachment = await MultipartFile.fromFile(
        filePath,
        filename: fileName,
        contentType: contentType,
      );
    } else {
      throw StateError('Attachment file is unavailable.');
    }
    final form = FormData.fromMap(<String, dynamic>{
      if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
      if (isDeliveryProof) 'is_delivery_proof': '1',
      if (artifactType != null && artifactType.trim().isNotEmpty)
        'artifact_type': artifactType.trim(),
      'attachment': attachment,
    });
    final json = await _apiClient.postMultipart(
      '/api/v1/chat/threads/$threadId/messages',
      data: form,
      onSendProgress: onSendProgress,
    );
    return ChatMessageDto(parseObjectEnvelope(json).data);
  }

  Future<void> markChatThreadRead(int threadId) async {
    await _apiClient.post('/api/v1/chat/threads/$threadId/read');
  }

  Future<void> markChatThreadsRead(Iterable<int> threadIds) async {
    final uniqueIds = threadIds.where((id) => id > 0).toSet();
    for (final threadId in uniqueIds) {
      await markChatThreadRead(threadId);
    }
  }

  Future<void> submitReview({
    required int orderId,
    required int rating,
    required String comment,
  }) async {
    await _apiClient.post('/api/v1/me/reviews', data: <String, dynamic>{
      'order_id': orderId,
      'rating': rating,
      'comment': comment.trim(),
    });
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

DioMediaType? _chatAttachmentContentType(String fileName) {
  final extension = fileName.split('.').last.toLowerCase();
  return switch (extension) {
    'jpg' || 'jpeg' => DioMediaType('image', 'jpeg'),
    'png' => DioMediaType('image', 'png'),
    'webp' => DioMediaType('image', 'webp'),
    'gif' => DioMediaType('image', 'gif'),
    'pdf' => DioMediaType('application', 'pdf'),
    'txt' => DioMediaType('text', 'plain'),
    'doc' => DioMediaType('application', 'msword'),
    'docx' => DioMediaType(
        'application',
        'vnd.openxmlformats-officedocument.wordprocessingml.document',
      ),
    'zip' => DioMediaType('application', 'zip'),
    _ => null,
  };
}

class ChatThreadDto {
  const ChatThreadDto(this.raw);
  final Map<String, dynamic> raw;

  int get id => (raw['id'] as num?)?.toInt() ?? 0;
  String get kind => (raw['kind'] ?? 'order').toString();
  String get subject => (raw['subject'] ?? 'Chat').toString();
  String get preview => (raw['last_message_preview'] ?? '').toString();
  bool get hasUnread =>
      raw['has_unread'] == true || raw['has_unread']?.toString() == '1';
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
      return rows
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
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
  bool get fromMe =>
      raw['from_me'] == true || raw['from_me']?.toString() == '1';
  String get body => (raw['body'] ?? '').toString();
  String? get markerType {
    final value = raw['marker_type']?.toString();
    return value == null || value.isEmpty ? null : value;
  }

  String get createdAt => (raw['created_at'] ?? '').toString();
  String? get attachmentUrl => raw['attachment_url']?.toString();
  String? get attachmentName => raw['attachment_name']?.toString();
  String get attachmentType => (raw['attachment_type'] ?? '').toString();
  String get attachmentMime => (raw['attachment_mime'] ?? '').toString();
  String? get attachmentDataUrl {
    final value = raw['attachment_data_url']?.toString();
    return value == null || value.isEmpty ? null : value;
  }

  Uint8List? get attachmentBytes {
    final dataUrl = attachmentDataUrl;
    if (dataUrl == null) return null;
    final comma = dataUrl.indexOf(',');
    final encoded = comma >= 0 ? dataUrl.substring(comma + 1) : dataUrl;
    try {
      return base64Decode(encoded);
    } catch (_) {
      return null;
    }
  }

  int? get attachmentSize => (raw['attachment_size'] as num?)?.toInt();
  bool get isDeliveryProof =>
      raw['is_delivery_proof'] == true ||
      raw['is_delivery_proof']?.toString() == '1';
  String get deliveryStatus => (raw['delivery_status'] ?? 'sent').toString();
}
