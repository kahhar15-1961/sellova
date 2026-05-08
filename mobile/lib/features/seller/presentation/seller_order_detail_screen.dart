import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_page_header.dart';
import 'seller_ui.dart';

class SellerOrderDetailScreen extends ConsumerWidget {
  const SellerOrderDetailScreen({super.key, required this.orderId});
  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orders = ref.watch(sellerOrdersProvider);
    final order = orders.where((item) => item.id == orderId).firstOrNull;
    if (order == null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8F9FE),
        appBar: SellerPanelAppBar(
          title: 'Order Details',
          leading: SellerHeaderActionButton(
            icon: Icons.arrow_back_ios_new_rounded,
            tooltip: 'Back',
            onTap: () =>
                context.canPop() ? context.pop() : context.go('/seller/orders'),
          ),
        ),
        body: const Center(child: Text('Order not found')),
      );
    }
    final primaryAction = _primaryAction(context, ref, order);
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: SellerPanelAppBar(
        title: 'Order Details',
        leading: SellerHeaderActionButton(
          icon: Icons.arrow_back_ios_new_rounded,
          tooltip: 'Back',
          onTap: () =>
              context.canPop() ? context.pop() : context.go('/seller/orders'),
        ),
        extraActions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.chat_bubble_outline_rounded,
              tooltip: 'Customer chat',
              onTap: () => context.push('/seller/orders/$orderId/chat'),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.timeline_rounded,
              tooltip: 'Timeline',
              onTap: () => context.push('/seller/orders/$orderId/timeline'),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
        children: <Widget>[
          _OrderHero(order: order, status: _status(order)),
          const SizedBox(height: 12),
          _WorkflowCard(
            order: order,
            onCopy: () =>
                _copy(context, order.orderNumber, 'Invoice ID copied.'),
            onTimeline: () => context.push('/seller/orders/$orderId/timeline'),
          ),
          const SizedBox(height: 12),
          _CustomerCard(order: order),
          const SizedBox(height: 12),
          _ItemCard(order: order),
          const SizedBox(height: 12),
          if (order.isPhysical)
            _physicalShippingCard(context, order)
          else
            _digitalDeliveryCard(context, order),
          const SizedBox(height: 12),
          if (order.status == SellerOrderStatus.buyerReview)
            _OrderNotice(
              icon: Icons.verified_user_outlined,
              message:
                  'Delivery proof is submitted. Buyer review is open${_timerCopy(order).isEmpty ? '.' : ' - ${_timerCopy(order)}.'}',
              bg: const Color(0xFFEFF6FF),
              fg: const Color(0xFF1E40AF),
            ),
          if (order.status == SellerOrderStatus.shipped)
            const _OrderNotice(
              icon: Icons.local_shipping_outlined,
              message:
                  'Shipped and waiting for buyer confirmation. Escrow releases automatically after delivery is confirmed.',
              bg: Color(0xFFF0F9FF),
              fg: Color(0xFF0C4A6E),
            ),
          if (order.status == SellerOrderStatus.delivered)
            const _OrderNotice(
              icon: Icons.check_circle_outline_rounded,
              message: 'Escrow has been released to your available balance.',
              bg: Color(0xFFECFDF5),
              fg: Color(0xFF166534),
            ),
        ],
      ),
      bottomNavigationBar: _OrderActionBar(
        primary: primaryAction,
        onChat: () => context.push('/seller/orders/$orderId/chat'),
        onTimeline: () => context.push('/seller/orders/$orderId/timeline'),
      ),
    );
  }

  Widget _card(BuildContext context, Widget child) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: sellerCardDecoration(Theme.of(context).colorScheme),
        child: child,
      );

  Widget _physicalShippingCard(BuildContext context, SellerOrder order) =>
      _card(
          context,
          Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                const Text('Shipping Address',
                    style: TextStyle(fontWeight: FontWeight.w900)),
                const SizedBox(height: 6),
                Text(order.shippingAddress),
                if (order.trackingId != null) ...<Widget>[
                  const SizedBox(height: 10),
                  _copyRow(context, 'Tracking Number', order.trackingId!,
                      'Tracking ID copied.'),
                  if (order.courierCompany != null) ...<Widget>[
                    const SizedBox(height: 6),
                    _r('Courier', order.courierCompany!),
                  ],
                ],
                if (order.shippingNote != null &&
                    order.shippingNote!.trim().isNotEmpty) ...<Widget>[
                  const SizedBox(height: 8),
                  _r('Shipping Note', order.shippingNote!),
                ],
                if (order.deliveredOn != null)
                  _r('Delivered On', sellerNiceDate(order.deliveredOn!)),
              ]));

  Widget _digitalDeliveryCard(BuildContext context, SellerOrder order) {
    final timer = _timerCopy(order);
    return _card(
      context,
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
        Row(children: <Widget>[
          const Icon(Icons.cloud_upload_outlined, color: Color(0xFF1D4ED8)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              switch (order.productType) {
                'instant_delivery' => 'Instant Delivery',
                'service' => 'Service Proof Of Work',
                _ => 'Digital Delivery',
              },
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
        ]),
        const SizedBox(height: 8),
        const Text(
          'Use escrow chat to send files, credentials, instructions, and screenshot proof. Do not add shipping details for this order.',
          style: TextStyle(color: kSellerMuted, height: 1.35),
        ),
        if (timer.isNotEmpty) ...<Widget>[
          const SizedBox(height: 10),
          _r('Active Timer', timer),
        ],
        const SizedBox(height: 10),
        OutlinedButton.icon(
          onPressed: () => context.push('/seller/orders/${order.id}/chat'),
          icon: const Icon(Icons.chat_bubble_outline_rounded),
          label: const Text('Open delivery chat'),
        ),
      ]),
    );
  }

  Widget _r(String k, String v) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(children: <Widget>[
          Expanded(child: Text(k, style: const TextStyle(color: kSellerMuted))),
          Flexible(
            child: Text(
              v,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          )
        ]),
      );

  Widget _copyRow(
    BuildContext context,
    String label,
    String value,
    String message,
  ) {
    return Row(children: <Widget>[
      Expanded(
        child: Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
      ),
      Flexible(
        child: Text(
          value,
          textAlign: TextAlign.right,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      TextButton(
        onPressed: () => _copy(context, value, message),
        child: const Text('Copy'),
      ),
    ]);
  }

  void _copy(BuildContext context, String value, String message) {
    Clipboard.setData(ClipboardData(text: value));
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  _OrderPrimaryAction? _primaryAction(
    BuildContext context,
    WidgetRef ref,
    SellerOrder order,
  ) {
    if (order.status == SellerOrderStatus.toShip && order.isPhysical) {
      return _OrderPrimaryAction(
        label: 'Start Processing',
        icon: Icons.play_arrow_rounded,
        onTap: () => context.push('/seller/orders/$orderId/update-status'),
      );
    }
    if (order.status == SellerOrderStatus.processing && order.isPhysical) {
      return _OrderPrimaryAction(
        label: 'Add Shipping Details',
        icon: Icons.local_shipping_outlined,
        onTap: () => context.push('/seller/orders/$orderId/shipping'),
      );
    }
    if (order.usesProofDelivery &&
        (order.status == SellerOrderStatus.toShip ||
            order.status == SellerOrderStatus.processing ||
            order.status == SellerOrderStatus.deliverySubmitted)) {
      return _OrderPrimaryAction(
        label: 'Submit Digital Delivery',
        icon: Icons.upload_file_rounded,
        onTap: () => _submitDigitalDelivery(context, ref, order),
      );
    }
    return null;
  }

  Widget _status(SellerOrder order) {
    final status = order.status;
    final (bg, fg) = switch (status) {
      SellerOrderStatus.toShip => (
          const Color(0xFFFFF7ED),
          const Color(0xFFEA580C)
        ),
      SellerOrderStatus.processing => (
          const Color(0xFFEFF6FF),
          const Color(0xFF1D4ED8)
        ),
      SellerOrderStatus.deliverySubmitted => (
          const Color(0xFFEFF6FF),
          const Color(0xFF2563EB)
        ),
      SellerOrderStatus.buyerReview => (
          const Color(0xFFEFF6FF),
          const Color(0xFF1D4ED8)
        ),
      SellerOrderStatus.shipped => (
          const Color(0xFFEFF6FF),
          const Color(0xFF2563EB)
        ),
      SellerOrderStatus.delivered => (
          const Color(0xFFECFDF5),
          const Color(0xFF16A34A)
        ),
      SellerOrderStatus.cancelled => (
          const Color(0xFFF1F5F9),
          const Color(0xFF475569)
        ),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration:
          BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(_statusLabel(order),
          style: TextStyle(color: fg, fontWeight: FontWeight.w800)),
    );
  }

  String _statusLabel(SellerOrder order) {
    if (order.usesProofDelivery && order.status == SellerOrderStatus.toShip) {
      return 'Awaiting Delivery';
    }
    if (order.usesProofDelivery &&
        order.status == SellerOrderStatus.processing) {
      return 'Preparing Delivery';
    }
    return order.status.label;
  }

  Future<void> _submitDigitalDelivery(
    BuildContext context,
    WidgetRef ref,
    SellerOrder order,
  ) async {
    final noteCtrl = TextEditingController();
    final note = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Submit Digital Delivery'),
        content: TextField(
          controller: noteCtrl,
          minLines: 3,
          maxLines: 5,
          decoration: const InputDecoration(
            hintText:
                'Add delivery note, credential instructions, or proof summary',
          ),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(noteCtrl.text),
            child: const Text('Mark submitted'),
          ),
        ],
      ),
    );
    noteCtrl.dispose();
    if (note == null) return;
    try {
      await ref.read(sellerOrdersProvider.notifier).submitDigitalDelivery(
            orderId: order.id,
            note: note,
          );
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Digital delivery submitted for buyer review.')),
        );
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Delivery submit failed: $e')),
        );
      }
    }
  }

  String _timerCopy(SellerOrder order) {
    final timer = order.timeoutState;
    final active = (timer['active_timer'] ?? '').toString();
    final nextAt =
        DateTime.tryParse((timer['next_event_at'] ?? '').toString())?.toLocal();
    if (active.isEmpty || nextAt == null) return '';
    final remaining = nextAt.difference(DateTime.now());
    final label = switch (active) {
      'buyer_review' => 'buyer review expires',
      'seller_fulfillment_deadline' => 'seller deadline',
      _ => 'next escrow event',
    };
    return '$label in ${_formatDuration(remaining)}';
  }
}

