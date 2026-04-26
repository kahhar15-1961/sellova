import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/errors/api_exception.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/repository_helpers.dart';

class PaymentMethodItem {
  const PaymentMethodItem({
    required this.id,
    required this.label,
    required this.subtitle,
    required this.kind,
    this.isDefault = false,
  });

  final String id;
  final String label;
  final String subtitle;
  final String kind;
  final bool isDefault;

  PaymentMethodItem copyWith({
    String? id,
    String? label,
    String? subtitle,
    String? kind,
    bool? isDefault,
  }) {
    return PaymentMethodItem(
      id: id ?? this.id,
      label: label ?? this.label,
      subtitle: subtitle ?? this.subtitle,
      kind: kind ?? this.kind,
      isDefault: isDefault ?? this.isDefault,
    );
  }

  Map<String, dynamic> toJson() => <String, dynamic>{
        'id': id,
        'label': label,
        'subtitle': subtitle,
        'kind': kind,
        'is_default': isDefault,
      };

  factory PaymentMethodItem.fromJson(Map<String, dynamic> json) {
    return PaymentMethodItem(
      id: (json['id'] ?? '').toString(),
      label: (json['label'] ?? json['display_name'] ?? '').toString(),
      subtitle: (json['subtitle'] ?? json['meta'] ?? '').toString(),
      kind: (json['kind'] ?? json['type'] ?? 'card').toString(),
      isDefault: json['is_default'] == true || json['is_default']?.toString() == '1',
    );
  }
}

class WishlistItem {
  const WishlistItem({
    required this.productId,
    required this.name,
    required this.priceLabel,
    this.imageUrl,
  });

  final int productId;
  final String name;
  final String priceLabel;
  final String? imageUrl;

  Map<String, dynamic> toJson() => <String, dynamic>{
        'product_id': productId,
        'name': name,
        'price_label': priceLabel,
        'image_url': imageUrl,
      };

  factory WishlistItem.fromJson(Map<String, dynamic> json) {
    final id = (json['product_id'] as num?)?.toInt() ?? (json['id'] as num?)?.toInt() ?? 0;
    return WishlistItem(
      productId: id,
      name: (json['name'] ?? json['title'] ?? 'Untitled').toString(),
      priceLabel: (json['price_label'] ?? json['price'] ?? json['base_price'] ?? '').toString(),
      imageUrl: json['image_url']?.toString(),
    );
  }
}

class MyReviewItem {
  const MyReviewItem({
    required this.id,
    required this.orderNo,
    required this.productName,
    required this.rating,
    required this.message,
    required this.dateLabel,
  });

  final String id;
  final String orderNo;
  final String productName;
  final int rating;
  final String message;
  final String dateLabel;

  factory MyReviewItem.fromJson(Map<String, dynamic> json) {
    return MyReviewItem(
      id: (json['id'] ?? '').toString(),
      orderNo: (json['order_no'] ?? json['order_number'] ?? '—').toString(),
      productName: (json['product_name'] ?? json['product_title'] ?? 'Product').toString(),
      rating: (json['rating'] as num?)?.toInt() ?? 0,
      message: (json['comment'] ?? json['message'] ?? '').toString(),
      dateLabel: (json['created_at_label'] ?? json['date'] ?? json['created_at'] ?? '').toString(),
    );
  }
}

class UserNotificationItem {
  const UserNotificationItem({
    required this.id,
    required this.title,
    required this.body,
    required this.channel,
    required this.isRead,
    required this.createdAt,
  });

  final int id;
  final String title;
  final String body;
  final String channel;
  final bool isRead;
  final String createdAt;

  factory UserNotificationItem.fromJson(Map<String, dynamic> json) {
    return UserNotificationItem(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: (json['title'] ?? 'Notification').toString(),
      body: (json['body'] ?? '').toString(),
      channel: (json['channel'] ?? 'in_app').toString(),
      isRead: json['is_read'] == true || json['is_read']?.toString() == '1',
      createdAt: (json['created_at'] ?? '').toString(),
    );
  }
}

