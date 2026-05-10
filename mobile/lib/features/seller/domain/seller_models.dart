import 'package:flutter/foundation.dart';

enum SellerOrderStatus {
  toShip,
  processing,
  deliverySubmitted,
  buyerReview,
  shipped,
  delivered,
  cancelled,
}

extension SellerOrderStatusX on SellerOrderStatus {
  String get label => switch (this) {
        SellerOrderStatus.toShip => 'To Ship',
        SellerOrderStatus.processing => 'Processing',
        SellerOrderStatus.deliverySubmitted => 'Proof Submitted',
        SellerOrderStatus.buyerReview => 'Buyer Review',
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
    this.productType = 'physical',
    this.fulfillmentState,
    this.timeoutState = const <String, dynamic>{},
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
  final String productType;
  final String? fulfillmentState;
  final Map<String, dynamic> timeoutState;

  bool get isPhysical => productType.toLowerCase() == 'physical';

  bool get usesProofDelivery => !isPhysical;

  String get totalLabel {
    final t = totalAmount.toStringAsFixed(2);
    return currency.toUpperCase() == 'USD'
        ? '\$$t'
        : '${currency.toUpperCase()} $t';
  }

  SellerOrder copyWith({
    SellerOrderStatus? status,
    String? trackingId,
    String? courierCompany,
    String? shippingNote,
    DateTime? shippingDate,
    DateTime? deliveredOn,
    String? productType,
    String? fulfillmentState,
    Map<String, dynamic>? timeoutState,
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
      productType: productType ?? this.productType,
      fulfillmentState: fulfillmentState ?? this.fulfillmentState,
      timeoutState: timeoutState ?? this.timeoutState,
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
    this.helpfulCount = 0,
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
  final int helpfulCount;
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
      helpfulCount: helpfulCount,
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
    this.imageUrls = const <String>[],
    this.productType = 'physical',
    this.attributes = const <String, dynamic>{},
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
  final List<String> imageUrls;
  final String productType;
  final Map<String, dynamic> attributes;
  final Map<String, int> warehouseStocks;

  bool get isPhysical => productType.toLowerCase() == 'physical';

  bool get isService {
    final normalized = productType.toLowerCase();
    return normalized == 'service' || normalized == 'manual_delivery';
  }

  bool get isInstantDelivery {
    if (productType.toLowerCase() == 'instant_delivery') {
      return true;
    }
    final explicit = attributes['is_instant_delivery'];
    if (explicit is bool) {
      return explicit;
    }
    if (explicit is num) {
      return explicit != 0;
    }
    final explicitText = explicit?.toString().trim().toLowerCase();
    if (explicitText == 'true' ||
        explicitText == '1' ||
        explicitText == 'yes') {
      return true;
    }
    final deliveryType =
        (attributes['delivery_type'] ?? '').toString().toLowerCase();
    final deliveryMode =
        (attributes['delivery_mode'] ?? '').toString().toLowerCase();
    final fulfillment =
        (attributes['fulfillment'] ?? '').toString().toLowerCase();
    return deliveryType == 'instant_delivery' ||
        deliveryType == 'instant' ||
        deliveryMode == 'instant' ||
        fulfillment.contains('instant');
  }

  String get priceLabel {
    final t = price.toStringAsFixed(2);
    return currency.toUpperCase() == 'USD'
        ? '\$$t'
        : '${currency.toUpperCase()} $t';
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
    List<String>? imageUrls,
    String? productType,
    Map<String, dynamic>? attributes,
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
      imageUrls: imageUrls ?? this.imageUrls,
      productType: productType ?? this.productType,
      attributes: attributes ?? this.attributes,
      warehouseStocks: warehouseStocks ?? this.warehouseStocks,
    );
  }
}

@immutable
class SellerWarehouse {
  const SellerWarehouse({
    required this.id,
    required this.name,
    this.code = '',
    this.city = '',
    this.isActive = true,
  });

  final int id;
  final String name;
  final String code;
  final String city;
  final bool isActive;

  factory SellerWarehouse.fromJson(Map<String, dynamic> json) {
    return SellerWarehouse(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name'] as String? ?? '',
      code: json['code'] as String? ?? '',
      city: json['city'] as String? ?? '',
      isActive: json['is_active'] as bool? ?? true,
    );
  }

  Map<String, dynamic> toJson() {
    return <String, dynamic>{
      'id': id,
      'name': name,
      'code': code,
      'city': city,
      'is_active': isActive,
    };
  }

  SellerWarehouse copyWith({
    String? name,
    String? code,
    String? city,
    bool? isActive,
  }) {
    return SellerWarehouse(
      id: id,
      name: name ?? this.name,
      code: code ?? this.code,
      city: city ?? this.city,
      isActive: isActive ?? this.isActive,
    );
  }
}

@immutable
class SellerUploadResult {
  const SellerUploadResult({
    required this.storagePath,
    required this.originalName,
    required this.checksumSha256,
    required this.mimeType,
    this.size,
  });

  final String storagePath;
  final String originalName;
  final String checksumSha256;
  final String mimeType;
  final int? size;
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
    this.addressLine,
    this.city,
    this.region,
    this.postalCode,
    this.country,
    this.storeAddress,
  });