class _OrderHero extends StatelessWidget {
  const _OrderHero({required this.order, required this.status});

  final SellerOrder order;
  final Widget status;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
      decoration: BoxDecoration(
        gradient: kSellerPrimaryGradient,
        borderRadius: BorderRadius.circular(18),
        boxShadow: <BoxShadow>[sellerGradientShadow(alpha: 0.16)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  order.orderNumber,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        height: 1.05,
                      ),
                ),
              ),
              const SizedBox(width: 10),
              status,
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              _HeroMetric(label: 'Total', value: order.totalLabel),
              const SizedBox(width: 10),
              _HeroMetric(
                label: order.isPhysical ? 'Fulfillment' : 'Delivery',
                value: order.isPhysical ? 'Shipping' : 'Proof',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Colors.white70,
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WorkflowCard extends StatelessWidget {
  const _WorkflowCard({
    required this.order,
    required this.onCopy,
    required this.onTimeline,
  });

  final SellerOrder order;
  final VoidCallback onCopy;
  final VoidCallback onTimeline;

  @override
  Widget build(BuildContext context) {
    final steps = order.usesProofDelivery
        ? <String>['Paid', 'Prepare', 'Proof', 'Review', 'Release']
        : <String>['Paid', 'Process', 'Ship', 'Confirm', 'Release'];
    final active = switch (order.status) {
      SellerOrderStatus.toShip => 1,
      SellerOrderStatus.processing => 1,
      SellerOrderStatus.deliverySubmitted => 2,
      SellerOrderStatus.buyerReview => 3,
      SellerOrderStatus.shipped => 2,
      SellerOrderStatus.delivered => 4,
      SellerOrderStatus.cancelled => 0,
    };

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Expanded(
                child: Text('Fulfillment progress',
                    style: TextStyle(fontWeight: FontWeight.w900)),
              ),
              TextButton(onPressed: onTimeline, child: const Text('Timeline')),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: List<Widget>.generate(steps.length, (index) {
              final done = index <= active &&
                  order.status != SellerOrderStatus.cancelled;
              return Expanded(
                child: Column(
                  children: <Widget>[
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      height: 8,
                      margin: const EdgeInsets.symmetric(horizontal: 2),
                      decoration: BoxDecoration(
                        color:
                            done ? kSellerGradientEnd : const Color(0xFFE2E8F0),
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      steps[index],
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                            color: done ? kSellerNavy : kSellerMuted,
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                  ],
                ),
              );
            }),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: onCopy,
            icon: const Icon(Icons.copy_rounded, size: 18),
            label: const Text('Copy Invoice ID'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(44),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ],
      ),
    );
  }
}

