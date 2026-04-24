import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/cart_controller.dart';
import '../application/checkout_draft_controller.dart';
import '../domain/cart_line.dart';
import 'cart_ui.dart';

IconData _paymentIcon(CheckoutPaymentMethod m) {
  return switch (m) {
    CheckoutPaymentMethod.wallet => Icons.account_balance_wallet_outlined,
    CheckoutPaymentMethod.card => Icons.credit_card,
    CheckoutPaymentMethod.bkash || CheckoutPaymentMethod.nagad => Icons.smartphone_outlined,
    CheckoutPaymentMethod.bank => Icons.account_balance_outlined,
  };
}

class CheckoutReviewScreen extends ConsumerWidget {
  const CheckoutReviewScreen({super.key});

  static String _money(CheckoutDraft d, double amount) {
    final c = d.lines.isEmpty ? 'USD' : d.lines.first.currency.toUpperCase();
    final t = amount.toStringAsFixed(2);
    return c == 'USD' ? '\$$t' : '$c $t';
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final draft = ref.watch(checkoutDraftProvider);
    if (draft == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (context.mounted) {
          context.go('/checkout/guard');
        }
      });
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final cs = Theme.of(context).colorScheme;
    final canPlace = draft.termsAccepted && draft.walletCoversTotal;

    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: Text('Checkout', style: cartSectionHeading(Theme.of(context).textTheme).copyWith(fontSize: 17)),
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
                            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: kCartNavy,
                                ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            draft.addressLine,
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted, height: 1.4),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            draft.phone,
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted),
                          ),
                          if (draft.needsShipping) ...<Widget>[
                            const SizedBox(height: 12),
                            Row(
                              children: <Widget>[
                                Expanded(
                                  child: Text(
                                    draft.shippingMethod.label,
                                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                                  ),
                                ),
                                Text(
                                  _money(draft, draft.shippingMethod.feeUsd),
                                  style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                                ),
                              ],
                            ),
                          ] else
                            Padding(
                              padding: const EdgeInsets.only(top: 10),
                              child: Text(
                                'No shipping required',
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kCartMuted),
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    _ReviewSectionTitle(
                      title: 'Payment Method',
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
                              child: Icon(_paymentIcon(draft.paymentMethod), color: cs.primary, size: 22),
                            ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Text(
                              draft.paymentMethod.label,
                              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: kCartNavy,
                                  ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text('Order Summary', style: cartSectionHeading(Theme.of(context).textTheme)),
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
                                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                          fontWeight: FontWeight.w600,
                                          color: kCartNavy.withValues(alpha: 0.88),
                                        ),
                                  ),
                                ),
                                Text(
                                  line.displayLineTotal,
                                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                        fontWeight: FontWeight.w800,
                                        color: kCartNavy,
                                      ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                          ],
                          const Divider(height: 20),
                          _SummaryLine(label: 'Subtotal', value: _money(draft, draft.subtotal)),
                          const SizedBox(height: 8),
                          _SummaryLine(label: 'Shipping', value: _money(draft, draft.shippingFee)),
                          const SizedBox(height: 8),
                          Row(
                            children: <Widget>[
                              Expanded(
                                child: Text(
                                  'Promo Code',
                                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                        color: kCartMuted,
                                        fontWeight: FontWeight.w600,
                                      ),
                                ),
                              ),
                              TextButton(
                                onPressed: () => context.push('/checkout/promo'),
                                child: Text(draft.promoCode ?? 'Apply'),
                              ),
                            ],
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
                                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                        fontWeight: FontWeight.w900,
                                        color: kCartNavy,
                                      ),
                                ),
                              ),
                              Text(
                                _money(draft, draft.total),
                                style: Theme.of(context).textTheme.titleLarge?.copyWith(
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
                      onTap: () => ref.read(checkoutDraftProvider.notifier).setTermsAccepted(!draft.termsAccepted),
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
                                onChanged: (v) => ref.read(checkoutDraftProvider.notifier).setTermsAccepted(v ?? false),
                                materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text.rich(
                                TextSpan(
                                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.35),
                                  children: <InlineSpan>[
                                    const TextSpan(text: 'I agree to the '),
                                    WidgetSpan(
                                      alignment: PlaceholderAlignment.baseline,
                                      baseline: TextBaseline.alphabetic,
                                      child: GestureDetector(
                                        onTap: () {},
                                        child: Text(
                                          'Terms & Conditions',
                                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                                color: cs.primary,
                                                fontWeight: FontWeight.w700,
                                                decoration: TextDecoration.underline,
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
                    if (!draft.walletCoversTotal && draft.paymentMethod == CheckoutPaymentMethod.wallet)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          'Select another payment method to place this order.',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: cs.error),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            Container(
              padding: EdgeInsets.fromLTRB(16, 12, 16, 16 + MediaQuery.paddingOf(context).bottom),
              decoration: BoxDecoration(
                color: cs.surface.withValues(alpha: 0.96),
                border: Border(top: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.35))),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: const Color(0xFF0F172A).withValues(alpha: 0.06),
                    blurRadius: 20,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: FilledButton(
                onPressed: canPlace
                    ? () {
                        final orderId = CheckoutDraftController.generateOrderId();
                        final total = draft.total.toStringAsFixed(2);
                        final currency = draft.lines.isEmpty ? 'USD' : draft.lines.first.currency;
                        ref.read(cartControllerProvider.notifier).decrementForCompletedOrder(draft.lines);
                        ref.read(checkoutDraftProvider.notifier).clear();
                        context.go(
                          '/order-success?orderId=${Uri.encodeComponent(orderId)}'
                          '&total=${Uri.encodeComponent(total)}'
                          '&currency=${Uri.encodeComponent(currency)}',
                        );
                      }
                    : null,
                style: cartPrimaryButtonStyle(cs).copyWith(
                  minimumSize: WidgetStateProperty.all(const Size.fromHeight(kCartBtnHeight + 8)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Icon(Icons.lock_outline_rounded, size: 18, color: cs.onPrimary),
                        const SizedBox(width: 8),
                        Text('Place Order', style: TextStyle(color: cs.onPrimary, fontWeight: FontWeight.w800)),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Payment will be held in escrow',
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
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted, fontWeight: FontWeight.w600),
          ),
        ),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w800, color: valueColor ?? kCartNavy),
        ),
      ],
    );
  }
}

class _ReviewSectionTitle extends StatelessWidget {
  const _ReviewSectionTitle({
    required this.title,
    required this.action,
    required this.onAction,
  });

  final String title;
  final String action;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(title, style: cartSectionHeading(Theme.of(context).textTheme)),
        ),
        TextButton(
          onPressed: onAction,
          child: Text(action, style: TextStyle(fontWeight: FontWeight.w700, color: cs.primary)),
        ),
      ],
    );
  }
}
