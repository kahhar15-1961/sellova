import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/cart_controller.dart';
import '../application/checkout_draft_controller.dart';
import '../data/promo_repository.dart';
import '../domain/cart_line.dart';
import 'cart_ui.dart';
import '../../../app/providers/repository_providers.dart';
import '../../../core/errors/api_exception.dart';
import '../../orders/application/order_detail_provider.dart';
import '../../orders/application/order_list_controller.dart';
import '../../seller/application/seller_demo_controller.dart';

IconData _paymentIcon(CheckoutPaymentMethod m) {
  return switch (m) {
    CheckoutPaymentMethod.wallet => Icons.account_balance_wallet_outlined,
    CheckoutPaymentMethod.card => Icons.credit_card,
    CheckoutPaymentMethod.bkash ||
    CheckoutPaymentMethod.nagad =>
      Icons.smartphone_outlined,
    CheckoutPaymentMethod.bank => Icons.account_balance_outlined,
  };
}

class CheckoutReviewScreen extends ConsumerStatefulWidget {
  const CheckoutReviewScreen({super.key});

  @override
  ConsumerState<CheckoutReviewScreen> createState() =>
      _CheckoutReviewScreenState();
}

class _CheckoutReviewScreenState extends ConsumerState<CheckoutReviewScreen> {
  bool _submitting = false;
  bool _promoExpanded = false;
  bool _promoLoading = false;
  String? _promoMessage;
  Future<List<PromoOfferDto>>? _promoOffersFuture;
  final TextEditingController _promoController = TextEditingController();