class _CustomerCard extends StatelessWidget {
  const _CustomerCard({required this.order});

  final SellerOrder order;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Column(
        children: <Widget>[
          _detailRow('Customer', order.customerName),
          _detailRow('Order Date', sellerNiceDate(order.orderDate)),
          _detailRow('Payment Method', order.paymentMethod),
          _detailRow('Total Amount', order.totalLabel, isLast: true),
        ],
      ),
    );
  }
}

class _ItemCard extends StatelessWidget {
  const _ItemCard({required this.order});

  final SellerOrder order;

  @override
  Widget build(BuildContext context) {
    final image = order.productImageUrl;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Row(
        children: <Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: Container(
              width: 56,
              height: 56,
              color: const Color(0xFFEFF6FF),
              child: image == null || image.isEmpty
                  ? const Icon(Icons.inventory_2_outlined, color: kSellerNavy)
                  : Image.network(
                      image,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => const Icon(
                        Icons.inventory_2_outlined,
                        color: kSellerNavy,
                      ),
                    ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  order.productTitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  order.isPhysical ? 'Physical item' : 'Digital delivery',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: kSellerMuted,
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Text(
            order.totalLabel,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: kSellerNavy,
                  fontWeight: FontWeight.w900,
                ),
          ),
        ],
      ),
    );
  }
}

