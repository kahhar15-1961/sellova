import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/errors/api_exception.dart';
import '../application/order_detail_provider.dart';
import '../data/order_repository.dart';
import '../domain/order_ui_stage.dart';

const Color _kNavy = Color(0xFF0B1A60);
const Color _kMuted = Color(0xFF64748B);
const Color _kOrderBlue = Color(0xFF29459E);

class OrderDetailScreen extends ConsumerWidget {
  const OrderDetailScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(orderDetailProvider(orderId));
    final trackingAsync = ref.watch(orderTrackingProvider(orderId));
    return Scaffold(
      body: detailAsync.when(
        loading: () => const _OrderDetailSkeleton(),
        error: (error, _) => _OrderDetailError(
          message: error.toString(),
          onRetry: () => ref.refresh(orderDetailProvider(orderId)),
        ),
        data: (order) => _OrderDetailContent(
          order: order,
          trackingAsync: trackingAsync,
          orderId: orderId,
        ),
      ),
    );
  }
}

class _OrderDetailContent extends ConsumerStatefulWidget {
  const _OrderDetailContent({
    required this.order,
    required this.trackingAsync,
    required this.orderId,
  });

  final OrderDto order;
  final AsyncValue<OrderTrackingDto> trackingAsync;
  final int orderId;

  @override
  ConsumerState<_OrderDetailContent> createState() =>
      _OrderDetailContentState();
}

class _OrderDetailContentState extends ConsumerState<_OrderDetailContent> {
  Timer? _countdownTimer;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void didUpdateWidget(covariant _OrderDetailContent oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.order.timeoutState['next_event_at'] !=
        widget.order.timeoutState['next_event_at']) {
      _startCountdown();
    }
  }

  void _startCountdown() {
    _countdownTimer?.cancel();
    if ((widget.order.timeoutState['next_event_at'] ?? '').toString().isEmpty) {
      return;
    }
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        setState(() {});
      }
    });
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final trackingAsync = widget.trackingAsync;
    final cs = Theme.of(context).colorScheme;
    final stage = _orderStageFromOrder(order);
    final currentStep = _currentStep(stage);
    final created = _niceDateTime(order.createdAt);
    final tracking = trackingAsync.valueOrNull;

    return DecoratedBox(
      decoration: const BoxDecoration(color: Color(0xFFF8FAFD)),
      child: SafeArea(
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 8, 8, 12),
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
                          style:
                              Theme.of(context).textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w900,
                                    color: _kOrderBlue,
                                  ),
                        ),
                        const SizedBox(height: 2),
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
                padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    _HeroStateCard(
                        order: order, stage: stage, tracking: tracking),
                    if ((order.timeoutState['active_timer'] ?? '')
                        .toString()
                        .isNotEmpty) ...<Widget>[
                      const SizedBox(height: 12),
                      _OrderEscrowTimerCard(order: order),
                    ],
                    const SizedBox(height: 18),
                    _OrderedItemsLinkCard(order: order),
                    const SizedBox(height: 18),
                    if (stage == OrderUiStage.toPay) ...<Widget>[
                      _ToPayStateSection(order: order),
                    ] else if (stage == OrderUiStage.disputed) ...<Widget>[
                      _DisputedStateSection(order: order),
                    ] else if (stage == OrderUiStage.cancelled) ...<Widget>[
                      _CancelledStateSection(order: order),
                    ] else ...<Widget>[
                      if (tracking != null && tracking.timeline.isNotEmpty)
                        _TrackingTimeline(tracking: tracking)
                      else
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
                  17, 10, 17, 12 + MediaQuery.paddingOf(context).bottom),
              decoration: BoxDecoration(
                color: Colors.white,
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
                      child: Text(_ctaText(stage, order)),
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
                        if (_canCancelOrder(order, stage)) ...<Widget>[
                          OutlinedButton.icon(
                            onPressed: () =>
                                _confirmCancelOrder(context, ref, order),
                            icon: const Icon(Icons.cancel_outlined, size: 16),
                            label: const Text('Cancel Order'),
                            style: OutlinedButton.styleFrom(
                              minimumSize: const Size.fromHeight(42),
                              foregroundColor: const Color(0xFFEF4444),
                              backgroundColor: const Color(0xFFFFFBFB),
                              side: const BorderSide(color: Color(0xFFFCA5A5)),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(9),
                              ),
                            ),
                          ),
                          const SizedBox(height: 10),
                        ],
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
                            minimumSize: const Size.fromHeight(40),
                            backgroundColor: _kOrderBlue,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8)),
                          ),
                          child: Text(_ctaText(stage, order)),
                        ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmCancelOrder(
    BuildContext context,
    WidgetRef ref,
    OrderDto order,
  ) async {
    final id = order.id;
    if (id == null) {
      return;
    }

    final reasonController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Cancel order?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text(
              'You can cancel before the seller starts processing. If payment is already in escrow, it will be refunded to your buyer wallet.',
            ),
            const SizedBox(height: 14),
            TextField(
              controller: reasonController,
              maxLines: 3,
              textInputAction: TextInputAction.done,
              decoration: const InputDecoration(
                labelText: 'Reason (optional)',
                hintText: 'Changed my mind, wrong item, etc.',
              ),
            ),
          ],
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Keep Order'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFB42318),
            ),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Cancel Order'),
          ),
        ],
      ),
    );

    if (confirmed != true || !context.mounted) {
      reasonController.dispose();
      return;
    }

    try {
      HapticFeedback.mediumImpact();
      await ref.read(orderRepositoryProvider).cancelOrder(
            orderId: id,
            reason: reasonController.text,
            correlationId:
                'cancel-$id-${DateTime.now().millisecondsSinceEpoch}',
          );
      ref.invalidate(orderDetailProvider(id));
      ref.invalidate(orderTrackingProvider(id));
      if (!context.mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Order cancelled successfully.')),
      );
    } catch (error) {
      if (!context.mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_friendlyOrderError(error))),
      );
    } finally {
      reasonController.dispose();
    }
  }
}

