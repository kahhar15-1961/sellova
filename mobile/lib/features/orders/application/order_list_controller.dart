import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/state/paginated_state.dart';
import '../data/order_repository.dart';

final orderListControllerProvider = NotifierProvider<OrderListController, PaginatedState<OrderDto>>(
  OrderListController.new,
);

class OrderListController extends Notifier<PaginatedState<OrderDto>> {
  @override
  PaginatedState<OrderDto> build() => const PaginatedState<OrderDto>();

  Future<void> loadFirstPage() async {
    state = state.copyWith(isInitialLoading: true, errorMessage: null);
    try {
      final result = await ref.read(orderRepositoryProvider).list(page: 1, perPage: 10);
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
      final result = await ref.read(orderRepositoryProvider).list(page: 1, perPage: 10);
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
      final result = await ref.read(orderRepositoryProvider).list(
            page: nextPage,
            perPage: state.meta?.perPage ?? 10,
          );
      state = state.copyWith(
        items: <OrderDto>[...state.items, ...result.items],
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
