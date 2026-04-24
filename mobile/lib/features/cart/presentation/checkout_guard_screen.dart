import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'cart_ui.dart';

class CheckoutGuardScreen extends StatelessWidget {
  const CheckoutGuardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Text('Checkout', style: cartSectionHeading(Theme.of(context).textTheme)),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Container(
                  width: 96,
                  height: 96,
                  decoration: BoxDecoration(
                    color: cs.primaryContainer.withValues(alpha: 0.28),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(Icons.shopping_bag_outlined, color: cs.primary, size: 42),
                ),
                const SizedBox(height: 20),
                Text(
                  'Your checkout session has expired or is no longer available.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700, color: kCartNavy),
                ),
                const SizedBox(height: 10),
                Text(
                  'Please review your cart and continue.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: kCartMuted),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () => context.go('/cart'),
                  style: cartPrimaryButtonStyle(cs),
                  child: const Text('Go to Cart'),
                ),
                const SizedBox(height: 8),
                OutlinedButton(
                  onPressed: () => context.go('/home'),
                  style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(kCartBtnHeight)),
                  child: const Text('Back to Home'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
