import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';

class SellerProductDetailScreen extends ConsumerWidget {
  const SellerProductDetailScreen({super.key, required this.productId});
  final int productId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = ref.watch(sellerProductsProvider.notifier).byId(productId);
    if (p == null) return const Scaffold(body: Center(child: Text('Product not found')));
    return Scaffold(
      appBar: AppBar(title: const Text('Product Details')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Container(
            width: 160,
            height: 160,
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHighest, borderRadius: BorderRadius.circular(14)),
            child: const Icon(Icons.inventory_2_outlined, size: 56),
          ),
          Text(p.name, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(p.priceLabel, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          _line('Status', p.status.label),
          _line('Stock', '${p.stock}'),
          _line('SKU', p.sku),
          _line('Category', p.category),
          _line('Views', '${p.views}'),
          _line('Sold', '${p.sold}'),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                child: FilledButton(
                  onPressed: () => context.push('/seller/products/$productId/edit'),
                  child: const Text('Edit Product'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton(
                  onPressed: () async {
                    final deactivate = p.status == SellerProductStatus.active;
                    await ref.read(sellerProductsProvider.notifier).toggleProductActive(productId, !deactivate);
                    if (context.mounted) {
                      showSellerSuccessToast(context, deactivate ? 'Product deactivated.' : 'Product activated.');
                    }
                  },
                  child: Text(p.status == SellerProductStatus.active ? 'Deactivate' : 'Activate'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () => context.push('/seller/inventory/add-stock-in?productId=$productId'),
            child: const Text('Add Stock In'),
          ),
        ],
      ),
    );
  }

  Widget _line(String k, String v) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(children: <Widget>[Expanded(child: Text(k, style: const TextStyle(color: Colors.black54))), Text(v, style: const TextStyle(fontWeight: FontWeight.w700))]),
      );
}
