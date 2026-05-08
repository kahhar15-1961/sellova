import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'address_book_controller.dart';
import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../profile/data/wallet_repository.dart';
import '../domain/cart_line.dart';

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

final buyerWalletBalanceProvider =
    FutureProvider.family<double?, String>((ref, currency) async {
  final wallets = await ref.read(walletRepositoryProvider).listWallets();
  final normalizedCurrency = currency.trim().toUpperCase();
  WalletDto? match;
  for (final wallet in wallets) {
    if (wallet.walletType == 'buyer' &&
        (normalizedCurrency.isEmpty || wallet.currency == normalizedCurrency)) {
      match = wallet;
      break;
    }
  }
  if (match == null) {
    for (final wallet in wallets) {
      if (wallet.walletType == 'buyer') {
        match = wallet;
        break;
      }
    }
  }
  if (match == null) {
    return null;
  }
  return double.tryParse(match.availableBalance);
});

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

  Map<String, dynamic> toJson() => <String, dynamic>{
        'lines': lines.map((CartLine e) => e.toJson()).toList(),
        'address_id': addressId,
        'recipient_name': recipientName,
        'address_line': addressLine,
        'phone': phone,
        'shipping_method': shippingMethod.name,
        'payment_method': paymentMethod.name,
        'promo_code': promoCode,
        'promo_discount': promoDiscount,
        'terms_accepted': termsAccepted,
      };

  factory CheckoutDraft.fromJson(Map<String, dynamic> json) {
    final linesRaw = json['lines'];
    final lines = linesRaw is List
        ? linesRaw
            .whereType<Map>()
            .map((e) => CartLine.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : const <CartLine>[];
    final shippingMethod = switch ((json['shipping_method'] ?? '').toString()) {
      'express' => CheckoutShippingMethod.express,
      _ => CheckoutShippingMethod.standard,
    };
    final paymentMethod = switch ((json['payment_method'] ?? '').toString()) {
      'card' => CheckoutPaymentMethod.card,
      'bkash' => CheckoutPaymentMethod.bkash,
      'nagad' => CheckoutPaymentMethod.nagad,
      'bank' => CheckoutPaymentMethod.bank,
      _ => CheckoutPaymentMethod.wallet,
    };
    return CheckoutDraft(
      lines: lines,
      addressId: (json['address_id'] ?? '').toString(),
      recipientName: (json['recipient_name'] ?? '').toString(),
      addressLine: (json['address_line'] ?? '').toString(),
      phone: (json['phone'] ?? '').toString(),
      shippingMethod: shippingMethod,
      paymentMethod: paymentMethod,
      promoCode: json['promo_code']?.toString(),
      promoDiscount: (json['promo_discount'] as num?)?.toDouble() ?? 0,
      termsAccepted: json['terms_accepted'] == true,
    );
  }

  bool get needsShipping => lines.any((CartLine e) => e.isPhysical);

  double get subtotal =>
      lines.fold<double>(0, (s, CartLine e) => s + e.lineTotal);

  double get shippingFee => needsShipping ? shippingMethod.feeUsd : 0;

  double get total {
    final raw = subtotal + shippingFee - promoDiscount;
    return raw < 0 ? 0 : raw;
  }

  bool get walletCoversTotal => paymentMethod != CheckoutPaymentMethod.wallet;

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
      promoCode: identical(promoCode, _noPromoCodeChange)
          ? this.promoCode
          : promoCode as String?,
      promoDiscount: promoDiscount ?? this.promoDiscount,
      termsAccepted: termsAccepted ?? this.termsAccepted,
    );
  }
}

final checkoutDraftProvider =
    NotifierProvider<CheckoutDraftController, CheckoutDraft?>(
        CheckoutDraftController.new);

class CheckoutDraftController extends Notifier<CheckoutDraft?> {
  static const String _prefsKey = 'checkout_draft_v1';

  @override
  CheckoutDraft? build() => _readFromPrefs();

  CheckoutDraft? _readFromPrefs() {
    final raw = ref.read(sharedPreferencesProvider).getString(_prefsKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) {
        return null;
      }
      return CheckoutDraft.fromJson(Map<String, dynamic>.from(decoded));
    } catch (_) {
      return null;
    }
  }

  Future<void> _persist(CheckoutDraft? draft) async {
    final prefs = ref.read(sharedPreferencesProvider);
    if (draft == null) {
      await prefs.remove(_prefsKey);
      return;
    }
    await prefs.setString(_prefsKey, jsonEncode(draft.toJson()));
  }

  void beginFromCart(List<CartLine> lines) {
    if (lines.isEmpty) {
      state = null;
      _persist(null);
      return;
    }
    final defaultAddress =
        ref.read(savedAddressesProvider.notifier).defaultAddress();
    state = CheckoutDraft(
      lines: List<CartLine>.from(lines),
      addressId: defaultAddress?.id ?? 'address_missing',
      recipientName: defaultAddress?.fullName ?? 'Address not set',
      addressLine:
          defaultAddress?.compactAddress ?? 'Please add a delivery address',
      phone: defaultAddress?.phone ?? '',
    );
    _persist(state);
  }

  bool restoreFromCart(List<CartLine> lines) {
    if (state != null || lines.isEmpty) {
      return state != null;
    }
    beginFromCart(lines);
    return state != null;
  }

  void updateShipping(CheckoutShippingMethod method) {
    final s = state;
    if (s == null) {
      return;
    }
    state = s.copyWith(shippingMethod: method);
    _persist(state);
  }

  void updatePayment(CheckoutPaymentMethod method) {
    final s = state;
    if (s == null) {
      return;
    }
    state = s.copyWith(paymentMethod: method);
    _persist(state);
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
    _persist(state);
  }

  bool applyPromoCode(String rawCode, double discount) {
    final s = state;
    if (s == null) return false;
    final code = rawCode.trim().toUpperCase();
    if (code.isEmpty) return false;
    final maxDiscount = s.subtotal + s.shippingFee;
    final safeDiscount =
        discount < 0 ? 0.0 : (discount > maxDiscount ? maxDiscount : discount);
    state = s.copyWith(promoCode: code, promoDiscount: safeDiscount);
    _persist(state);
    return true;
  }

  void removePromoCode() {
    final s = state;
    if (s == null) return;
    state = s.copyWith(promoCode: null, promoDiscount: 0);
    _persist(state);
  }

  void setTermsAccepted(bool value) {
    final s = state;
    if (s == null) {
      return;
    }
    state = s.copyWith(termsAccepted: value);
    _persist(state);
  }

  void clear() {
    state = null;
    _persist(null);
  }

  static String generateOrderId() {
    final n = DateTime.now().millisecondsSinceEpoch % 10000000000;
    return 'BS${n.toString().padLeft(10, '0')}';
  }
}
