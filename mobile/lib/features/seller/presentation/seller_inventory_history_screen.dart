import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_inventory_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_product_thumbnail.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerInventoryHistoryScreen extends ConsumerStatefulWidget {
  const SellerInventoryHistoryScreen({super.key});

  @override
  ConsumerState<SellerInventoryHistoryScreen> createState() =>
      _SellerInventoryHistoryScreenState();
}

class _SellerInventoryHistoryScreenState
    extends ConsumerState<SellerInventoryHistoryScreen> {
  SellerMovementType? _type;
  String _warehouse = 'All Warehouses';
  final TextEditingController _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final all = ref.watch(sellerInventoryProvider);
    final products = ref.watch(sellerProductsProvider);
    final warehouseNames =
        ref.watch(sellerWarehouseProvider.notifier).names(includeAll: true);
    final selectedWarehouse =
        warehouseNames.contains(_warehouse) ? _warehouse : warehouseNames.first;
    final q = _search.text.trim().toLowerCase();
    final filtered = all.where((m) {
      if (_type != null && m.type != _type) {
        return false;
      }
      if (selectedWarehouse != 'All Warehouses' &&
          m.warehouse != selectedWarehouse) {
        return false;
      }
      if (q.isNotEmpty &&
          !m.productName.toLowerCase().contains(q) &&
          !m.referenceId.toLowerCase().contains(q)) {
        return false;
      }
      return true;
    }).toList();
    return SellerScaffold(
      selectedNavIndex: 2,
      appBar: AppBar(
        title: const Text('Inventory History'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/seller/products'),
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Products',
            onPressed: () => context.go('/seller/products'),
            icon: const Icon(Icons.storefront_outlined),
          ),
          IconButton(
            tooltip: 'Inventory',
            onPressed: () => context.go('/seller/inventory'),
            icon: const Icon(Icons.inventory_2_outlined),
          ),
          IconButton(
            tooltip: 'Filter',
            onPressed: () => context.push('/seller/inventory/filter'),
            icon: const Icon(Icons.filter_alt_outlined),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          TextField(
            controller: _search,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
                prefixIcon: Icon(Icons.search),
                hintText: 'Search product or reference ID'),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            children: <Widget>[
              ChoiceChip(
                  label: const Text('All'),
                  selected: _type == null,
                  onSelected: (_) => setState(() => _type = null)),
              ChoiceChip(
                  label: const Text('Stock In'),
                  selected: _type == SellerMovementType.stockIn,
                  onSelected: (_) =>
                      setState(() => _type = SellerMovementType.stockIn)),
              ChoiceChip(
                  label: const Text('Stock Out'),
                  selected: _type == SellerMovementType.stockOut,
                  onSelected: (_) =>
                      setState(() => _type = SellerMovementType.stockOut)),
              ChoiceChip(
                  label: const Text('Adjustments'),
                  selected: _type == SellerMovementType.adjustment,
                  onSelected: (_) =>
                      setState(() => _type = SellerMovementType.adjustment)),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              const Expanded(
                  child: Text('Warehouse',
                      style: TextStyle(
                          fontWeight: FontWeight.w700, color: kSellerMuted))),
              DropdownButton<String>(
                value: selectedWarehouse,
                items: warehouseNames
                    .map((e) =>
                        DropdownMenuItem<String>(value: e, child: Text(e)))
                    .toList(),
                onChanged: (v) {
                  if (v != null) setState(() => _warehouse = v);
                },
              ),
            ],
          ),
          const SizedBox(height: 6),
          _SectionShortcuts(
            onProducts: () => context.go('/seller/products'),
            onInventory: () => context.go('/seller/inventory'),
            onDashboard: () => context.go('/seller/dashboard'),
          ),
          const SizedBox(height: 12),
          ...filtered.map((m) => Container(
                margin: const EdgeInsets.only(bottom: 10),
                decoration: sellerCardDecoration(Theme.of(context).colorScheme),
                child: ListTile(
                  onTap: () =>
                      context.push('/seller/inventory/movement/${m.id}'),
                  leading: SellerProductThumbnail(
                    product: _productFor(products, m.productId),
                  ),
                  title: Text(m.productName,
                      style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text(
                      '${m.type.label}\n${sellerNiceDate(m.at)}      Ref: ${m.referenceId}'),
                  isThreeLine: true,
                  trailing: Text(
                    '${m.quantity > 0 ? '+' : ''}${m.quantity}',
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      color: m.quantity > 0
                          ? const Color(0xFF16A34A)
                          : const Color(0xFFDC2626),
                    ),
                  ),
                ),
              )),
          if (filtered.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 72),
              child: _EmptyInventoryHistory(),
            ),
        ],
      ),
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

class _SectionShortcuts extends StatelessWidget {
  const _SectionShortcuts({
    required this.onProducts,
    required this.onInventory,
    required this.onDashboard,
  });

  final VoidCallback onProducts;
  final VoidCallback onInventory;
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
          child: OutlinedButton.icon(
            onPressed: onInventory,
            icon: const Icon(Icons.inventory_2_outlined, size: 18),
            label: const Text('Inventory'),
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

class _EmptyInventoryHistory extends StatelessWidget {
  const _EmptyInventoryHistory();

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(
          Icons.manage_search_rounded,
          color: Theme.of(context).colorScheme.primary,
          size: 44,
        ),
        const SizedBox(height: 10),
        Text(
          'No movements found',
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 4),
        const Text(
          'Try another search, type, or warehouse.',
          textAlign: TextAlign.center,
          style: TextStyle(color: kSellerMuted),
        ),
      ],
    );
  }
}
