import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerProductsScreen extends ConsumerStatefulWidget {
  const SellerProductsScreen({super.key});

  @override
  ConsumerState<SellerProductsScreen> createState() => _SellerProductsScreenState();
}

class _SellerProductsScreenState extends ConsumerState<SellerProductsScreen> {
  SellerProductStatus? _tab;

  @override
  Widget build(BuildContext context) {
    final products = ref.watch(sellerProductsProvider);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    final filtered = _tab == null ? products : products.where((e) => e.status == _tab).toList();
    return SellerScaffold(
      selectedNavIndex: 2,
      appBar: AppBar(
        title: const Text('Products'),
        actions: <Widget>[
          IconButton(onPressed: () => context.push('/seller/inventory'), icon: const Icon(Icons.inventory_2_outlined)),
          IconButton(onPressed: () {}, icon: const Icon(Icons.search_rounded)),
          IconButton(onPressed: () => context.push('/seller/products/add/type'), icon: const Icon(Icons.add_rounded)),
        ],
      ),
      body: Column(
        children: <Widget>[
          if (error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              child: SellerInlineFeedback(
                message: error,
                onRetry: () => ref.read(sellerProductsProvider.notifier).refresh(),
              ),
            ),
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: <Widget>[
                _tabBtn('All', _tab == null, () => setState(() => _tab = null)),
                _tabBtn('Active', _tab == SellerProductStatus.active, () => setState(() => _tab = SellerProductStatus.active)),
                _tabBtn('Inactive', _tab == SellerProductStatus.inactive, () => setState(() => _tab = SellerProductStatus.inactive)),
                _tabBtn('Out of Stock', _tab == SellerProductStatus.outOfStock, () => setState(() => _tab = SellerProductStatus.outOfStock)),
              ],
            ),
          ),
          Expanded(
            child: busy && products.isEmpty
                ? const SellerListSkeleton()
                : filtered.isEmpty
                    ? SellerEmptyState(
                        title: 'No products yet',
                        subtitle: 'Create your first product listing to start selling.',
                        ctaLabel: 'Add Product',
                        onTap: () => context.push('/seller/products/add/type'),
                      )
                    : RefreshIndicator(
                        onRefresh: () => ref.read(sellerProductsProvider.notifier).refresh(),
                        child: ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
                          itemCount: filtered.length,
                          itemBuilder: (_, i) {
                            final p = filtered[i];
                            return Container(
                              margin: const EdgeInsets.only(bottom: 10),
                              decoration: sellerCardDecoration(Theme.of(context).colorScheme),
                              child: ListTile(
                                onTap: () => context.push('/seller/products/${p.id}'),
                                leading: const CircleAvatar(child: Icon(Icons.inventory_2_outlined)),
                                title: Text(p.name, style: const TextStyle(fontWeight: FontWeight.w800)),
                                subtitle: Text('${p.priceLabel}\nStock ${p.stock}'),
                                isThreeLine: true,
                                trailing: Switch(
                                  value: p.status == SellerProductStatus.active,
                                  onChanged: p.status == SellerProductStatus.outOfStock
                                      ? null
                                      : (v) => ref.read(sellerProductsProvider.notifier).toggleProductActive(p.id, v),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
          if (busy) const LinearProgressIndicator(minHeight: 2),
        ],
      ),
    );
  }

  Widget _tabBtn(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(label: Text(label), selected: selected, onSelected: (_) => onTap()),
    );
  }
}
