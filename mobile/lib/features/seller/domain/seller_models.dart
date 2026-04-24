import 'package:flutter/foundation.dart';

enum SellerOrderStatus { toShip, processing, shipped, delivered, cancelled }

extension SellerOrderStatusX on SellerOrderStatus {
  String get label => switch (this) {
        SellerOrderStatus.toShip => 'To Ship',
        SellerOrderStatus.processing => 'Processing',
        SellerOrderStatus.shipped => 'Shipped',
        SellerOrderStatus.delivered => 'Delivered',
        SellerOrderStatus.cancelled => 'Cancelled',
      };
}

@immutable
class SellerOrder {
  const SellerOrder({
    required this.id,
    required this.orderNumber,
    required this.customerName,
    required this.orderDate,
    required this.totalAmount,
    required this.currency,
    required this.productTitle,
    required this.productImageUrl,
    required this.shippingAddress,
    required this.paymentMethod,
    required this.status,
    this.trackingId,
    this.courierCompany,
    this.shippingNote,
    this.shippingDate,
    this.deliveredOn,
  });

  final int id;
  final String orderNumber;
  final String customerName;
  final DateTime orderDate;
  final double totalAmount;
  final String currency;
  final String productTitle;
  final String? productImageUrl;
  final String shippingAddress;
  final String paymentMethod;
  final SellerOrderStatus status;
  final String? trackingId;
  final String? courierCompany;
  final String? shippingNote;
  final DateTime? shippingDate;
  final DateTime? deliveredOn;

  String get totalLabel {
    final t = totalAmount.toStringAsFixed(2);
    return currency.toUpperCase() == 'USD' ? '\$$t' : '${currency.toUpperCase()} $t';
  }

  SellerOrder copyWith({
    SellerOrderStatus? status,
    String? trackingId,
    String? courierCompany,
    String? shippingNote,
    DateTime? shippingDate,
    DateTime? deliveredOn,
  }) {
    return SellerOrder(
      id: id,
      orderNumber: orderNumber,
      customerName: customerName,
      orderDate: orderDate,
      totalAmount: totalAmount,
      currency: currency,
      productTitle: productTitle,
      productImageUrl: productImageUrl,
      shippingAddress: shippingAddress,
      paymentMethod: paymentMethod,
      status: status ?? this.status,
      trackingId: trackingId ?? this.trackingId,
      courierCompany: courierCompany ?? this.courierCompany,
      shippingNote: shippingNote ?? this.shippingNote,
      shippingDate: shippingDate ?? this.shippingDate,
      deliveredOn: deliveredOn ?? this.deliveredOn,
    );
  }
}

@immutable
class SellerReview {
  const SellerReview({
    required this.id,
    required this.buyerName,
    required this.date,
    required this.rating,
    required this.comment,
    required this.productName,
    required this.orderNumber,
    required this.photoUrls,
    required this.isVerifiedBuyer,
    this.sellerReply,
    this.sellerReplyDate,
    this.moderationState,
  });

  final int id;
  final String buyerName;
  final DateTime date;
  final int rating;
  final String comment;
  final String productName;
  final String orderNumber;
  final List<String> photoUrls;
  final bool isVerifiedBuyer;
  final String? sellerReply;
  final DateTime? sellerReplyDate;
  final String? moderationState;

  SellerReview copyWith({
    String? sellerReply,
    DateTime? sellerReplyDate,
    String? moderationState,
  }) {
    return SellerReview(
      id: id,
      buyerName: buyerName,
      date: date,
      rating: rating,
      comment: comment,
      productName: productName,
      orderNumber: orderNumber,
      photoUrls: photoUrls,
      isVerifiedBuyer: isVerifiedBuyer,
      sellerReply: sellerReply ?? this.sellerReply,
      sellerReplyDate: sellerReplyDate ?? this.sellerReplyDate,
      moderationState: moderationState ?? this.moderationState,
    );
  }
}