  static String _money(CheckoutDraft d, double amount) {
    final c = d.lines.isEmpty ? 'USD' : d.lines.first.currency.toUpperCase();
    final t = amount.toStringAsFixed(2);
    return c == 'USD' ? '\$$t' : '$c $t';
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final draft = ref.read(checkoutDraftProvider);
      if (mounted && draft != null) {
        _promoOffersFuture = _loadPromoOffers(draft);
      }
    });
  }

  @override
  void dispose() {
    _promoController.dispose();
    super.dispose();
  }

  Future<List<PromoOfferDto>> _loadPromoOffers(CheckoutDraft draft) {
    return ref.read(promoRepositoryProvider).listOffers(
          subtotal: draft.subtotal,
          currency: draft.lines.isEmpty ? 'USD' : draft.lines.first.currency,
          shippingMethod: draft.shippingMethod.name,
        );
  }

  void _togglePromoPanel(CheckoutDraft draft) {
    setState(() {
      _promoExpanded = !_promoExpanded;
      _promoMessage = null;
      if (_promoExpanded && _promoOffersFuture == null) {
        _promoOffersFuture = _loadPromoOffers(draft);
      }
    });
  }

  void _removePromo(CheckoutDraft draft) {
    ref.read(checkoutDraftProvider.notifier).removePromoCode();
    setState(() {
      _promoController.clear();
      _promoExpanded = true;
      _promoMessage = 'Removed.';
      _promoOffersFuture =
          _loadPromoOffers(ref.read(checkoutDraftProvider) ?? draft);
    });
  }

  Future<void> _applyPromo(CheckoutDraft draft, String rawCode) async {
    if (_promoLoading) {
      return;
    }
    final normalized = rawCode.trim().toUpperCase();
    if (normalized.isEmpty) {
      setState(() => _promoMessage = 'Enter a code.');
      return;
    }

    setState(() {
      _promoLoading = true;
      _promoMessage = null;
    });

    try {
      final result = await ref.read(promoRepositoryProvider).validate(
            code: normalized,
            subtotal: draft.subtotal,
            shippingFee: draft.shippingFee,
            currency: draft.lines.isEmpty ? 'USD' : draft.lines.first.currency,
            shippingMethod: draft.shippingMethod.name,
          );
      ref
          .read(checkoutDraftProvider.notifier)
          .applyPromoCode(result.code, result.discountAmount);
      setState(() {
        _promoController.text = result.code;
        _promoMessage = 'Applied.';
        _promoOffersFuture = _loadPromoOffers(ref.read(checkoutDraftProvider)!);
      });
    } catch (e) {
      setState(() => _promoMessage = _friendlyPromoError(e));
    } finally {
      if (mounted) {
        setState(() => _promoLoading = false);
      }
    }
  }

  String _friendlyPromoError(Object error) {
    if (error is ApiException) {
      final reasonCode = (error.context['reason_code'] ?? '').toString();
      final message = error.message.toLowerCase();
      switch (reasonCode) {
        case 'promo_code_not_found':
          return 'Code not found.';
        case 'promo_code_minimum_spend_not_met':
          return 'Minimum not met.';
        case 'promo_code_currency_mismatch':
          return 'Wrong currency.';
        case 'promo_code_usage_limit_reached':
          return 'Limit reached.';
        case 'promo_code_inactive':
          return 'Code inactive.';
        case 'promo_code_not_applicable':
          return 'Not applicable.';
      }
      if (message.contains('minimum spend')) {
        return 'Minimum not met.';
      }
      if (message.contains('not found')) {
        return 'Code not found.';
      }
      if (message.contains('inactive')) {
        return 'Code inactive.';
      }
    }
    final text = error.toString();
    if (text.contains('minimum spend')) {
      return 'Minimum not met.';
    }
    if (text.contains('not found')) {
      return 'Code not found.';
    }
    if (text.contains('inactive')) {
      return 'Code inactive.';
    }
    return 'Could not apply.';
  }

  Future<void> _placeOrder(CheckoutDraft draft) async {
    if (_submitting) {
      return;
    }
    setState(() => _submitting = true);
    try {
      final correlationId = CheckoutDraftController.generateOrderId();
      final created = await ref.read(orderRepositoryProvider).createOrder(
            lines: draft.lines,
            correlationId: correlationId,
            shippingMethod:
                draft.needsShipping ? draft.shippingMethod.name : null,
            shippingAddressId: draft.needsShipping ? draft.addressId : null,
            shippingRecipientName:
                draft.needsShipping ? draft.recipientName : null,
            shippingAddressLine: draft.needsShipping ? draft.addressLine : null,
            shippingPhone: draft.needsShipping ? draft.phone : null,
            promoCode: draft.promoCode,
          );
      if (!mounted) {
        return;
      }
      final orderId = created.id ?? 0;
      ref.invalidate(orderListControllerProvider);
      if (orderId > 0) {
        ref.invalidate(orderDetailProvider(orderId));
      }
      ref.invalidate(sellerOrdersProvider);

      if (draft.paymentMethod == CheckoutPaymentMethod.wallet) {
        context.go('/orders/$orderId/pay?autopay=1');
        return;
      }

      context.go(
        '/orders/$orderId/pay?method=${Uri.encodeComponent(draft.paymentMethod.name)}',
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_placeOrderErrorMessage(e))),
      );
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  String _placeOrderErrorMessage(Object error) {
    if (error is ApiException) {
      if (error.errorCode == 'validation_failed' &&
          error.message.contains('self_purchase_not_allowed')) {
        return 'You cannot order your own product. Use the seller dashboard to manage this listing.';
      }
      final reason = error.context['reason_code']?.toString();
      if (reason == 'self_purchase_not_allowed') {
        return 'You cannot order your own product. Use the seller dashboard to manage this listing.';
      }
      switch (error.type) {
        case ApiExceptionType.validationFailed:
          return error.message.isNotEmpty
              ? error.message
              : 'Please check your order and try again.';
        case ApiExceptionType.network:
          return 'Network issue. Check your connection and try again.';
        case ApiExceptionType.unauthenticated:
          return 'Your session expired. Please sign in again.';
        case ApiExceptionType.internalError:
          return 'Server error. Please try again shortly.';
        case ApiExceptionType.forbidden:
          return 'You do not have permission to place this order.';
        case ApiExceptionType.notFound:
          return 'One item in this order is no longer available.';
        case ApiExceptionType.conflict:
        case ApiExceptionType.invalidStateTransition:
        case ApiExceptionType.unknown:
          return error.message.isNotEmpty
              ? error.message
              : 'Unable to place order.';
      }
    }
    return 'Unable to place order. Please try again.';
  }

  @override
  Widget build(BuildContext context) {
    final cartLines = ref.watch(cartControllerProvider);
    final draft = ref.watch(checkoutDraftProvider);
    if (draft == null) {
      if (cartLines.isNotEmpty) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (context.mounted) {
            ref.read(checkoutDraftProvider.notifier).restoreFromCart(cartLines);
          }
        });
      } else {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (context.mounted) {
            context.go('/checkout/guard');
          }
        });
      }
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final cs = Theme.of(context).colorScheme;
    final currency = draft.lines.isEmpty ? 'USD' : draft.lines.first.currency;
    final walletBalanceAsync = ref.watch(buyerWalletBalanceProvider(currency));
    final walletBalance = walletBalanceAsync.asData?.value;
    final walletReady = walletBalance != null;
    final walletShort = draft.paymentMethod == CheckoutPaymentMethod.wallet &&
        (!walletReady || draft.total > walletBalance);
    final canPlace = draft.termsAccepted && !walletShort;

    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: Text('Checkout',
            style: cartSectionHeading(Theme.of(context).textTheme)
                .copyWith(fontSize: 17)),
        centerTitle: true,
        surfaceTintColor: Colors.transparent,
        backgroundColor: cs.surface.withValues(alpha: 0.94),
        elevation: 0,
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[kCartPageBgTop, kCartPageBgBottom],
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: CheckoutStepper(activeStep: 2),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    if (draft.needsShipping) ...<Widget>[
                      _ReviewSectionTitle(
                        title: 'Shipping',
                        action: 'Change',
                        onAction: () => context.go('/checkout/shipping'),
                      ),
                      const SizedBox(height: 10),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(18),
                        decoration: cartCardDecoration(cs),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              draft.recipientName,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleSmall
                                  ?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: kCartNavy,
                                  ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              draft.addressLine,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyMedium
                                  ?.copyWith(color: kCartMuted, height: 1.4),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              draft.phone,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyMedium
                                  ?.copyWith(color: kCartMuted),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: <Widget>[
                                Expanded(
                                  child: Text(
                                    draft.shippingMethod.label,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyMedium
                                        ?.copyWith(fontWeight: FontWeight.w600),
                                  ),
                                ),
                                Text(
                                  _money(draft, draft.shippingMethod.feeUsd),
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleSmall
                                      ?.copyWith(fontWeight: FontWeight.w800),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),
                    ] else ...<Widget>[
                      const _ReviewSectionTitle(title: 'Digital delivery'),
                      const SizedBox(height: 10),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(18),
                        decoration: cartCardDecoration(cs),
                        child: Text(
                          'Delivered through escrow chat after payment. No shipping address is required.',
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium
                              ?.copyWith(color: kCartMuted, height: 1.4),
                        ),
                      ),
                      const SizedBox(height: 20),
                    ],
                    _ReviewSectionTitle(
                      title: 'Payment',
                      action: 'Change',
                      onAction: () => context.go('/checkout/payment'),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(18),
                      decoration: cartCardDecoration(cs),
                      child: Row(
                        children: <Widget>[
                          DecoratedBox(
                            decoration: BoxDecoration(
                              color: cs.primaryContainer.withValues(alpha: 0.5),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Padding(
                              padding: const EdgeInsets.all(10),
                              child: Icon(_paymentIcon(draft.paymentMethod),
                                  color: cs.primary, size: 22),
                            ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Text(
                              draft.paymentMethod.label,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyLarge
                                  ?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: kCartNavy,
                                  ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text('Order Summary',
                        style: cartSectionHeading(Theme.of(context).textTheme)),
                    const SizedBox(height: 10),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(18),
                      decoration: cartCardDecoration(cs),
                      child: Column(
                        children: <Widget>[
                          for (final CartLine line in draft.lines) ...<Widget>[
                            Row(
                              children: <Widget>[
                                Expanded(
                                  child: Text(
                                    line.title,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyMedium
                                        ?.copyWith(
                                          fontWeight: FontWeight.w600,
                                          color:
                                              kCartNavy.withValues(alpha: 0.88),
                                        ),
                                  ),
                                ),
                                Text(
                                  line.displayLineTotal,
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(
                                        fontWeight: FontWeight.w800,
                                        color: kCartNavy,
                                      ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                          ],
                          const Divider(height: 20),
                          _SummaryLine(
                              label: 'Subtotal',
                              value: _money(draft, draft.subtotal)),
                          const SizedBox(height: 8),
                          if (draft.needsShipping) ...<Widget>[
                            _SummaryLine(
                                label: 'Shipping',
                                value: _money(draft, draft.shippingFee)),
                            const SizedBox(height: 8),
                          ],
                          Container(
                            width: double.infinity,
                            margin: const EdgeInsets.only(top: 6),
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(14),
                              border:
                                  Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Row(
                                  children: <Widget>[
                                    Expanded(
                                      child: Text(
                                        'Promo',
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodyMedium
                                            ?.copyWith(
                                              color: kCartMuted,
                                              fontWeight: FontWeight.w700,
                                            ),
                                      ),
                                    ),
                                    TextButton.icon(
                                      onPressed: () => _togglePromoPanel(draft),
                                      icon: Icon(
                                        _promoExpanded
                                            ? Icons.keyboard_arrow_up_rounded
                                            : Icons.local_offer_outlined,
                                        size: 18,
                                      ),
                                      label: Text(draft.promoCode ?? 'Apply'),
                                    ),
                                  ],
                                ),
                                if (!_promoExpanded) ...<Widget>[
                                  const SizedBox(height: 4),
                                  Row(
                                    children: <Widget>[
                                      Expanded(
                                        child: Text(
                                          draft.promoCode == null
                                              ? 'No code.'
                                              : 'Applied: ${draft.promoCode}',
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodySmall
                                              ?.copyWith(
                                                color: kCartMuted,
                                                height: 1.35,
                                              ),
                                        ),
                                      ),
                                      if (draft.promoCode != null) ...<Widget>[
                                        const SizedBox(width: 10),
                                        TextButton(
                                          onPressed: () => _removePromo(draft),
                                          style: TextButton.styleFrom(
                                            foregroundColor:
                                                const Color(0xFFB45309),
                                            visualDensity:
                                                VisualDensity.compact,
                                          ),
                                          child: const Text('Remove'),
                                        ),
                                      ],
                                    ],
                                  ),
                                ] else ...<Widget>[
                                  const SizedBox(height: 12),
                                  TextField(
                                    controller: _promoController,
                                    textCapitalization:
                                        TextCapitalization.characters,
                                    onSubmitted: (value) =>
                                        _applyPromo(draft, value),
                                    decoration: const InputDecoration(
                                      hintText: 'Enter promo code',
                                      prefixIcon: Icon(
                                          Icons.confirmation_number_outlined),
                                    ),
                                  ),
                                  const SizedBox(height: 10),
                                  Row(
                                    children: <Widget>[
                                      Expanded(
                                        child: FilledButton(
                                          onPressed: _promoLoading
                                              ? null
                                              : () => _applyPromo(
                                                  draft, _promoController.text),
                                          style: cartPrimaryButtonStyle(cs),
                                          child: _promoLoading
                                              ? SizedBox(
                                                  width: 18,
                                                  height: 18,
                                                  child:
                                                      CircularProgressIndicator(
                                                    strokeWidth: 2.2,
                                                    valueColor:
                                                        AlwaysStoppedAnimation<
                                                                Color>(
                                                            cs.onPrimary),
                                                  ),
                                                )
                                              : const Text('Apply'),
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                      if (draft.promoCode != null) ...<Widget>[
                                        TextButton.icon(
                                          onPressed: _promoLoading
                                              ? null
                                              : () => _removePromo(draft),
                                          icon: const Icon(
                                              Icons.remove_circle_outline,
                                              size: 18),
                                          label: const Text('Remove'),
                                          style: TextButton.styleFrom(
                                            foregroundColor:
                                                const Color(0xFFB45309),
                                            visualDensity:
                                                VisualDensity.compact,
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                      ],
                                      TextButton(
                                        onPressed: () {
                                          setState(() {
                                            _promoExpanded = false;
                                            _promoMessage = null;
                                          });
                                        },
                                        child: const Text('Close'),
                                      ),
                                    ],
                                  ),
                                  if (_promoMessage != null) ...<Widget>[
                                    const SizedBox(height: 12),
                                    Container(
                                      width: double.infinity,
                                      padding: const EdgeInsets.all(12),
                                      decoration: BoxDecoration(
                                        color: draft.promoCode == null
                                            ? const Color(0xFFFFFBEB)
                                            : const Color(0xFFF0FDF4),
                                        borderRadius: BorderRadius.circular(12),
                                        border: Border.all(
                                          color: draft.promoCode == null
                                              ? const Color(0xFFFCD34D)
                                              : const Color(0xFF86EFAC),
                                        ),
                                      ),
                                      child: Row(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: <Widget>[
                                          Icon(
                                            draft.promoCode == null
                                                ? Icons.info_outline
                                                : Icons.check_circle_outline,
                                            size: 20,
                                            color: draft.promoCode == null
                                                ? const Color(0xFFD97706)
                                                : const Color(0xFF15803D),
                                          ),
                                          const SizedBox(width: 10),
                                          Expanded(
                                            child: Text(
                                              _promoMessage!,
                                              style: Theme.of(context)
                                                  .textTheme
                                                  .bodyMedium
                                                  ?.copyWith(
                                                    color:
                                                        draft.promoCode == null
                                                            ? const Color(
                                                                0xFF92400E)
                                                            : const Color(
                                                                0xFF166534),
                                                    height: 1.35,
                                                  ),
                                            ),
                                          ),
                                          if (draft.promoCode != null)
                                            TextButton(
                                              onPressed: _promoLoading
                                                  ? null
                                                  : () => _removePromo(draft),
                                              style: TextButton.styleFrom(
                                                foregroundColor:
                                                    const Color(0xFFB45309),
                                                visualDensity:
                                                    VisualDensity.compact,
                                              ),
                                              child: const Text('Remove'),
                                            ),
                                        ],
                                      ),
                                    ),
                                  ],
                                  const SizedBox(height: 14),
                                  Text(
                                    'Offers',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleSmall
                                        ?.copyWith(
                                          fontWeight: FontWeight.w900,
                                          color: kCartNavy,
                                        ),
                                  ),
                                  const SizedBox(height: 10),
                                  FutureBuilder<List<PromoOfferDto>>(
                                    future: _promoOffersFuture ??
                                        _loadPromoOffers(draft),
                                    builder: (context, snapshot) {
                                      if (snapshot.connectionState ==
                                          ConnectionState.waiting) {
                                        return const Padding(
                                          padding: EdgeInsets.symmetric(
                                              vertical: 18),
                                          child: Center(
                                              child:
                                                  CircularProgressIndicator()),
                                        );
                                      }
                                      if (snapshot.hasError) {
                                        return const _InlinePromoNotice(
                                          icon: Icons.cloud_off_outlined,
                                          title: 'Could not load',
                                          message: 'Try again later.',
                                          accent: Color(0xFF475569),
                                        );
                                      }
                                      final offers = snapshot.data ??
                                          const <PromoOfferDto>[];
                                      if (offers.isEmpty) {
                                        return const _InlinePromoNotice(
                                          icon: Icons.local_offer_outlined,
                                          title: 'No offers',
                                          message: 'Enter a code manually.',
                                          accent: Color(0xFF475569),
                                        );
                                      }
                                      return Column(
                                        children: <Widget>[
                                          for (final offer
                                              in offers) ...<Widget>[
                                            Padding(
                                              padding: const EdgeInsets.only(
                                                  bottom: 10),
                                              child: _InlineOfferTile(
                                                offer: offer,
                                                selected: draft.promoCode ==
                                                    offer.code,
                                                onTap: () => _applyPromo(
                                                    draft, offer.code),
                                              ),
                                            ),
                                          ],
                                        ],
                                      );
                                    },
                                  ),
                                ],
                              ],
                            ),
                          ),
                          if (draft.promoDiscount > 0) ...<Widget>[
                            _SummaryLine(
                              label: 'Discount',
                              value: '-${_money(draft, draft.promoDiscount)}',
                              valueColor: const Color(0xFF15803D),
                            ),
                            const SizedBox(height: 8),
                          ],
                          const SizedBox(height: 4),
                          Row(
                            children: <Widget>[
                              Expanded(
                                child: Text(
                                  'Total',
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleSmall
                                      ?.copyWith(
                                        fontWeight: FontWeight.w900,
                                        color: kCartNavy,
                                      ),
                                ),
                              ),
                              Text(
                                _money(draft, draft.total),
                                style: Theme.of(context)
                                    .textTheme
                                    .titleLarge
                                    ?.copyWith(
                                      fontWeight: FontWeight.w900,
                                      color: kCartNavy,
                                    ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    InkWell(
                      onTap: () => ref
                          .read(checkoutDraftProvider.notifier)
                          .setTermsAccepted(!draft.termsAccepted),
                      borderRadius: BorderRadius.circular(12),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            SizedBox(
                              width: 24,
                              height: 24,
                              child: Checkbox(
                                value: draft.termsAccepted,
                                onChanged: (v) => ref
                                    .read(checkoutDraftProvider.notifier)
                                    .setTermsAccepted(v ?? false),
                                materialTapTargetSize:
                                    MaterialTapTargetSize.shrinkWrap,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text.rich(
                                TextSpan(
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(height: 1.35),
                                  children: <InlineSpan>[
                                    const TextSpan(text: 'I agree to the '),
                                    WidgetSpan(
                                      alignment: PlaceholderAlignment.baseline,
                                      baseline: TextBaseline.alphabetic,
                                      child: GestureDetector(
                                        onTap: () {},
                                        child: Text(
                                          'Terms & Conditions',
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodyMedium
                                              ?.copyWith(
                                                color: cs.primary,
                                                fontWeight: FontWeight.w700,
                                                decoration:
                                                    TextDecoration.underline,
                                                decorationColor: cs.primary,
                                              ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    if (walletShort)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          walletBalanceAsync.isLoading
                              ? 'Checking wallet balance...'
                              : 'Choose another payment method.',
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall
                              ?.copyWith(color: cs.error),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            Container(
              padding: EdgeInsets.fromLTRB(
                  16, 12, 16, 16 + MediaQuery.paddingOf(context).bottom),
              decoration: BoxDecoration(
                color: cs.surface.withValues(alpha: 0.96),
                border: Border(
                    top: BorderSide(
                        color: cs.outlineVariant.withValues(alpha: 0.35))),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: const Color(0xFF0F172A).withValues(alpha: 0.06),
                    blurRadius: 20,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: FilledButton(
                onPressed:
                    canPlace && !_submitting ? () => _placeOrder(draft) : null,
                style: cartPrimaryButtonStyle(cs).copyWith(
                  minimumSize: WidgetStateProperty.all(
                      const Size.fromHeight(kCartBtnHeight + 8)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        if (_submitting)
                          SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.2,
                              valueColor:
                                  AlwaysStoppedAnimation<Color>(cs.onPrimary),
                            ),
                          )
                        else
                          Icon(Icons.lock_outline_rounded,
                              size: 18, color: cs.onPrimary),
                        const SizedBox(width: 8),
                        Text(_submitting ? 'Placing Order...' : 'Place Order',
                            style: TextStyle(
                                color: cs.onPrimary,
                                fontWeight: FontWeight.w800)),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Held in escrow',
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                            color: cs.onPrimary.withValues(alpha: 0.92),
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryLine extends StatelessWidget {
  const _SummaryLine({
    required this.label,
    required this.value,
    this.valueColor,
  });

  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            label,
            style: Theme.of(context)
                .textTheme
                .bodyMedium
                ?.copyWith(color: kCartMuted, fontWeight: FontWeight.w600),
          ),
        ),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w800, color: valueColor ?? kCartNavy),
        ),
      ],
    );
  }
}

class _InlinePromoNotice extends StatelessWidget {
  const _InlinePromoNotice({
    required this.icon,
    required this.title,
    required this.message,
    required this.accent,
  });

  final IconData icon;
  final String title;
  final String message;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, color: accent),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: kCartNavy,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: kCartMuted,
                        height: 1.35,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InlineOfferTile extends StatelessWidget {
  const _InlineOfferTile({
    required this.offer,
    required this.selected,
    required this.onTap,
  });

  final PromoOfferDto offer;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Ink(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
                color: selected ? cs.primary : const Color(0xFFE2E8F0),
                width: selected ? 1.4 : 1),
          ),
          child: Row(
            children: <Widget>[
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: const Color(0xFFF5F3FF),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.local_offer_outlined,
                    color: Color(0xFF4F46E5), size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            offer.code,
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium
                                ?.copyWith(
                                  fontWeight: FontWeight.w900,
                                  color: kCartNavy,
                                ),
                          ),
                        ),
                        if (selected)
                          Icon(Icons.check_circle_rounded,
                              color: cs.primary, size: 18),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      offer.description,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: kCartMuted,
                            height: 1.3,
                          ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReviewSectionTitle extends StatelessWidget {
  const _ReviewSectionTitle({
    required this.title,
    this.action,
    this.onAction,
  });

  final String title;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(title,
              style: cartSectionHeading(Theme.of(context).textTheme)),
        ),
        if (action != null && onAction != null)
          TextButton(
            onPressed: onAction,
            child: Text(action!,
                style:
                    TextStyle(fontWeight: FontWeight.w700, color: cs.primary)),
          ),
      ],
    );
  }
}
