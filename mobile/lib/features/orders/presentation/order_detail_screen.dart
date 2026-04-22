import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/order_detail_provider.dart';
import '../data/order_repository.dart';

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
        loading: () => const Center(child: CircularProgressIndicator()),
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
    final timeline = order.timeline;
    final items = order.items;
    final theme = Theme.of(context);
    final cs = theme.colorScheme;

    return CustomScrollView(
      slivers: <Widget>[
        SliverAppBar(
          pinned: true,
          expandedHeight: 120,
          flexibleSpace: FlexibleSpaceBar(
            titlePadding: const EdgeInsetsDirectional.only(start: 56, bottom: 14),
            title: Text(
              order.orderNumber,
              style: theme.textTheme.titleLarge?.copyWith(
                color: cs.onSurface,
                fontWeight: FontWeight.w700,
              ),
            ),
            background: Container(
              alignment: Alignment.bottomLeft,
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 48),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: <Color>[
                    cs.primaryContainer.withValues(alpha: 0.35),
                    cs.surface,
                  ],
                ),
              ),
            ),
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
          sliver: SliverList.list(
            children: <Widget>[
              _SectionHeader(
                icon: Icons.receipt_long_outlined,
                title: 'Status & total',
                subtitle: 'Order reference, state, and amount',
              ),
              const SizedBox(height: 10),
              _OrderStatusHero(order: order),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.summarize_outlined,
                title: 'Order summary',
                subtitle: 'Identifiers and key dates',
              ),
              const SizedBox(height: 10),
              _OrderSummaryCard(order: order),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.payments_outlined,
                title: 'Payment & escrow',
                subtitle: 'Funding and hold state',
              ),
              const SizedBox(height: 10),
              _PaymentEscrowCard(order: order),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.local_shipping_outlined,
                title: 'Fulfillment & shipping',
                subtitle: 'Delivery progress and tracking',
              ),
              const SizedBox(height: 10),
              _ShippingFulfillmentCard(order: order),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.storefront_outlined,
                title: 'Seller / store',
                subtitle: 'Who fulfilled this order',
              ),
              const SizedBox(height: 10),
              _SellerCard(order: order),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.inventory_2_outlined,
                title: 'Line items',
                subtitle: 'Products, quantities, and line totals',
              ),
              const SizedBox(height: 10),
              if (items.isEmpty)
                const _ItemsFallback()
              else
                ...items.map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _OrderLineItemCard(order: order, item: item),
                  ),
                ),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.account_balance_wallet_outlined,
                title: 'Amount breakdown',
                subtitle: 'Gross, discounts, fees, and net',
              ),
              const SizedBox(height: 10),
              _TotalsBreakdownCard(order: order),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.history,
                title: 'Order timeline',
                subtitle: 'Status and lifecycle events',
              ),
              const SizedBox(height: 10),
              if (timeline.isEmpty)
                const _TimelineFallback()
              else
                _TimelineSection(events: timeline),
            ],
          ),
        ),
      ],
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(icon, size: 22, color: theme.colorScheme.primary),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _OrderStatusHero extends StatelessWidget {
  const _OrderStatusHero({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final tier = _OrderTimelineTier.fromStatus(order.status);
    final created = _formatDetailDateTime(
      (order.raw['created_at'] ?? order.raw['createdAt'] ?? '').toString(),
    );
    final placed = _formatDetailDateTime(
      (order.raw['placed_at'] ?? order.raw['placedAt'] ?? '').toString().trim(),
    );
    final payment = _displayState(order.paymentStatus);
    final escrow = _displayState(order.escrowStatus);

    return Card(
      elevation: 0,
      color: cs.surfaceContainerHighest.withValues(alpha: 0.45),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.6)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              'Order #${order.id ?? 'unknown'}',
              style: theme.textTheme.labelLarge?.copyWith(
                color: cs.onSurfaceVariant,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              order.orderNumber,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 14),
            _StatusChip(
              label: _humanizeStatus(order.status),
              color: tier.accentColor(context),
              icon: tier.icon,
            ),
            const SizedBox(height: 20),
            Text(
              'Order total',
              style: theme.textTheme.labelLarge?.copyWith(
                color: cs.onSurfaceVariant,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              order.totalLabel,
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w900,
                letterSpacing: -0.5,
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                _MiniMetaChip(
                  icon: Icons.credit_card,
                  label: 'Payment',
                  value: payment,
                ),
                _MiniMetaChip(
                  icon: Icons.lock_outline,
                  label: 'Escrow',
                  value: escrow,
                ),
              ],
            ),
            const SizedBox(height: 16),
            Divider(height: 1, color: cs.outlineVariant.withValues(alpha: 0.5)),
            const SizedBox(height: 12),
            _HeroMetaRow(icon: Icons.event_outlined, label: 'Created', value: created),
            if (placed.isNotEmpty && placed != 'Date unavailable' && placed != created) ...<Widget>[
              const SizedBox(height: 8),
              _HeroMetaRow(icon: Icons.shopping_cart_outlined, label: 'Placed', value: placed),
            ],
          ],
        ),
      ),
    );
  }
}

