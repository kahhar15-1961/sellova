import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../application/order_detail_provider.dart';
import '../application/order_list_controller.dart';
import '../data/order_repository.dart';

class OrderPaymentScreen extends ConsumerStatefulWidget {
  const OrderPaymentScreen({
    super.key,
    required this.orderId,
    this.autoPay = false,
    this.paymentMethod = 'wallet',
  });

  final int orderId;
  final bool autoPay;
  final String paymentMethod;

  @override
  ConsumerState<OrderPaymentScreen> createState() => _OrderPaymentScreenState();
}

class _OrderPaymentScreenState extends ConsumerState<OrderPaymentScreen> {
  final TextEditingController _referenceCtrl = TextEditingController();
  bool _paying = false;
  bool _autoPayTriggered = false;

  @override
  void dispose() {
    _referenceCtrl.dispose();
    super.dispose();
  }

  String get _normalizedMethod {
    final value = widget.paymentMethod.trim().toLowerCase();
    if (value == 'card' ||
        value == 'bkash' ||
        value == 'nagad' ||
        value == 'bank') {
      return value;
    }
    return 'wallet';
  }

  String get _methodLabel => switch (_normalizedMethod) {
        'card' => 'Card',
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'bank' => 'Bank transfer',
        _ => 'Wallet',
      };

  String get _referenceLabel => switch (_normalizedMethod) {
        'card' => 'Approval code',
        'bkash' || 'nagad' => 'Transaction ID',
        'bank' => 'Deposit reference',
        _ => 'Reference',
      };

  String get _referenceHint => switch (_normalizedMethod) {
        'card' => 'AUTH-12345',
        'bkash' => 'BKASH-123456789',
        'nagad' => 'NAGAD-123456789',
        'bank' => 'Bank slip / reference',
        _ => 'Enter reference',
      };

  Color get _accentColor => switch (_normalizedMethod) {
        'card' => const Color(0xFF1D4ED8),
        'bkash' => const Color(0xFFE2136E),
        'nagad' => const Color(0xFFFF7A00),
        'bank' => const Color(0xFF0F766E),
        _ => const Color(0xFF4338CA),
      };

  IconData get _methodIcon => switch (_normalizedMethod) {
        'card' => Icons.credit_card_outlined,
        'bkash' || 'nagad' => Icons.phone_android_outlined,
        'bank' => Icons.account_balance_outlined,
        _ => Icons.account_balance_wallet_outlined,
      };