class _OrderNotice extends StatelessWidget {
  const _OrderNotice({
    required this.icon,
    required this.message,
    required this.bg,
    required this.fg,
  });

  final IconData icon;
  final String message;
  final Color bg;
  final Color fg;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: fg.withValues(alpha: 0.22)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, color: fg, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: fg, fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }
}

class _OrderPrimaryAction {
  const _OrderPrimaryAction({
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;
}

class _OrderActionBar extends StatelessWidget {
  const _OrderActionBar({
    required this.primary,
    required this.onChat,
    required this.onTimeline,
  });

  final _OrderPrimaryAction? primary;
  final VoidCallback onChat;
  final VoidCallback onTimeline;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: Color(0xFFE2E8F0))),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: const Color(0xFF0F172A).withValues(alpha: 0.08),
              blurRadius: 18,
              offset: const Offset(0, -6),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            if (primary != null) ...<Widget>[
              FilledButton.icon(
                onPressed: primary!.onTap,
                icon: Icon(primary!.icon),
                label: Text(primary!.label),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(46),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 8),
            ],
            Row(
              children: <Widget>[
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onChat,
                    icon: const Icon(Icons.chat_bubble_outline_rounded),
                    label: const Text('Chat'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onTimeline,
                    icon: const Icon(Icons.timeline_rounded),
                    label: const Text('Timeline'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

Widget _detailRow(String label, String value, {bool isLast = false}) {
  return Padding(
    padding: EdgeInsets.only(bottom: isLast ? 0 : 10),
    child: Row(
      children: <Widget>[
        Expanded(
          child: Text(label, style: const TextStyle(color: kSellerMuted)),
        ),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
      ],
    ),
  );
}

String _formatDuration(Duration duration) {
  if (duration.isNegative) return '0m';
  final hours = duration.inHours;
  final minutes = duration.inMinutes.remainder(60);
  if (hours >= 24) return '${hours ~/ 24}d ${hours.remainder(24)}h';
  if (hours > 0) return '${hours}h ${minutes}m';
  return '${minutes}m';
}