String _displayState(String raw) {
  final s = raw.trim().toLowerCase();
  if (s.isEmpty || s == 'unavailable') {
    return 'Not provided';
  }
  return _humanizeStatus(raw);
}

class _MiniMetaChip extends StatelessWidget {
  const _MiniMetaChip({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: cs.surface.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.45)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 18, color: cs.primary),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                label,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: cs.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                value,
                style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w800),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({
    required this.label,
    required this.color,
    required this.icon,
  });

  final String label;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.45)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 20, color: color),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              label,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: color,
                  ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroMetaRow extends StatelessWidget {
  const _HeroMetaRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(icon, size: 20, color: cs.onSurfaceVariant),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                label,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: cs.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                value,
                style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _OrderSummaryCard extends StatelessWidget {
  const _OrderSummaryCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final completed = _formatDetailDateTime(
      (order.raw['completed_at'] ?? order.raw['completedAt'] ?? '').toString().trim(),
    );
    final updated = _formatDetailDateTime(
      (order.raw['updated_at'] ?? order.raw['updatedAt'] ?? '').toString().trim(),
    );

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            _MetaRow(label: 'Items', value: order.itemSummary),
            const SizedBox(height: 10),
            _MetaRow(label: 'Currency', value: _currencyOrDash(order.raw['currency'])),
            if ((order.raw['uuid'] ?? '').toString().isNotEmpty) ...<Widget>[
              const SizedBox(height: 10),
              _MetaRow(label: 'UUID', value: order.raw['uuid'].toString()),
            ],
            if (completed.isNotEmpty && completed != 'Date unavailable') ...<Widget>[
              const SizedBox(height: 10),
              _MetaRow(label: 'Completed', value: completed),
            ],
            if (updated.isNotEmpty &&
                updated != 'Date unavailable' &&
                updated != _formatDetailDateTime((order.raw['created_at'] ?? '').toString())) ...<Widget>[
              const SizedBox(height: 10),
              _MetaRow(label: 'Last updated', value: updated),
            ],
          ],
        ),
      ),
    );
  }
}

String _currencyOrDash(Object? c) {
  final s = (c ?? '').toString().trim().toUpperCase();
  return s.isEmpty ? '—' : s;
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          width: 104,
          child: Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    );
  }
}

class _PaymentEscrowCard extends StatelessWidget {
  const _PaymentEscrowCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final extras = _paymentEscrowExtras(order.raw);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            _MetaRow(label: 'Payment state', value: _displayState(order.paymentStatus)),
            const SizedBox(height: 12),
            _MetaRow(label: 'Escrow state', value: _displayState(order.escrowStatus)),
            ...extras.map(
              (line) => Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text(
                  line,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: cs.onSurfaceVariant,
                    height: 1.35,
                  ),
                ),
              ),
            ),
            if (extras.isEmpty &&
                order.paymentStatus.trim().toLowerCase() == 'unavailable' &&
                order.escrowStatus.trim().toLowerCase() == 'unavailable')
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: _InlineFallback(
                  message:
                      'Detailed payment and escrow fields were not included in this response. They may appear after checkout or capture events.',
                ),
              ),
          ],
        ),
      ),
    );
  }
}

