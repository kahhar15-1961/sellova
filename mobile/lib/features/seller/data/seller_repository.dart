import 'package:dio/dio.dart';
import 'dart:typed_data';

import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';
import '../../../core/pagination/pagination_meta.dart';
import '../../products/data/product_repository.dart';
import '../domain/seller_models.dart';

class SellerRepository {
  SellerRepository(this._apiClient, {required String baseUrl})
      : _baseUrl = baseUrl;

  final ApiClient _apiClient;
  final String _baseUrl;

  Future<PaginatedResult<ProductDto>> listProducts({
    int page = 1,
    int perPage = 50,
  }) async {
    final json = await _apiClient
        .get('/api/v1/seller/products', queryParameters: <String, dynamic>{
      'page': page,
      'per_page': perPage,
    });
    final result = parsePaginatedObjectList(json);
    return PaginatedResult<ProductDto>(
      items: result.items.map(ProductDto.new).toList(),
      meta: result.meta,
    );
  }

  Future<List<Map<String, dynamic>>> listSellerOrders() async {
    final json = await _apiClient.get('/api/v1/seller/orders',
        queryParameters: <String, dynamic>{'page': 1, 'per_page': 30});
    return parsePaginatedObjectList(json).items;
  }

  Future<void> updateOrderStatus({
    required int orderId,
    required String status,
  }) async {
    await _apiClient.post('/api/v1/seller/orders/$orderId/status',
        data: <String, dynamic>{'status': status});
  }

  Future<void> addShippingDetails({
    required int orderId,
    required String courierCompany,
    required String trackingId,
    required String shippingDateIso,
    String? note,
  }) async {
    await _apiClient.post('/api/v1/seller/orders/$orderId/shipping',
        data: <String, dynamic>{
          'courier_company': courierCompany,
          'tracking_id': trackingId,
          'shipping_date': shippingDateIso,
          if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
        });
  }

  Future<void> submitDigitalDelivery({
    required int orderId,
    String? note,
  }) async {
    await _apiClient.post('/api/v1/seller/orders/$orderId/delivery',
        data: <String, dynamic>{
          if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
        });
  }

  Future<void> postReviewReply({
    required int reviewId,
    required String reply,
  }) async {
    await _apiClient.post('/api/v1/seller/reviews/$reviewId/reply',
        data: <String, dynamic>{'reply': reply});
  }

  Future<List<SellerReview>> listSellerReviews() async {
    final json = await _apiClient.get('/api/v1/seller/reviews');
    final envelope = parseListEnvelope(json);
    return envelope.data.map(_mapSellerReview).toList();
  }

