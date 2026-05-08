import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'cart_ui.dart';

class BkashPaymentScreen extends StatelessWidget {
  const BkashPaymentScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const _BrandPaymentScaffold(
      title: 'bKash Payment',
      brandText: 'bKash',
      bannerColor: Color(0xFFE2136E),
      payTo: 'Marketplace Limited',
      invoiceNo: 'ORD-2025-000123',
      amount: '৳2,450.00',
      ctaLabel: 'Back to options',
    );
  }
}

class NagadPaymentScreen extends StatelessWidget {
  const NagadPaymentScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const _BrandPaymentScaffold(
      title: 'Nagad Payment',
      brandText: 'Nagad',
      bannerColor: Color(0xFFFF6A00),
      payTo: 'Marketplace Limited',
      invoiceNo: 'ORD-2025-000123',
      amount: '৳2,450.00',
      ctaLabel: 'Back to options',
    );
  }
}

class CardPaymentScreen extends StatelessWidget {
  const CardPaymentScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(title: const Text('Card Payment'), centerTitle: true),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
          children: <Widget>[
            Container(
              padding: const EdgeInsets.all(14),
              decoration: cartCardDecoration(cs),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: <Widget>[
                  Text('VISA',
                      style: TextStyle(
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF1A56DB))),
                  Text('Mastercard',
                      style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: Color(0xFFDC2626))),
                  Text('AMEX',
                      style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F766E))),
                ],
              ),
            ),
            const SizedBox(height: 12),
            const _CardField(label: 'Card Number', hint: '4242 4242 4242 4242'),
            const _CardField(
                label: 'Card Holder Name', hint: 'Mohammad Ashikur Rahman'),
            const Row(
              children: <Widget>[
                Expanded(
                    child: _CardField(label: 'Expiry Date', hint: 'MM / YY')),
                SizedBox(width: 10),
                Expanded(child: _CardField(label: 'CVV', hint: '123')),
              ],
            ),
            const SizedBox(height: 10),
            Text('Amount',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            Text('৳2,450.00',
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w900, color: kCartNavy)),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () => context.go('/checkout/payment'),
              style: cartPrimaryButtonStyle(cs),
              child: const Text('Back to options'),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: cs.surfaceContainerHighest.withValues(alpha: 0.3),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                children: <Widget>[
                  Icon(Icons.lock_outline, size: 18),
                  SizedBox(width: 8),
                  Text('Your payment is 100% secure'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CardField extends StatelessWidget {
  const _CardField({required this.label, required this.hint});

  final String label;
  final String hint;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label,
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: kCartMuted, fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          TextField(decoration: InputDecoration(hintText: hint)),
        ],
      ),
    );
  }
}

class _BrandPaymentScaffold extends StatelessWidget {
  const _BrandPaymentScaffold({
    required this.title,
    required this.brandText,
    required this.bannerColor,
    required this.payTo,
    required this.invoiceNo,
    required this.amount,
    required this.ctaLabel,
  });

  final String title;
  final String brandText;
  final Color bannerColor;
  final String payTo;
  final String invoiceNo;
  final String amount;
  final String ctaLabel;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(title: Text(title), centerTitle: true),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
          children: <Widget>[
            Container(
              height: 110,
              decoration: BoxDecoration(
                color: bannerColor,
                borderRadius: BorderRadius.circular(14),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                      color: bannerColor.withValues(alpha: 0.35),
                      blurRadius: 18,
                      offset: const Offset(0, 8)),
                ],
              ),
              alignment: Alignment.center,
              child: Text(brandText,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 36,
                      fontWeight: FontWeight.w900)),
            ),
            const SizedBox(height: 16),
            _DataRow(label: 'Pay To', value: payTo),
            const SizedBox(height: 6),
            _DataRow(label: 'Invoice No', value: invoiceNo),
            const SizedBox(height: 6),
            _DataRow(label: 'Amount', value: amount, strong: true),
            const Divider(height: 26),
            Text('Pay with $brandText',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            Text('1. You will be redirected to $brandText',
                style: Theme.of(context).textTheme.bodyLarge),
            const SizedBox(height: 6),
            const Text('2. Complete the payment'),
            const SizedBox(height: 6),
            const Text('3. You will be redirected back automatically'),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: cs.surfaceContainerHighest.withValues(alpha: 0.3),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                children: <Widget>[
                  Icon(Icons.lock_outline, size: 18),
                  SizedBox(width: 8),
                  Text('Your payment is 100% secure'),
                ],
              ),
            ),
            const SizedBox(height: 18),
            FilledButton(
              onPressed: () => context.go('/checkout/payment'),
              style: cartPrimaryButtonStyle(cs).copyWith(
                  backgroundColor: WidgetStatePropertyAll(bannerColor)),
              child: Text(ctaLabel),
            ),
          ],
        ),
      ),
    );
  }
}

class _DataRow extends StatelessWidget {
  const _DataRow(
      {required this.label, required this.value, this.strong = false});

  final String label;
  final String value;
  final bool strong;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
            child: Text(label,
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium
                    ?.copyWith(color: kCartMuted))),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              fontWeight: strong ? FontWeight.w900 : FontWeight.w700,
              color: kCartNavy),
        ),
      ],
    );
  }
}
