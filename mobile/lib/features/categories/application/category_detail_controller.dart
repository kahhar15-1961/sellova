import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../../core/pagination/pagination_meta.dart';
import '../../../core/state/list_state_persistence.dart';
import '../../../core/state/paginated_state.dart';
import '../../products/data/product_repository.dart';

final categoryDetailControllerProvider = NotifierProvider.autoDispose
    .family<CategoryDetailController, PaginatedState<ProductDto>, int>(
  CategoryDetailController.new,
);

class CategoryDetailController extends AutoDisposeFamilyNotifier<PaginatedState<ProductDto>, int> {
  static const int _perPage = 10;

  String _searchQuery = '';
  double _scrollOffset = 0;
  String _selectedType = 'All';
  String _sort = 'relevance';
  bool _onlyWithImages = false;
  bool _onlyWithRating = false;
  bool _bootstrapped = false;

  String get persistenceKey => 'category_detail_$arg';
  String get search => _searchQuery;
  double get scrollOffset => _scrollOffset;
  bool get hasActiveSearch => _searchQuery.trim().isNotEmpty;
  String get selectedType => _selectedType;
  String get sort => _sort;
  bool get onlyWithImages => _onlyWithImages;
  bool get onlyWithRating => _onlyWithRating;

  @override
  PaginatedState<ProductDto> build(int categoryId) {
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
    _selectedType = (persisted.filters['selected_type'] ?? 'All').toString();
    _sort = persisted.sort;
    _onlyWithImages = persisted.filters['only_with_images'] == true;
    _onlyWithRating = persisted.filters['only_with_rating'] == true;

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

  Future<void> applySearch(String raw) async {
    final trimmed = raw.trim();
    if (trimmed == _searchQuery.trim()) {
      return;
    }
    _searchQuery = trimmed;
    _scrollOffset = 0;
    await _persist();
    await loadFirstPage();
  }

  Future<void> updateScrollOffset(double offset) async {
    _scrollOffset = offset;
    await _persist();
  }

  Future<void> updateUiPreferences({
    required String selectedType,
    required String sort,
    required bool onlyWithImages,
    required bool onlyWithRating,
  }) async {
    _selectedType = selectedType;
    _sort = sort;
    _onlyWithImages = onlyWithImages;
    _onlyWithRating = onlyWithRating;
    await _persist();
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
        categoryId: arg,
      );
    }
    return repository.list(
      page: page,
      perPage: perPage,
      categoryId: arg,
    );
  }

  Future<void> _persist() async {
    final meta = state.meta;
    await ref.read(listStatePersistenceProvider).save(
          persistenceKey,
          PersistedListUiState(
            query: _searchQuery,
            sort: _sort,
            filters: <String, dynamic>{
              'category_id': arg,
              'selected_type': _selectedType,
              'only_with_images': _onlyWithImages,
              'only_with_rating': _onlyWithRating,
            },
            currentTab: _tabForSelectedType(_selectedType),
            scrollOffset: _scrollOffset,
            page: meta?.page ?? 1,
            perPage: meta?.perPage ?? _perPage,
            items: state.items.map((ProductDto e) => e.raw).toList(),
            savedAtEpochMs: DateTime.now().millisecondsSinceEpoch,
          ),
        );
  }

  int _tabForSelectedType(String value) {
    switch (value) {
      case 'Physical':
        return 1;
      case 'Digital':
        return 2;
      case 'Manual':
        return 3;
      case 'All':
      default:
        return 0;
    }
  }
}

