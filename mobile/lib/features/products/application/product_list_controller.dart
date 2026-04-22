import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/state/paginated_state.dart';
import '../data/product_repository.dart';

final productListControllerProvider = NotifierProvider<ProductListController, PaginatedState<ProductDto>>(
  ProductListController.new,
);

class ProductListController extends Notifier<PaginatedState<ProductDto>> {
  @override
  PaginatedState<ProductDto> build() => const PaginatedState<ProductDto>();

  Future<void> loadFirstPage() async {
    state = state.copyWith(isInitialLoading: true, errorMessage: null);
    try {
      final result = await ref.read(productRepositoryProvider).list(page: 1, perPage: 10);
      state = state.copyWith(
        items: result.items,
        meta: result.meta,
        isInitialLoading: false,
      );
    } catch (error) {
      state = state.copyWith(
        isInitialLoading: false,
        errorMessage: error.toString(),
      );
    }
  }

  Future<void> refresh() async {
    try {
      final result = await ref.read(productRepositoryProvider).list(page: 1, perPage: 10);
      state = state.copyWith(
        items: result.items,
        meta: result.meta,
        isInitialLoading: false,
        isAppending: false,
        errorMessage: null,
      );
    } catch (error) {
      state = state.copyWith(errorMessage: error.toString());
    }
  }

  Future<void> loadNextPage() async {
    if (state.isAppending || !state.hasMore) {
      return;
    }
    final nextPage = (state.meta?.page ?? 1) + 1;
    state = state.copyWith(isAppending: true, errorMessage: null);
    try {
      final result = await ref.read(productRepositoryProvider).list(
            page: nextPage,
            perPage: state.meta?.perPage ?? 10,
          );
      state = state.copyWith(
        items: <ProductDto>[...state.items, ...result.items],
        meta: result.meta,
        isAppending: false,
      );
    } catch (error) {
      state = state.copyWith(
        isAppending: false,
        errorMessage: error.toString(),
      );
    }
  }
}
