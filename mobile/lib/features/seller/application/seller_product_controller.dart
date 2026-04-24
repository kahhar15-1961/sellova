import 'dart:math';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../../products/data/product_repository.dart';
import '../domain/seller_models.dart';
import 'seller_demo_controller.dart';

final sellerProductsProvider = NotifierProvider<SellerProductsController, List<SellerProduct>>(SellerProductsController.new);

class SellerProductsController extends Notifier<List<SellerProduct>> {
  bool _loadedRemote = false;

  @override
  List<SellerProduct> build() {
    Future<void>.microtask(_hydrateFromBackend);
    return <SellerProduct>[
      const SellerProduct(
        id: 1001,
        name: 'Wireless Headphones',
        price: 2450,
        currency: 'BDT',
        stock: 15,
        status: SellerProductStatus.active,
        category: 'Electronics',
        description: 'High quality wireless headphones with noise cancelation.',
        sku: 'WH-001',
        views: 1245,
        sold: 120,
        warehouseStocks: <String, int>{'Main Warehouse': 10, 'Dhaka Hub': 5},
      ),
      const SellerProduct(
        id: 1002,
        name: 'Bluetooth Speaker',
        price: 1250,
        currency: 'BDT',
        stock: 8,
        status: SellerProductStatus.active,
        category: 'Electronics',
        description: 'Portable speaker with clear bass and long battery life.',
        sku: 'BS-008',
        views: 930,
        sold: 74,
        warehouseStocks: <String, int>{'Main Warehouse': 5, 'Dhaka Hub': 3},
      ),
      const SellerProduct(
        id: 1003,
        name: 'LED Desk Lamp',
        price: 750,
        currency: 'BDT',
        stock: 0,
        status: SellerProductStatus.outOfStock,
        category: 'Home',
        description: 'Minimal LED desk lamp with adjustable brightness.',
        sku: 'DL-110',
        views: 430,
        sold: 41,
        warehouseStocks: <String, int>{'Main Warehouse': 0, 'Dhaka Hub': 0},
      ),
    ];
  }

  Future<void> _hydrateFromBackend() async {
    if (_loadedRemote) return;
    _loadedRemote = true;
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      final page = await ref.read(productRepositoryProvider).list(page: 1, perPage: 50);
      if (page.items.isNotEmpty) {
        state = page.items.map(_fromProductDto).toList();
      }
    } catch (e) {
      ref.read(sellerErrorProvider.notifier).state = e.toString();
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> refresh() async {
    _loadedRemote = false;
    await _hydrateFromBackend();
  }

  SellerProduct? byId(int id) {
    for (final p in state) {
      if (p.id == id) return p;
    }
    return null;
  }

  Future<void> createProduct({
    required String name,
    required double price,
    required int stock,
    required String category,
    required String description,
    required String productType,
  }) async {
    final nextId = (state.map((e) => e.id).fold<int>(1000, max)) + 1;
    final draft = SellerProduct(
      id: nextId,
      name: name,
      price: price,
      currency: 'BDT',
      stock: stock,
      status: stock <= 0 ? SellerProductStatus.outOfStock : SellerProductStatus.active,
      category: category,
      description: description,
      sku: 'SKU-$nextId',
      views: 0,
      sold: 0,
      productType: productType,
      warehouseStocks: <String, int>{'Main Warehouse': stock},
    );
    state = <SellerProduct>[draft, ...state];
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref.read(sellerRepositoryProvider).createProduct(
            title: name,
            price: price,
            stock: stock,
            category: category,
            description: description,
            productType: productType,
          );
    } catch (_) {
      ref.read(sellerErrorProvider.notifier).state = 'Saved locally. Remote sync endpoint is unavailable.';
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> updateProduct(SellerProduct next) async {
    final prev = state;
    state = state.map((e) => e.id == next.id ? next : e).toList();
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref.read(sellerRepositoryProvider).updateProduct(
            productId: next.id,
            title: next.name,
            price: next.price,
            stock: next.stock,
            category: next.category,
            description: next.description,
            status: next.status.name,
          );
    } catch (_) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state = 'Update kept local only. Remote sync endpoint is unavailable.';
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> toggleProductActive(int productId, bool active) async {
    final prev = state;
    state = state.map((e) {
      if (e.id != productId) return e;
      if (e.stock <= 0) return e.copyWith(status: SellerProductStatus.outOfStock);
      return e.copyWith(status: active ? SellerProductStatus.active : SellerProductStatus.inactive);
    }).toList();
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref.read(sellerRepositoryProvider).toggleProduct(
            productId: productId,
            active: active,
          );
    } catch (_) {
      state = prev;
      ref.read(sellerErrorProvider.notifier).state = 'Status changed locally. Remote toggle endpoint is unavailable.';
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }
}

SellerProduct _fromProductDto(ProductDto p) {
  final rawPrice = p.raw['base_price'] ?? p.raw['price'] ?? p.raw['amount'] ?? 0;
  final price = double.tryParse(rawPrice.toString()) ?? 0;
  final stock = (p.raw['stock'] as num?)?.toInt() ?? (p.raw['stock_quantity'] as num?)?.toInt() ?? 0;
  final statusText = (p.status.isEmpty ? (stock > 0 ? 'active' : 'out_of_stock') : p.status).toLowerCase();
  final status = statusText.contains('out')
      ? SellerProductStatus.outOfStock
      : statusText.contains('inactive')
          ? SellerProductStatus.inactive
          : SellerProductStatus.active;
  return SellerProduct(
    id: p.id ?? 0,
    name: p.title,
    price: price,
    currency: (p.raw['currency'] ?? 'BDT').toString(),
    stock: stock,
    status: status,
    category: (p.raw['category_name'] ?? p.raw['category'] ?? 'General').toString(),
    description: p.description,
    sku: (p.raw['sku'] ?? p.uuid).toString().isEmpty ? 'SKU-${p.id ?? 0}' : (p.raw['sku'] ?? p.uuid).toString(),
    views: (p.raw['views'] as num?)?.toInt() ?? 0,
    sold: (p.raw['sold'] as num?)?.toInt() ?? 0,
    imageUrl: p.primaryImageUrl,
    productType: p.productType.isEmpty ? 'physical' : p.productType,
    warehouseStocks: <String, int>{'Main Warehouse': stock},
  );
}