  Future<void> createProduct({
    required String title,
    required double price,
    required int stock,
    required String category,
    required String description,
    required String productType,
    String? imageUrl,
    List<String> imageUrls = const <String>[],
    Map<String, dynamic> attributes = const <String, dynamic>{},
  }) async {
    final categoryId = await _resolveCategoryId(category);
    await _apiClient.post('/api/v1/seller/products', data: <String, dynamic>{
      'title': title,
      'currency': 'BDT',
      'base_price': price.toStringAsFixed(4),
      'stock': stock,
      'category_id': categoryId,
      'description': description,
      'product_type': _normalizeProductType(productType),
      'attributes': attributes,
      if ((imageUrl ?? '').trim().isNotEmpty) 'image_url': imageUrl!.trim(),
      if (imageUrls.isNotEmpty) 'images': imageUrls,
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
    required String productType,
    String? imageUrl,
    List<String> imageUrls = const <String>[],
    Map<String, dynamic> attributes = const <String, dynamic>{},
  }) async {
    final categoryId = await _resolveCategoryId(category);
    await _apiClient
        .patch('/api/v1/seller/products/$productId', data: <String, dynamic>{
      'title': title,
      'currency': 'BDT',
      'base_price': price.toStringAsFixed(4),
      'stock': stock,
      'category_id': categoryId,
      'description': description,
      'product_type': _normalizeProductType(productType),
      'attributes': attributes,
      if ((imageUrl ?? '').trim().isNotEmpty) 'image_url': imageUrl!.trim(),
      'images': imageUrls,
    });
  }

  Future<void> toggleProduct({
    required int productId,
    required bool active,
  }) async {
    await _apiClient.post('/api/v1/seller/products/$productId/toggle',
        data: <String, dynamic>{'active': active});
  }

  Future<SellerStoreSettings> getStoreSettings() async {
    final json = await _apiClient.get('/api/v1/me/seller');
    final data = parseObjectEnvelope(json).data;
    return _mapStoreSettings(data);
  }

  Future<SellerStoreSettings> updateStoreSettings({
    required String storeName,
    required String storeDescription,
    String? storeLogoUrl,
    String? bannerImageUrl,
    String? contactEmail,
    String? contactPhone,
    String? addressLine,
    String? city,
    String? region,
    String? postalCode,
    String? country,
  }) async {
    final json = await _apiClient.patch(
      '/api/v1/me/seller',
      data: <String, dynamic>{
        'store_name': storeName,
        'store_description': storeDescription,
        if (storeLogoUrl != null && storeLogoUrl.trim().isNotEmpty)
          'store_logo_url': storeLogoUrl.trim(),
        if (bannerImageUrl != null && bannerImageUrl.trim().isNotEmpty)
          'banner_image_url': bannerImageUrl.trim(),
        if (contactEmail != null && contactEmail.trim().isNotEmpty)
          'contact_email': contactEmail.trim(),
        if (contactPhone != null && contactPhone.trim().isNotEmpty)
          'contact_phone': contactPhone.trim(),
        if (addressLine != null && addressLine.trim().isNotEmpty)
          'address_line': addressLine.trim(),
        if (city != null && city.trim().isNotEmpty) 'city': city.trim(),
        if (region != null && region.trim().isNotEmpty) 'region': region.trim(),
        if (postalCode != null && postalCode.trim().isNotEmpty)
          'postal_code': postalCode.trim(),
        if (country != null && country.trim().isNotEmpty)
          'country': country.trim(),
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

  Future<SellerShippingSettings> updateShippingSettings(
      SellerShippingSettings value) async {
    final json = await _apiClient.patch(
      '/api/v1/seller/shipping/settings',
      data: <String, dynamic>{
        'inside_dhaka_label': value.insideDhakaLabel,
        'inside_dhaka_fee': value.insideDhakaFee,
        'outside_dhaka_label': value.outsideDhakaLabel,
        'outside_dhaka_fee': value.outsideDhakaFee,
        'cash_on_delivery_enabled': value.cashOnDeliveryEnabled,
        'processing_time_label': value.processingTimeLabel,
        'shipping_methods': value.shippingMethods
            .map((method) => <String, dynamic>{
                  'shipping_method_id': method.shippingMethodId,
                  'method_name': method.methodName,
                  'price': method.price,
                  'processing_time_label': method.processingTimeLabel,
                  'is_enabled': method.isEnabled,
                  'sort_order': method.sortOrder,
                })
            .toList(),
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

  Future<SellerWithdrawalSettings> getWithdrawalSettings() async {
    final json = await _apiClient.get('/api/v1/withdrawals/settings');
    final data = parseObjectEnvelope(json).data;
    final rawMinimum =
        data['minimum_withdrawal_amount'] ?? data['minimum'] ?? '500';
    return SellerWithdrawalSettings(
      minimumWithdrawalAmount: double.tryParse(rawMinimum.toString()) ?? 500,
      currency: (data['currency'] ?? 'BDT').toString().toUpperCase(),
    );
  }

  Future<List<SellerPayoutMethod>> upsertPayoutMethod({
    required SellerPayoutMethodType type,
    required String accountName,
    required String accountNumber,
    String? bankName,
    String? branchName,
    String? routingNumber,
    String? accountType,
    bool asDefault = false,
  }) async {
    final json = await _apiClient
        .post('/api/v1/seller/payout-methods', data: <String, dynamic>{
      'method_type': type.apiValue,
      'account_name': accountName,
      'account_number': accountNumber,
      if (bankName != null && bankName.trim().isNotEmpty)
        'bank_name': bankName.trim(),
      if (branchName != null && branchName.trim().isNotEmpty)
        'branch_name': branchName.trim(),
      if (routingNumber != null && routingNumber.trim().isNotEmpty)
        'routing_number': routingNumber.trim(),
      if (accountType != null && accountType.trim().isNotEmpty)
        'account_type_label': accountType.trim(),
      'is_default': asDefault,
    });
    final result = parsePaginatedObjectList(json);
    return result.items.map(_mapPayoutMethod).toList();
  }

  Future<List<SellerPayoutMethod>> deletePayoutMethod(String id) async {
    final json = await _apiClient.delete('/api/v1/seller/payout-methods/$id');
    final result = parsePaginatedObjectList(json);
    return result.items.map(_mapPayoutMethod).toList();
  }

  Future<void> requestWithdrawal({
    required SellerPayoutMethodType methodType,
    required String accountNumber,
    required String amountText,
    int? walletId,
    String? currency,
  }) async {
    await _apiClient.post('/api/v1/withdrawals', data: <String, dynamic>{
      'payout_method': methodType.apiValue,
      'account_number': accountNumber,
      'amount': amountText,
      if (walletId != null && walletId > 0) 'wallet_id': walletId,
      if ((currency ?? '').trim().isNotEmpty)
        'currency': currency!.trim().toUpperCase(),
    });
  }

  Future<void> submitCategoryRequest({
    required String name,
    int? parentId,
    String? reason,
    String? exampleProductName,
  }) async {
    await _apiClient
        .post('/api/v1/seller/category-requests', data: <String, dynamic>{
      'name': name,
      if (parentId != null) 'parent_id': parentId,
      if ((reason ?? '').trim().isNotEmpty) 'reason': reason!.trim(),
      if ((exampleProductName ?? '').trim().isNotEmpty)
        'example_product_name': exampleProductName!.trim(),
    });
  }

  Future<List<SellerNotificationItem>> listSellerNotifications() async {
    final json = await _apiClient.get('/api/v1/seller/notifications',
        queryParameters: <String, dynamic>{'page': 1, 'per_page': 40});
    final result = parsePaginatedObjectList(json);
    return result.items.map(_mapNotification).toList();
  }

  Future<void> markAllNotificationsRead() async {
    await _apiClient.post('/api/v1/seller/notifications/mark-all-read');
  }

  /// Storefront / seller media upload (logo, banner, etc.).
  Future<String> uploadStoreMedia(String filePath) async {
    final result = await uploadSellerMedia(filePath, purpose: 'store_media');
    return result.storagePath;
  }

  Future<SellerUploadResult> uploadSellerMedia(
    String filePath, {
    String purpose = 'kyc',
    Uint8List? bytes,
    String? fileName,
  }) async {
    final resolvedName = (fileName ?? filePath.split('/').last).trim();
    final multipart = bytes != null
        ? MultipartFile.fromBytes(bytes,
            filename: resolvedName.isEmpty ? 'upload.bin' : resolvedName)
        : await MultipartFile.fromFile(
            filePath,
            filename: resolvedName.isEmpty ? null : resolvedName,
          );
    final form = FormData.fromMap(<String, dynamic>{
      'file': multipart,
      'purpose': purpose,
    });
    final json = await _apiClient.postMultipart('/api/v1/seller/media/upload',
        data: form);
    Map<String, dynamic> payload;
    try {
      payload = parseObjectEnvelope(json).data;
    } catch (_) {
      payload = Map<String, dynamic>.from(json);
    }
    final storagePath =
        (payload['storage_path'] ?? payload['url'] ?? payload['path'] ?? '')
            .toString();
    if (storagePath.isEmpty) {
      throw const FormatException('Upload response missing file URL');
    }
    return SellerUploadResult(
      storagePath: storagePath,
      originalName: (payload['original_name'] ?? '').toString(),
      checksumSha256: (payload['checksum_sha256'] ?? '').toString(),
      mimeType: (payload['mime_type'] ?? '').toString(),
      size: (payload['size'] as num?)?.toInt(),
    );
  }

  Future<int> _resolveCategoryId(String category) async {
    final trimmed = category.trim();
    if (trimmed.isEmpty) {
      return 1;
    }
    final numeric = int.tryParse(trimmed);
    if (numeric != null && numeric > 0) {
      return numeric;
    }
    final json = await _apiClient.get('/api/v1/categories');
    final envelope = parseListEnvelope(json);
    for (final row in envelope.data) {
      final id = (row['id'] as num?)?.toInt();
      final name = (row['name'] ?? '').toString().trim();
      final slug = (row['slug'] ?? '').toString().trim();
      if (id != null &&
          (name.toLowerCase() == trimmed.toLowerCase() ||
              slug.toLowerCase() == trimmed.toLowerCase())) {
        return id;
      }
    }
    final first = envelope.data.isNotEmpty
        ? (envelope.data.first['id'] as num?)?.toInt()
        : null;
    if (first == null) {
      throw const FormatException(
          'No active product categories are available.');
    }
    throw FormatException('Selected category "$trimmed" is not available.');
  }

  String _normalizeProductType(String productType) {
    final normalized = productType.trim().toLowerCase();
    if (normalized == 'manual') {
      return 'manual_delivery';
    }
    if (normalized == 'instant' ||
        normalized == 'instant_delivery' ||
        normalized == 'instant-delivery') {
      return 'instant_delivery';
    }
    if (normalized == 'digital') {
      return 'digital';
    }
    return 'physical';
  }

  SellerStoreSettings _mapStoreSettings(Map<String, dynamic> raw) {
    return SellerStoreSettings(
      storeName: (raw['store_name'] ?? raw['display_name'] ?? raw['name'] ?? '')
          .toString(),
      storeDescription: (raw['store_description'] ??
              raw['legal_name'] ??
              raw['description'] ??
              '')
          .toString(),
      storeLogoUrl: _mediaUrl(raw['store_logo_url']?.toString()),
      bannerImageUrl: _mediaUrl(raw['banner_image_url']?.toString()),
      contactEmail: raw['contact_email']?.toString(),
      contactPhone: raw['contact_phone']?.toString(),
      addressLine: raw['address_line']?.toString(),
      city: raw['city']?.toString(),
      region: raw['region']?.toString(),
      postalCode: raw['postal_code']?.toString(),
      country: raw['country']?.toString(),
      storeAddress: raw['store_address']?.toString(),
    );
  }

  String? _mediaUrl(String? raw) {
    final value = (raw ?? '').trim();
    if (value.isEmpty) {
      return null;
    }
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }
    if (value.startsWith('/api/v1/media/')) {
      return '$_baseUrl$value';
    }
    if (value.startsWith('seller-uploads/')) {
      return '$_baseUrl/api/v1/media/$value';
    }
    return value.startsWith('/') ? '$_baseUrl$value' : value;
  }

  ProductDto normalizeProductDto(ProductDto product) {
    final raw = Map<String, dynamic>.from(product.raw);
    final imageUrl = _mediaUrl(product.primaryImageUrl);
    if (imageUrl != null && imageUrl.isNotEmpty) {
      raw['image_url'] = imageUrl;
      raw['thumbnail_url'] = imageUrl;
    }
    final images =
        product.imageUrls.map(_mediaUrl).whereType<String>().toList();
    if (images.isNotEmpty) {
      raw['images'] = images;
      raw['image_url'] = images.first;
      raw['thumbnail_url'] = images.first;
    }

    return ProductDto(raw);
  }
}

SellerShippingSettings _mapShippingSettings(Map<String, dynamic> raw) {
  double asDouble(Object? value, double fallback) =>
      num.tryParse((value ?? '').toString())?.toDouble() ?? fallback;
  bool asBool(Object? value, bool fallback) {
    if (value is bool) {
      return value;
    }
    final normalized = (value ?? '').toString().toLowerCase();
    if (normalized == '1' || normalized == 'true') {
      return true;
    }
    if (normalized == '0' || normalized == 'false') {
      return false;
    }
    return fallback;
  }

  SellerShippingMethodOption optionFrom(Object? value) {
    final map =
        value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
    return SellerShippingMethodOption(
      id: (map['id'] as num?)?.toInt() ?? int.tryParse('${map['id']}') ?? 0,
      code: (map['code'] ?? '').toString(),
      name: (map['name'] ?? '').toString(),
      suggestedFee: asDouble(map['suggested_fee'], 0),
      processingTimeLabel: (map['processing_time_label'] ?? '').toString(),
      sortOrder: (map['sort_order'] as num?)?.toInt() ??
          int.tryParse('${map['sort_order']}') ??
          0,
    );
  }

  SellerShippingMethodSelection selectionFrom(Object? value) {
    final map =
        value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
    return SellerShippingMethodSelection(
      shippingMethodId: (map['shipping_method_id'] as num?)?.toInt() ??
          int.tryParse('${map['shipping_method_id']}') ??
          0,
      methodCode: (map['method_code'] ?? '').toString(),
      methodName: (map['method_name'] ?? '').toString(),
      suggestedFee: asDouble(map['suggested_fee'], 0),
      price: asDouble(map['price'] ?? map['fee'], 0),
      processingTimeLabel: (map['processing_time_label'] ?? '').toString(),
      isEnabled: asBool(map['is_enabled'], true),
      sortOrder: (map['sort_order'] as num?)?.toInt() ??
          int.tryParse('${map['sort_order']}') ??
          0,
    );
  }

  final availableMethods = raw['available_methods'] is List
      ? (raw['available_methods'] as List)
          .map(optionFrom)
          .where((item) => item.id > 0 && item.name.trim().isNotEmpty)
          .toList()
      : <SellerShippingMethodOption>[];
  final selectedMethods = raw['shipping_methods'] is List
      ? (raw['shipping_methods'] as List)
          .map(selectionFrom)
          .where((item) =>
              item.shippingMethodId > 0 && item.methodName.trim().isNotEmpty)
          .toList()
      : <SellerShippingMethodSelection>[];
  final processingOptions = raw['processing_time_options'] is List
      ? (raw['processing_time_options'] as List)
          .map((item) => item.toString())
          .where((item) => item.trim().isNotEmpty)
          .toList()
      : <String>[];

  return SellerShippingSettings(
    insideDhakaLabel: (raw['inside_dhaka_label'] ?? '').toString(),
    insideDhakaFee: asDouble(raw['inside_dhaka_fee'], 0),
    outsideDhakaLabel: (raw['outside_dhaka_label'] ?? '').toString(),
    outsideDhakaFee: asDouble(raw['outside_dhaka_fee'], 0),
    cashOnDeliveryEnabled: asBool(raw['cash_on_delivery_enabled'], true),
    processingTimeLabel: (raw['processing_time_label'] ?? '').toString(),
    isConfigured: (raw['is_configured'] as bool?) ?? false,
    availableMethods: availableMethods,
    shippingMethods: selectedMethods,
    processingTimeOptions: processingOptions,
  );
}

SellerPayoutMethod _mapPayoutMethod(Map<String, dynamic> raw) {
  final typeRaw =
      (raw['method_type'] ?? raw['type'] ?? '').toString().toLowerCase();
  final type = switch (typeRaw) {
    'bkash' => SellerPayoutMethodType.bkash,
    'nagad' => SellerPayoutMethodType.nagad,
    _ => SellerPayoutMethodType.bankTransfer,
  };
  return SellerPayoutMethod(
    id: (raw['id'] ?? '').toString(),
    type: type,
    accountName: (raw['account_name'] ?? raw['name'] ?? '').toString(),
    accountNumberMasked:
        (raw['account_number_masked'] ?? raw['account_number'] ?? '')
            .toString(),
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
    read: (raw['read'] as bool?) ?? (raw['is_read'] as bool?) ?? false,
    href: (raw['href'] ?? '').toString(),
  );
}

SellerReview _mapSellerReview(Map<String, dynamic> raw) {
  int rating(Object? value) {
    final parsed = (value as num?)?.toInt() ?? int.tryParse('$value') ?? 0;
    return parsed.clamp(0, 5);
  }

  List<String> photoUrls(Object? value) {
    if (value is List) {
      return value.map((item) => item.toString()).toList();
    }
    return const <String>[];
  }

  return SellerReview(
    id: (raw['id'] as num?)?.toInt() ?? int.tryParse('${raw['id']}') ?? 0,
    buyerName:
        (raw['buyer_name'] ?? raw['customer_name'] ?? raw['buyer'] ?? 'Buyer')
            .toString(),
    date: DateTime.tryParse((raw['created_at'] ?? '').toString())?.toLocal() ??
        DateTime.now(),
    rating: rating(raw['rating']),
    comment: (raw['comment'] ?? '').toString(),
    productName:
        (raw['product_name'] ?? raw['product_title'] ?? 'Product').toString(),
    orderNumber: (raw['order_number'] ?? raw['order_no'] ?? '').toString(),
    photoUrls: photoUrls(raw['photo_urls']),
    isVerifiedBuyer: (raw['is_verified_buyer'] as bool?) ?? true,
    sellerReply: raw['seller_reply']?.toString(),
    sellerReplyDate:
        DateTime.tryParse((raw['seller_reply_date'] ?? '').toString())
            ?.toLocal(),
    moderationState: raw['moderation_state']?.toString(),
  );
}