class NotificationPreference {
  const NotificationPreference({
    required this.inAppEnabled,
    required this.emailEnabled,
    required this.orderUpdatesEnabled,
    required this.promotionEnabled,
  });

  final bool inAppEnabled;
  final bool emailEnabled;
  final bool orderUpdatesEnabled;
  final bool promotionEnabled;

  Map<String, dynamic> toJson() => <String, dynamic>{
        'in_app_enabled': inAppEnabled,
        'email_enabled': emailEnabled,
        'order_updates_enabled': orderUpdatesEnabled,
        'promotion_enabled': promotionEnabled,
      };

  factory NotificationPreference.fromJson(Map<String, dynamic> json) {
    return NotificationPreference(
      inAppEnabled: json['in_app_enabled'] != false,
      emailEnabled: json['email_enabled'] != false,
      orderUpdatesEnabled: json['order_updates_enabled'] != false,
      promotionEnabled: json['promotion_enabled'] != false,
    );
  }
}

class NotificationListResult {
  const NotificationListResult({
    required this.items,
    required this.unreadCount,
  });

  final List<UserNotificationItem> items;
  final int unreadCount;
}

class ProfileExtrasRepository {
  ProfileExtrasRepository({
    required ApiClient apiClient,
    required SharedPreferences preferences,
  })  : _apiClient = apiClient,
        _preferences = preferences;

  final ApiClient _apiClient;
  final SharedPreferences _preferences;

  static const String _paymentMethodsKey = 'profile_extras.v1.payment_methods';
  static const String _wishlistKey = 'profile_extras.v1.wishlist';
  static const String _notificationPrefsKey = 'profile_extras.v1.notification_prefs';

