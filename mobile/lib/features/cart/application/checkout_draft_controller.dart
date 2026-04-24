import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'address_book_controller.dart';
import '../domain/cart_line.dart';

/// Mock wallet balance for payment UI until wallet API exists.
const double kMockWalletBalance = 120.50;

enum CheckoutShippingMethod {
  standard,
  express,
}

enum CheckoutPaymentMethod {
  wallet,
  card,
  bkash,
  nagad,
  bank,
}

extension CheckoutShippingMethodX on CheckoutShippingMethod {
  String get label => switch (this) {
        CheckoutShippingMethod.standard => 'Standard Shipping (3-5 days)',
        CheckoutShippingMethod.express => 'Express Shipping (1-2 days)',
      };

  double get feeUsd => switch (this) {
        CheckoutShippingMethod.standard => 20,
        CheckoutShippingMethod.express => 40,
      };
}

extension CheckoutPaymentMethodX on CheckoutPaymentMethod {
  String get label => switch (this) {
        CheckoutPaymentMethod.wallet => 'Wallet Balance',
        CheckoutPaymentMethod.card => 'Credit / Debit Card',
        CheckoutPaymentMethod.bkash => 'bKash',
        CheckoutPaymentMethod.nagad => 'Nagad',
        CheckoutPaymentMethod.bank => 'Bank Transfer',
      };
}

class CheckoutDraft {
  static const Object _noPromoCodeChange = Object();

  const CheckoutDraft({
    required this.lines,
    required this.addressId,
    required this.recipientName,
    required this.addressLine,
    required this.phone,
    this.shippingMethod = CheckoutShippingMethod.standard,
    this.paymentMethod = CheckoutPaymentMethod.wallet,
    this.promoCode,
    this.promoDiscount = 0,
    this.termsAccepted = false,
  });

  final List<CartLine> lines;
  final String addressId;
  final String recipientName;
  final String addressLine;
  final String phone;
  final CheckoutShippingMethod shippingMethod;
  final CheckoutPaymentMethod paymentMethod;
  final String? promoCode;
  final double promoDiscount;
  final bool termsAccepted;

  bool get needsShipping => lines.any((CartLine e) => e.isPhysical);

  double get subtotal => lines.fold<double>(0, (s, CartLine e) => s + e.lineTotal);

  double get shippingFee => needsShipping ? shippingMethod.feeUsd : 0;

  double get total {
    final raw = subtotal + shippingFee - promoDiscount;
    return raw < 0 ? 0 : raw;
  }

  bool get walletCoversTotal => paymentMethod != CheckoutPaymentMethod.wallet || total <= kMockWalletBalance;

  CheckoutDraft copyWith({
    List<CartLine>? lines,
    String? recipientName,
    String? addressLine,
    String? phone,
    CheckoutShippingMethod? shippingMethod,
    CheckoutPaymentMethod? paymentMethod,
    String? addressId,
    Object? promoCode = _noPromoCodeChange,
    double? promoDiscount,
    bool? termsAccepted,
  }) {
    return CheckoutDraft(
      lines: lines ?? this.lines,
      addressId: addressId ?? this.addressId,
      recipientName: recipientName ?? this.recipientName,
      addressLine: addressLine ?? this.addressLine,
      phone: phone ?? this.phone,
      shippingMethod: shippingMethod ?? this.shippingMethod,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      promoCode: identical(promoCode, _noPromoCodeChange) ? this.promoCode : promoCode as String?,
      promoDiscount: promoDiscount ?? this.promoDiscount,
      termsAccepted: termsAccepted ?? this.termsAccepted,
    );
  }
}

final checkoutDraftProvider = NotifierProvider<CheckoutDraftController, CheckoutDraft?>(CheckoutDraftController.new);

class CheckoutDraftController extends Notifier<CheckoutDraft?> {
  @override
  CheckoutDraft? build() => null;

  void beginFromCart(List<CartLine> lines) {
    if (lines.isEmpty) {
      state = null;
      return;
    }
    final defaultAddress = ref.read(savedAddressesProvider.notifier).defaultAddress();
    state = CheckoutDraft(
      lines: List<CartLine>.from(lines),
      addressId: defaultAddress?.id ?? 'address_missing',
      recipientName: defaultAddress?.fullName ?? 'Address not set',
      addressLine: defaultAddress?.compactAddress ?? 'Please add a delivery address',
      phone: defaultAddress?.phone ?? '',
    );
  }

  void updateShipping(CheckoutShippingMethod method) {
    final s = state;
    if (s == null) {
      return;
    }
    state = s.copyWith(shippingMethod: method);
  }

  void updatePayment(CheckoutPaymentMethod method) {
    final s = state;
    if (s == null) {
      return;
    }
    state = s.copyWith(paymentMethod: method);
  }

  void selectAddress(String addressId) {
    final s = state;
    if (s == null) return;
    final address = ref.read(savedAddressesProvider.notifier).byId(addressId);
    if (address == null) return;
    state = s.copyWith(
      addressId: address.id,
      recipientName: address.fullName,
      addressLine: address.compactAddress,
      phone: address.phone,
    );
  }

  static const Map<String, ({String title, String description, String minSpend, String badge})> promoCatalog =
      <String, ({String title, String description, String minSpend, String badge})>{
        'WELCOME10': (title: 'WELCOME10', description: '10% off on orders above ৳1,000', minSpend: 'Min. spend ৳1,000', badge: '10% OFF'),
        'SAVE50': (title: 'SAVE50', description: '৳50 off on orders above ৳500', minSpend: 'Min. spend ৳500', badge: '৳50 OFF'),
        'FREESHIP': (title: 'FREESHIP', description: 'Free shipping on orders above ৳800', minSpend: 'Min. spend ৳800', badge: 'FREE'),
      };

  bool applyPromoCode(String rawCode) {
    final s = state;
    if (s == null) return false;
    final code = rawCode.trim().toUpperCase();
    if (!promoCatalog.containsKey(code)) return false;

    final subtotal = s.subtotal;
    final shipping = s.shippingFee;
    final discount = switch (code) {
      'WELCOME10' => subtotal >= 1000 ? subtotal * 0.10 : -1,
      'SAVE50' => subtotal >= 500 ? 50 : -1,
      'FREESHIP' => subtotal >= 800 ? shipping : -1,
      _ => -1,
    };
    if (discount < 0) return false;
    final maxDiscount = subtotal + shipping;
    final safeDiscount = (discount > maxDiscount ? maxDiscount : discount).toDouble();
    state = s.copyWith(promoCode: code, promoDiscount: safeDiscount);
    return true;
  }

  void removePromoCode() {
    final s = state;
    if (s == null) return;
    state = s.copyWith(promoCode: null, promoDiscount: 0);
  }

  void setTermsAccepted(bool value) {
    final s = state;
    if (s == null) {
      return;
    }
    state = s.copyWith(termsAccepted: value);
  }

  void clear() {
    state = null;
  }

  static String generateOrderId() {
    final n = DateTime.now().millisecondsSinceEpoch % 10000000000;
    return 'BS${n.toString().padLeft(10, '0')}';
  }
}
