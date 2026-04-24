import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/checkout_draft_controller.dart';
import '../domain/cart_line.dart';
import 'cart_ui.dart';

class CheckoutPaymentScreen extends ConsumerWidget {
  const CheckoutPaymentScreen({super.key});

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
    final walletShort = draft.paymentMethod == CheckoutPaymentMethod.wallet && !draft.walletCoversTotal;

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
              child: CheckoutStepper(activeStep: 1),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
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
                          _PayRow(label: 'Subtotal', value: _money(draft, draft.subtotal)),
                          const SizedBox(height: 8),
                          _PayRow(
                            label: 'Shipping Fee',
                            value: _money(draft, draft.shippingFee),
                            caption: draft.shippingFee == 0 ? 'No shipping for digital / service items' : null,
                          ),
                          if (draft.promoDiscount > 0) ...<Widget>[
                            const SizedBox(height: 8),
                            _PayRow(
                              label: 'Promo Discount',
                              value: '-${_money(draft, draft.promoDiscount)}',
                            ),
                          ],
                          const Divider(height: 20),
                          _PayRow(
                            label: 'Total',
                            value: _money(draft, draft.total),
                            emphasize: true,
                          ),
                        ],
                      ),
                    ),
                  if (walletShort) ...<Widget>[
                    const SizedBox(height: 14),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: cartCardDecoration(cs, elevated: false).copyWith(
                        color: const Color(0xFFFFFBEB),
                        border: Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.45)),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Icon(Icons.account_balance_wallet_outlined, color: const Color(0xFFB45309), size: 24),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              'Wallet (${_money(draft, kMockWalletBalance)}) does not cover this total. '
                              'Select card, bKash, Nagad, or bank transfer to continue — split settlement can be enabled when payments go live.',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: const Color(0xFF78350F),
                                    height: 1.4,
                                    fontWeight: FontWeight.w600,
                                  ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 22),
                  Text('Payment Method', style: cartSectionHeading(Theme.of(context).textTheme)),
                  const SizedBox(height: 10),
                  _PaymentTile(
                    title: CheckoutPaymentMethod.wallet.label,
                    subtitle: 'Available ${_money(draft, kMockWalletBalance)}',
                    selected: draft.paymentMethod == CheckoutPaymentMethod.wallet,
                    onTap: () => ref.read(checkoutDraftProvider.notifier).updatePayment(CheckoutPaymentMethod.wallet),
                  ),
                  const SizedBox(height: 8),
                  for (final CheckoutPaymentMethod m in <CheckoutPaymentMethod>[
                    CheckoutPaymentMethod.card,
                    CheckoutPaymentMethod.bkash,
                    CheckoutPaymentMethod.nagad,
                    CheckoutPaymentMethod.bank,
                  ]) ...<Widget>[
                    _PaymentTile(
                      title: m.label,
                      selected: draft.paymentMethod == m,
                      onTap: () => ref.read(checkoutDraftProvider.notifier).updatePayment(m),
                    ),
                    const SizedBox(height: 8),
                  ],
                  const SizedBox(height: 12),
                  TextButton.icon(
                    onPressed: () {
                      final route = switch (draft.paymentMethod) {
                        CheckoutPaymentMethod.card => '/checkout/payment/card',
                        CheckoutPaymentMethod.bkash => '/checkout/payment/bkash',
                        CheckoutPaymentMethod.nagad => '/checkout/payment/nagad',
                        CheckoutPaymentMethod.wallet || CheckoutPaymentMethod.bank => null,
                      };
                      if (route != null) {
                        context.push(route);
                      }
                    },
                    icon: const Icon(Icons.open_in_new_rounded, size: 18),
                    label: const Text('Open selected payment screen'),
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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  OutlinedButton(
                    onPressed: () => context.pop(),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(50),
                      side: BorderSide(color: kCartNavy.withValues(alpha: 0.2)),
                      foregroundColor: kCartNavy,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: const Text('Back', style: TextStyle(fontWeight: FontWeight.w800)),
                  ),
                  const SizedBox(height: 10),
                  FilledButton(
                    onPressed: walletShort ? null : () => context.push('/checkout/review'),
                    style: cartPrimaryButtonStyle(cs),
                    child: const Text('Continue to review'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PayRow extends StatelessWidget {
  const _PayRow({
    required this.label,
    required this.value,
    this.emphasize = false,
    this.caption,
  });

  final String label;
  final String value;
  final bool emphasize;
  final String? caption;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                label,
                style: emphasize
                    ? theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800, color: kCartNavy)
                    : theme.textTheme.bodyMedium?.copyWith(color: kCartMuted, fontWeight: FontWeight.w600),
              ),
            ),
            Text(
              value,
              style: emphasize
                  ? theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: kCartNavy)
                  : theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
          ],
        ),
        if (caption != null) ...<Widget>[
          const SizedBox(height: 4),
          Text(caption!, style: theme.textTheme.bodySmall?.copyWith(color: kCartMuted)),
        ],
      ],
    );
  }
}

class _PaymentTile extends StatelessWidget {
  const _PaymentTile({
    required this.title,
    required this.selected,
    required this.onTap,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(kCartRadius),
        child: Ink(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          decoration: selected
              ? cartCardDecoration(cs).copyWith(
                  color: cs.primaryContainer.withValues(alpha: 0.42),
                  border: Border.all(color: cs.primary.withValues(alpha: 0.5), width: 1.75),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: cs.primary.withValues(alpha: 0.18),
                      blurRadius: 18,
                      offset: const Offset(0, 8),
                    ),
                  ],
                )
              : cartCardDecoration(cs),
          child: Row(
            children: <Widget>[
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_off,
                color: selected ? cs.primary : kCartMuted,
                size: 22,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(title, style: Theme.of(context).textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600)),
                    if (subtitle != null)
                      Text(
                        subtitle!,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kCartMuted, fontWeight: FontWeight.w600),
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
