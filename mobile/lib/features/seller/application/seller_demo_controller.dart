import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../domain/seller_models.dart';

final sellerOrdersProvider = NotifierProvider<SellerOrdersController, List<SellerOrder>>(SellerOrdersController.new);
final sellerReviewsProvider = NotifierProvider<SellerReviewsController, List<SellerReview>>(SellerReviewsController.new);
final sellerBusyProvider = StateProvider<bool>((_) => false);
final sellerErrorProvider = StateProvider<String?>((_) => null);

class SellerOrdersController extends Notifier<List<SellerOrder>> {
  bool _loadedRemote = false;

  @override
  List<SellerOrder> build() {
    Future<void>.microtask(_hydrateFromBackend);
    return _seed();
  }

  List<SellerOrder> _seed() {
    return <SellerOrder>[
      SellerOrder(
        id: 123,
        orderNumber: 'ORD-2025-000123',
        customerName: 'Ahammad Uddin',
        orderDate: DateTime(2025, 5, 29, 10, 30),
        totalAmount: 2450,
        currency: 'BDT',
        productTitle: 'Wireless Noise Cancelling Headphones',
        productImageUrl: null,
        shippingAddress: '123 Green Road, Dhanmondi, Dhaka 1205, Bangladesh',
        paymentMethod: 'bKash',
        status: SellerOrderStatus.toShip,
      ),
      SellerOrder(
        id: 122,
        orderNumber: 'ORD-2025-000122',
        customerName: 'Rokib Hasan',
        orderDate: DateTime(2025, 5, 28, 9, 15),
        totalAmount: 1250,
        currency: 'BDT',
        productTitle: 'Bluetooth Speaker',
        productImageUrl: null,
        shippingAddress: 'House 15, Road 7, Mirpur DOHS, Dhaka 1216',
        paymentMethod: 'bKash',
        status: SellerOrderStatus.shipped,
        trackingId: 'BD1234567890',
        courierCompany: 'Pathao',
        shippingDate: DateTime(2025, 5, 28, 10, 0),
      ),
      SellerOrder(
        id: 121,
        orderNumber: 'ORD-2025-000121',
        customerName: 'Radia Akter',
        orderDate: DateTime(2025, 5, 27, 11, 20),
        totalAmount: 3990,
        currency: 'BDT',
        productTitle: 'Travel Backpack',
        productImageUrl: null,
        shippingAddress: 'House 15, Road 7, Mirpur DOHS, Dhaka 1216',
        paymentMethod: 'bKash',
        status: SellerOrderStatus.delivered,
        deliveredOn: DateTime(2025, 5, 29, 14, 15),
      ),
      SellerOrder(
        id: 120,
        orderNumber: 'ORD-2025-000120',
        customerName: 'Nusrat Jahan',
        orderDate: DateTime(2025, 5, 26, 12, 0),
        totalAmount: 750,
        currency: 'BDT',
        productTitle: 'Wired Earphones',
        productImageUrl: null,
        shippingAddress: 'Banasree, Dhaka, Bangladesh',
        paymentMethod: 'Card',
        status: SellerOrderStatus.cancelled,
      ),
    ];
  }

  Future<void> _hydrateFromBackend() async {
    if (_loadedRemote) return;
    _loadedRemote = true;
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      final rows = await ref.read(sellerRepositoryProvider).listSellerOrders();
      final mapped = rows.map(_fromOrderRaw).toList();
      if (mapped.isNotEmpty) {
        state = mapped;
      }
    } catch (e) {
      ref.read(sellerErrorProvider.notifier).state = e.toString();
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> refresh() async {
    _loadedRemote = false;
    await _hydrateFromBackend();
  }

  SellerOrder? byId(int orderId) {
    for (final item in state) {
      if (item.id == orderId) return item;
    }
    return null;
  }

  Future<void> updateStatus(int orderId, SellerOrderStatus status) async {
    final prev = state;
    state = state.map((e) => e.id == orderId ? e.copyWith(status: status) : e).toList();
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref.read(sellerRepositoryProvider).updateOrderStatus(orderId: orderId, status: status.name);
    } catch (e) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state = e.toString();
      rethrow;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> addShippingDetails({
    required int orderId,
    required String courierCompany,
    required String trackingId,
    required DateTime shippingDate,
    String? note,
  }) async {
    final prev = state;
    state = state
        .map(
          (e) => e.id == orderId
              ? e.copyWith(
                  status: SellerOrderStatus.shipped,
                  courierCompany: courierCompany,
                  trackingId: trackingId,
                  shippingDate: shippingDate,
                  shippingNote: note,
                )
              : e,
        )
        .toList();
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref.read(sellerRepositoryProvider).addShippingDetails(
            orderId: orderId,
            courierCompany: courierCompany,
            trackingId: trackingId,
            shippingDateIso: shippingDate.toIso8601String(),
            note: note,
          );
    } catch (e) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state = e.toString();
      rethrow;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }
}