List<String> _paymentEscrowExtras(Map<String, dynamic> raw) {
  final lines = <String>[];
  void add(String label, Object? key) {
    final v = raw[key];
    if (v == null) {
      return;
    }
    final s = v.toString().trim();
    if (s.isEmpty) {
      return;
    }
    lines.add('$label: $s');
  }

  add('Escrow account', 'escrow_account_id');
  add('Payment intent', 'payment_intent_id');
  add('Capture state', 'capture_state');
  add('Risk review', 'risk_review_state');
  return lines;
}

class _ShippingFulfillmentCard extends StatelessWidget {
  const _ShippingFulfillmentCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final lines = _shippingLines(order.raw);

    if (lines.isEmpty) {
      return Card(
        elevation: 0,
        color: cs.surfaceContainerHighest.withValues(alpha: 0.35),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(Icons.info_outline, color: cs.outline),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'No shipping or tracking details were returned for this order. Fulfillment metadata may appear once the seller ships.',
                  style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: lines
              .map(
                (e) => Padding(
                  padding: EdgeInsets.only(bottom: e == lines.last ? 0 : 10),
                  child: _MetaRow(label: e.key, value: e.value),
                ),
              )
              .toList(),
        ),
      ),
    );
  }
}

class _MapEntry {
  const _MapEntry(this.key, this.value);
  final String key;
  final String value;
}

List<_MapEntry> _shippingLines(Map<String, dynamic> raw) {
  final out = <_MapEntry>[];
  void row(
    String label,
    Object? k, {
    bool humanize = false,
    bool formatAsDateTime = false,
  }) {
    final v = raw[k];
    if (v == null) {
      return;
    }
    final s = v.toString().trim();
    if (s.isEmpty) {
      return;
    }
    String display = s;
    if (formatAsDateTime && DateTime.tryParse(s) != null) {
      display = _formatDetailDateTime(s);
    } else if (humanize) {
      display = _humanizeStatus(s);
    }
    out.add(_MapEntry(label, display));
  }

  row('Fulfillment status', 'fulfillment_status', humanize: true);
  row('Fulfillment state', 'fulfillment_state', humanize: true);
  row('Shipping status', 'shipping_status', humanize: true);
  row('Shipping state', 'shipping_state', humanize: true);
  row('Carrier', 'carrier');
  row('Service', 'shipping_service');
  row('Tracking number', 'tracking_number');
  row('Tracking URL', 'tracking_url');
  row('Shipped at', 'shipped_at', formatAsDateTime: true);
  row('Delivered at', 'delivered_at', formatAsDateTime: true);
  return out;
}

class _SellerCard extends StatelessWidget {
  const _SellerCard({required this.order});

  final OrderDto order;

  static const String _unavailable = 'Seller unavailable';

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final ok = order.sellerLabel != _unavailable;
    final extras = _sellerExtras(order.raw);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                CircleAvatar(
                  backgroundColor: cs.secondaryContainer,
                  child: Icon(Icons.store_mall_directory_outlined, color: cs.onSecondaryContainer),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ok
                      ? Text(
                          order.sellerLabel,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        )
                      : Text(
                          'Seller details not included',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: cs.onSurfaceVariant,
                          ),
                        ),
                ),
              ],
            ),
            if (ok) ...extras.map((s) => Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    s,
                    style: theme.textTheme.bodySmall?.copyWith(color: cs.onSurfaceVariant, height: 1.35),
                  ),
                )),
            if (!ok)
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: _InlineFallback(
                  message:
                      'The API response did not include a seller or store label for this order. List views may still show a summary when available.',
                ),
              ),
          ],
        ),
      ),
    );
  }
}

List<String> _sellerExtras(Map<String, dynamic> raw) {
  final lines = <String>[];
  void add(String label, Object? key) {
    final v = raw[key];
    if (v == null) {
      return;
    }
    final s = v.toString().trim();
    if (s.isEmpty) {
      return;
    }
    lines.add('$label: $s');
  }

  add('Seller profile ID', 'seller_profile_id');
  add('Store slug', 'store_slug');
  add('Seller ID', 'seller_user_id');
  return lines;
}

class _OrderLineItemCard extends StatelessWidget {
  const _OrderLineItemCard({
    required this.order,
    required this.item,
  });

