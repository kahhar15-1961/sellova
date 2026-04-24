import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../domain/seller_models.dart';

class SellerRepository {
  SellerRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<Map<String, dynamic>>> listSellerOrders() async {
    final json = await _apiClient.get('/api/v1/orders', queryParameters: <String, dynamic>{'page': 1, 'per_page': 30});
    return parsePaginatedObjectList(json).items;
  }

  Future<void> updateOrderStatus({
    required int orderId,
    required String status,
  }) async {
    await _apiClient.post('/api/v1/seller/orders/$orderId/status', data: <String, dynamic>{'status': status});
  }

  Future<void> addShippingDetails({
    required int orderId,
    required String courierCompany,
    required String trackingId,
    required String shippingDateIso,
    String? note,
  }) async {
    await _apiClient.post('/api/v1/seller/orders/$orderId/shipping', data: <String, dynamic>{
      'courier_company': courierCompany,
      'tracking_id': trackingId,
      'shipping_date': shippingDateIso,
      if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
    });
  }

  Future<void> postReviewReply({
    required int reviewId,
    required String reply,
  }) async {
    await _apiClient.post('/api/v1/seller/reviews/$reviewId/reply', data: <String, dynamic>{'reply': reply});
  }

  Future<void> createProduct({
    required String title,
    required double price,
    required int stock,
    required String category,
    required String description,
    required String productType,
  }) async {
    await _apiClient.post('/api/v1/seller/products', data: <String, dynamic>{
      'title': title,
      'price': price,
      'stock': stock,
      'category': category,
      'description': description,
      'product_type': productType,
    });
  }

  Future<void> updateProduct({
    required int productId,
    required String title,
    required double price,
    required int stock,
    required String category,
    required String description,
    required String status,
  }) async {
    await _apiClient.patch('/api/v1/seller/products/$productId', data: <String, dynamic>{
      'title': title,
      'price': price,
      'stock': stock,
      'category': category,
      'description': description,
      'status': status,
    });
  }

  Future<void> toggleProduct({
    required int productId,
    required bool active,
  }) async {
    await _apiClient.post('/api/v1/seller/products/$productId/toggle', data: <String, dynamic>{'active': active});
  }

  Future<SellerStoreSettings> getStoreSettings() async {
    final json = await _apiClient.get('/api/v1/seller/store/settings');
    final data = parseObjectEnvelope(json).data;
    return _mapStoreSettings(data);
  }

  Future<SellerStoreSettings> updateStoreSettings({
    required String storeName,
    required String storeDescription,
  }) async {
    final json = await _apiClient.patch(
      '/api/v1/seller/store/settings',
      data: <String, dynamic>{
        'store_name': storeName,
        'store_description': storeDescription,
      },
    );
    final data = parseObjectEnvelope(json).data;
    return _mapStoreSettings(data);
  }

  Future<SellerShippingSettings> getShippingSettings() async {
    final json = await _apiClient.get('/api/v1/seller/shipping/settings');
    final data = parseObjectEnvelope(json).data;
    return _mapShippingSettings(data);
  }

  Future<SellerShippingSettings> updateShippingSettings(SellerShippingSettings value) async {
    final json = await _apiClient.patch(
      '/api/v1/seller/shipping/settings',
      data: <String, dynamic>{
        'inside_dhaka_label': value.insideDhakaLabel,
        'inside_dhaka_fee': value.insideDhakaFee,
        'outside_dhaka_label': value.outsideDhakaLabel,
        'outside_dhaka_fee': value.outsideDhakaFee,
        'cash_on_delivery_enabled': value.cashOnDeliveryEnabled,
        'processing_time_label': value.processingTimeLabel,
      },
    );
    final data = parseObjectEnvelope(json).data;
    return _mapShippingSettings(data);
  }

  Future<List<SellerPayoutMethod>> listPayoutMethods() async {
    final json = await _apiClient.get('/api/v1/seller/payout-methods');
    final result = parsePaginatedObjectList(json);
    return result.items.map(_mapPayoutMethod).toList();
  }

  Future<List<SellerPayoutMethod>> upsertPayoutMethod({
    required SellerPayoutMethodType type,
    required String accountName,
    required String accountNumber,
    String? bankName,
    bool asDefault = false,
  }) async {
    final json = await _apiClient.post('/api/v1/seller/payout-methods', data: <String, dynamic>{
      'method_type': type.apiValue,
      'account_name': accountName,
      'account_number': accountNumber,
      if (bankName != null && bankName.trim().isNotEmpty) 'bank_name': bankName.trim(),
      'is_default': asDefault,
    });
    final result = parsePaginatedObjectList(json);
    return result.items.map(_mapPayoutMethod).toList();
  }

