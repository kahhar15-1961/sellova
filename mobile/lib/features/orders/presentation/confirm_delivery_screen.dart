import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class ConfirmDeliveryScreen extends StatelessWidget {
  const ConfirmDeliveryScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Text('Order #$orderId'),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
          child: Column(
            children: <Widget>[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: cs.surface,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
                ),
                child: const Column(
                  children: <Widget>[
                    _MetaRow(label: 'Order Date', value: '29 May 2025, 10:30 AM'),
                    SizedBox(height: 8),
                    _MetaRow(label: 'Delivered On', value: '01 June 2025, 02:15 PM'),
                    SizedBox(height: 8),
                    _MetaRow(label: 'Payment Method', value: 'bKash'),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: cs.surface,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: const Color(0xFF0F172A).withValues(alpha: 0.06),
                      blurRadius: 20,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: Column(
                  children: <Widget>[
                    Container(
                      width: 82,
                      height: 82,
                      decoration: const BoxDecoration(
                        color: Color(0xFFEAF8EF),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 44),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Confirm Delivery',
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 24, fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Please confirm that you have received your order in good condition.',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
                    ),
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF5F3FF),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFE9D5FF)),
                      ),
                      child: const Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Icon(Icons.info_outline, color: Color(0xFF4F46E5)),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'After confirmation, payment will be released to the seller from escrow.',
                              style: TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              FilledButton(
                onPressed: () => context.go('/orders/$orderId/review'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Confirm Delivery'),
              ),
              const SizedBox(height: 10),
              OutlinedButton(
                onPressed: () => Navigator.of(context).maybePop(),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Cancel'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: const Color(0xFF64748B)),
          ),
        ),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
      ],
    );
  }
}
