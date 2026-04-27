import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/order_detail_provider.dart';
import '../data/order_repository.dart';
import '../domain/order_ui_stage.dart';

const Color _kNavy = Color(0xFF0B1A60);
const Color _kMuted = Color(0xFF64748B);

class OrderDetailScreen extends ConsumerWidget {
  const OrderDetailScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(orderDetailProvider(orderId));
    return Scaffold(
      body: detailAsync.when(
        loading: () => const _OrderDetailSkeleton(),
        error: (error, _) => _OrderDetailError(
          message: error.toString(),
          onRetry: () => ref.refresh(orderDetailProvider(orderId)),
        ),
        data: (order) => _OrderDetailContent(order: order),
      ),
    );
  }
}

class _OrderDetailContent extends StatelessWidget {
  const _OrderDetailContent({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final stage = _orderStageFromOrder(order);
    final currentStep = _currentStep(stage);
    final created = _niceDateTime(order.createdAt);

    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: <Color>[Color(0xFFF4F6FC), Color(0xFFF8F9FE)],
        ),
      ),
      child: SafeArea(
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 2, 8, 8),
              child: Row(
                children: <Widget>[
                  IconButton(
                    onPressed: () => Navigator.of(context).maybePop(),
                    icon: const Icon(Icons.arrow_back_ios_new_rounded),
                  ),
                  Expanded(
                    child: Column(
                      children: <Widget>[
                        Text(
                          order.orderNumber.startsWith('#')
                              ? order.orderNumber
                              : '#${order.orderNumber}',
                          style: Theme.of(context)
                              .textTheme
                              .titleLarge
                              ?.copyWith(
                                  fontWeight: FontWeight.w900, color: _kNavy),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Placed on $created',
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall
                              ?.copyWith(
                                  color: _kMuted, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () {},
                    icon: const Icon(Icons.more_vert_rounded),
                  ),
                ],
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    _HeroStateCard(order: order, stage: stage),
                    const SizedBox(height: 18),
                    if (stage == OrderUiStage.toPay) ...<Widget>[
                      _ToPayStateSection(order: order),
                    ] else if (stage == OrderUiStage.disputed) ...<Widget>[
                      _DisputedStateSection(order: order),
                    ] else if (stage == OrderUiStage.cancelled) ...<Widget>[
                      _CancelledStateSection(order: order),
                    ] else ...<Widget>[
                      _MilestoneTimeline(currentStep: currentStep),
                      const SizedBox(height: 18),
                      _AmountCard(order: order),
                    ],
                  ],
                ),
              ),
            ),
            Container(
              padding: EdgeInsets.fromLTRB(
                  16, 10, 16, 14 + MediaQuery.paddingOf(context).bottom),
              decoration: BoxDecoration(
                color: cs.surface.withValues(alpha: 0.95),
                border: Border(
                    top: BorderSide(
                        color: cs.outlineVariant.withValues(alpha: 0.35))),
              ),
              child: stage == OrderUiStage.disputed ||
                      stage == OrderUiStage.cancelled
                  ? OutlinedButton(
                      onPressed: () {
                        final id = order.id;
                        if (id == null) return;
                        if (stage == OrderUiStage.disputed) {
                          final disputeId = _extractDisputeId(order.raw);
                          HapticFeedback.lightImpact();
                          if (disputeId != null) {
                            context.push('/disputes/$disputeId');
                          } else {
                            context.push('/disputes/create?orderId=$id');
                          }
                          return;
                        }
                        HapticFeedback.selectionClick();
                        context.push('/orders/$id/chat');
                      },
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(52),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14)),
                        side: BorderSide(
                            color: cs.primary.withValues(alpha: 0.4)),
                      ),
                      child: Text(_ctaText(stage)),
                    )
                  : Column(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        if (stage == OrderUiStage.completed)
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(
                              onPressed: () {
                                final id = order.id;
                                if (id == null) return;
                                HapticFeedback.selectionClick();
                                context.push('/orders/$id/return-request');
                              },
                              child: const Text('Need return/refund?'),
                            ),
                          ),
                        FilledButton(
                          onPressed: () {
                            final id = order.id;
                            if (id == null) {
                              return;
                            }
                            switch (stage) {
                              case OrderUiStage.toPay:
                                HapticFeedback.lightImpact();
                                context.push(_paymentRouteForOrder(order));
                                break;
                              case OrderUiStage.shipped:
                              case OrderUiStage.delivered:
                                HapticFeedback.lightImpact();
                                context.push('/orders/$id/confirm-delivery');
                                break;
                              case OrderUiStage.completed:
                                HapticFeedback.lightImpact();
                                context.push('/orders/$id/review');
                                break;
                              case OrderUiStage.disputed:
                              case OrderUiStage.cancelled:
                                break;
                              case OrderUiStage.escrow:
                              case OrderUiStage.processing:
                              case OrderUiStage.other:
                                HapticFeedback.lightImpact();
                                context.push('/orders/$id/chat');
                                break;
                            }
                          },
                          style: FilledButton.styleFrom(
                            minimumSize: const Size.fromHeight(52),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14)),
                          ),
                          child: Text(_ctaText(stage)),
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

