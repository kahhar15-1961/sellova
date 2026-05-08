import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_inventory_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_product_thumbnail.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerInventoryOverviewScreen extends ConsumerStatefulWidget {
  const SellerInventoryOverviewScreen({super.key});

  @override
  ConsumerState<SellerInventoryOverviewScreen> createState() =>
      _SellerInventoryOverviewScreenState();
}

class _SellerInventoryOverviewScreenState
    extends ConsumerState<SellerInventoryOverviewScreen> {
  String _warehouse = 'All Warehouses';

  @override
  Widget build(BuildContext context) {
    final products = ref.watch(sellerProductsProvider);
    final moves = ref.watch(sellerInventoryProvider);
    final warehouseNames =
        ref.watch(sellerWarehouseProvider.notifier).names(includeAll: true);
    final selectedWarehouse =
        warehouseNames.contains(_warehouse) ? _warehouse : warehouseNames.first;
    int stockFor(SellerProduct p) {
      if (selectedWarehouse == 'All Warehouses') return p.stock;
      return p.warehouseStocks[selectedWarehouse] ?? 0;
    }

    final totalStock = products.fold<int>(0, (s, e) => s + stockFor(e));
    final totalValue =
        products.fold<double>(0, (s, e) => s + (e.price * stockFor(e)));
    final lowStock =
        products.where((e) => stockFor(e) > 0 && stockFor(e) <= 5).length;
    final outStock = products.where((e) => stockFor(e) <= 0).length;
    final filteredMoves = selectedWarehouse == 'All Warehouses'
        ? moves
        : moves.where((m) => m.warehouse == selectedWarehouse).toList();

    return SellerScaffold(
      selectedNavIndex: 2,
      appBar: AppBar(
        title: const Text('Inventory'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/seller/products'),
        ),
        actions: <Widget>[
          IconButton(
              tooltip: 'Products',
              onPressed: () => context.go('/seller/products'),
              icon: const Icon(Icons.storefront_outlined)),
          IconButton(
              tooltip: 'History',
              onPressed: () => context.push('/seller/inventory/history'),
              icon: const Icon(Icons.history_rounded)),
          IconButton(
              tooltip: 'Summary',
              onPressed: () => context.push('/seller/inventory/summary'),
              icon: const Icon(Icons.insert_chart_outlined_rounded)),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              gradient: kSellerPrimaryGradient,
              boxShadow: <BoxShadow>[sellerGradientShadow(alpha: 0.16)],
            ),
            child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Text('Total Products',
                      style: TextStyle(color: Colors.white70)),
                  const SizedBox(height: 4),
                  Text('${products.length}',
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 34,
                          fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  Row(children: <Widget>[
                    Expanded(
                        child: Text(
                            'Active Listings\n${products.where((e) => e.status == SellerProductStatus.active).length}',
                            style: const TextStyle(color: Colors.white))),
                    Expanded(
                        child: Text('Out of Stock\n$outStock',
                            style: const TextStyle(color: Colors.white))),
                  ]),
                ]),
          ),
          const SizedBox(height: 12),
          _InventoryNavigationTabs(
            onProducts: () => context.go('/seller/products'),
            onHistory: () => context.push('/seller/inventory/history'),
            onDashboard: () => context.go('/seller/dashboard'),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: sellerCardDecoration(Theme.of(context).colorScheme),
            child: Column(
              children: <Widget>[
                Row(
                  children: <Widget>[
                    const Expanded(
                        child: Text('Stock Summary',
                            style: TextStyle(fontWeight: FontWeight.w800))),
                    DropdownButton<String>(
                      value: selectedWarehouse,
                      items: warehouseNames
                          .map((e) => DropdownMenuItem<String>(
                              value: e, child: Text(e)))
                          .toList(),
                      onChanged: (v) {
                        if (v != null) setState(() => _warehouse = v);
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                _kv('Total Stock', '$totalStock'),
                _kv('Total Value', '৳ ${totalValue.toStringAsFixed(2)}'),
                _kv('Low Stock Items', '$lowStock'),
                _kv('Out of Stock Items', '$outStock'),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                  child: Text('Recent Movements',
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(fontWeight: FontWeight.w900))),
              TextButton(
                  onPressed: () => context.push('/seller/inventory/history'),
                  child: const Text('View All')),
            ],
          ),
          ...filteredMoves.take(3).map((e) => ListTile(
                onTap: () => context.push('/seller/inventory/movement/${e.id}'),
                leading: SellerProductThumbnail(
                  product: _productFor(products, e.productId),
                ),
                title: Text(e.productName,
                    style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text(
                    '${e.type.label}  •  ${e.quantity > 0 ? '+' : ''}${e.quantity}'),
                trailing: Text(_ago(e.at),
                    style: const TextStyle(color: kSellerMuted)),
              )),
          const SizedBox(height: 10),
          FilledButton(
            onPressed: () => context.push('/seller/inventory/history'),
            child: const Text('Open Inventory History'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () {
              final products = ref.read(sellerProductsProvider);
              if (products.isNotEmpty) {
                context.push(
                    '/seller/inventory/add-stock-in?productId=${products.first.id}');
              }
            },
            child: const Text('Add Stock In'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () {
              final products = ref.read(sellerProductsProvider);
              if (products.isNotEmpty) {
                context.push(
                    '/seller/inventory/add-stock-out?productId=${products.first.id}');
              }
            },
            child: const Text('Add Stock Out'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () {
              final products = ref.read(sellerProductsProvider);
              if (products.isNotEmpty) {
                context.push(
                    '/seller/inventory/add-adjustment?productId=${products.first.id}');
              }
            },
            child: const Text('Add Adjustment'),
          ),
        ],
      ),
    );
  }

  Widget _kv(String k, String v) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(children: <Widget>[
          Expanded(child: Text(k, style: const TextStyle(color: kSellerMuted))),
          Text(v, style: const TextStyle(fontWeight: FontWeight.w800))
        ]),
      );
}

class _InventoryNavigationTabs extends StatelessWidget {
  const _InventoryNavigationTabs({
    required this.onProducts,
    required this.onHistory,
    required this.onDashboard,
  });

  final VoidCallback onProducts;
  final VoidCallback onHistory;
  final VoidCallback onDashboard;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: OutlinedButton.icon(
            onPressed: onProducts,
            icon: const Icon(Icons.storefront_outlined, size: 18),
            label: const Text('Products'),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: FilledButton.icon(
            onPressed: onHistory,
            icon: const Icon(Icons.history_rounded, size: 18),
            label: const Text('History'),
          ),
        ),
        const SizedBox(width: 8),
        IconButton.filledTonal(
          tooltip: 'Dashboard',
          onPressed: onDashboard,
          icon: const Icon(Icons.home_outlined),
        ),
      ],
    );
  }
}

SellerProduct? _productFor(List<SellerProduct> products, int productId) {
  for (final product in products) {
    if (product.id == productId) {
      return product;
    }
  }
  return null;
}

String _ago(DateTime d) {
  final diff = DateTime.now().difference(d);
  if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
  if (diff.inHours < 24) return '${diff.inHours}h ago';
  return '${diff.inDays}d ago';
}