class _HeroStateCard extends StatelessWidget {
  const _HeroStateCard(
      {required this.order, required this.stage, required this.tracking});
  final OrderDto order;
  final OrderUiStage stage;
  final OrderTrackingDto? tracking;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final style = _stageStyle(stage, order);
    final escrowState = order.escrowStatus.toLowerCase().trim();
    final displayStyle =
        stage == OrderUiStage.escrow && escrowState.contains('released')
            ? const _StateStyle(
                'Escrow Released',
                'Payment has been released. Seller can now continue processing the order.',
                Icons.verified_rounded,
                Color(0xFFEFF6FF),
                Color(0xFF1D4ED8),
              )
            : style;
    final trackingId = tracking?.trackingId.isNotEmpty == true
        ? tracking!.trackingId
        : _extractTracking(order.raw);
    final carrier =
        tracking?.carrierName.isNotEmpty == true ? tracking!.carrierName : null;
    final eta = tracking?.eta.isNotEmpty == true ? tracking!.eta : null;

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 15),
      decoration: BoxDecoration(
        color: displayStyle.bg,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: displayStyle.fg.withValues(alpha: 0.18)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: displayStyle.fg.withValues(alpha: 0.045),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(displayStyle.icon, color: displayStyle.fg, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  displayStyle.title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                        color: displayStyle.fg,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          Text(
            displayStyle.message,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: displayStyle.fg.withValues(alpha: 0.88),
                  fontWeight: FontWeight.w700,
                  height: 1.4,
                ),
          ),
          if (trackingId != null &&
              (stage == OrderUiStage.shipped ||
                  stage == OrderUiStage.delivered)) ...<Widget>[
            const SizedBox(height: 10),
            if (carrier != null)
              Text(
                carrier,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: displayStyle.fg, fontWeight: FontWeight.w800),
              ),
            Text(
              'Tracking ID: $trackingId',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: displayStyle.fg, fontWeight: FontWeight.w800),
            ),
            if (eta != null && eta.isNotEmpty) ...<Widget>[
              const SizedBox(height: 4),
              Text(
                'ETA: ${_niceDateTime(DateTime.tryParse(eta) ?? DateTime.now())}',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: displayStyle.fg.withValues(alpha: 0.95),
                    fontWeight: FontWeight.w700),
              ),
            ],
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

