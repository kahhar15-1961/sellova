import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../categories/application/category_detail_controller.dart';
import '../../products/application/product_detail_provider.dart';
import '../../products/application/product_list_controller.dart';
import '../../products/data/product_repository.dart';
import '../../storefronts/application/storefront_browse_controller.dart';
import '../domain/seller_models.dart';
import 'seller_demo_controller.dart';
import 'seller_failure.dart';

final sellerProductsProvider =
    NotifierProvider<SellerProductsController, List<SellerProduct>>(
        SellerProductsController.new);

class SellerProductsController extends Notifier<List<SellerProduct>> {
  bool _loadedRemote = false;

  @override
  List<SellerProduct> build() {
    Future<void>.microtask(_hydrateFromBackend);
    return const <SellerProduct>[];
  }

  Future<void> _hydrateFromBackend() async {
    if (_loadedRemote) return;
    _loadedRemote = true;
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      final page = await ref
          .read(sellerRepositoryProvider)
          .listProducts(page: 1, perPage: 50);
      final repo = ref.read(sellerRepositoryProvider);
      state = page.items
          .map(repo.normalizeProductDto)
          .map(_fromProductDto)
          .toList();
    } catch (e) {
      if (isMissingSellerProfileError(e)) {
        state = const <SellerProduct>[];
        ref.read(sellerErrorProvider.notifier).state = null;
      } else {
        ref.read(sellerErrorProvider.notifier).state =
            SellerFailure.from(e).message;
      }
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
    bool isInstantDelivery = false,
    String? imageUrl,
    List<String> imageUrls = const <String>[],
    Map<String, dynamic> attributes = const <String, dynamic>{},
  }) async {
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
            isInstantDelivery: isInstantDelivery,
            imageUrl: imageUrl,
            imageUrls: imageUrls,
            attributes: attributes,
          );
      await refresh();
      await _refreshBuyerFacingProductLists();
    } catch (e) {
      ref.read(sellerErrorProvider.notifier).state =
          SellerFailure.from(e).message;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> updateProduct(SellerProduct next) async {
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
            productType: next.productType,
            isInstantDelivery: next.isInstantDelivery,
            imageUrl: next.imageUrl,
            imageUrls: next.imageUrls,
            attributes: next.attributes,
          );
      ref.invalidate(productDetailProvider(next.id));
      await refresh();
      await _refreshBuyerFacingProductLists();
    } catch (e) {
      ref.read(sellerErrorProvider.notifier).state =
          SellerFailure.from(e).message;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> toggleProductActive(int productId, bool active) async {
    ref.read(sellerBusyProvider.notifier).state = true;
    ref.read(sellerErrorProvider.notifier).state = null;
    try {
      await ref.read(sellerRepositoryProvider).toggleProduct(
            productId: productId,
            active: active,
          );
      ref.invalidate(productDetailProvider(productId));
      await refresh();
      await _refreshBuyerFacingProductLists();
    } catch (e) {
      ref.read(sellerErrorProvider.notifier).state =
          SellerFailure.from(e).message;
    } finally {
      ref.read(sellerBusyProvider.notifier).state = false;
    }
  }

  Future<void> _refreshBuyerFacingProductLists() async {
    await ref.read(listStatePersistenceProvider).clearProductBrowsingState();
    ref.invalidate(categoryDetailControllerProvider);
    ref.invalidate(storefrontBrowseControllerProvider);
    await ref.read(productListControllerProvider.notifier).refresh();
  }
}

SellerProduct _fromProductDto(ProductDto p) {
  final rawPrice =
      p.raw['base_price'] ?? p.raw['price'] ?? p.raw['amount'] ?? 0;
  final price = double.tryParse(rawPrice.toString()) ?? 0;
  final stock = (p.raw['stock'] as num?)?.toInt() ??
      (p.raw['stock_quantity'] as num?)?.toInt() ??
      0;
  final statusText =
      (p.status.isEmpty ? (stock > 0 ? 'active' : 'out_of_stock') : p.status)
          .toLowerCase();
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
    category:
        (p.raw['category_name'] ?? p.raw['category'] ?? 'General').toString(),
    description: p.description,
    sku: (p.raw['sku'] ?? p.uuid).toString().isEmpty
        ? 'SKU-${p.id ?? 0}'
        : (p.raw['sku'] ?? p.uuid).toString(),
    views: (p.raw['views'] as num?)?.toInt() ?? 0,
    sold: (p.raw['sold'] as num?)?.toInt() ?? 0,
    imageUrl: p.primaryImageUrl,
    imageUrls: p.imageUrls,
    productType: p.productType.isEmpty ? 'physical' : p.productType,
    attributes: p.raw['attributes'] is Map
        ? Map<String, dynamic>.from(p.raw['attributes'] as Map)
        : <String, dynamic>{},
    warehouseStocks: <String, int>{'Main Warehouse': stock},
  );
}