  Future<void> _payWithWallet(OrderDto order) async {
    if (_paying) {
      return;
    }
    final router = GoRouter.of(context);
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _paying = true);
    try {
      final settled = await ref.read(orderRepositoryProvider).payWithWallet(
            orderId: widget.orderId,
            correlationId: 'wallet-pay-${widget.orderId}',
          );
      if (!mounted) return;
      final freshOrder =
          await ref.read(orderRepositoryProvider).getById(widget.orderId);
      ref.invalidate(orderDetailProvider(widget.orderId));
      ref.invalidate(orderListControllerProvider);
      if (widget.autoPay) {
        final orderId = freshOrder.id ?? settled.id ?? widget.orderId;
        final total = _amountOnly(
          freshOrder.raw['net_amount'] ??
              freshOrder.raw['total_amount'] ??
              freshOrder.raw['gross_amount'] ??
              '0.00',
        );
        final currency =
            (freshOrder.raw['currency'] ?? settled.raw['currency'] ?? 'USD')
                .toString()
                .toUpperCase();
        router.go(
          '/order-success?orderId=${Uri.encodeComponent(orderId.toString())}'
          '&orderNumber=${Uri.encodeComponent(freshOrder.orderNumber)}'
          '&total=${Uri.encodeComponent(total)}'
          '&currency=${Uri.encodeComponent(currency)}',
        );
      } else {
        router.go('/orders/${freshOrder.id ?? settled.id ?? widget.orderId}');
      }
    } catch (e) {
      if (!mounted) return;
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            widget.autoPay
                ? 'The order was created, but wallet payment needs a retry: $e'
                : 'Unable to complete wallet payment: $e',
          ),
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _paying = false);
      }
    }
  }

  Future<void> _payWithManualMethod(OrderDto order) async {
    if (_paying) {
      return;
    }
    final reference = _referenceCtrl.text.trim();
    if (reference.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Enter a ${_referenceLabel.toLowerCase()}.')),
      );
      return;
    }

    final router = GoRouter.of(context);
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _paying = true);
    try {
      final settled = await ref
          .read(orderRepositoryProvider)
          .payWithManualMethod(
            orderId: widget.orderId,
            provider: _normalizedMethod,
            providerReference: reference,
            correlationId: 'manual-pay-${widget.orderId}-$_normalizedMethod',
          );
      if (!mounted) return;
      final freshOrder =
          await ref.read(orderRepositoryProvider).getById(widget.orderId);
      ref.invalidate(orderDetailProvider(widget.orderId));
      ref.invalidate(orderListControllerProvider);

      final orderId = freshOrder.id ?? settled.id ?? widget.orderId;
      final total = _amountOnly(
        freshOrder.raw['net_amount'] ??
            freshOrder.raw['total_amount'] ??
            freshOrder.raw['gross_amount'] ??
            '0.00',
      );
      final currency =
          (freshOrder.raw['currency'] ?? settled.raw['currency'] ?? 'USD')
              .toString()
              .toUpperCase();
      router.go(
        '/order-success?orderId=${Uri.encodeComponent(orderId.toString())}'
        '&orderNumber=${Uri.encodeComponent(freshOrder.orderNumber)}'
        '&total=${Uri.encodeComponent(total)}'
        '&currency=${Uri.encodeComponent(currency)}',
      );
    } catch (e) {
      if (!mounted) return;
      messenger.showSnackBar(
        SnackBar(
          content: Text(
              'Unable to submit ${_methodLabel.toLowerCase()} payment: $e'),
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _paying = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(orderDetailProvider(widget.orderId));
    return Scaffold(
      backgroundColor: const Color(0xFFF7F8FC),
      appBar: AppBar(
        title: const Text('Complete Payment'),
        centerTitle: true,
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
      ),
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(child: Text(error.toString())),
        data: (order) {
          if (widget.autoPay && !_autoPayTriggered) {
            _autoPayTriggered = true;
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (mounted) {
                _payWithWallet(order);
              }
            });
          }

          final total = order.totalLabel;
          if (_normalizedMethod == 'wallet') {
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              children: <Widget>[
                _PaymentHero(
                  methodLabel: _methodLabel,
                  orderNumber: order.orderNumber,
                  total: total,
                  accentColor: _accentColor,
                  icon: _methodIcon,
                  subtitle: 'Instant settlement to escrow',
                ),
                const SizedBox(height: 16),
                _MethodCard(
                  title: 'Wallet',
                  subtitle: 'Instant settlement',
                  amountLabel: total,
                  accentColor: _accentColor,
                  icon: _methodIcon,
                  actionLabel: _paying ? 'Paying...' : 'Pay now',
                  onPressed: _paying ? null : () => _payWithWallet(order),
                ),
              ],
            );
          }

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            children: <Widget>[
              _PaymentHero(
                methodLabel: _methodLabel,
                orderNumber: order.orderNumber,
                total: total,
                accentColor: _accentColor,
                icon: _methodIcon,
                subtitle: 'Manual capture reference required',
              ),
              const SizedBox(height: 8),
              Text(
                'Enter the reference from ${_methodLabel.toLowerCase()} to settle the order.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 16),
              _MethodCard(
                title: _methodLabel,
                subtitle: 'Manual capture',
                amountLabel: total,
                accentColor: _accentColor,
                icon: _methodIcon,
                actionLabel: _paying ? 'Submitting...' : 'Confirm payment',
                onPressed: _paying ? null : () => _payWithManualMethod(order),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _referenceCtrl,
                textInputAction: TextInputAction.done,
                onSubmitted: (_) => _payWithManualMethod(order),
                decoration: InputDecoration(
                  labelText: _referenceLabel,
                  hintText: _referenceHint,
                  prefixIcon: const Icon(Icons.confirmation_number_outlined),
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Icon(Icons.lock_outline, size: 18),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                          'The payment is recorded immediately and moved into escrow.'),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

String _amountOnly(dynamic raw) {
  final parsed = num.tryParse(raw.toString()) ?? 0;
  return parsed.toStringAsFixed(2);
}

class _PaymentHero extends StatelessWidget {
  const _PaymentHero({
    required this.methodLabel,
    required this.orderNumber,
    required this.total,
    required this.accentColor,
    required this.icon,
    required this.subtitle,
  });

  final String methodLabel;
  final String orderNumber;
  final String total;
  final Color accentColor;
  final IconData icon;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            accentColor.withValues(alpha: 0.96),
            const Color(0xFF111827),
          ],
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: accentColor.withValues(alpha: 0.22),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              DecoratedBox(
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Icon(icon, color: Colors.white, size: 24),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      methodLabel,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      orderNumber,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Colors.white.withValues(alpha: 0.82),
                          ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            total,
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.white.withValues(alpha: 0.82),
                ),
          ),
        ],
      ),
    );
  }
}

class _MethodCard extends StatelessWidget {
  const _MethodCard({
    required this.title,
    required this.subtitle,
    required this.amountLabel,
    required this.accentColor,
    required this.icon,
    required this.actionLabel,
    required this.onPressed,
  });

  final String title;
  final String subtitle;
  final String amountLabel;
  final Color accentColor;
  final IconData icon;
  final String actionLabel;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cs.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              DecoratedBox(
                decoration: BoxDecoration(
                  color: accentColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(10),
                  child: Icon(icon, color: accentColor, size: 22),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium
                          ?.copyWith(color: Colors.black54),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            amountLabel,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                  color: accentColor,
                ),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: onPressed,
            style: FilledButton.styleFrom(
              backgroundColor: accentColor,
              minimumSize: const Size.fromHeight(50),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            child: Text(actionLabel),
          ),
        ],
      ),
    );
  }
}
