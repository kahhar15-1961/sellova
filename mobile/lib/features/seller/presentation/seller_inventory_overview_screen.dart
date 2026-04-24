import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_inventory_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerInventoryOverviewScreen extends ConsumerStatefulWidget {
  const SellerInventoryOverviewScreen({super.key});

  @override
  ConsumerState<SellerInventoryOverviewScreen> createState() => _SellerInventoryOverviewScreenState();
}

class _SellerInventoryOverviewScreenState extends ConsumerState<SellerInventoryOverviewScreen> {
  String _warehouse = 'All Warehouses';

  @override
  Widget build(BuildContext context) {
    final products = ref.watch(sellerProductsProvider);
    final moves = ref.watch(sellerInventoryProvider);
    int stockFor(SellerProduct p) {
      if (_warehouse == 'All Warehouses') return p.stock;
      return p.warehouseStocks[_warehouse] ?? 0;
    }

    final totalStock = products.fold<int>(0, (s, e) => s + stockFor(e));
    final totalValue = products.fold<double>(0, (s, e) => s + (e.price * stockFor(e)));
    final lowStock = products.where((e) => stockFor(e) > 0 && stockFor(e) <= 5).length;
    final outStock = products.where((e) => stockFor(e) <= 0).length;
    final filteredMoves = _warehouse == 'All Warehouses' ? moves : moves.where((m) => m.warehouse == _warehouse).toList();

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: const Text('Inventory'),
        actions: <Widget>[
          IconButton(onPressed: () => context.push('/seller/inventory/summary'), icon: const Icon(Icons.insert_chart_outlined_rounded)),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              gradient: const LinearGradient(colors: <Color>[Color(0xFF5B4AD9), Color(0xFF4F46E5)]),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
              const Text('Total Products', style: TextStyle(color: Colors.white70)),
              const SizedBox(height: 4),
              Text('${products.length}', style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              Row(children: <Widget>[
                Expanded(child: Text('Active Listings\n${products.where((e) => e.status == SellerProductStatus.active).length}', style: const TextStyle(color: Colors.white))),
                Expanded(child: Text('Out of Stock\n$outStock', style: const TextStyle(color: Colors.white))),
              ]),
            ]),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: sellerCardDecoration(Theme.of(context).colorScheme),
            child: Column(
              children: <Widget>[
                Row(
                  children: <Widget>[
                    const Expanded(child: Text('Stock Summary', style: TextStyle(fontWeight: FontWeight.w800))),
                    DropdownButton<String>(
                      value: _warehouse,
                      items: kSellerWarehouses.map((e) => DropdownMenuItem<String>(value: e, child: Text(e))).toList(),
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
              Expanded(child: Text('Recent Movements', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900))),
              TextButton(onPressed: () => context.push('/seller/inventory/history'), child: const Text('View All')),
            ],
          ),
          ...filteredMoves.take(3).map((e) => ListTile(
                onTap: () => context.push('/seller/inventory/movement/${e.id}'),
                leading: const CircleAvatar(child: Icon(Icons.inventory_2_outlined)),
                title: Text(e.productName, style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text('${e.type.label}  •  ${e.quantity > 0 ? '+' : ''}${e.quantity}'),
                trailing: Text(_ago(e.at), style: const TextStyle(color: kSellerMuted)),
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
                context.push('/seller/inventory/add-stock-in?productId=${products.first.id}');
              }
            },
            child: const Text('Add Stock In'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () {
              final products = ref.read(sellerProductsProvider);
              if (products.isNotEmpty) {
                context.push('/seller/inventory/add-stock-out?productId=${products.first.id}');
              }
            },
            child: const Text('Add Stock Out'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () {
              final products = ref.read(sellerProductsProvider);
              if (products.isNotEmpty) {
                context.push('/seller/inventory/add-adjustment?productId=${products.first.id}');
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
        child: Row(children: <Widget>[Expanded(child: Text(k, style: const TextStyle(color: kSellerMuted))), Text(v, style: const TextStyle(fontWeight: FontWeight.w800))]),
      );
}

String _ago(DateTime d) {
  final diff = DateTime.now().difference(d);
  if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
  if (diff.inHours < 24) return '${diff.inHours}h ago';
  return '${diff.inDays}d ago';
}