  final OrderDto order;
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final title =
        (item['title'] ?? item['name'] ?? item['product_name'] ?? item['sku'] ?? 'Item').toString();
    final qty = (item['quantity'] ?? item['qty'] ?? '1').toString();
    final unit = _formatMoneyLine(order, item['unit_price'] ?? item['price'] ?? item['unit_amount']);
    final subtotal = _formatMoneyLine(order, _lineSubtotalRaw(item));
    final imageUrl = _itemImageUrl(item);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.55)),
      ),
      clipBehavior: Clip.antiAlias,
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            SizedBox(
              width: 96,
              child: imageUrl != null
                  ? Image.network(
                      imageUrl,
                      fit: BoxFit.cover,
                      loadingBuilder: (_, child, progress) {
                        if (progress == null) {
                          return child;
                        }
                        return ColoredBox(
                          color: cs.surfaceContainerHighest,
                          child: const Center(
                            child: SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                          ),
                        );
                      },
                      errorBuilder: (_, __, ___) => _ItemImagePlaceholder(cs: cs),
                    )
                  : _ItemImagePlaceholder(cs: cs),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: <Widget>[
                        _ItemStat(label: 'Qty', value: qty),
                        const SizedBox(width: 16),
                        Expanded(child: _ItemStat(label: 'Unit', value: unit)),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Subtotal · $subtotal',
                      style: theme.textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: cs.primary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ItemStat extends StatelessWidget {
  const _ItemStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          label,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: cs.onSurfaceVariant,
                fontWeight: FontWeight.w600,
              ),
        ),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
      ],
    );
  }
}

class _ItemImagePlaceholder extends StatelessWidget {
  const _ItemImagePlaceholder({required this.cs});

  final ColorScheme cs;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: cs.surfaceContainerHighest.withValues(alpha: 0.6),
      child: Center(
        child: Icon(Icons.image_not_supported_outlined, color: cs.outline, size: 32),
      ),
    );
  }
}

String? _itemImageUrl(Map<String, dynamic> item) {
  const keys = <String>[
    'image_url',
    'imageUrl',
    'thumbnail_url',
    'thumb_url',
    'product_image_url',
    'cover_image_url',
    'image',
  ];
  for (final k in keys) {
    final v = item[k];
    if (v is String && v.trim().startsWith('http')) {
      return v.trim();
    }
  }
  return null;
}

Object? _lineSubtotalRaw(Map<String, dynamic> item) {
  final direct = item['line_total'] ?? item['subtotal'] ?? item['total_line'] ?? item['line_amount'];
  if (direct != null && direct.toString().trim().isNotEmpty) {
    return direct;
  }
  final q = num.tryParse('${item['quantity'] ?? item['qty'] ?? 1}') ?? 1;
  final p = num.tryParse('${item['unit_price'] ?? item['price'] ?? item['unit_amount'] ?? ''}');
  if (p == null) {
    return null;
  }
  return q * p;
}

String _formatMoneyLine(OrderDto order, Object? value) {
  if (value == null) {
    return '—';
  }
  final s = value.toString().trim();
  if (s.isEmpty) {
    return '—';
  }
  final c = (order.raw['currency'] ?? '').toString().toUpperCase();
  return c.isEmpty ? s : '$c $s';
}

class _TotalsBreakdownCard extends StatelessWidget {
  const _TotalsBreakdownCard({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final currency = (order.raw['currency'] ?? '').toString().toUpperCase();
    final gross = order.raw['gross_amount'];
    final discount = order.raw['discount_amount'];
    final fee = order.raw['fee_amount'] ?? order.raw['platform_fee'];
    final net = order.raw['net_amount'];
    final hasAny = gross != null || discount != null || fee != null || net != null;

    if (!hasAny) {
      return Card(
        elevation: 0,
        color: cs.surfaceContainerHighest.withValues(alpha: 0.35),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: <Widget>[
              Icon(Icons.info_outline, color: cs.outline),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'No line-level amount breakdown was returned beyond the order total above.',
                  style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
                ),
              ),
            ],
          ),
        ),
      );
    }

    String fmt(Object? v) {
      if (v == null) {
        return '—';
      }
      final s = v.toString().trim();
      if (s.isEmpty) {
        return '—';
      }
      return currency.isEmpty ? s : '$currency $s';
    }

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        child: Column(
          children: <Widget>[
            _BreakdownRow(label: 'Gross', value: fmt(gross), emphasize: false),
            Divider(height: 20, color: cs.outlineVariant.withValues(alpha: 0.45)),
            _BreakdownRow(label: 'Discount', value: fmt(discount), emphasize: false),
            Divider(height: 20, color: cs.outlineVariant.withValues(alpha: 0.45)),
            _BreakdownRow(label: 'Fees', value: fmt(fee), emphasize: false),
            Divider(height: 20, color: cs.outlineVariant.withValues(alpha: 0.45)),
            _BreakdownRow(label: 'Net', value: fmt(net), emphasize: true),
          ],
        ),
      ),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  const _BreakdownRow({
    required this.label,
    required this.value,
    required this.emphasize,
  });

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Expanded(
          flex: 2,
          child: Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        Expanded(
          flex: 3,
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: emphasize
                ? theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)
                : theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    );
  }
}

