import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../products/data/product_repository.dart';
import '../../../core/pagination/pagination_meta.dart';
import '../../../core/state/list_state_persistence.dart';
import '../../../core/state/paginated_state.dart';

/// Paginated products for a single storefront via
/// `GET /api/v1/products?storefront_id=…` or, when [search] is non-empty,
/// `GET /api/v1/products/search?search=…&storefront_id=…`.
final storefrontBrowseControllerProvider = NotifierProvider.autoDispose
    .family<StorefrontBrowseController, PaginatedState<ProductDto>, int>(
  StorefrontBrowseController.new,
);

class StorefrontBrowseController extends AutoDisposeFamilyNotifier<PaginatedState<ProductDto>, int> {
  static const int _perPage = 10;

  String _searchQuery = '';
  double _scrollOffset = 0;
  bool _bootstrapped = false;

  String get persistenceKey => 'storefront_$arg';

  String get search => _searchQuery;

  double get scrollOffset => _scrollOffset;

  bool get hasActiveSearch => _searchQuery.trim().isNotEmpty;

  @override
  PaginatedState<ProductDto> build(int storefrontId) {
    Future<void>.microtask(_bootstrap);
    return const PaginatedState<ProductDto>(isInitialLoading: true);
  }

  Future<void> _bootstrap() async {
    if (_bootstrapped) {
      return;
    }
    _bootstrapped = true;
    final persisted = ref.read(listStatePersistenceProvider).load(persistenceKey);
    if (persisted == null) {
      await loadFirstPage();
      return;
    }
    _searchQuery = persisted.query.trim();
    _scrollOffset = persisted.scrollOffset;

    if (persisted.items.isNotEmpty) {
      final currentPage = persisted.page;
      final perPage = persisted.perPage;
      final approxTotal = currentPage * perPage;
      state = state.copyWith(
        items: persisted.items.map(ProductDto.new).toList(),
        meta: PaginationMeta(
          page: currentPage,
          perPage: perPage,
          total: approxTotal,
          lastPage: currentPage + 1,
          raw: <String, dynamic>{
            'page': currentPage,
            'per_page': perPage,
            'total': approxTotal,
            'last_page': currentPage + 1,
          },
        ),
        isInitialLoading: false,
        isAppending: false,
      );
      await refreshIfStale();
    } else {
      await loadFirstPage();
    }
  }

  Future<void> refreshIfStale() async {
    final isStale = ref.read(listStatePersistenceProvider).isStale(persistenceKey);
    if (!isStale) {
      return;
    }
    await refresh();
  }

  Future<PaginatedResult<ProductDto>> _fetchPage({
    required int page,
    required int perPage,
  }) async {
    final repository = ref.read(productRepositoryProvider);
    final q = _searchQuery.trim();
    if (q.isNotEmpty) {
      return repository.search(
        query: q,
        page: page,
        perPage: perPage,
        storefrontId: arg,
      );
    }
    return repository.list(
      page: page,
      perPage: perPage,
      storefrontId: arg,
    );
  }

  Future<void> loadFirstPage() async {
    state = state.copyWith(
      isInitialLoading: state.items.isEmpty,
      errorMessage: null,
    );
    try {
      final result = await _fetchPage(page: 1, perPage: _perPage);
      state = state.copyWith(
        items: result.items,
        meta: result.meta,
        isInitialLoading: false,
        isAppending: false,
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
      final result = await _fetchPage(page: 1, perPage: _perPage);
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
      final result = await _fetchPage(
        page: nextPage,
        perPage: state.meta?.perPage ?? _perPage,
      );
      state = state.copyWith(
        items: <ProductDto>[...state.items, ...result.items],
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

  /// Applies a trimmed search query. Empty string falls back to [clearSearch] (list API, not search).
  Future<void> applySearch(String raw) async {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) {
      await clearSearch();
      return;
    }
    _searchQuery = trimmed;
    _scrollOffset = 0;
    await _persist();
    await loadFirstPage();
  }

  Future<void> clearSearch() async {
    _searchQuery = '';
    _scrollOffset = 0;
    await _persist();
    await loadFirstPage();
  }

  Future<void> updateScrollOffset(double offset) async {
    _scrollOffset = offset;
    await _persist();
  }

  Future<void> _persist() async {
    final meta = state.meta;
    await ref.read(listStatePersistenceProvider).save(
          persistenceKey,
          PersistedListUiState(
            query: _searchQuery,
            sort: 'latest',
            filters: <String, dynamic>{'storefront_id': arg},
            currentTab: null,
            scrollOffset: _scrollOffset,
            page: meta?.page ?? 1,
            perPage: meta?.perPage ?? _perPage,
            items: state.items.map((ProductDto e) => e.raw).toList(),
            savedAtEpochMs: DateTime.now().millisecondsSinceEpoch,
          ),
        );
  }
}