class _HeroStateCard extends StatelessWidget {
  const _HeroStateCard({required this.order, required this.stage});
  final OrderDto order;
  final OrderUiStage stage;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final style = _stageStyle(stage);
    final tracking = _extractTracking(order.raw);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: style.bg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: style.fg.withValues(alpha: 0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(style.icon, color: style.fg),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  style.title,
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge
                      ?.copyWith(fontWeight: FontWeight.w900, color: style.fg),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            style.message,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: style.fg.withValues(alpha: 0.88),
                  fontWeight: FontWeight.w600,
                  height: 1.4,
                ),
          ),
          if (tracking != null &&
              (stage == OrderUiStage.shipped ||
                  stage == OrderUiStage.delivered)) ...<Widget>[
            const SizedBox(height: 10),
            Text(
              'Tracking ID: $tracking',
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium
                  ?.copyWith(color: style.fg, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            FilledButton.tonal(
              onPressed: () {
                final id = order.id;
                if (id != null) {
                  HapticFeedback.selectionClick();
                  context.push('/orders/$id/track');
                }
              },
              style: FilledButton.styleFrom(
                minimumSize: const Size(120, 36),
                backgroundColor: cs.primary.withValues(alpha: 0.16),
                foregroundColor: cs.primary,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(999)),
              ),
              child: const Text('Track Order'),
            ),
          ],
        ],
      ),
    );
  }
}

class _MilestoneTimeline extends StatelessWidget {
  const _MilestoneTimeline({required this.currentStep});
  final int currentStep;

  @override
  Widget build(BuildContext context) {
    const labels = <String>[
      'Order Placed',
      'Paid in Escrow',
      'Seller Processing',
      'Shipped / Delivered',
      'Completed',
    ];
    final now = DateTime.now();

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
            color: Theme.of(context)
                .colorScheme
                .outlineVariant
                .withValues(alpha: 0.35)),
      ),
      child: Column(
        children: List<Widget>.generate(labels.length, (i) {
          final done = i < currentStep;
          final active = i == currentStep;
          final color = done || active
              ? (active ? const Color(0xFF4F46E5) : const Color(0xFF22C55E))
              : const Color(0xFF94A3B8);
          return _TimelineRow(
            title: labels[i],
            subtitle: _timelineDate(
                now.subtract(Duration(hours: (labels.length - i) * 2))),
            color: color,
            showLine: i < labels.length - 1,
            state: done
                ? _PointState.done
                : (active ? _PointState.active : _PointState.pending),
          );
        }),
      ),
    );
  }
}

enum _PointState { done, active, pending }

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({
    required this.title,
    required this.subtitle,
    required this.color,
    required this.showLine,
    required this.state,
  });

  final String title;
  final String subtitle;
  final Color color;
  final bool showLine;
  final _PointState state;

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 28,
            child: Column(
              children: <Widget>[
                Container(
                  width: 20,
                  height: 20,
                  decoration: BoxDecoration(
                    color: state == _PointState.pending
                        ? Colors.transparent
                        : color,
                    border: Border.all(color: color, width: 2),
                    shape: BoxShape.circle,
                  ),
                  child: state == _PointState.done
                      ? const Icon(Icons.check, size: 12, color: Colors.white)
                      : state == _PointState.pending
                          ? Icon(Icons.circle, size: 8, color: color)
                          : null,
                ),
                if (showLine)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      color: color.withValues(alpha: 0.35),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    title,
                    style: Theme.of(context)
                        .textTheme
                        .titleSmall
                        ?.copyWith(fontWeight: FontWeight.w800, color: _kNavy),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: _kMuted, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AmountCard extends StatelessWidget {
  const _AmountCard({required this.order});
  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
            color: Theme.of(context)
                .colorScheme
                .outlineVariant
                .withValues(alpha: 0.35)),
      ),
      child: Column(
        children: <Widget>[
          _moneyRow(context, 'Total Amount', _totalMoney(order), true),
          const SizedBox(height: 10),
          _moneyRow(
              context, 'Payment State', _humanize(order.paymentStatus), false),
          const SizedBox(height: 8),
          _moneyRow(
              context, 'Escrow State', _humanize(order.escrowStatus), false),
        ],
      ),
    );
  }
}