class SellerReviewsController extends Notifier<List<SellerReview>> {
  @override
  List<SellerReview> build() {
    return <SellerReview>[
      SellerReview(
        id: 1,
        buyerName: 'Ahammad Uddin',
        date: DateTime(2025, 6, 2),
        rating: 4,
        comment: 'Sound quality is excellent and battery backup is great. Very comfortable to use. Recommended!',
        productName: 'Wireless Noise Cancelling Headphones',
        orderNumber: 'ORD-2025-000125',
        photoUrls: const <String>['1', '2', '3'],
        isVerifiedBuyer: true,
        moderationState: 'under_review',
      ),
    ];
  }

  SellerReview? byId(int reviewId) {
    for (final item in state) {
      if (item.id == reviewId) return item;
    }
    return null;
  }

  Future<void> postReply(int reviewId, String reply) async {
    final prev = state;
    state = state
        .map((e) => e.id == reviewId ? e.copyWith(sellerReply: reply.trim(), sellerReplyDate: DateTime.now(), moderationState: null) : e)
        .toList();
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref.read(sellerRepositoryProvider).postReviewReply(reviewId: reviewId, reply: reply.trim());
    } catch (e) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state = e.toString();
      rethrow;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }
}

SellerOrder _fromOrderRaw(Map<String, dynamic> raw) {
  final id = (raw['id'] as num?)?.toInt() ?? 0;
  final number = (raw['order_number'] ?? 'ORD-$id').toString();
  final customer = (raw['buyer_name'] ?? raw['customer_name'] ?? raw['user_name'] ?? 'Customer').toString();
  final createdRaw = (raw['created_at'] ?? '').toString();
  final created = DateTime.tryParse(createdRaw)?.toLocal() ?? DateTime.now();
  final totalNum = num.tryParse((raw['total_amount'] ?? raw['total'] ?? 0).toString()) ?? 0;
  final currency = (raw['currency'] ?? 'BDT').toString();
  final items = raw['items'];
  final firstItem = (items is List && items.isNotEmpty && items.first is Map) ? Map<String, dynamic>.from(items.first as Map) : <String, dynamic>{};
  final title = (firstItem['title'] ?? firstItem['name'] ?? raw['product_name'] ?? 'Order item').toString();
  final address = (raw['shipping_address'] ?? raw['address'] ?? 'Address unavailable').toString();
  final payment = (raw['payment_method'] ?? raw['payment_channel'] ?? 'Unknown').toString();
  final statusRaw = (raw['status'] ?? raw['order_status'] ?? '').toString().toLowerCase();
  final status = statusRaw.contains('cancel')
      ? SellerOrderStatus.cancelled
      : statusRaw.contains('deliver')
          ? SellerOrderStatus.delivered
          : statusRaw.contains('ship')
              ? SellerOrderStatus.shipped
              : statusRaw.contains('process')
                  ? SellerOrderStatus.processing
                  : SellerOrderStatus.toShip;

  return SellerOrder(
    id: id,
    orderNumber: number,
    customerName: customer,
    orderDate: created,
    totalAmount: totalNum.toDouble(),
    currency: currency,
    productTitle: title,
    productImageUrl: null,
    shippingAddress: address,
    paymentMethod: payment,
    status: status,
    trackingId: (raw['tracking_id'] ?? raw['tracking_number'])?.toString(),
    courierCompany: raw['courier_company']?.toString(),
  );
}
