import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../../core/pagination/pagination_meta.dart';
import '../../../core/state/paginated_state.dart';
import '../../../core/state/list_state_persistence.dart';
import '../data/product_repository.dart';

final productListControllerProvider = NotifierProvider<ProductListController, PaginatedState<ProductDto>>(
  ProductListController.new,
);

class ProductListController extends Notifier<PaginatedState<ProductDto>> {
  static const _moduleKey = 'products';

  String _search = '';
  int? _categoryId;
  int? _storefrontId;
  String _sort = 'latest';
  double _scrollOffset = 0;
  bool _initialized = false;

  @override
  PaginatedState<ProductDto> build() => const PaginatedState<ProductDto>();

  String get search => _search;
  int? get categoryId => _categoryId;
  int? get storefrontId => _storefrontId;
  String get sort => _sort;
  double get scrollOffset => _scrollOffset;

  bool get hasActiveQuery =>
      _search.trim().isNotEmpty || _categoryId != null || _storefrontId != null;

  Future<void> loadFirstPage() async {
    state = state.copyWith(isInitialLoading: true, errorMessage: null);
    try {
      final result = await _fetchPage(page: 1, perPage: 10);
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
      final result = await _fetchPage(page: 1, perPage: 10);
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
      final result = await _fetchPage(page: nextPage, perPage: state.meta?.perPage ?? 10);
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

  Future<void> applyQuery({
    required String search,
    required int? categoryId,
    required int? storefrontId,
    String? sort,
  }) async {
    _search = search.trim();
    _categoryId = categoryId;
    _storefrontId = storefrontId;
    if (sort != null) {
      _sort = sort;
    }
    _scrollOffset = 0;
    await _persist();
    await loadFirstPage();
  }

  Future<void> initialize() async {
    if (_initialized) {
      return;
    }
    _initialized = true;
    final persisted = ref.read(listStatePersistenceProvider).load(_moduleKey);
    if (persisted == null) {
      await loadFirstPage();
      return;
    }
    _search = persisted.query;
    _sort = persisted.sort;
    _categoryId = (persisted.filters['category_id'] as num?)?.toInt();
    _storefrontId = (persisted.filters['storefront_id'] as num?)?.toInt();
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
    _search = '';
    _categoryId = null;
    _storefrontId = null;
    _sort = 'latest';
    _scrollOffset = 0;
    await ref.read(listStatePersistenceProvider).clear(_moduleKey);
    await loadFirstPage();
  }

  Future<void> updateScrollOffset(double offset) async {
    _scrollOffset = offset;
    await _persist();
  }

  Future<PaginatedResult<ProductDto>> _fetchPage({
    required int page,
    required int perPage,
  }) async {
    final repository = ref.read(productRepositoryProvider);
    if (_search.isNotEmpty) {
      return repository.search(
        query: _search,
        page: page,
        perPage: perPage,
        categoryId: _categoryId,
        storefrontId: _storefrontId,
      );
    }
    return repository.list(
      page: page,
      perPage: perPage,
      categoryId: _categoryId,
      storefrontId: _storefrontId,
    );
  }

  Future<void> _persist() async {
    final meta = state.meta;
    await ref.read(listStatePersistenceProvider).save(
      _moduleKey,
      PersistedListUiState(
        query: _search,
        sort: _sort,
        filters: <String, dynamic>{
          if (_categoryId != null) 'category_id': _categoryId,
          if (_storefrontId != null) 'storefront_id': _storefrontId,
        },
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