class _ToPayStateSection extends StatelessWidget {
  const _ToPayStateSection({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final items = order.items;
    final first = items.isNotEmpty ? items.first : <String, dynamic>{};
    final itemName = (first['title'] ??
            first['name'] ??
            first['product_name'] ??
            'Order item')
        .toString();
    final qty = ((first['quantity'] as num?)?.toInt() ?? 1).toString();
    final total = _totalMoney(order);
    return Column(
      children: <Widget>[
        _DetailsCard(
          rows: <({String label, String value})>[
            (label: 'Order Date', value: _niceDateTime(order.createdAt)),
            (label: 'Payment Method', value: _paymentMethodLabel(order)),
            (label: 'Total Amount', value: total),
          ],
        ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: _cardDeco(context),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text('Items (${items.isEmpty ? 1 : items.length})',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900, color: _kNavy)),
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                      color: Theme.of(context)
                          .colorScheme
                          .outlineVariant
                          .withValues(alpha: 0.4)),
                ),
                child: Row(
                  children: <Widget>[
                    Container(
                      width: 56,
                      height: 56,
                      decoration: BoxDecoration(
                          color: Theme.of(context)
                              .colorScheme
                              .surfaceContainerHighest,
                          borderRadius: BorderRadius.circular(10)),
                      child: const Icon(Icons.headphones_rounded),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(itemName,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyLarge
                                  ?.copyWith(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 6),
                          Text('Qty: $qty',
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyMedium
                                  ?.copyWith(color: _kMuted)),
                        ],
                      ),
                    ),
                    Text(total,
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(fontWeight: FontWeight.w900)),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Text('Payment Summary',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900, color: _kNavy)),
              const SizedBox(height: 8),
              _moneyRow(context, 'Subtotal', total, false),
              const SizedBox(height: 8),
              _moneyRow(
                  context,
                  'Shipping',
                  _moneyFromRaw(order.raw['shipping_amount'] ?? 0, order),
                  false),
              const Divider(height: 22),
              _moneyRow(context, 'Total', total, true),
            ],
          ),
        ),
      ],
    );
  }
}

class _DisputedStateSection extends StatelessWidget {
  const _DisputedStateSection({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final disputeOpenedAt = _niceDateTime(_parseAnyDate(
        order.raw['disputed_at'] ?? order.raw['dispute_opened_at']));
    return Column(
      children: <Widget>[
        _DetailsCard(
          rows: <({String label, String value})>[
            (label: 'Order Date', value: _niceDateTime(order.createdAt)),
            (label: 'Dispute Opened', value: disputeOpenedAt),
            (label: 'Total Amount', value: _totalMoney(order)),
          ],
        ),
        const SizedBox(height: 14),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: _cardDeco(context),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text('Issue',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900, color: _kNavy)),
              const SizedBox(height: 6),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                      color: Theme.of(context)
                          .colorScheme
                          .outlineVariant
                          .withValues(alpha: 0.4)),
                ),
                child: Text(
                  (order.raw['dispute_reason'] ??
                          order.raw['reason'] ??
                          'Item not as described.')
                      .toString(),
                  style: Theme.of(context)
                      .textTheme
                      .bodyLarge
                      ?.copyWith(height: 1.4),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _MilestoneTimeline(currentStep: 2),
      ],
    );
  }
}