  final String storeName;
  final String storeDescription;
  final String? storeLogoUrl;
  final String? bannerImageUrl;
  final String? contactEmail;
  final String? contactPhone;
  final String? addressLine;
  final String? city;
  final String? region;
  final String? postalCode;
  final String? country;
  final String? storeAddress;

  SellerStoreSettings copyWith({
    String? storeName,
    String? storeDescription,
    String? storeLogoUrl,
    String? bannerImageUrl,
    String? contactEmail,
    String? contactPhone,
    String? addressLine,
    String? city,
    String? region,
    String? postalCode,
    String? country,
    String? storeAddress,
  }) {
    return SellerStoreSettings(
      storeName: storeName ?? this.storeName,
      storeDescription: storeDescription ?? this.storeDescription,
      storeLogoUrl: storeLogoUrl ?? this.storeLogoUrl,
      bannerImageUrl: bannerImageUrl ?? this.bannerImageUrl,
      contactEmail: contactEmail ?? this.contactEmail,
      contactPhone: contactPhone ?? this.contactPhone,
      addressLine: addressLine ?? this.addressLine,
      city: city ?? this.city,
      region: region ?? this.region,
      postalCode: postalCode ?? this.postalCode,
      country: country ?? this.country,
      storeAddress: storeAddress ?? this.storeAddress,
    );
  }
}

@immutable
class SellerShippingMethodOption {
  const SellerShippingMethodOption({
    required this.id,
    required this.code,
    required this.name,
    required this.suggestedFee,
    required this.processingTimeLabel,
    required this.sortOrder,
  });

  final int id;
  final String code;
  final String name;
  final double suggestedFee;
  final String processingTimeLabel;
  final int sortOrder;
}

@immutable
class SellerShippingMethodSelection {
  const SellerShippingMethodSelection({
    required this.shippingMethodId,
    required this.methodCode,
    required this.methodName,
    required this.suggestedFee,
    required this.price,
    required this.processingTimeLabel,
    required this.isEnabled,
    required this.sortOrder,
  });

  final int shippingMethodId;
  final String methodCode;
  final String methodName;
  final double suggestedFee;
  final double price;
  final String processingTimeLabel;
  final bool isEnabled;
  final int sortOrder;
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
    this.isConfigured = false,
    this.availableMethods = const <SellerShippingMethodOption>[],
    this.shippingMethods = const <SellerShippingMethodSelection>[],
    this.processingTimeOptions = const <String>[],
  });

  final String insideDhakaLabel;
  final double insideDhakaFee;
  final String outsideDhakaLabel;
  final double outsideDhakaFee;
  final bool cashOnDeliveryEnabled;
  final String processingTimeLabel;
  final bool isConfigured;
  final List<SellerShippingMethodOption> availableMethods;
  final List<SellerShippingMethodSelection> shippingMethods;
  final List<String> processingTimeOptions;

  SellerShippingSettings copyWith({
    String? insideDhakaLabel,
    double? insideDhakaFee,
    String? outsideDhakaLabel,
    double? outsideDhakaFee,
    bool? cashOnDeliveryEnabled,
    String? processingTimeLabel,
    bool? isConfigured,
    List<SellerShippingMethodOption>? availableMethods,
    List<SellerShippingMethodSelection>? shippingMethods,
    List<String>? processingTimeOptions,
  }) {
    return SellerShippingSettings(
      insideDhakaLabel: insideDhakaLabel ?? this.insideDhakaLabel,
      insideDhakaFee: insideDhakaFee ?? this.insideDhakaFee,
      outsideDhakaLabel: outsideDhakaLabel ?? this.outsideDhakaLabel,
      outsideDhakaFee: outsideDhakaFee ?? this.outsideDhakaFee,
      cashOnDeliveryEnabled:
          cashOnDeliveryEnabled ?? this.cashOnDeliveryEnabled,
      processingTimeLabel: processingTimeLabel ?? this.processingTimeLabel,
      isConfigured: isConfigured ?? this.isConfigured,
      availableMethods: availableMethods ?? this.availableMethods,
      shippingMethods: shippingMethods ?? this.shippingMethods,
      processingTimeOptions:
          processingTimeOptions ?? this.processingTimeOptions,
    );
  }
}

@immutable
class SellerWithdrawalSettings {
  const SellerWithdrawalSettings({
    required this.minimumWithdrawalAmount,
    required this.currency,
  });

  final double minimumWithdrawalAmount;
  final String currency;

  static const fallback = SellerWithdrawalSettings(
    minimumWithdrawalAmount: 500,
    currency: 'BDT',
  );
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
    required this.href,
  });

  final String id;
  final String title;
  final String body;
  final String timeAgoLabel;
  final String kind;
  final bool read;
  final String href;

  SellerNotificationItem copyWith({bool? read}) {
    return SellerNotificationItem(
      id: id,
      title: title,
      body: body,
      timeAgoLabel: timeAgoLabel,
      kind: kind,
      read: read ?? this.read,
      href: href,
    );
  }
}
