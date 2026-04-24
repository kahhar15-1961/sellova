import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'cart_ui.dart';

class OrderSuccessScreen extends StatelessWidget {
  const OrderSuccessScreen({
    super.key,
    required this.orderId,
    required this.totalFormatted,
  });

  final String orderId;
  final String totalFormatted;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[
              cs.primary.withValues(alpha: 0.08),
              const Color(0xFFEEF2FF),
              kCartPageBgBottom,
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              children: <Widget>[
                const SizedBox(height: 16),
                Expanded(
                  child: SingleChildScrollView(
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.fromLTRB(22, 40, 22, 28),
                      decoration: cartCardDecoration(cs, radius: kCartRadiusLarge).copyWith(
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: cs.primary.withValues(alpha: 0.1),
                            blurRadius: 32,
                            offset: const Offset(0, 14),
                          ),
                          BoxShadow(
                            color: const Color(0xFF0F172A).withValues(alpha: 0.06),
                            blurRadius: 24,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: Column(
                        children: <Widget>[
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: <Widget>[
                              Icon(Icons.auto_awesome_rounded, size: 18, color: _accent(0)),
                              const SizedBox(width: 8),
                              Icon(Icons.auto_awesome_rounded, size: 18, color: _accent(1)),
                              const SizedBox(width: 8),
                              Icon(Icons.auto_awesome_rounded, size: 18, color: _accent(2)),
                            ],
                          ),
                          const SizedBox(height: 16),
                          Container(
                            width: 92,
                            height: 92,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              gradient: const LinearGradient(
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                                colors: <Color>[Color(0xFF34D399), Color(0xFF059669)],
                              ),
                              boxShadow: <BoxShadow>[
                                BoxShadow(
                                  color: const Color(0xFF059669).withValues(alpha: 0.45),
                                  blurRadius: 20,
                                  offset: const Offset(0, 10),
                                ),
                              ],
                            ),
                            child: const Icon(Icons.check_rounded, color: Colors.white, size: 50),
                          ),
                          const SizedBox(height: 28),
                          Text(
                            'Order placed successfully',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                  fontWeight: FontWeight.w900,
                                  color: kCartNavy,
                                  letterSpacing: -0.4,
                                  height: 1.15,
                                ),
                          ),
                          const SizedBox(height: 12),
                          Text.rich(
                            TextSpan(
                              style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: kCartMuted, height: 1.45),
                              children: <InlineSpan>[
                                const TextSpan(text: 'Your payment of '),
                                TextSpan(
                                  text: totalFormatted,
                                  style: const TextStyle(fontWeight: FontWeight.w900, color: kCartNavy),
                                ),
                                const TextSpan(text: ' is held securely in escrow.'),
                              ],
                            ),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 26),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                            decoration: BoxDecoration(
                              color: cs.surfaceContainerHighest.withValues(alpha: 0.55),
                              borderRadius: BorderRadius.circular(999),
                              border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: <Widget>[
                                Text(
                                  'Order ID ',
                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                        color: kCartMuted,
                                        fontWeight: FontWeight.w600,
                                      ),
                                ),
                                Text(
                                  '#$orderId',
                                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                        fontWeight: FontWeight.w900,
                                        color: kCartNavy,
                                      ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 28),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(18),
                            decoration: BoxDecoration(
                              color: cs.primaryContainer.withValues(alpha: 0.22),
                              borderRadius: BorderRadius.circular(kCartRadius),
                              border: Border.all(color: cs.primary.withValues(alpha: 0.12)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  'What happens next?',
                                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                        fontWeight: FontWeight.w900,
                                        color: kCartNavy,
                                      ),
                                ),
                                const SizedBox(height: 14),
                                _EscrowStep(n: 1, text: 'Seller will process your order', isLast: false, cs: cs),
                                _EscrowStep(n: 2, text: 'You will receive your item or service', isLast: false, cs: cs),
                                _EscrowStep(n: 3, text: 'Confirm delivery to release payment', isLast: true, cs: cs),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                Padding(
                  padding: EdgeInsets.fromLTRB(0, 12, 0, 12 + MediaQuery.paddingOf(context).bottom),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      FilledButton(
                        onPressed: () => context.go('/orders'),
                        style: cartPrimaryButtonStyle(cs),
                        child: const Text('View order'),
                      ),
                      const SizedBox(height: 10),
                      OutlinedButton(
                        onPressed: () => context.go('/home'),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(kCartBtnHeight),
                          side: BorderSide(color: kCartNavy.withValues(alpha: 0.18)),
                          foregroundColor: kCartNavy,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        child: Text('Continue shopping', style: TextStyle(fontWeight: FontWeight.w800, color: cs.primary)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  static Color _accent(int i) {
    const colors = <Color>[
      Color(0xFF6366F1),
      Color(0xFFF59E0B),
      Color(0xFF0EA5E9),
    ];
    return colors[i % colors.length];
  }
}

class _EscrowStep extends StatelessWidget {
  const _EscrowStep({
    required this.n,
    required this.text,
    required this.isLast,
    required this.cs,
  });

  final int n;
  final String text;
  final bool isLast;
  final ColorScheme cs;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Column(
          children: <Widget>[
            Container(
              width: 30,
              height: 30,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: <Color>[cs.primary, cs.primary.withValues(alpha: 0.85)],
                ),
                shape: BoxShape.circle,
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: cs.primary.withValues(alpha: 0.25),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Text(
                '$n',
                style: TextStyle(color: cs.onPrimary, fontWeight: FontWeight.w900, fontSize: 13),
              ),
            ),
            if (!isLast)
              Container(
                width: 2,
                height: 26,
                margin: const EdgeInsets.symmetric(vertical: 4),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(2),
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: <Color>[
                      cs.primary.withValues(alpha: 0.45),
                      cs.primary.withValues(alpha: 0.12),
                    ],
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: isLast ? 0 : 6, top: 2),
            child: Text(
              text,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: kCartNavy.withValues(alpha: 0.9),
                    fontWeight: FontWeight.w600,
                    height: 1.4,
                  ),
            ),
          ),
        ),
      ],
    );
  }
}
