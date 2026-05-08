import 'dart:async';
import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';
import '../domain/seller_models.dart';
import 'seller_product_controller.dart';

final sellerInventoryProvider =
    NotifierProvider<SellerInventoryController, List<SellerInventoryMovement>>(
        SellerInventoryController.new);
final sellerWarehouseProvider =
    NotifierProvider<SellerWarehouseController, List<SellerWarehouse>>(
        SellerWarehouseController.new);

class SellerWarehouseController extends Notifier<List<SellerWarehouse>> {
  static const String _prefsKey = 'seller.warehouses.v1';
  static const List<SellerWarehouse> _defaultWarehouses = <SellerWarehouse>[
    SellerWarehouse(
      id: 1,
      name: 'Main Warehouse',
      code: 'MAIN',
      city: 'Dhaka',
    ),
    SellerWarehouse(
      id: 2,
      name: 'Dhaka Hub',
      code: 'DHK',
      city: 'Dhaka',
    ),
  ];

  @override
  List<SellerWarehouse> build() {
    final raw = ref.read(sharedPreferencesProvider).getString(_prefsKey);
    if (raw == null || raw.isEmpty) {
      return _defaultWarehouses;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) {
        return _defaultWarehouses;
      }
      final saved = decoded
          .whereType<Map<String, dynamic>>()
          .map(SellerWarehouse.fromJson)
          .where((warehouse) => warehouse.id > 0 && warehouse.name.isNotEmpty)
          .toList();
      return saved.isEmpty ? _defaultWarehouses : saved;
    } catch (_) {
      return _defaultWarehouses;
    }
  }

  List<String> names({bool includeAll = false, bool activeOnly = true}) {
    final activeValues = state
        .where((warehouse) => !activeOnly || warehouse.isActive)
        .map((warehouse) => warehouse.name)
        .toList();
    final values = activeValues.isEmpty && state.isNotEmpty
        ? <String>[state.first.name]
        : activeValues;
    return <String>[if (includeAll) 'All Warehouses', ...values];
  }

  void addWarehouse({
    required String name,
    String code = '',
    String city = '',
  }) {
    final cleanName = name.trim();
    if (cleanName.isEmpty) {
      return;
    }
    if (state.any((warehouse) =>
        warehouse.name.toLowerCase() == cleanName.toLowerCase())) {
      return;
    }
    final nextId = state.isEmpty
        ? 1
        : state
                .map((warehouse) => warehouse.id)
                .reduce((a, b) => a > b ? a : b) +
            1;
    _setWarehouses(<SellerWarehouse>[
      ...state,
      SellerWarehouse(
        id: nextId,
        name: cleanName,
        code: code.trim(),
        city: city.trim(),
      ),
    ]);
  }

  void updateWarehouse(
    int id, {
    required String name,
    String code = '',
    String city = '',
  }) {
    final cleanName = name.trim();
    if (cleanName.isEmpty) {
      return;
    }
    if (state.any((warehouse) =>
        warehouse.id != id &&
        warehouse.name.toLowerCase() == cleanName.toLowerCase())) {
      return;
    }
    _setWarehouses(state
        .map((warehouse) => warehouse.id == id
            ? warehouse.copyWith(
                name: cleanName,
                code: code.trim(),
                city: city.trim(),
              )
            : warehouse)
        .toList());
  }

  void setActive(int id, bool active) {
    _setWarehouses(state
        .map((warehouse) => warehouse.id == id
            ? warehouse.copyWith(isActive: active)
            : warehouse)
        .toList());
  }

  void deleteWarehouse(int id) {
    _setWarehouses(state.where((warehouse) => warehouse.id != id).toList());
  }

  void _setWarehouses(List<SellerWarehouse> warehouses) {
    state = warehouses;
    final encoded = jsonEncode(warehouses.map((e) => e.toJson()).toList());
    unawaited(
        ref.read(sharedPreferencesProvider).setString(_prefsKey, encoded));
  }
}

