import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_inventory_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerWarehouseManagementScreen extends ConsumerWidget {
  const SellerWarehouseManagementScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warehouses = ref.watch(sellerWarehouseProvider);
    final products = ref.watch(sellerProductsProvider);
    final activeCount = warehouses.where((e) => e.isActive).length;

    return SellerScaffold(
      selectedNavIndex: 4,
      appBar: AppBar(
        backgroundColor: const Color(0xFFF8FAFD),
        surfaceTintColor: Colors.transparent,
        title: const Text('Warehouse Management'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/seller/menu'),
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Inventory',
            onPressed: () => context.go('/seller/inventory'),
            icon: const Icon(Icons.inventory_2_outlined),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showWarehouseSheet(context, ref),
        backgroundColor: kSellerGradientStart,
        foregroundColor: Colors.white,
        elevation: 8,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Add', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(color: Color(0xFFF8FAFD)),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 100),
          children: <Widget>[
            _WarehouseSummaryCard(activeCount: activeCount),
            const SizedBox(height: 24),
            ...warehouses.map((warehouse) {
              final stock = _stockForWarehouse(products, warehouse.name);
              final canDelete =
                  stock == 0 && warehouse.name != 'Main Warehouse';
              final canDeactivate =
                  warehouse.name != 'Main Warehouse' && activeCount > 1;
              return Padding(
                padding: const EdgeInsets.only(bottom: 18),
                child: _WarehouseCard(
                  warehouse: warehouse,
                  stock: stock,
                  onEdit: () => _showWarehouseSheet(
                    context,
                    ref,
                    warehouse: warehouse,
                    hasStock: stock > 0,
                  ),
                  onToggle: (!warehouse.isActive || canDeactivate)
                      ? () => ref
                          .read(sellerWarehouseProvider.notifier)
                          .setActive(warehouse.id, !warehouse.isActive)
                      : null,
                  onDelete: canDelete
                      ? () => ref
                          .read(sellerWarehouseProvider.notifier)
                          .deleteWarehouse(warehouse.id)
                      : null,
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}

class _WarehouseSummaryCard extends StatelessWidget {
  const _WarehouseSummaryCard({required this.activeCount});

  final int activeCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 18, 16, 18),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFBFDBFE)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF2563EB).withValues(alpha: 0.06),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: <Widget>[
          Container(
            width: 40,
            height: 40,
            decoration: const BoxDecoration(
              color: Color(0xFFDBEAFE),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.apartment_rounded,
                color: Color(0xFF2563EB), size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  '$activeCount active warehouses',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: const Color(0xFF172554),
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Inventory stock-in, stock-out, and history filters use this list.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: const Color(0xFF2563EB),
                        height: 1.45,
                        fontWeight: FontWeight.w600,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _WarehouseCard extends StatelessWidget {
  const _WarehouseCard({
    required this.warehouse,
    required this.stock,
    required this.onEdit,
    required this.onToggle,
    required this.onDelete,
  });

  final SellerWarehouse warehouse;
  final int stock;
  final VoidCallback onEdit;
  final VoidCallback? onToggle;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final active = warehouse.isActive;
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 12, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFEFEFF2)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF111827).withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: const Color(0xFFFAFAFA),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFEDEEF1)),
            ),
            child: Icon(
              active ? Icons.warehouse_rounded : Icons.warehouse_outlined,
              color: active ? const Color(0xFF2563EB) : const Color(0xFF94A3B8),
              size: 25,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  warehouse.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: const Color(0xFF1F2937),
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 3),
                Text(
                  [
                    if (warehouse.code.isNotEmpty) warehouse.code.toUpperCase(),
                    if (warehouse.city.isNotEmpty) warehouse.city,
                  ].join(' • '),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: const Color(0xFF64748B),
                        letterSpacing: 0.5,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 12),
                _StockBadge(stock: stock),
              ],
            ),
          ),
          PopupMenuButton<String>(
            tooltip: 'Warehouse actions',
            icon: const Icon(Icons.more_vert_rounded,
                color: Color(0xFF94A3B8), size: 22),
            onSelected: (value) {
              switch (value) {
                case 'edit':
                  onEdit();
                  break;
                case 'toggle':
                  onToggle?.call();
                  break;
                case 'delete':
                  onDelete?.call();
                  break;
              }
            },
            itemBuilder: (_) => <PopupMenuEntry<String>>[
              const PopupMenuItem<String>(
                value: 'edit',
                child: Row(
                  children: <Widget>[
                    Icon(Icons.edit_outlined, size: 18),
                    SizedBox(width: 10),
                    Text('Edit'),
                  ],
                ),
              ),
              PopupMenuItem<String>(
                value: 'toggle',
                enabled: onToggle != null,
                child: Row(
                  children: <Widget>[
                    Icon(
                      active
                          ? Icons.pause_circle_outline_rounded
                          : Icons.play_circle_outline_rounded,
                      size: 18,
                    ),
                    const SizedBox(width: 10),
                    Text(active ? 'Deactivate' : 'Activate'),
                  ],
                ),
              ),
              PopupMenuItem<String>(
                value: 'delete',
                enabled: onDelete != null,
                child: const Row(
                  children: <Widget>[
                    Icon(Icons.delete_outline_rounded, size: 18),
                    SizedBox(width: 10),
                    Text('Delete'),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StockBadge extends StatelessWidget {
  const _StockBadge({required this.stock});

  final int stock;

  @override
  Widget build(BuildContext context) {
    final hasStock = stock > 0;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: hasStock ? const Color(0xFFE8FFF4) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(
          color: hasStock ? const Color(0xFFA7F3D0) : const Color(0xFFD8E0EA),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(
            hasStock ? Icons.inventory_2_outlined : Icons.info_outline_rounded,
            size: 15,
            color: hasStock ? const Color(0xFF047857) : const Color(0xFF64748B),
          ),
          const SizedBox(width: 5),
          Text(
            'Stock $stock',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: hasStock
                      ? const Color(0xFF047857)
                      : const Color(0xFF64748B),
                  fontWeight: FontWeight.w900,
                  letterSpacing: 0.2,
                ),
          ),
        ],
      ),
    );
  }
}

Future<void> _showWarehouseSheet(
  BuildContext context,
  WidgetRef ref, {
  SellerWarehouse? warehouse,
  bool hasStock = false,
}) async {
  final name = TextEditingController(text: warehouse?.name ?? '');
  final code = TextEditingController(text: warehouse?.code ?? '');
  final city = TextEditingController(text: warehouse?.city ?? '');
  final formKey = GlobalKey<FormState>();

  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: Colors.white,
    builder: (context) => SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 18,
          right: 18,
          top: 4,
          bottom: MediaQuery.of(context).viewInsets.bottom + 18,
        ),
        child: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                warehouse == null ? 'Add Warehouse' : 'Edit Warehouse',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: const Color(0xFF1F2937),
                      fontWeight: FontWeight.w900,
                    ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: name,
                enabled: !(hasStock && warehouse != null),
                decoration: InputDecoration(
                  labelText: 'Warehouse name',
                  helperText: hasStock && warehouse != null
                      ? 'Name locked because this warehouse has stock history.'
                      : null,
                ),
                validator: (value) =>
                    (value ?? '').trim().isEmpty ? 'Name is required' : null,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: code,
                textCapitalization: TextCapitalization.characters,
                decoration: const InputDecoration(labelText: 'Code'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: city,
                decoration: const InputDecoration(labelText: 'City'),
              ),
              const SizedBox(height: 18),
              FilledButton.icon(
                onPressed: () {
                  if (!(formKey.currentState?.validate() ?? false)) {
                    return;
                  }
                  final controller = ref.read(sellerWarehouseProvider.notifier);
                  if (warehouse == null) {
                    controller.addWarehouse(
                      name: name.text,
                      code: code.text,
                      city: city.text,
                    );
                  } else {
                    controller.updateWarehouse(
                      warehouse.id,
                      name: hasStock ? warehouse.name : name.text,
                      code: code.text,
                      city: city.text,
                    );
                  }
                  Navigator.of(context).pop();
                },
                icon: Icon(warehouse == null
                    ? Icons.add_rounded
                    : Icons.check_rounded),
                label: Text(
                    warehouse == null ? 'Add Warehouse' : 'Save Warehouse'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(54),
                  backgroundColor: kSellerGradientStart,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );

  name.dispose();
  code.dispose();
  city.dispose();
}

int _stockForWarehouse(List<SellerProduct> products, String warehouse) {
  return products.fold<int>(
    0,
    (sum, product) => sum + (product.warehouseStocks[warehouse] ?? 0),
  );
}
