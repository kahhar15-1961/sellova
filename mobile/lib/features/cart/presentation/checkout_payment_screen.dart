import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/cart_controller.dart';
import '../application/checkout_draft_controller.dart';
import '../application/payment_gateway_provider.dart';
import '../../orders/data/order_repository.dart';
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
    final gatewaysAsync = ref.watch(paymentGatewayCatalogProvider);
    final walletBalance = walletBalanceAsync.asData?.value;
    final enabledGateways = gatewaysAsync.asData?.value;
    final gatewaysLoaded = enabledGateways != null;
    final activeGateways = enabledGateways ?? const <PaymentGatewayItem>[];
    final walletReady = walletBalance != null;
    final walletShort = draft.paymentMethod == CheckoutPaymentMethod.wallet &&
        (!walletReady || draft.total > walletBalance);
    final supportedMethods = <CheckoutPaymentMethod>{
      CheckoutPaymentMethod.wallet,
      if (gatewaysLoaded)
        for (final gateway in activeGateways)
          for (final method in gateway.supportedMethods)
            switch (method) {
              'card' => CheckoutPaymentMethod.card,
              'bkash' => CheckoutPaymentMethod.bkash,
              'nagad' => CheckoutPaymentMethod.nagad,
              'bank' => CheckoutPaymentMethod.bank,
              _ => CheckoutPaymentMethod.wallet,
            },
    };
    final availableMethods = <CheckoutPaymentMethod>[
      CheckoutPaymentMethod.wallet,
      for (final method in <CheckoutPaymentMethod>[
        CheckoutPaymentMethod.card,
        CheckoutPaymentMethod.bkash,
        CheckoutPaymentMethod.nagad,
        CheckoutPaymentMethod.bank,
      ])
        if (supportedMethods.contains(method)) method,
    ];
    final currentMethodAvailable =
        supportedMethods.contains(draft.paymentMethod);

    if (gatewaysLoaded &&
        !currentMethodAvailable &&
        draft.paymentMethod != CheckoutPaymentMethod.wallet) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!context.mounted) {
          return;
        }
        final nextMethod = availableMethods.firstWhere(
          (method) => method != CheckoutPaymentMethod.wallet,
          orElse: () => CheckoutPaymentMethod.wallet,
        );
        ref.read(checkoutDraftProvider.notifier).updatePayment(nextMethod);
      });
    }

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
              child: CheckoutStepper(activeStep: 1),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
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
                          _PayRow(
                              label: 'Subtotal',
                              value: _money(draft, draft.subtotal)),
                          const SizedBox(height: 8),
                          _PayRow(
                            label: 'Shipping',
                            value: _money(draft, draft.shippingFee),
                            caption:
                                draft.shippingFee == 0 ? 'No shipping' : null,
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
                        decoration:
                            cartCardDecoration(cs, elevated: false).copyWith(
                          color: const Color(0xFFFFFBEB),
                          border: Border.all(
                              color: const Color(0xFFF59E0B)
                                  .withValues(alpha: 0.45)),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            const Icon(Icons.account_balance_wallet_outlined,
                                color: Color(0xFFB45309), size: 24),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                'Wallet is short. Choose another method to continue.',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall
                                    ?.copyWith(
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
                    Text('Payment Method',
                        style: cartSectionHeading(Theme.of(context).textTheme)),
                    const SizedBox(height: 10),
                    _PaymentTile(
                      title: CheckoutPaymentMethod.wallet.label,
                      subtitle: walletBalanceAsync.isLoading
                          ? 'Checking balance...'
                          : walletBalance != null
                              ? 'Available ${_money(draft, walletBalance)}'
                              : 'Balance unavailable',
                      selected:
                          draft.paymentMethod == CheckoutPaymentMethod.wallet,
                      onTap: () => ref
                          .read(checkoutDraftProvider.notifier)
                          .updatePayment(CheckoutPaymentMethod.wallet),
                    ),
                    const SizedBox(height: 8),
                    for (final CheckoutPaymentMethod m
                        in <CheckoutPaymentMethod>[
                      CheckoutPaymentMethod.card,
                      CheckoutPaymentMethod.bkash,
                      CheckoutPaymentMethod.nagad,
                      CheckoutPaymentMethod.bank,
                    ]) ...<Widget>[
                      if (!gatewaysLoaded || supportedMethods.contains(m))
                        _PaymentTile(
                          title: m.label,
                          subtitle: gatewaysLoaded
                              ? _gatewaySubtitle(m, activeGateways)
                              : 'Checking availability...',
                          selected: draft.paymentMethod == m,
                          onTap: () => ref
                              .read(checkoutDraftProvider.notifier)
                              .updatePayment(m),
                        )
                      else
                        _PaymentTile(
                          title: m.label,
                          subtitle: 'Not enabled',
                          selected: draft.paymentMethod == m,
                          enabled: false,
                        ),
                      const SizedBox(height: 8),
                    ],
                    if (gatewaysLoaded &&
                        supportedMethods.length == 1) ...<Widget>[
                      const SizedBox(height: 2),
                      Text(
                        'No online payment methods are active yet.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: kCartMuted,
                              fontWeight: FontWeight.w600,
                            ),
                      ),
                    ],
                    const SizedBox(height: 12),
                    TextButton.icon(
                      onPressed: (!gatewaysLoaded ||
                              supportedMethods.contains(draft.paymentMethod))
                          ? () {
                              final route = switch (draft.paymentMethod) {
                                CheckoutPaymentMethod.card =>
                                  '/checkout/payment/card',
                                CheckoutPaymentMethod.bkash =>
                                  '/checkout/payment/bkash',
                                CheckoutPaymentMethod.nagad =>
                                  '/checkout/payment/nagad',
                                CheckoutPaymentMethod.wallet ||
                                CheckoutPaymentMethod.bank =>
                                  null,
                              };
                              if (route != null) {
                                context.push(route);
                              }
                            }
                          : null,
                      icon: const Icon(Icons.open_in_new_rounded, size: 18),
                      label: const Text('Open screen'),
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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  OutlinedButton(
                    onPressed: () => context.pop(),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(50),
                      side: BorderSide(color: kCartNavy.withValues(alpha: 0.2)),
                      foregroundColor: kCartNavy,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14)),
                    ),
                    child: const Text('Back',
                        style: TextStyle(fontWeight: FontWeight.w800)),
                  ),
                  const SizedBox(height: 10),
                  FilledButton(
                    onPressed: walletShort
                        ? null
                        : (!gatewaysLoaded ||
                                supportedMethods.contains(draft.paymentMethod))
                            ? () => context.push('/checkout/review')
                            : null,
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
                    ? theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800, color: kCartNavy)
                    : theme.textTheme.bodyMedium?.copyWith(
                        color: kCartMuted, fontWeight: FontWeight.w600),
              ),
            ),
            Text(
              value,
              style: emphasize
                  ? theme.textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w900, color: kCartNavy)
                  : theme.textTheme.bodyMedium
                      ?.copyWith(fontWeight: FontWeight.w700),
            ),
          ],
        ),
        if (caption != null) ...<Widget>[
          const SizedBox(height: 4),
          Text(caption!,
              style: theme.textTheme.bodySmall?.copyWith(color: kCartMuted)),
        ],
      ],
    );
  }
}

