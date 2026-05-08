import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/errors/api_exception.dart';
import '../domain/seller_models.dart';
import 'seller_failure.dart';

final sellerOrdersProvider =
    NotifierProvider<SellerOrdersController, List<SellerOrder>>(
        SellerOrdersController.new);
final sellerReviewsProvider =
    NotifierProvider<SellerReviewsController, List<SellerReview>>(
        SellerReviewsController.new);
final sellerBusyProvider = StateProvider<bool>((_) => false);
final sellerErrorProvider = StateProvider<String?>((_) => null);

class SellerOrdersController extends Notifier<List<SellerOrder>> {
  @override
  List<SellerOrder> build() {
    Future<void>.microtask(_hydrateFromBackend);
    return const <SellerOrder>[];
  }

  Future<void> _hydrateFromBackend() async {
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      final rows = await ref.read(sellerRepositoryProvider).listSellerOrders();
      final mapped = rows.map(_fromOrderRaw).toList();
      state = mapped;
    } catch (e) {
      if (_isMissingSellerProfile(e)) {
        state = const <SellerOrder>[];
        ref.read(sellerErrorProvider.notifier).state = null;
      } else {
        ref.read(sellerErrorProvider.notifier).state =
            SellerFailure.from(e).message;
      }
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> refresh() async {
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
    state = state
        .map((e) => e.id == orderId ? e.copyWith(status: status) : e)
        .toList();
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref
          .read(sellerRepositoryProvider)
          .updateOrderStatus(orderId: orderId, status: status.name);
      await refresh();
    } catch (e) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state =
          SellerFailure.from(e).message;
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
      await refresh();
    } catch (e) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state =
          SellerFailure.from(e).message;
      rethrow;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> submitDigitalDelivery({
    required int orderId,
    String? note,
  }) async {
    final prev = state;
    state = state
        .map(
          (e) => e.id == orderId
              ? e.copyWith(
                  status: SellerOrderStatus.buyerReview,
                  fulfillmentState: 'buyer_review',
                )
              : e,
        )
        .toList();
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref.read(sellerRepositoryProvider).submitDigitalDelivery(
            orderId: orderId,
            note: note,
          );
      await refresh();
    } catch (e) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state =
          SellerFailure.from(e).message;
      rethrow;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }
}

class SellerReviewsController extends Notifier<List<SellerReview>> {
  bool _loadedRemote = false;

  @override
  List<SellerReview> build() {
    Future<void>.microtask(_hydrateFromBackend);
    return const <SellerReview>[];
  }

  Future<void> _hydrateFromBackend() async {
    if (_loadedRemote) return;
    _loadedRemote = true;
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      state = await ref.read(sellerRepositoryProvider).listSellerReviews();
    } catch (e) {
      state = const <SellerReview>[];
      if (_isMissingSellerProfile(e)) {
        ref.read(sellerErrorProvider.notifier).state = null;
      } else {
        ref.read(sellerErrorProvider.notifier).state =
            SellerFailure.from(e).message;
      }
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> refresh() async {
    _loadedRemote = false;
    await _hydrateFromBackend();
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
        .map((e) => e.id == reviewId
            ? e.copyWith(
                sellerReply: reply.trim(),
                sellerReplyDate: DateTime.now(),
                moderationState: null)
            : e)
        .toList();
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref
          .read(sellerRepositoryProvider)
          .postReviewReply(reviewId: reviewId, reply: reply.trim());
      await refresh();
    } catch (e) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state =
          SellerFailure.from(e).message;
      rethrow;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }
}

bool _isMissingSellerProfile(Object error) {
  return error is ApiException &&
      error.type == ApiExceptionType.notFound &&
      error.errorCode == 'seller_profile_not_found';
}

bool isMissingSellerProfileError(Object error) =>
    _isMissingSellerProfile(error);

SellerOrder _fromOrderRaw(Map<String, dynamic> raw) {
  final id = (raw['id'] as num?)?.toInt() ?? 0;
  final number = (raw['order_number'] ?? 'ORD-$id').toString();
  final customer = (raw['buyer_name'] ??
          raw['customer_name'] ??
          raw['user_name'] ??
          raw['buyer_email'] ??
          'Customer')
      .toString();
  final createdRaw = (raw['created_at'] ?? '').toString();
  final created = DateTime.tryParse(createdRaw)?.toLocal() ?? DateTime.now();
  final totalNum = num.tryParse((raw['total_amount'] ??
              raw['net_amount'] ??
              raw['gross_amount'] ??
              raw['total'] ??
              0)
          .toString()) ??
      0;
  final currency = (raw['currency'] ?? 'BDT').toString();
  final items = raw['items'];
  final firstItem = (items is List && items.isNotEmpty && items.first is Map)
      ? Map<String, dynamic>.from(items.first as Map)
      : <String, dynamic>{};
  final title = (firstItem['title'] ??
          firstItem['name'] ??
          raw['product_name'] ??
          'Order item')
      .toString();
  final address = (raw['shipping_address_line'] ??
          raw['shipping_address'] ??
          raw['address'] ??
          'Address unavailable')
      .toString();
  final payment =
      (raw['payment_method'] ?? raw['payment_channel'] ?? 'Unknown').toString();
  final statusRaw =
      (raw['status'] ?? raw['order_status'] ?? '').toString().toLowerCase();
  final status = statusRaw.contains('cancel')
      ? SellerOrderStatus.cancelled
      : statusRaw.contains('buyer_review')
          ? SellerOrderStatus.buyerReview
          : statusRaw.contains('delivery_submitted')
              ? SellerOrderStatus.deliverySubmitted
              : statusRaw.contains('completed') || statusRaw.contains('deliver')
                  ? SellerOrderStatus.delivered
                  : statusRaw.contains('ship')
                      ? SellerOrderStatus.shipped
                      : statusRaw.contains('process')
                          ? SellerOrderStatus.processing
                          : SellerOrderStatus.toShip;

  final productType = (raw['product_type'] ??
          firstItem['product_type'] ??
          firstItem['product_type_snapshot'] ??
          'physical')
      .toString()
      .toLowerCase();
  final timeoutState = raw['timeout_state'] is Map
      ? Map<String, dynamic>.from(raw['timeout_state'] as Map)
      : const <String, dynamic>{};

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
    shippingNote: raw['shipping_note']?.toString(),
    shippingDate:
        DateTime.tryParse((raw['shipped_at'] ?? '').toString())?.toLocal(),
    deliveredOn:
        DateTime.tryParse((raw['delivered_at'] ?? '').toString())?.toLocal(),
    productType: productType,
    fulfillmentState: raw['fulfillment_state']?.toString(),
    timeoutState: timeoutState,
  );
}