class _ItemsFallback extends StatelessWidget {
  const _ItemsFallback();

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.35),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(Icons.info_outline, color: Theme.of(context).colorScheme.outline),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'No line items were included in this order response. The total above still reflects the order; item rows may appear when the API expands the payload.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InlineFallback extends StatelessWidget {
  const _InlineFallback({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(Icons.info_outline, size: 20, color: Theme.of(context).colorScheme.outline),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            message,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(height: 1.35),
          ),
        ),
      ],
    );
  }
}

class _TimelineFallback extends StatelessWidget {
  const _TimelineFallback();

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.35),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: <Widget>[
            Icon(Icons.info_outline, color: Theme.of(context).colorScheme.outline),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'No detailed timeline was returned. Use the status hero and payment sections for the latest known state.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TimelineSection extends StatelessWidget {
  const _TimelineSection({required this.events});

  final List<Map<String, dynamic>> events;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(
          color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(8, 12, 12, 12),
        child: Column(
          children: List<Widget>.generate(events.length, (index) {
            final event = events[index];
            final isLast = index == events.length - 1;
            return _TimelineRow(
              event: event,
              showConnectorBelow: !isLast,
            );
          }),
        ),
      ),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({
    required this.event,
    required this.showConnectorBelow,
  });

  final Map<String, dynamic> event;
  final bool showConnectorBelow;

  @override
  Widget build(BuildContext context) {
    final status = (event['status'] ?? event['state'] ?? 'update').toString();
    final tier = _OrderTimelineTier.fromStatus(status);
    final atRaw = (event['created_at'] ?? event['at'] ?? event['timestamp'] ?? '').toString();
    final note = (event['note'] ?? event['reason'] ?? event['message'] ?? '').toString();
    final formatted = _formatTimelineDate(atRaw);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 40,
            child: Column(
              children: <Widget>[
                Container(
                  width: 14,
                  height: 14,
                  decoration: BoxDecoration(
                    color: tier.accentColor(context),
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: Theme.of(context).colorScheme.surface,
                      width: 2,
                    ),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: tier.accentColor(context).withValues(alpha: 0.35),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                ),
                if (showConnectorBelow)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.only(top: 2),
                      color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.55),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Icon(tier.icon, size: 18, color: tier.accentColor(context)),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          _humanizeStatus(status),
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    formatted,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                  if (note.isNotEmpty) ...<Widget>[
                    const SizedBox(height: 8),
                    Text(
                      note,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

String _humanizeStatus(String raw) {
  final s = raw.replaceAll('_', ' ').trim();
  if (s.isEmpty) {
    return 'Update';
  }
  return s.split(' ').map((w) {
    if (w.isEmpty) {
      return w;
    }
    return '${w[0].toUpperCase()}${w.length > 1 ? w.substring(1).toLowerCase() : ''}';
  }).join(' ');
}

String _formatTimelineDate(String raw) {
  if (raw.isEmpty) {
    return 'Time not recorded';
  }
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) {
    return raw;
  }
  final local = parsed.toLocal();
  final y = local.year.toString().padLeft(4, '0');
  final m = local.month.toString().padLeft(2, '0');
  final d = local.day.toString().padLeft(2, '0');
  final h = local.hour.toString().padLeft(2, '0');
  final min = local.minute.toString().padLeft(2, '0');
  return '$y-$m-$d · $h:$min';
}

String _formatDetailDateTime(String raw) {
  if (raw.isEmpty) {
    return 'Date unavailable';
  }
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) {
    return raw;
  }
  final local = parsed.toLocal();
  final y = local.year.toString().padLeft(4, '0');
  final m = local.month.toString().padLeft(2, '0');
  final d = local.day.toString().padLeft(2, '0');
  final h = local.hour.toString().padLeft(2, '0');
  final min = local.minute.toString().padLeft(2, '0');
  return '$y-$m-$d at $h:$min';
}

enum _OrderTimelineTier {
  created,
  pendingPayment,
  escrow,
  disputed,
  refunded,
  completed,
  processing,
  shipped,
  cancelled,
  other;

  static _OrderTimelineTier fromStatus(String raw) {
    final s = raw.toLowerCase().trim();
    switch (s) {
      case 'draft':
      case 'created':
        return _OrderTimelineTier.created;
      case 'pending_payment':
        return _OrderTimelineTier.pendingPayment;
      case 'paid':
      case 'paid_in_escrow':
        return _OrderTimelineTier.escrow;
      case 'disputed':
        return _OrderTimelineTier.disputed;
      case 'refunded':
        return _OrderTimelineTier.refunded;
      case 'completed':
        return _OrderTimelineTier.completed;
      case 'processing':
        return _OrderTimelineTier.processing;
      case 'shipped_or_delivered':
        return _OrderTimelineTier.shipped;
      case 'cancelled':
        return _OrderTimelineTier.cancelled;
      default:
        break;
    }
    if (s.contains('refund')) {
      return _OrderTimelineTier.refunded;
    }
    if (s.contains('disput')) {
      return _OrderTimelineTier.disputed;
    }
    if (s.contains('pending') && s.contains('payment')) {
      return _OrderTimelineTier.pendingPayment;
    }
    if (s.contains('cancel')) {
      return _OrderTimelineTier.cancelled;
    }
    if (s.contains('ship') || s.contains('deliver')) {
      return _OrderTimelineTier.shipped;
    }
    if (s.contains('process')) {
      return _OrderTimelineTier.processing;
    }
    if (s.contains('escrow') || (s.contains('paid') && !s.contains('pending'))) {
      return _OrderTimelineTier.escrow;
    }
    if (s.contains('complete')) {
      return _OrderTimelineTier.completed;
    }
    if (s.contains('draft') || s.contains('create')) {
      return _OrderTimelineTier.created;
    }
    return _OrderTimelineTier.other;
  }

  Color accentColor(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    switch (this) {
      case _OrderTimelineTier.created:
        return cs.outline;
      case _OrderTimelineTier.pendingPayment:
        return cs.secondary;
      case _OrderTimelineTier.escrow:
        return cs.primary;
      case _OrderTimelineTier.disputed:
        return cs.error;
      case _OrderTimelineTier.refunded:
        return cs.tertiary;
      case _OrderTimelineTier.completed:
        return cs.tertiary;
      case _OrderTimelineTier.processing:
        return cs.primary;
      case _OrderTimelineTier.shipped:
        return cs.secondary;
      case _OrderTimelineTier.cancelled:
        return cs.outline;
      case _OrderTimelineTier.other:
        return cs.outline;
    }
  }

  IconData get icon {
    switch (this) {
      case _OrderTimelineTier.created:
        return Icons.edit_note_outlined;
      case _OrderTimelineTier.pendingPayment:
        return Icons.schedule;
      case _OrderTimelineTier.escrow:
        return Icons.account_balance_wallet_outlined;
      case _OrderTimelineTier.disputed:
        return Icons.gavel;
      case _OrderTimelineTier.refunded:
        return Icons.replay;
      case _OrderTimelineTier.completed:
        return Icons.check_circle_outline;
      case _OrderTimelineTier.processing:
        return Icons.autorenew;
      case _OrderTimelineTier.shipped:
        return Icons.local_shipping_outlined;
      case _OrderTimelineTier.cancelled:
        return Icons.cancel_outlined;
      case _OrderTimelineTier.other:
        return Icons.circle_outlined;
    }
  }
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