class _PaymentTile extends StatelessWidget {
  const _PaymentTile({
    required this.title,
    required this.selected,
    this.subtitle,
    this.enabled = true,
    this.onTap,
  });

  final String title;
  final String? subtitle;
  final bool selected;
  final VoidCallback? onTap;
  final bool enabled;

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
                  border: Border.all(
                      color: cs.primary.withValues(alpha: 0.5), width: 1.75),
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
                color: !enabled
                    ? kCartMuted.withValues(alpha: 0.5)
                    : selected
                        ? cs.primary
                        : kCartMuted,
                size: 22,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(title,
                        style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                              fontWeight: FontWeight.w600,
                              color: enabled ? null : kCartMuted,
                            )),
                    if (subtitle != null)
                      Text(
                        subtitle!,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: kCartMuted, fontWeight: FontWeight.w600),
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

String _gatewaySubtitle(
  CheckoutPaymentMethod method,
  List<PaymentGatewayItem> gateways,
) {
  final matching = gateways.where((gateway) {
    return gateway.supportedMethods
        .map((value) => value.toLowerCase())
        .contains(method.name);
  }).toList();
  if (matching.isEmpty) {
    return 'Managed by admin';
  }
  final defaultGateway = matching.firstWhere(
    (gateway) => gateway.isDefault,
    orElse: () => matching.first,
  );
  final name = defaultGateway.name.trim();
  final driver = defaultGateway.driver.trim();
  if (name.isEmpty) {
    return 'Managed by admin';
  }
  final suffix = driver.isEmpty ? '' : ' · $driver';
  return 'Enabled via $name$suffix';
}