class _OrderedItemsLinkCard extends StatelessWidget {
  const _OrderedItemsLinkCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final items = order.items;
    final count = items.isNotEmpty
        ? items.length
        : ((order.raw['item_count'] as num?)?.toInt() ?? 0);

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(11),
      child: InkWell(
        borderRadius: BorderRadius.circular(11),
        onTap: () => _showOrderedItemsSheet(context, order),
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 14, 12, 14),
          decoration: _cardDeco(context),
          child: Row(
            children: <Widget>[
              Container(
                width: 39,
                height: 39,
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFEDEEF1)),
                ),
                child: const Icon(
                  Icons.shopping_bag_outlined,
                  color: Color(0xFF64748B),
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Ordered items',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w900,
                            color: const Color(0xFF1F2937),
                          ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      count <= 0
                          ? 'View purchased products'
                          : '$count item${count == 1 ? '' : 's'} purchased',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: const Color(0xFF64748B),
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: _kMuted),
            ],
          ),
        ),
      ),
    );
  }

  void _showOrderedItemsSheet(BuildContext context, OrderDto order) {
    final items = order.items;
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            0,
            16,
            16 + MediaQuery.paddingOf(sheetContext).bottom,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                'Ordered items',
                style: Theme.of(sheetContext).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: _kNavy,
                    ),
              ),
              const SizedBox(height: 4),
              Text(
                order.orderNumber.startsWith('#')
                    ? order.orderNumber
                    : '#${order.orderNumber}',
                style: Theme.of(sheetContext).textTheme.bodySmall?.copyWith(
                      color: _kMuted,
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: 14),
              if (items.isEmpty)
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: _cardDeco(sheetContext),
                  child:
                      const Text('No item detail is available for this order.'),
                )
              else
                ConstrainedBox(
                  constraints: BoxConstraints(
                    maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.62,
                  ),
                  child: ListView.separated(
                    shrinkWrap: true,
                    itemCount: items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (itemContext, index) {
                      final item = items[index];
                      final title = (item['title'] ??
                              item['name'] ??
                              item['product_name'] ??
                              'Order item')
                          .toString();
                      final sku = (item['sku'] ?? '').toString();
                      final quantity = (item['quantity'] as num?)?.toInt() ??
                          int.tryParse(
                            (item['quantity'] ?? '1').toString(),
                          ) ??
                          1;
                      final unit = _moneyFromRaw(item['unit_price'], order);
                      final total = _moneyFromRaw(item['line_total'], order);
                      return Container(
                        padding: const EdgeInsets.all(12),
                        decoration: _cardDeco(itemContext),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Container(
                              width: 52,
                              height: 52,
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(Icons.inventory_2_outlined),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  Text(
                                    title,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: Theme.of(itemContext)
                                        .textTheme
                                        .titleSmall
                                        ?.copyWith(fontWeight: FontWeight.w900),
                                  ),
                                  const SizedBox(height: 5),
                                  Text(
                                    sku.isEmpty
                                        ? 'Qty $quantity • $unit each'
                                        : 'SKU $sku • Qty $quantity • $unit each',
                                    style: Theme.of(itemContext)
                                        .textTheme
                                        .bodySmall
                                        ?.copyWith(
                                          color: _kMuted,
                                          fontWeight: FontWeight.w600,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 10),
                            Text(
                              total,
                              style: Theme.of(itemContext)
                                  .textTheme
                                  .titleSmall
                                  ?.copyWith(
                                    fontWeight: FontWeight.w900,
                                    color: _kNavy,
                                  ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OrderEscrowTimerCard extends StatelessWidget {
  const _OrderEscrowTimerCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final timer = order.timeoutState;
    final active = (timer['active_timer'] ?? '').toString();
    final nextAt =
        DateTime.tryParse((timer['next_event_at'] ?? '').toString())?.toLocal();
    final remaining = nextAt?.difference(DateTime.now());
    final action =
        (timer['expiry_action'] ?? '').toString().replaceAll('_', ' ');
    final title = switch (active) {
      'buyer_review' => 'Buyer review timer',
      'seller_fulfillment_deadline' => 'Seller delivery deadline',
      'unpaid_order_expiration' => 'Payment expiration',
      _ => 'Escrow timer',
    };
    final description = switch (active) {
      'buyer_review' => 'Confirm delivery or open a dispute before this time.',
      'seller_fulfillment_deadline' =>
        'Seller must submit delivery before this deadline.',
      'unpaid_order_expiration' =>
        'Complete payment before this order expires.',
      _ => 'Escrow automation is monitoring this order.',
    };

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 15),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFBFDBFE)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF2563EB).withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(Icons.schedule_rounded,
              color: Color(0xFF1D4ED8), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  remaining == null
                      ? title
                      : '$title: ${_formatCountdown(remaining)}',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: _kNavy,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  description,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: const Color(0xFF64748B),
                        height: 1.35,
                        fontWeight: FontWeight.w600,
                      ),
                ),
                if (action.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 6),
                  Text(
                    'Next action: $action',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: const Color(0xFF1D4ED8),
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TrackingTimeline extends StatelessWidget {
  const _TrackingTimeline({required this.tracking});

  final OrderTrackingDto tracking;

  @override
  Widget build(BuildContext context) {
    final rows = tracking.timeline;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFEDEEF1)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF111827).withValues(alpha: 0.045),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        children: rows.map((row) {
          final title = (row['title'] ?? row['code'] ?? 'Update').toString();
          final atRaw = (row['at'] ?? '').toString();
          final at = DateTime.tryParse(atRaw);
          return _TimelineRow(
            title: title,
            subtitle: at == null ? 'Pending update' : _niceDateTime(at),
            color: const Color(0xFF4F46E5),
            showLine: row != rows.last,
            state: at == null ? _PointState.pending : _PointState.done,
          );
        }).toList(),
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
        color: Colors.white,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFEDEEF1)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF111827).withValues(alpha: 0.045),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
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
              if (_rawAmount(order.raw['discount_amount']) > 0) ...<Widget>[
                const SizedBox(height: 8),
                _moneyRow(
                  context,
                  order.raw['promo_code'] == null
                      ? 'Discount'
                      : 'Promo Discount',
                  '-${_moneyFromRaw(order.raw['discount_amount'], order)}',
                  false,
                  valueColor: const Color(0xFF15803D),
                ),
              ],
              if ((order.raw['promo_code'] ?? '')
                  .toString()
                  .isNotEmpty) ...<Widget>[
                const SizedBox(height: 8),
                _moneyRow(
                  context,
                  'Promo Code',
                  order.raw['promo_code'].toString(),
                  false,
                  useMonospaceValue: true,
                ),
              ],
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
        const _MilestoneTimeline(currentStep: 2),
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
  BuildContext context,
  String label,
  String value,
  bool emphasize, {
  Color? valueColor,
  bool useMonospaceValue = false,
}) {
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
        style: (emphasize
                ? Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w900, color: valueColor ?? _kNavy)
                : Theme.of(context).textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: valueColor ?? _kNavy,
                    )) ??
            TextStyle(
              fontWeight: emphasize ? FontWeight.w900 : FontWeight.w800,
              color: valueColor ?? _kNavy,
            ),
      ),
    ],
  );
}

