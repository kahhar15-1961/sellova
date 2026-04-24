import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/address_book_controller.dart';
import '../application/checkout_draft_controller.dart';
import 'cart_ui.dart';

class CheckoutShippingScreen extends ConsumerWidget {
  const CheckoutShippingScreen({super.key});

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
    final addresses = ref.watch(savedAddressesProvider);
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
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
            child: CheckoutStepper(activeStep: 0),
          ),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  _SectionTitle(title: 'Delivery Address', action: 'Manage', onAction: () => context.push('/addresses')),
                  const SizedBox(height: 10),
                  for (final address in addresses) ...<Widget>[
                    _AddressOptionTile(
                      address: address,
                      selected: draft.addressId == address.id,
                      onTap: () => ref.read(checkoutDraftProvider.notifier).selectAddress(address.id),
                    ),
                    const SizedBox(height: 10),
                  ],
                  const SizedBox(height: 10),
                  OutlinedButton.icon(
                    onPressed: () => context.push('/addresses/edit'),
                    icon: const Icon(Icons.add_rounded),
                    style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                    label: const Text('Add New Address'),
                  ),
                  const SizedBox(height: 22),
                  Text('Shipping Method', style: cartSectionHeading(Theme.of(context).textTheme)),
                  const SizedBox(height: 10),
                  if (!draft.needsShipping)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: cartCardDecoration(cs, elevated: false).copyWith(
                        color: cs.primaryContainer.withValues(alpha: 0.25),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Icon(Icons.verified_user_outlined, size: 22, color: cs.primary),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              'No shipping required — your order contains digital or service items only.',
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: kCartNavy.withValues(alpha: 0.85),
                                    height: 1.4,
                                    fontWeight: FontWeight.w600,
                                  ),
                            ),
                          ),
                        ],
                      ),
                    )
                  else ...<Widget>[
                    _ShippingOptionTile(
                      title: CheckoutShippingMethod.standard.label,
                      feeLabel: '\$20.00',
                      selected: draft.shippingMethod == CheckoutShippingMethod.standard,
                      onTap: () => ref.read(checkoutDraftProvider.notifier).updateShipping(CheckoutShippingMethod.standard),
                    ),
                    const SizedBox(height: 10),
                    _ShippingOptionTile(
                      title: CheckoutShippingMethod.express.label,
                      feeLabel: '\$40.00',
                      selected: draft.shippingMethod == CheckoutShippingMethod.express,
                      onTap: () => ref.read(checkoutDraftProvider.notifier).updateShipping(CheckoutShippingMethod.express),
                    ),
                  ],
                ],
              ),
            ),
          ),
          Container(
            padding: EdgeInsets.fromLTRB(16, 10, 16, 16 + MediaQuery.paddingOf(context).bottom),
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
              onPressed: () => context.push('/checkout/payment'),
              style: cartPrimaryButtonStyle(cs),
              child: const Text('Continue'),
            ),
          ),
          ],
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({
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

class _ShippingOptionTile extends StatelessWidget {
  const _ShippingOptionTile({
    required this.title,
    required this.feeLabel,
    required this.selected,
    required this.onTap,
  });

  final String title;
  final String feeLabel;
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
                      color: cs.primary.withValues(alpha: 0.2),
                      blurRadius: 18,
                      offset: const Offset(0, 8),
                    ),
                    BoxShadow(
                      color: const Color(0xFF0F172A).withValues(alpha: 0.05),
                      blurRadius: 14,
                      offset: const Offset(0, 4),
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
              Expanded(child: Text(title, style: Theme.of(context).textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600))),
              Text(
                feeLabel,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AddressOptionTile extends StatelessWidget {
  const _AddressOptionTile({
    required this.address,
    required this.selected,
    required this.onTap,
  });

  final CheckoutAddress address;
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
          padding: const EdgeInsets.all(14),
          decoration: selected
              ? cartCardDecoration(cs).copyWith(
                  color: cs.primaryContainer.withValues(alpha: 0.36),
                  border: Border.all(color: cs.primary.withValues(alpha: 0.52), width: 1.5),
                )
              : cartCardDecoration(cs),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(
                selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                color: selected ? cs.primary : kCartMuted,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Text(address.title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800, color: kCartNavy)),
                        if (address.isDefault) ...<Widget>[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(color: const Color(0xFFEDE9FE), borderRadius: BorderRadius.circular(999)),
                            child: const Text('Default', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF4F46E5))),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(address.detailsAddress, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted, height: 1.35)),
                    const SizedBox(height: 4),
                    Text(address.phone, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted)),
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