class _CancelledStateSection extends StatelessWidget {
  const _CancelledStateSection({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final cancelledAt = _niceDateTime(
        _parseAnyDate(order.raw['cancelled_at'] ?? order.raw['canceled_at']));
    return Column(
      children: <Widget>[
        _DetailsCard(
          rows: <({String label, String value})>[
            (label: 'Order Date', value: _niceDateTime(order.createdAt)),
            (label: 'Cancelled On', value: cancelledAt),
            (label: 'Payment Method', value: _paymentMethodLabel(order)),
            (label: 'Total Amount', value: _totalMoney(order)),
          ],
        ),
        const SizedBox(height: 14),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: _cardDeco(context),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text('Reason',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900, color: _kNavy)),
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                      color: Theme.of(context)
                          .colorScheme
                          .outlineVariant
                          .withValues(alpha: 0.4)),
                ),
                child: Text(
                  (order.raw['cancel_reason'] ??
                          order.raw['reason'] ??
                          'Order was cancelled by the buyer.')
                      .toString(),
                  style: Theme.of(context)
                      .textTheme
                      .bodyLarge
                      ?.copyWith(height: 1.4),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _DetailsCard extends StatelessWidget {
  const _DetailsCard({required this.rows});

  final List<({String label, String value})> rows;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: _cardDeco(context),
      child: Column(
        children: <Widget>[
          for (int i = 0; i < rows.length; i++) ...<Widget>[
            _moneyRow(context, rows[i].label, rows[i].value, false),
            if (i < rows.length - 1) const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

Widget _moneyRow(
    BuildContext context, String label, String value, bool emphasize) {
  return Row(
    children: <Widget>[
      Expanded(
        child: Text(
          label,
          style: Theme.of(context)
              .textTheme
              .bodyMedium
              ?.copyWith(color: _kMuted, fontWeight: FontWeight.w600),
        ),
      ),
      Text(
        value,
        style: emphasize
            ? Theme.of(context)
                .textTheme
                .titleLarge
                ?.copyWith(fontWeight: FontWeight.w900, color: _kNavy)
            : Theme.of(context)
                .textTheme
                .bodyMedium
                ?.copyWith(fontWeight: FontWeight.w800, color: _kNavy),
      ),
    ],
  );
}

class _StateStyle {
  const _StateStyle(this.title, this.message, this.icon, this.bg, this.fg);
  final String title;
  final String message;
  final IconData icon;
  final Color bg;
  final Color fg;
}

_StateStyle _stageStyle(OrderUiStage stage) {
  return switch (stage) {
    OrderUiStage.toPay => const _StateStyle(
        'Awaiting Payment',
        'Complete your payment to secure this order in escrow.',
        Icons.payments_outlined,
        Color(0xFFFFFBEB),
        Color(0xFFB45309),
      ),
    OrderUiStage.escrow => const _StateStyle(
        'Paid in Escrow',
        'Your payment is securely held. Seller gets paid after you confirm delivery.',
        Icons.account_balance_wallet_outlined,
        Color(0xFFEDE9FE),
        Color(0xFF4F46E5),
      ),
    OrderUiStage.processing => const _StateStyle(
        'Seller Processing',
        'Seller is preparing your order.',
        Icons.inventory_2_outlined,
        Color(0xFFEFF6FF),
        Color(0xFF1D4ED8),
      ),
    OrderUiStage.shipped => const _StateStyle(
        'Shipped',
        'Your order is on the way.',
        Icons.local_shipping_outlined,
        Color(0xFFECFDF5),
        Color(0xFF15803D),
      ),
    OrderUiStage.delivered => const _StateStyle(
        'Delivered',
        'Please confirm delivery to release escrow.',
        Icons.inventory_rounded,
        Color(0xFFECFDF5),
        Color(0xFF15803D),
      ),
    OrderUiStage.completed => const _StateStyle(
        'Completed',
        'You have confirmed delivery.',
        Icons.task_alt_rounded,
        Color(0xFFDCFCE7),
        Color(0xFF15803D),
      ),
    OrderUiStage.disputed => const _StateStyle(
        'Dispute Open',
        'This order is under dispute review.',
        Icons.gavel_outlined,
        Color(0xFFFFF1F2),
        Color(0xFFDC2626),
      ),
    OrderUiStage.cancelled => const _StateStyle(
        'Cancelled',
        'This order was cancelled.',
        Icons.cancel_outlined,
        Color(0xFFF1F5F9),
        Color(0xFF475569),
      ),
    OrderUiStage.other => const _StateStyle(
        'Order Updated',
        'Order status has changed.',
        Icons.sync,
        Color(0xFFF1F5F9),
        Color(0xFF475569),
      ),
  };
}

OrderUiStage _orderStageFromOrder(OrderDto o) => inferOrderUiStage(o);

int _currentStep(OrderUiStage stage) {
  return switch (stage) {
    OrderUiStage.toPay => 0,
    OrderUiStage.escrow => 1,
    OrderUiStage.processing => 2,
    OrderUiStage.shipped || OrderUiStage.delivered => 3,
    OrderUiStage.completed => 4,
    OrderUiStage.disputed || OrderUiStage.cancelled || OrderUiStage.other => 2,
  };
}

String _ctaText(OrderUiStage stage) {
  return switch (stage) {
    OrderUiStage.toPay => 'Pay Now',
    OrderUiStage.shipped || OrderUiStage.delivered => 'Confirm Delivery',
    OrderUiStage.completed => 'Rate & Review',
    OrderUiStage.disputed => 'View Dispute Details',
    OrderUiStage.cancelled => 'View Support',
    OrderUiStage.escrow ||
    OrderUiStage.processing ||
    OrderUiStage.other =>
      'Contact Seller',
  };
}

String _humanize(String raw) {
  final s = raw.trim().toLowerCase();
  if (s.isEmpty || s == 'unavailable') return 'Not provided';
  return s
      .split(RegExp(r'[_\s]+'))
      .where((w) => w.isNotEmpty)
      .map((w) => '${w[0].toUpperCase()}${w.substring(1)}')
      .join(' ');
}

String _totalMoney(OrderDto order) {
  final currency = (order.raw['currency'] ?? '').toString().toUpperCase();
  final total = order.raw['total_amount'] ??
      order.raw['gross_amount'] ??
      order.raw['net_amount'] ??
      order.raw['total'];
  if (total == null) return order.totalLabel;
  final n = num.tryParse(total.toString());
  if (n == null)
    return currency.isEmpty ? total.toString() : '$currency $total';
  final t = n.toStringAsFixed(2);
  return currency == 'USD' ? '\$$t' : (currency.isEmpty ? t : '$currency $t');
}

String _niceDateTime(DateTime? date) {
  if (date == null) return 'date unavailable';
  final d = date.toLocal();
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
    'Dec'
  ];
  final hour = d.hour > 12 ? d.hour - 12 : (d.hour == 0 ? 12 : d.hour);
  final min = d.minute.toString().padLeft(2, '0');
  final amPm = d.hour >= 12 ? 'PM' : 'AM';
  return '${months[d.month - 1]} ${d.day}, ${d.year}, $hour:$min $amPm';
}

String _timelineDate(DateTime d) => _niceDateTime(d);

String? _extractTracking(Map<String, dynamic> raw) {
  final tracking =
      raw['tracking_number'] ?? raw['tracking_id'] ?? raw['tracking'];
  final v = tracking?.toString().trim() ?? '';
  if (v.isEmpty) return null;
  return v;
}

String _paymentMethodLabel(OrderDto order) {
  final raw = (order.raw['payment_method'] ??
          order.raw['payment_channel'] ??
          order.raw['payment_provider'] ??
          '')
      .toString();
  if (raw.trim().isEmpty) return 'Not provided';
  return _humanize(raw);
}

String _paymentRouteForOrder(OrderDto order) {
  final raw =
      (order.raw['payment_method'] ?? order.raw['payment_channel'] ?? '')
          .toString()
          .toLowerCase();
  if (raw.contains('bkash')) return '/checkout/payment/bkash';
  if (raw.contains('nagad')) return '/checkout/payment/nagad';
  if (raw.contains('card') || raw.contains('visa') || raw.contains('master'))
    return '/checkout/payment/card';
  return '/checkout/payment';
}

int? _extractDisputeId(Map<String, dynamic> raw) {
  return (raw['dispute_id'] as num?)?.toInt() ??
      (raw['latest_dispute_id'] as num?)?.toInt();
}

DateTime? _parseAnyDate(dynamic raw) {
  if (raw is String && raw.trim().isNotEmpty) return DateTime.tryParse(raw);
  return null;
}

String _moneyFromRaw(dynamic value, OrderDto order) {
  final n = num.tryParse(value.toString()) ?? 0;
  final currency = (order.raw['currency'] ?? '').toString().toUpperCase();
  final t = n.toStringAsFixed(2);
  return currency == 'USD' ? '\$$t' : (currency.isEmpty ? t : '$currency $t');
}

BoxDecoration _cardDeco(BuildContext context) {
  final cs = Theme.of(context).colorScheme;
  return BoxDecoration(
    color: cs.surface,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
  );
}

class _OrderDetailError extends StatelessWidget {
  const _OrderDetailError({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.error_outline, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

class _OrderDetailSkeleton extends StatelessWidget {
  const _OrderDetailSkeleton();

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 48, 16, 24),
      children: <Widget>[
        _SkeletonBlock(height: 90, color: cs),
        const SizedBox(height: 16),
        _SkeletonBlock(height: 180, color: cs),
        const SizedBox(height: 16),
        _SkeletonBlock(height: 120, color: cs),
      ],
    );
  }
}

class _SkeletonBlock extends StatelessWidget {
  const _SkeletonBlock({
    required this.height,
    required this.color,
  });

  final double height;
  final ColorScheme color;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      decoration: BoxDecoration(
        color: color.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(16),
      ),
    );
  }
}