double _rawAmount(Object? value) {
  return double.tryParse((value ?? '0').toString()) ?? 0.0;
}

class _StateStyle {
  const _StateStyle(this.title, this.message, this.icon, this.bg, this.fg);
  final String title;
  final String message;
  final IconData icon;
  final Color bg;
  final Color fg;
}

_StateStyle _stageStyle(OrderUiStage stage, [OrderDto? order]) {
  final proofDelivery = order?.usesProofDelivery ?? false;
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
    OrderUiStage.processing => proofDelivery
        ? const _StateStyle(
            'Seller Preparing Delivery',
            'The seller is preparing proof-based delivery in escrow chat.',
            Icons.mark_chat_unread_outlined,
            Color(0xFFEFF6FF),
            Color(0xFF1D4ED8),
          )
        : const _StateStyle(
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
    OrderUiStage.delivered => proofDelivery
        ? const _StateStyle(
            'Delivery Submitted',
            'Review the seller proof in escrow chat, then confirm if it works.',
            Icons.fact_check_outlined,
            Color(0xFFECFDF5),
            Color(0xFF15803D),
          )
        : const _StateStyle(
            'Delivered',
            'Please confirm delivery to release escrow.',
            Icons.inventory_rounded,
            Color(0xFFECFDF5),
            Color(0xFF15803D),
          ),
    OrderUiStage.completed => proofDelivery
        ? const _StateStyle(
            'Completed',
            'You confirmed delivery and escrow was released.',
            Icons.task_alt_rounded,
            Color(0xFFDCFCE7),
            Color(0xFF15803D),
          )
        : const _StateStyle(
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

String _ctaText(OrderUiStage stage, [OrderDto? order]) {
  final proofDelivery = order?.usesProofDelivery ?? false;
  return switch (stage) {
    OrderUiStage.toPay => 'Pay Now',
    OrderUiStage.shipped ||
    OrderUiStage.delivered =>
      proofDelivery ? 'Review Delivery' : 'Confirm Delivery',
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
  if (n == null) {
    return currency.isEmpty ? total.toString() : '$currency $total';
  }
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

String _formatCountdown(Duration duration) {
  final safe = duration.isNegative ? Duration.zero : duration;
  final days = safe.inDays;
  final hours = safe.inHours.remainder(24);
  final minutes = safe.inMinutes.remainder(60);
  final seconds = safe.inSeconds.remainder(60);
  if (days > 0) {
    return '${days}d ${hours}h ${minutes}m';
  }
  if (safe.inHours > 0) {
    return '${safe.inHours}h ${minutes}m';
  }
  if (safe.inMinutes > 0) {
    return '${safe.inMinutes}m ${seconds}s';
  }
  return '${seconds}s';
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
  final id = order.id ?? 0;
  return '/orders/$id/pay';
}

int? _extractDisputeId(Map<String, dynamic> raw) {
  return (raw['dispute_id'] as num?)?.toInt() ??
      (raw['latest_dispute_id'] as num?)?.toInt();
}

bool _canCancelOrder(OrderDto order, OrderUiStage stage) {
  if (!order.canCancel) {
    return false;
  }
  return stage == OrderUiStage.toPay || stage == OrderUiStage.escrow;
}

String _friendlyOrderError(Object error) {
  if (error is ApiException) {
    switch (error.type) {
      case ApiExceptionType.validationFailed:
      case ApiExceptionType.invalidStateTransition:
      case ApiExceptionType.conflict:
        return error.message.isNotEmpty
            ? error.message
            : 'This order can no longer be cancelled.';
      case ApiExceptionType.network:
        return 'Network issue. Check your connection and try again.';
      case ApiExceptionType.unauthenticated:
        return 'Your session expired. Please sign in again.';
      case ApiExceptionType.forbidden:
        return 'You do not have permission to cancel this order.';
      case ApiExceptionType.notFound:
        return 'Order information is unavailable right now.';
      case ApiExceptionType.internalError:
        return 'Server error. Please try again shortly.';
      case ApiExceptionType.unknown:
        return error.message.isNotEmpty
            ? error.message
            : 'Something went wrong.';
    }
  }
  return 'Something went wrong. Please try again.';
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
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(11),
    border: Border.all(color: const Color(0xFFEDEEF1)),
    boxShadow: <BoxShadow>[
      BoxShadow(
        color: const Color(0xFF111827).withValues(alpha: 0.045),
        blurRadius: 12,
        offset: const Offset(0, 5),
      ),
    ],
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
