import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../../core/pagination/pagination_meta.dart';
import '../../../core/state/paginated_state.dart';
import '../../../core/state/list_state_persistence.dart';
import '../data/order_repository.dart';

final orderListControllerProvider = NotifierProvider<OrderListController, PaginatedState<OrderDto>>(
  OrderListController.new,
);

class OrderListController extends Notifier<PaginatedState<OrderDto>> {
  static const _moduleKey = 'orders';
  String _query = '';
  String _sort = 'latest';
  double _scrollOffset = 0;
  bool _initialized = false;

  @override
  PaginatedState<OrderDto> build() => const PaginatedState<OrderDto>();

  String get query => _query;
  String get sort => _sort;
  double get scrollOffset => _scrollOffset;

  Future<void> loadFirstPage() async {
    state = state.copyWith(isInitialLoading: true, errorMessage: null);
    try {
      final result = await ref.read(orderRepositoryProvider).list(page: 1, perPage: 10);
      state = state.copyWith(
        items: result.items,
        meta: result.meta,
        isInitialLoading: false,
      );
      await _persist();
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
      await _persist();
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
      await _persist();
    } catch (error) {
      state = state.copyWith(
        isAppending: false,
        errorMessage: error.toString(),
      );
    }
  }

  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;
    final persisted = ref.read(listStatePersistenceProvider).load(_moduleKey);
    if (persisted == null) {
      await loadFirstPage();
      return;
    }
    _query = persisted.query;
    _sort = persisted.sort;
    _scrollOffset = persisted.scrollOffset;
    if (persisted.items.isNotEmpty) {
      final page = persisted.page;
      final perPage = persisted.perPage;
      final total = page * perPage;
      state = state.copyWith(
        items: persisted.items.map(OrderDto.new).toList(),
        meta: PaginationMeta(
          page: page,
          perPage: perPage,
          total: total,
          lastPage: page + 1,
          raw: <String, dynamic>{'page': page, 'per_page': perPage, 'total': total, 'last_page': page + 1},
        ),
      );
      await refreshIfStale();
    } else {
      await loadFirstPage();
    }
  }

  Future<void> refreshIfStale() async {
    final isStale = ref.read(listStatePersistenceProvider).isStale(_moduleKey);
    if (!isStale) {
      return;
    }
    await refresh();
  }

  Future<void> clearPersistedState() async {
    _query = '';
    _sort = 'latest';
    _scrollOffset = 0;
    await ref.read(listStatePersistenceProvider).clear(_moduleKey);
    await loadFirstPage();
  }

  Future<void> updateScrollOffset(double offset) async {
    _scrollOffset = offset;
    await _persist();
  }

  Future<void> _persist() async {
    final meta = state.meta;
    await ref.read(listStatePersistenceProvider).save(
      _moduleKey,
      PersistedListUiState(
        query: _query,
        sort: _sort,
        filters: const <String, dynamic>{},
        currentTab: null,
        scrollOffset: _scrollOffset,
        page: meta?.page ?? 1,
        perPage: meta?.perPage ?? 10,
        items: state.items.map((e) => e.raw).toList(),
        savedAtEpochMs: DateTime.now().millisecondsSinceEpoch,
      ),
    );
  }
}