enum SellerProductStatus { active, inactive, outOfStock }

extension SellerProductStatusX on SellerProductStatus {
  String get label => switch (this) {
        SellerProductStatus.active => 'Active',
        SellerProductStatus.inactive => 'Inactive',
        SellerProductStatus.outOfStock => 'Out of Stock',
      };
}

@immutable
class SellerProduct {
  const SellerProduct({
    required this.id,
    required this.name,
    required this.price,
    required this.currency,
    required this.stock,
    required this.status,
    required this.category,
    required this.description,
    required this.sku,
    required this.views,
    required this.sold,
    this.imageUrl,
    this.productType = 'physical',
    this.warehouseStocks = const <String, int>{},
  });

  final int id;
  final String name;
  final double price;
  final String currency;
  final int stock;
  final SellerProductStatus status;
  final String category;
  final String description;
  final String sku;
  final int views;
  final int sold;
  final String? imageUrl;
  final String productType;
  final Map<String, int> warehouseStocks;

  String get priceLabel {
    final t = price.toStringAsFixed(2);
    return currency.toUpperCase() == 'USD' ? '\$$t' : '${currency.toUpperCase()} $t';
  }

  SellerProduct copyWith({
    String? name,
    double? price,
    String? currency,
    int? stock,
    SellerProductStatus? status,
    String? category,
    String? description,
    String? sku,
    int? views,
    int? sold,
    String? imageUrl,
    String? productType,
    Map<String, int>? warehouseStocks,
  }) {
    return SellerProduct(
      id: id,
      name: name ?? this.name,
      price: price ?? this.price,
      currency: currency ?? this.currency,
      stock: stock ?? this.stock,
      status: status ?? this.status,
      category: category ?? this.category,
      description: description ?? this.description,
      sku: sku ?? this.sku,
      views: views ?? this.views,
      sold: sold ?? this.sold,
      imageUrl: imageUrl ?? this.imageUrl,
      productType: productType ?? this.productType,
      warehouseStocks: warehouseStocks ?? this.warehouseStocks,
    );
  }
}

enum SellerMovementType { stockIn, stockOut, adjustment }

extension SellerMovementTypeX on SellerMovementType {
  String get label => switch (this) {
        SellerMovementType.stockIn => 'Stock In',
        SellerMovementType.stockOut => 'Stock Out',
        SellerMovementType.adjustment => 'Adjustment',
      };
}

@immutable
class SellerInventoryMovement {
  const SellerInventoryMovement({
    required this.id,
    required this.productId,
    required this.productName,
    required this.productSku,
    required this.type,
    required this.quantity,
    required this.previousStock,
    required this.newStock,
    required this.unitAmount,
    required this.currency,
    required this.warehouse,
    required this.referenceId,
    required this.reason,
    required this.note,
    required this.actor,
    required this.at,
  });

  final int id;
  final int productId;
  final String productName;
  final String productSku;
  final SellerMovementType type;
  final int quantity;
  final int previousStock;
  final int newStock;
  final double unitAmount;
  final String currency;
  final String warehouse;
  final String referenceId;
  final String reason;
  final String note;
  final String actor;
  final DateTime at;

  double get totalAmount => unitAmount * quantity.abs();
}

enum SellerPayoutMethodType { bkash, nagad, bankTransfer }

extension SellerPayoutMethodTypeX on SellerPayoutMethodType {
  String get label => switch (this) {
        SellerPayoutMethodType.bkash => 'bKash',
        SellerPayoutMethodType.nagad => 'Nagad',
        SellerPayoutMethodType.bankTransfer => 'Bank Transfer',
      };

  String get apiValue => switch (this) {
        SellerPayoutMethodType.bkash => 'bkash',
        SellerPayoutMethodType.nagad => 'nagad',
        SellerPayoutMethodType.bankTransfer => 'bank_transfer',
      };
}

