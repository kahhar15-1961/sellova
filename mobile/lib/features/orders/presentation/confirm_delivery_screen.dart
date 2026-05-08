import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../application/order_detail_provider.dart';
import '../application/order_list_controller.dart';
import '../data/order_repository.dart';

class ConfirmDeliveryScreen extends ConsumerStatefulWidget {
  const ConfirmDeliveryScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  ConsumerState<ConfirmDeliveryScreen> createState() => _ConfirmDeliveryScreenState();
}

class _ConfirmDeliveryScreenState extends ConsumerState<ConfirmDeliveryScreen> {
  bool _submitting = false;

  Future<void> _confirmDelivery(OrderDto order) async {
    if (_submitting) {
      return;
    }

    final router = GoRouter.of(context);
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _submitting = true);
    try {
      await ref.read(orderRepositoryProvider).completeOrder(
            orderId: widget.orderId,
            correlationId: 'buyer-complete-${widget.orderId}',
          );
      final fresh = await ref.read(orderRepositoryProvider).getById(widget.orderId);
      ref.invalidate(orderDetailProvider(widget.orderId));
      ref.invalidate(orderTrackingProvider(widget.orderId));
      ref.invalidate(orderListControllerProvider);
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(
        const SnackBar(content: Text('Delivery confirmed. Escrow has been released.')),
      );
      router.go('/orders/${fresh.id ?? widget.orderId}/review');
    } catch (error) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(
        SnackBar(content: Text('Unable to confirm delivery: $error')),
      );
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(orderDetailProvider(widget.orderId));
    final cs = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: const Text('Confirm Delivery'),
        centerTitle: true,
      ),
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              error.toString(),
              textAlign: TextAlign.center,
            ),
          ),
        ),
        data: (order) {
          final deliveredOn = _niceDate(order.raw['delivered_at'] ?? order.raw['completed_at']);
          final shippedOn = _niceDate(order.raw['shipped_at']);
          final paymentMethod = _paymentMethodLabel(order);
          return SafeArea(
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
                    child: Column(
                      children: <Widget>[
                        _MetaRow(label: 'Order Date', value: _niceDate(order.createdAt)),
                        const SizedBox(height: 8),
                        _MetaRow(label: 'Shipped On', value: shippedOn),
                        const SizedBox(height: 8),
                        _MetaRow(label: 'Delivered On', value: deliveredOn),
                        const SizedBox(height: 8),
                        _MetaRow(label: 'Payment Method', value: paymentMethod),
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
                          'Release Escrow',
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 24, fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Confirm that you received this order in good condition. We will mark it completed and release the held payment to the seller.',
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
                          child: Text(
                            order.totalLabel,
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: const Color(0xFF312E81),
                                ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),
                  FilledButton(
                    onPressed: _submitting ? null : () => _confirmDelivery(order),
                    style: FilledButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: Text(_submitting ? 'Confirming...' : 'Confirm Delivery'),
                  ),
                  const SizedBox(height: 10),
                  OutlinedButton(
                    onPressed: _submitting ? null : () => Navigator.of(context).maybePop(),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: const Text('Cancel'),
                  ),
                ],
              ),
            ),
          );
        },
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

String _niceDate(DateTime? value) {
  if (value == null) {
    return 'Not recorded';
  }
  const months = <String>[
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  final hour = value.hour % 12 == 0 ? 12 : value.hour % 12;
  final minute = value.minute.toString().padLeft(2, '0');
  final suffix = value.hour >= 12 ? 'PM' : 'AM';
  return '${value.day} ${months[value.month - 1]} ${value.year}, $hour:$minute $suffix';
}

String _paymentMethodLabel(OrderDto order) {
  final raw = (order.raw['payment_method'] ?? order.raw['payment_provider'] ?? '').toString().trim();
  if (raw.isEmpty) {
    return 'Wallet';
  }
  final normalized = raw.toLowerCase();
  if (normalized == 'wallet') {
    return 'Wallet';
  }
  return raw
      .split(RegExp(r'[_\s]+'))
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}
