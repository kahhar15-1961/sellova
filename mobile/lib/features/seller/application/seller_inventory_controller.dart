import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/seller_models.dart';
import 'seller_product_controller.dart';

final sellerInventoryProvider = NotifierProvider<SellerInventoryController, List<SellerInventoryMovement>>(SellerInventoryController.new);
const List<String> kSellerWarehouses = <String>['All Warehouses', 'Main Warehouse', 'Dhaka Hub'];

class SellerInventoryController extends Notifier<List<SellerInventoryMovement>> {
  @override
  List<SellerInventoryMovement> build() {
    return <SellerInventoryMovement>[
      SellerInventoryMovement(
        id: 18,
        productId: 1001,
        productName: 'Wireless Headphones',
        productSku: 'WH-001',
        type: SellerMovementType.stockIn,
        quantity: 20,
        previousStock: 45,
        newStock: 65,
        unitAmount: 1250,
        currency: 'BDT',
        warehouse: 'Main Warehouse',
        referenceId: 'IN-2025-00018',
        reason: 'New Stock Received',
        note: 'Received new stock from supplier.',
        actor: 'Ashikur Rahman (Seller)',
        at: DateTime(2025, 5, 30, 10, 30),
      ),
      SellerInventoryMovement(
        id: 17,
        productId: 1004,
        productName: 'Smart Watch Series 8',
        productSku: 'SW-008',
        type: SellerMovementType.stockOut,
        quantity: -1,
        previousStock: 10,
        newStock: 9,
        unitAmount: 3990,
        currency: 'BDT',
        warehouse: 'Main Warehouse',
        referenceId: 'OUT-2025-00017',
        reason: 'Order #ORD-2025-000125',
        note: 'Stock reduced for order.',
        actor: 'System (Order)',
        at: DateTime(2025, 5, 30, 10, 15),
      ),
      SellerInventoryMovement(
        id: 16,
        productId: 1002,
        productName: 'Bluetooth Speaker',
        productSku: 'BS-002',
        type: SellerMovementType.adjustment,
        quantity: -2,
        previousStock: 18,
        newStock: 16,
        unitAmount: 1250,
        currency: 'BDT',
        warehouse: 'Main Warehouse',
        referenceId: 'ADJ-2025-00016',
        reason: 'Damaged Items',
        note: '2 units found damaged during quality check.',
        actor: 'Ashikur Rahman (Seller)',
        at: DateTime(2025, 5, 30, 9, 45),
      ),
    ];
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
      actor: 'Ashikur Rahman (Seller)',
      at: DateTime.now(),
    );
    final byWarehouse = Map<String, int>.from(product.warehouseStocks);
    final currentWh = byWarehouse[warehouse] ?? 0;
    byWarehouse[warehouse] = currentWh + quantity;
    state = <SellerInventoryMovement>[movement, ...state];
    await ref.read(sellerProductsProvider.notifier).updateProduct(
          product.copyWith(
            stock: next,
            status: next > 0 ? SellerProductStatus.active : SellerProductStatus.outOfStock,
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
      actor: 'Ashikur Rahman (Seller)',
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
      actor: 'Ashikur Rahman (Seller)',
      at: DateTime.now(),
    );
    final byWarehouse = Map<String, int>.from(product.warehouseStocks);
    final currentWh = byWarehouse[warehouse] ?? previous;
    byWarehouse[warehouse] = (currentWh + quantityChange).clamp(0, 1 << 30);
    state = <SellerInventoryMovement>[movement, ...state];
    await ref.read(sellerProductsProvider.notifier).updateProduct(
          product.copyWith(
            stock: next,
            status: next <= 0 ? SellerProductStatus.outOfStock : SellerProductStatus.active,
            warehouseStocks: byWarehouse,
          ),
        );
  }
}