  Future<void> requestWithdrawal({
    required SellerPayoutMethodType methodType,
    required String accountNumber,
    required String amountText,
  }) async {
    await _apiClient.post('/api/v1/withdrawals', data: <String, dynamic>{
      'payout_method': methodType.apiValue,
      'account_number': accountNumber,
      'amount': amountText,
    });
  }

  Future<List<SellerNotificationItem>> listSellerNotifications() async {
    final json = await _apiClient.get('/api/v1/seller/notifications', queryParameters: <String, dynamic>{'page': 1, 'per_page': 40});
    final result = parsePaginatedObjectList(json);
    return result.items.map(_mapNotification).toList();
  }

  Future<void> markAllNotificationsRead() async {
    await _apiClient.post('/api/v1/seller/notifications/mark-all-read');
  }

  /// Storefront / seller media upload (logo, banner, etc.).
  Future<String> uploadStoreMedia(String filePath) async {
    final form = FormData.fromMap(<String, dynamic>{
      'file': await MultipartFile.fromFile(filePath),
      'purpose': 'store_media',
    });
    final json = await _apiClient.postMultipart('/api/v1/seller/media/upload', data: form);
    Map<String, dynamic> payload;
    try {
      payload = parseObjectEnvelope(json).data;
    } catch (_) {
      payload = Map<String, dynamic>.from(json);
    }
    final url = (payload['url'] ?? payload['storage_path'] ?? payload['file_url'] ?? payload['path'] ?? '').toString();
    if (url.isEmpty) {
      throw const FormatException('Upload response missing file URL');
    }
    return url;
  }
}

SellerStoreSettings _mapStoreSettings(Map<String, dynamic> raw) {
  return SellerStoreSettings(
    storeName: (raw['store_name'] ?? raw['name'] ?? '').toString(),
    storeDescription: (raw['store_description'] ?? raw['description'] ?? '').toString(),
    storeLogoUrl: raw['store_logo_url']?.toString(),
    bannerImageUrl: raw['banner_image_url']?.toString(),
    contactEmail: raw['contact_email']?.toString(),
    contactPhone: raw['contact_phone']?.toString(),
  );
}

SellerShippingSettings _mapShippingSettings(Map<String, dynamic> raw) {
  double asDouble(Object? value, double fallback) => num.tryParse((value ?? '').toString())?.toDouble() ?? fallback;
  return SellerShippingSettings(
    insideDhakaLabel: (raw['inside_dhaka_label'] ?? 'Inside Dhaka').toString(),
    insideDhakaFee: asDouble(raw['inside_dhaka_fee'], 60),
    outsideDhakaLabel: (raw['outside_dhaka_label'] ?? 'Outside Dhaka').toString(),
    outsideDhakaFee: asDouble(raw['outside_dhaka_fee'], 120),
    cashOnDeliveryEnabled: (raw['cash_on_delivery_enabled'] as bool?) ?? true,
    processingTimeLabel: (raw['processing_time_label'] ?? '1-2 Business Days').toString(),
  );
}

SellerPayoutMethod _mapPayoutMethod(Map<String, dynamic> raw) {
  final typeRaw = (raw['method_type'] ?? raw['type'] ?? '').toString().toLowerCase();
  final type = switch (typeRaw) {
    'bkash' => SellerPayoutMethodType.bkash,
    'nagad' => SellerPayoutMethodType.nagad,
    _ => SellerPayoutMethodType.bankTransfer,
  };
  return SellerPayoutMethod(
    id: (raw['id'] ?? '').toString(),
    type: type,
    accountName: (raw['account_name'] ?? raw['name'] ?? '').toString(),
    accountNumberMasked: (raw['account_number_masked'] ?? raw['account_number'] ?? '').toString(),
    providerName: raw['provider_name']?.toString(),
    bankName: raw['bank_name']?.toString(),
    isDefault: (raw['is_default'] as bool?) ?? false,
    isActive: (raw['is_active'] as bool?) ?? true,
  );
}

SellerNotificationItem _mapNotification(Map<String, dynamic> raw) {
  return SellerNotificationItem(
    id: (raw['id'] ?? '').toString(),
    title: (raw['title'] ?? 'Notification').toString(),
    body: (raw['body'] ?? raw['message'] ?? '').toString(),
    timeAgoLabel: (raw['time_ago'] ?? raw['time'] ?? '').toString(),
    kind: (raw['kind'] ?? raw['type'] ?? 'general').toString(),
    read: (raw['read'] as bool?) ?? false,
  );
}