  Future<List<PaymentMethodItem>> loadPaymentMethods() async {
    try {
      final json = await _apiClient.get('/api/v1/me/payment-methods');
      final envelope = parseListEnvelope(json);
      final items = envelope.data.map(PaymentMethodItem.fromJson).toList();
      await _savePaymentMethods(items);
      return items;
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        return _loadPaymentMethodsLocal();
      }
      rethrow;
    }
  }

  Future<void> persistPaymentMethods(List<PaymentMethodItem> items) async {
    await _savePaymentMethods(items);
  }

  Future<List<PaymentMethodItem>> addPaymentMethod({
    required String kind,
    required String label,
    required String subtitle,
    bool isDefault = false,
  }) async {
    try {
      final json = await _apiClient.post(
        '/api/v1/me/payment-methods',
        data: <String, dynamic>{
          'kind': kind,
          'label': label,
          'subtitle': subtitle,
          'is_default': isDefault,
        },
      );
      final created = PaymentMethodItem.fromJson(parseObjectEnvelope(json).data);
      final current = await loadPaymentMethods();
      final next = <PaymentMethodItem>[
        created,
        ...current.where((e) => e.id != created.id).map((e) => e.copyWith(isDefault: created.isDefault ? false : e.isDefault)),
      ];
      await _savePaymentMethods(next);
      return next;
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        final current = _loadPaymentMethodsLocal();
        final id = 'pm_${DateTime.now().millisecondsSinceEpoch}';
        final next = <PaymentMethodItem>[
          PaymentMethodItem(
            id: id,
            label: label,
            subtitle: subtitle,
            kind: kind,
            isDefault: current.isEmpty ? true : isDefault,
          ),
          ...current.map((e) => e.copyWith(isDefault: isDefault ? false : e.isDefault)),
        ];
        await _savePaymentMethods(next);
        return next;
      }
      rethrow;
    }
  }

  Future<List<PaymentMethodItem>> setDefaultPaymentMethod(String id) async {
    try {
      await _apiClient.patch('/api/v1/me/payment-methods/$id');
      return loadPaymentMethods();
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        final current = _loadPaymentMethodsLocal();
        final next = current.map((e) => e.copyWith(isDefault: e.id == id)).toList();
        await _savePaymentMethods(next);
        return next;
      }
      rethrow;
    }
  }

  Future<List<PaymentMethodItem>> removePaymentMethod(String id) async {
    try {
      await _apiClient.delete('/api/v1/me/payment-methods/$id');
      return loadPaymentMethods();
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        final current = _loadPaymentMethodsLocal();
        final next = current.where((e) => e.id != id).toList();
        if (next.isNotEmpty && !next.any((e) => e.isDefault)) {
          next[0] = next[0].copyWith(isDefault: true);
        }
        await _savePaymentMethods(next);
        return next;
      }
      rethrow;
    }
  }

  Future<List<WishlistItem>> loadWishlist() async {
    try {
      final json = await _apiClient.get('/api/v1/me/wishlist');
      final envelope = parseListEnvelope(json);
      final items = envelope.data.map(WishlistItem.fromJson).toList();
      await _saveWishlist(items);
      return items;
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        return _loadWishlistLocal();
      }
      rethrow;
    }
  }

  Future<void> persistWishlist(List<WishlistItem> items) async {
    await _saveWishlist(items);
  }

  Future<List<WishlistItem>> addWishlistItem(int productId) async {
    try {
      await _apiClient.post('/api/v1/me/wishlist', data: <String, dynamic>{'product_id': productId});
      return loadWishlist();
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        return _loadWishlistLocal();
      }
      rethrow;
    }
  }

  Future<List<WishlistItem>> removeWishlistItem(int productId) async {
    try {
      await _apiClient.delete('/api/v1/me/wishlist/$productId');
      return loadWishlist();
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        final current = _loadWishlistLocal();
        final next = current.where((e) => e.productId != productId).toList();
        await _saveWishlist(next);
        return next;
      }
      rethrow;
    }
  }

  Future<List<MyReviewItem>> loadMyReviews() async {
    try {
      final json = await _apiClient.get('/api/v1/me/reviews');
      final envelope = parseListEnvelope(json);
      return envelope.data.map(MyReviewItem.fromJson).toList();
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        return _loadMyReviewsFromOrders();
      }
      rethrow;
    }
  }

  Future<NotificationListResult> loadNotifications() async {
    try {
      final json = await _apiClient.get('/api/v1/me/notifications');
      final envelope = parseObjectEnvelope(json);
      final data = envelope.data;
      final rawItems = (data['items'] as List?) ?? const <dynamic>[];
      final items = rawItems.whereType<Map>().map((e) => UserNotificationItem.fromJson(Map<String, dynamic>.from(e))).toList();
      final unreadCount = (data['unread_count'] as num?)?.toInt() ?? 0;
      return NotificationListResult(items: items, unreadCount: unreadCount);
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        return const NotificationListResult(items: <UserNotificationItem>[], unreadCount: 0);
      }
      rethrow;
    }
  }

  Future<void> markNotificationRead(int id) async {
    try {
      await _apiClient.patch('/api/v1/me/notifications/$id/read');
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        return;
      }
      rethrow;
    }
  }

  Future<void> markAllNotificationsRead() async {
    try {
      await _apiClient.post('/api/v1/me/notifications/read-all');
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        return;
      }
      rethrow;
    }
  }

  Future<NotificationPreference> loadNotificationPreferences() async {
    try {
      final json = await _apiClient.get('/api/v1/me/notifications/preferences');
      final pref = NotificationPreference.fromJson(parseObjectEnvelope(json).data);
      await _preferences.setString(_notificationPrefsKey, jsonEncode(pref.toJson()));
      return pref;
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        final raw = _preferences.getString(_notificationPrefsKey);
        if (raw != null && raw.isNotEmpty) {
          try {
            final decoded = jsonDecode(raw);
            if (decoded is Map) {
              return NotificationPreference.fromJson(Map<String, dynamic>.from(decoded));
            }
          } catch (_) {}
        }
        return const NotificationPreference(
          inAppEnabled: true,
          emailEnabled: true,
          orderUpdatesEnabled: true,
          promotionEnabled: true,
        );
      }
      rethrow;
    }
  }

  Future<NotificationPreference> updateNotificationPreferences(NotificationPreference next) async {
    try {
      final json = await _apiClient.patch('/api/v1/me/notifications/preferences', data: next.toJson());
      final pref = NotificationPreference.fromJson(parseObjectEnvelope(json).data);
      await _preferences.setString(_notificationPrefsKey, jsonEncode(pref.toJson()));
      return pref;
    } catch (e) {
      if (e is ApiException &&
          (e.type == ApiExceptionType.notFound || e.type == ApiExceptionType.forbidden)) {
        await _preferences.setString(_notificationPrefsKey, jsonEncode(next.toJson()));
        return next;
      }
      rethrow;
    }
  }

  Future<List<MyReviewItem>> _loadMyReviewsFromOrders() async {
    final json = await _apiClient.get('/api/v1/orders', queryParameters: <String, dynamic>{'per_page': 20});
    final paginated = parsePaginatedObjectList(json);
    final out = <MyReviewItem>[];
    for (final row in paginated.items) {
      final review = row['review'];
      if (review is! Map) {
        continue;
      }
      final m = Map<String, dynamic>.from(review);
      final orderNo = (row['order_number'] ?? row['order_no'] ?? '—').toString();
      out.add(
        MyReviewItem(
          id: (m['id'] ?? '').toString(),
          orderNo: orderNo,
          productName: (m['product_name'] ?? row['title'] ?? 'Order item').toString(),
          rating: (m['rating'] as num?)?.toInt() ?? 0,
          message: (m['comment'] ?? m['message'] ?? '').toString(),
          dateLabel: (m['created_at'] ?? row['created_at'] ?? '').toString(),
        ),
      );
    }
    return out;
  }

  List<PaymentMethodItem> _loadPaymentMethodsLocal() {
    final raw = _preferences.getString(_paymentMethodsKey);
    if (raw == null || raw.isEmpty) {
      return const <PaymentMethodItem>[
        PaymentMethodItem(
          id: 'pm_1',
          label: 'Visa **** 4242',
          subtitle: 'Expires 09/28',
          kind: 'card',
          isDefault: true,
        ),
      ];
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is List) {
        return decoded
            .whereType<Map>()
            .map((e) => PaymentMethodItem.fromJson(Map<String, dynamic>.from(e)))
            .toList();
      }
    } catch (_) {}
    return const <PaymentMethodItem>[];
  }

  List<WishlistItem> _loadWishlistLocal() {
    final raw = _preferences.getString(_wishlistKey);
    if (raw == null || raw.isEmpty) {
      return const <WishlistItem>[
        WishlistItem(productId: 1, name: 'Wireless Earbuds Pro', priceLabel: 'USD 89.00'),
        WishlistItem(productId: 2, name: 'Bluetooth Speaker Mini', priceLabel: 'USD 49.00'),
      ];
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is List) {
        return decoded
            .whereType<Map>()
            .map((e) => WishlistItem.fromJson(Map<String, dynamic>.from(e)))
            .toList();
      }
    } catch (_) {}
    return const <WishlistItem>[];
  }

  Future<void> _savePaymentMethods(List<PaymentMethodItem> items) async {
    await _preferences.setString(
      _paymentMethodsKey,
      jsonEncode(items.map((e) => e.toJson()).toList()),
    );
  }

  Future<void> _saveWishlist(List<WishlistItem> items) async {
    await _preferences.setString(
      _wishlistKey,
      jsonEncode(items.map((e) => e.toJson()).toList()),
    );
  }
}

