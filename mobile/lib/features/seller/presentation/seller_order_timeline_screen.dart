import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_page_header.dart';
import 'seller_ui.dart';

class SellerOrderTimelineScreen extends ConsumerWidget {
  const SellerOrderTimelineScreen({super.key, required this.orderId});
  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final order = ref.read(sellerOrdersProvider.notifier).byId(orderId);
    if (order == null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8F9FE),
        appBar: SellerPanelAppBar(
          title: 'Order Timeline',
          leading: SellerHeaderActionButton(
            icon: Icons.arrow_back_ios_new_rounded,
            tooltip: 'Back',
            onTap: () => context.canPop()
                ? context.pop()
                : context.go('/seller/orders/$orderId'),
          ),
        ),
        body: const Center(child: Text('Order not found')),
      );
    }
    final steps = order.usesProofDelivery
        ? <({String title, String subtitle, bool done})>[
            (
              title: 'Order Placed',
              subtitle: 'The buyer placed the order.',
              done: true
            ),
            (
              title: 'Paid In Escrow',
              subtitle: 'Payment is held until delivery is confirmed.',
              done: true
            ),
            (
              title: 'Seller Preparing Delivery',
              subtitle:
                  'Files, credentials, notes, and screenshots go through escrow chat.',
              done: order.status != SellerOrderStatus.toShip
            ),
            (
              title: 'Proof Submitted',
              subtitle: 'Digital delivery proof has been sent to the buyer.',
              done: order.status == SellerOrderStatus.deliverySubmitted ||
                  order.status == SellerOrderStatus.buyerReview ||
                  order.status == SellerOrderStatus.delivered
            ),
            (
              title: 'Buyer Review Timer',
              subtitle:
                  'Buyer can confirm delivery or open a dispute before timeout.',
              done: order.status == SellerOrderStatus.buyerReview ||
                  order.status == SellerOrderStatus.delivered
            ),
            (
              title: 'Escrow Released',
              subtitle:
                  'Escrow releases only after buyer confirmation or valid timeout policy.',
              done: order.status == SellerOrderStatus.delivered
            ),
          ]
        : <({String title, String subtitle, bool done})>[
            (
              title: 'Order Placed',
              subtitle: 'The buyer placed the order.',
              done: true
            ),
            (
              title: 'Paid',
              subtitle: 'Payment received and held in escrow.',
              done: true
            ),
            (
              title: 'Processing',
              subtitle: 'You have started processing the order.',
              done: order.status != SellerOrderStatus.toShip
            ),
            (
              title: 'Shipped',
              subtitle:
                  'Order shipped via ${order.courierCompany ?? '-'}\nTracking ID: ${order.trackingId ?? '-'}',
              done: order.status == SellerOrderStatus.shipped ||
                  order.status == SellerOrderStatus.delivered
            ),
            (
              title: 'Escrow Release',
              subtitle: 'Buyer confirms delivery and escrow is released.',
              done: order.status == SellerOrderStatus.delivered
            ),
          ];
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: SellerPanelAppBar(
        title: 'Order Timeline',
        leading: SellerHeaderActionButton(
          icon: Icons.arrow_back_ios_new_rounded,
          tooltip: 'Back',
          onTap: () => context.canPop()
              ? context.pop()
              : context.go('/seller/orders/$orderId'),
        ),
        extraActions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.receipt_long_outlined,
              tooltip: 'Order details',
              onTap: () => context.go('/seller/orders/$orderId'),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: <Widget>[
          _TimelineHero(order: order),
          const SizedBox(height: 12),
          _TimelineCard(steps: steps),
          const SizedBox(height: 12),
          _TimelineSupportCard(
            onSupport: () => context.push('/seller/help-support'),
            onDetails: () => context.go('/seller/orders/$orderId'),
          ),
        ],
      ),
    );
  }
}

class _TimelineHero extends StatelessWidget {
  const _TimelineHero({required this.order});

  final SellerOrder order;

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
              _TimelineStatusPill(label: order.status.label),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            order.usesProofDelivery
                ? 'Digital delivery progress is tracked through escrow review.'
                : 'Shipping progress is tracked from processing to escrow release.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.white.withValues(alpha: 0.82),
                  fontWeight: FontWeight.w700,
                  height: 1.35,
                ),
          ),
        ],
      ),
    );
  }
}

class _TimelineStatusPill extends StatelessWidget {
  const _TimelineStatusPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.26)),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w900,
            ),
      ),
    );
  }
}

class _TimelineCard extends StatelessWidget {
  const _TimelineCard({required this.steps});

  final List<({String title, String subtitle, bool done})> steps;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 6),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Order lifecycle',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: kSellerNavy,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 14),
          ...List<Widget>.generate(steps.length, (index) {
            final step = steps[index];
            return _TimelineStepRow(
              title: step.title,
              subtitle: step.subtitle,
              done: step.done,
              isLast: index == steps.length - 1,
            );
          }),
        ],
      ),
    );
  }
}

class _TimelineStepRow extends StatelessWidget {
  const _TimelineStepRow({
    required this.title,
    required this.subtitle,
    required this.done,
    required this.isLast,
  });

  final String title;
  final String subtitle;
  final bool done;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final color = done ? const Color(0xFF16A34A) : const Color(0xFF94A3B8);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          width: 34,
          child: Column(
            children: <Widget>[
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: done ? color : Colors.white,
                  shape: BoxShape.circle,
                  border: Border.all(color: color, width: 2),
                ),
                child: Icon(
                  done ? Icons.check_rounded : Icons.circle_outlined,
                  size: done ? 18 : 12,
                  color: done ? Colors.white : color,
                ),
              ),
              if (!isLast)
                Container(
                  width: 2,
                  height: 58,
                  color:
                      done ? const Color(0xFFBBF7D0) : const Color(0xFFE2E8F0),
                ),
            ],
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: isLast ? 12 : 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: const Color(0xFF111827),
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 5),
                Text(
                  subtitle,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: kSellerMuted,
                        height: 1.36,
                        fontWeight: FontWeight.w600,
                      ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _TimelineSupportCard extends StatelessWidget {
  const _TimelineSupportCard({
    required this.onSupport,
    required this.onDetails,
  });

  final VoidCallback onSupport;
  final VoidCallback onDetails;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F3FF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE9D5FF)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Need help?',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w900,
                  color: const Color(0xFF111827),
                ),
          ),
          const SizedBox(height: 5),
          Text(
            'Contact support or return to the order workspace if anything looks off.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: kSellerMuted,
                  height: 1.36,
                  fontWeight: FontWeight.w600,
                ),
          ),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onSupport,
                  icon: const Icon(Icons.support_agent_rounded),
                  label: const Text('Support'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton.tonalIcon(
                  onPressed: onDetails,
                  icon: const Icon(Icons.receipt_long_outlined),
                  label: const Text('Details'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
