import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_inventory_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_product_thumbnail.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerInventoryMovementDetailScreen extends ConsumerWidget {
  const SellerInventoryMovementDetailScreen(
      {super.key, required this.movementId});
  final int movementId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final m = ref.watch(sellerInventoryProvider.notifier).byId(movementId);
    if (m == null) {
      return SellerScaffold(
        selectedNavIndex: 2,
        appBar: AppBar(
          title: const Text('Movement Details'),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded),
            onPressed: () => context.canPop()
                ? context.pop()
                : context.go('/seller/inventory/history'),
          ),
        ),
        body: const Center(child: Text('Movement not found')),
      );
    }
    final product =
        ref.watch(sellerProductsProvider.notifier).byId(m.productId);
    final isIn = m.type == SellerMovementType.stockIn;
    final isOut = m.type == SellerMovementType.stockOut;
    final title = isIn
        ? 'Stock In Details'
        : isOut
            ? 'Stock Out Details'
            : 'Adjustment Details';
    final bannerBg = isIn
        ? const Color(0xFFECFDF5)
        : isOut
            ? const Color(0xFFFFF1F2)
            : const Color(0xFFFFFBEB);
    final bannerFg = isIn
        ? const Color(0xFF15803D)
        : isOut
            ? const Color(0xFFDC2626)
            : const Color(0xFFB45309);
    return SellerScaffold(
      selectedNavIndex: 2,
      appBar: AppBar(
        title: Text(title),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.canPop()
              ? context.pop()
              : context.go('/seller/inventory/history'),
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Product',
            onPressed: () => context.go('/seller/products/${m.productId}'),
            icon: const Icon(Icons.storefront_outlined),
          ),
          IconButton(
            tooltip: 'History',
            onPressed: () => context.go('/seller/inventory/history'),
            icon: const Icon(Icons.history_rounded),
          ),
          IconButton(
            tooltip: 'Copy',
            onPressed: () {
              Clipboard.setData(
                ClipboardData(
                  text:
                      '${m.type.label} • ${m.productName} • ${m.referenceId} • ${m.newStock}',
                ),
              );
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Movement summary copied.')),
              );
            },
            icon: const Icon(Icons.share_outlined),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
                color: bannerBg, borderRadius: BorderRadius.circular(12)),
            child: Text('${m.type.label} Completed',
                textAlign: TextAlign.center,
                style: TextStyle(color: bannerFg, fontWeight: FontWeight.w800)),
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: sellerCardDecoration(Theme.of(context).colorScheme),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => context.go('/seller/products/${m.productId}'),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(children: <Widget>[
                  SellerProductThumbnail(product: product, size: 52),
                  const SizedBox(width: 10),
                  Expanded(
                      child: Text('${m.productName}\nSKU: ${m.productSku}',
                          style: const TextStyle(fontWeight: FontWeight.w700))),
                  Text('${m.quantity > 0 ? '+' : ''}${m.quantity}',
                      style: TextStyle(
                          color: bannerFg, fontWeight: FontWeight.w900)),
                ]),
              ),
            ),
          ),
          const SizedBox(height: 10),
          _row('Date & Time', sellerNiceDate(m.at)),
          _row('Reference ID', m.referenceId),
          _row('Warehouse', m.warehouse),
          _row('Previous Stock', '${m.previousStock}'),
          _row(
              m.type == SellerMovementType.stockIn
                  ? 'Quantity In'
                  : m.type == SellerMovementType.stockOut
                      ? 'Quantity Out'
                      : 'Quantity Change',
              '${m.quantity}'),
          _row('New Stock', '${m.newStock}'),
          _row(
              m.type == SellerMovementType.stockOut
                  ? 'Unit Price'
                  : 'Unit Cost',
              '৳ ${m.unitAmount.toStringAsFixed(2)}'),
          _row('Total Value', '৳ ${m.totalAmount.toStringAsFixed(2)}'),
          _row(
              m.type == SellerMovementType.stockOut
                  ? 'Removed By'
                  : m.type == SellerMovementType.adjustment
                      ? 'Adjusted By'
                      : 'Added By',
              m.actor),
          _row('Reason', m.reason),
          const SizedBox(height: 8),
          Text('Notes',
              style: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text(m.note),
          const SizedBox(height: 14),
          OutlinedButton(
            onPressed: () => context.go('/seller/products/${m.productId}'),
            child: const Text('View Product'),
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () => context.go('/seller/inventory/history'),
            icon: const Icon(Icons.history_rounded),
            label: const Text('Back to History'),
          ),
        ],
      ),
    );
  }
}

Widget _row(String k, String v) => Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(children: <Widget>[
        Expanded(child: Text(k, style: const TextStyle(color: kSellerMuted))),
        Text(v, style: const TextStyle(fontWeight: FontWeight.w700))
      ]),
    );