class SellerInventoryController
    extends Notifier<List<SellerInventoryMovement>> {
  @override
  List<SellerInventoryMovement> build() {
    return const <SellerInventoryMovement>[];
  }

  SellerInventoryMovement? byId(int id) {
    for (final m in state) {
      if (m.id == id) return m;
    }
    return null;
  }

  Future<void> addStockIn({
    required SellerProduct product,
    required int quantity,
    required double unitCost,
    required String warehouse,
    required String reason,
    required String note,
  }) async {
    if (quantity <= 0) return;
    final previous = product.stock;
    final next = previous + quantity;
    final movement = SellerInventoryMovement(
      id: (state.isEmpty ? 1 : state.first.id + 1),
      productId: product.id,
      productName: product.name,
      productSku: product.sku,
      type: SellerMovementType.stockIn,
      quantity: quantity,
      previousStock: previous,
      newStock: next,
      unitAmount: unitCost,
      currency: product.currency,
      warehouse: warehouse,
      referenceId: 'IN-${DateTime.now().millisecondsSinceEpoch % 1000000}',
      reason: reason,
      note: note,
      actor: 'Seller',
      at: DateTime.now(),
    );
    final byWarehouse = Map<String, int>.from(product.warehouseStocks);
    final currentWh = byWarehouse[warehouse] ?? 0;
    byWarehouse[warehouse] = currentWh + quantity;
    state = <SellerInventoryMovement>[movement, ...state];
    await ref.read(sellerProductsProvider.notifier).updateProduct(
          product.copyWith(
            stock: next,
            status: next > 0
                ? SellerProductStatus.active
                : SellerProductStatus.outOfStock,
            warehouseStocks: byWarehouse,
          ),
        );
  }

  Future<void> addStockOut({
    required SellerProduct product,
    required int quantity,
    required String warehouse,
    required String reason,
    required String note,
  }) async {
    if (quantity <= 0) return;
    final previous = product.stock;
    final next = (previous - quantity).clamp(0, 1 << 30);
    final movement = SellerInventoryMovement(
      id: (state.isEmpty ? 1 : state.first.id + 1),
      productId: product.id,
      productName: product.name,
      productSku: product.sku,
      type: SellerMovementType.stockOut,
      quantity: -quantity,
      previousStock: previous,
      newStock: next,
      unitAmount: product.price,
      currency: product.currency,
      warehouse: warehouse,
      referenceId: 'OUT-${DateTime.now().millisecondsSinceEpoch % 1000000}',
      reason: reason,
      note: note,
      actor: 'Seller',
      at: DateTime.now(),
    );
    final byWarehouse = Map<String, int>.from(product.warehouseStocks);
    final currentWh = byWarehouse[warehouse] ?? previous;
    byWarehouse[warehouse] = (currentWh - quantity).clamp(0, 1 << 30);
    state = <SellerInventoryMovement>[movement, ...state];
    await ref.read(sellerProductsProvider.notifier).updateProduct(
          product.copyWith(
            stock: next,
            status: next <= 0 ? SellerProductStatus.outOfStock : product.status,
            warehouseStocks: byWarehouse,
          ),
        );
  }

  Future<void> addAdjustment({
    required SellerProduct product,
    required int quantityChange,
    required String warehouse,
    required String reason,
    required String note,
  }) async {
    if (quantityChange == 0) return;
    final previous = product.stock;
    final next = (previous + quantityChange).clamp(0, 1 << 30);
    final movement = SellerInventoryMovement(
      id: (state.isEmpty ? 1 : state.first.id + 1),
      productId: product.id,
      productName: product.name,
      productSku: product.sku,
      type: SellerMovementType.adjustment,
      quantity: quantityChange,
      previousStock: previous,
      newStock: next,
      unitAmount: product.price,
      currency: product.currency,
      warehouse: warehouse,
      referenceId: 'ADJ-${DateTime.now().millisecondsSinceEpoch % 1000000}',
      reason: reason,
      note: note,
      actor: 'Seller',
      at: DateTime.now(),
    );
    final byWarehouse = Map<String, int>.from(product.warehouseStocks);
    final currentWh = byWarehouse[warehouse] ?? previous;
    byWarehouse[warehouse] = (currentWh + quantityChange).clamp(0, 1 << 30);
    state = <SellerInventoryMovement>[movement, ...state];
    await ref.read(sellerProductsProvider.notifier).updateProduct(
          product.copyWith(
            stock: next,
            status: next <= 0
                ? SellerProductStatus.outOfStock
                : SellerProductStatus.active,
            warehouseStocks: byWarehouse,
          ),
        );
  }
}