@immutable
class SellerPayoutMethod {
  const SellerPayoutMethod({
    required this.id,
    required this.type,
    required this.accountName,
    required this.accountNumberMasked,
    this.providerName,
    this.bankName,
    this.isDefault = false,
    this.isActive = true,
  });

  final String id;
  final SellerPayoutMethodType type;
  final String accountName;
  final String accountNumberMasked;
  final String? providerName;
  final String? bankName;
  final bool isDefault;
  final bool isActive;

  SellerPayoutMethod copyWith({
    bool? isDefault,
    bool? isActive,
  }) {
    return SellerPayoutMethod(
      id: id,
      type: type,
      accountName: accountName,
      accountNumberMasked: accountNumberMasked,
      providerName: providerName,
      bankName: bankName,
      isDefault: isDefault ?? this.isDefault,
      isActive: isActive ?? this.isActive,
    );
  }
}

@immutable
class SellerStoreSettings {
  const SellerStoreSettings({
    required this.storeName,
    required this.storeDescription,
    this.storeLogoUrl,
    this.bannerImageUrl,
    this.contactEmail,
    this.contactPhone,
  });

  final String storeName;
  final String storeDescription;
  final String? storeLogoUrl;
  final String? bannerImageUrl;
  final String? contactEmail;
  final String? contactPhone;

  SellerStoreSettings copyWith({
    String? storeName,
    String? storeDescription,
    String? storeLogoUrl,
    String? bannerImageUrl,
    String? contactEmail,
    String? contactPhone,
  }) {
    return SellerStoreSettings(
      storeName: storeName ?? this.storeName,
      storeDescription: storeDescription ?? this.storeDescription,
      storeLogoUrl: storeLogoUrl ?? this.storeLogoUrl,
      bannerImageUrl: bannerImageUrl ?? this.bannerImageUrl,
      contactEmail: contactEmail ?? this.contactEmail,
      contactPhone: contactPhone ?? this.contactPhone,
    );
  }
}

@immutable
class SellerShippingSettings {
  const SellerShippingSettings({
    required this.insideDhakaLabel,
    required this.insideDhakaFee,
    required this.outsideDhakaLabel,
    required this.outsideDhakaFee,
    required this.cashOnDeliveryEnabled,
    required this.processingTimeLabel,
  });

  final String insideDhakaLabel;
  final double insideDhakaFee;
  final String outsideDhakaLabel;
  final double outsideDhakaFee;
  final bool cashOnDeliveryEnabled;
  final String processingTimeLabel;

  SellerShippingSettings copyWith({
    String? insideDhakaLabel,
    double? insideDhakaFee,
    String? outsideDhakaLabel,
    double? outsideDhakaFee,
    bool? cashOnDeliveryEnabled,
    String? processingTimeLabel,
  }) {
    return SellerShippingSettings(
      insideDhakaLabel: insideDhakaLabel ?? this.insideDhakaLabel,
      insideDhakaFee: insideDhakaFee ?? this.insideDhakaFee,
      outsideDhakaLabel: outsideDhakaLabel ?? this.outsideDhakaLabel,
      outsideDhakaFee: outsideDhakaFee ?? this.outsideDhakaFee,
      cashOnDeliveryEnabled: cashOnDeliveryEnabled ?? this.cashOnDeliveryEnabled,
      processingTimeLabel: processingTimeLabel ?? this.processingTimeLabel,
    );
  }
}

@immutable
class SellerNotificationItem {
  const SellerNotificationItem({
    required this.id,
    required this.title,
    required this.body,
    required this.timeAgoLabel,
    required this.kind,
    required this.read,
  });

  final String id;
  final String title;
  final String body;
  final String timeAgoLabel;
  final String kind;
  final bool read;

  SellerNotificationItem copyWith({bool? read}) {
    return SellerNotificationItem(
      id: id,
      title: title,
      body: body,
      timeAgoLabel: timeAgoLabel,
      kind: kind,
      read: read ?? this.read,
    );
  }
}
