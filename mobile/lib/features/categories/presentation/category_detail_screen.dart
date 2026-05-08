import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/state/paginated_state.dart';
import '../../products/data/product_repository.dart';
import '../application/category_detail_controller.dart';
import '../application/category_list_provider.dart';
import '../data/category_repository.dart';

class CategoryDetailScreen extends ConsumerStatefulWidget {
  const CategoryDetailScreen({
    super.key,
    required this.categoryId,
  });

  final int categoryId;

  @override
  ConsumerState<CategoryDetailScreen> createState() =>
      _CategoryDetailScreenState();
}

class _CategoryDetailScreenState extends ConsumerState<CategoryDetailScreen> {
  final ScrollController _scrollController = ScrollController();
  late final TextEditingController _searchController;
  Timer? _searchDebounce;
  String _selectedType = 'All';
  _SortKey _sortKey = _SortKey.relevance;
  bool _onlyWithImages = false;
  bool _onlyWithRating = false;
  bool _didSyncSearchField = false;
  bool _didSyncUiState = false;
  bool _didRestoreScroll = false;

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController();
    _scrollController.addListener(_onScroll);
  }

  @override
  void didUpdateWidget(covariant CategoryDetailScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.categoryId != widget.categoryId) {
      _didSyncSearchField = false;
      _didSyncUiState = false;
      _didRestoreScroll = false;
      _searchController.clear();
    }
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scrollController.hasClients) {
      return;
    }
    final pos = _scrollController.position;
    ref
        .read(categoryDetailControllerProvider(widget.categoryId).notifier)
        .updateScrollOffset(pos.pixels);
    if (pos.pixels >= pos.maxScrollExtent - 200) {
      ref
          .read(categoryDetailControllerProvider(widget.categoryId).notifier)
          .loadNextPage();
    }
  }

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 320), () async {
      if (!mounted) {
        return;
      }
      await ref
          .read(categoryDetailControllerProvider(widget.categoryId).notifier)
          .applySearch(value);
      if (mounted && _scrollController.hasClients) {
        _scrollController.jumpTo(0);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final state =
        ref.watch(categoryDetailControllerProvider(widget.categoryId));
    final controller =
        ref.read(categoryDetailControllerProvider(widget.categoryId).notifier);
    final categoryName = _resolveCategoryName(ref, widget.categoryId);
    final visibleItems = _applyUiFilters(state.items);
    final hasUiFilters =
        _selectedType != 'All' || _onlyWithImages || _onlyWithRating;

    ref.listen(categoryDetailControllerProvider(widget.categoryId),
        (Object? previous, Object? next) {
      if (next is! PaginatedState<ProductDto>) {
        return;
      }
      final nextState = next;
      final q = controller.search;
      if (!_didSyncSearchField && !nextState.isInitialLoading) {
        _didSyncSearchField = true;
        if (_searchController.text != q) {
          _searchController.value = TextEditingValue(
            text: q,
            selection: TextSelection.collapsed(offset: q.length),
          );
        }
      }
      if (!_didSyncUiState && !nextState.isInitialLoading) {
        _didSyncUiState = true;
        setState(() {
          _selectedType = controller.selectedType;
          _sortKey = _sortKeyFromStorage(controller.sort);
          _onlyWithImages = controller.onlyWithImages;
          _onlyWithRating = controller.onlyWithRating;
        });
      }
      if (!_didRestoreScroll &&
          !nextState.isInitialLoading &&
          nextState.items.isNotEmpty) {
        _didRestoreScroll = true;
        final offset = controller.scrollOffset;
        if (offset > 0) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (!mounted || !_scrollController.hasClients) {
              return;
            }
            _scrollController.jumpTo(
                offset.clamp(0, _scrollController.position.maxScrollExtent));
          });
        }
      }
    });

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () => ref
            .read(categoryDetailControllerProvider(widget.categoryId).notifier)
            .refresh(),
        child: CustomScrollView(
          controller: _scrollController,
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: <Widget>[
            SliverAppBar(
              pinned: true,
              elevation: 0,
              title: Text(categoryName),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: _SearchFilterBar(
                  categoryName: categoryName,
                  searchController: _searchController,
                  hasActiveSearch: controller.hasActiveSearch,
                  hasActiveFilters: hasUiFilters,
                  onSearchChanged: _onSearchChanged,
                  onOpenFilter: _openFilterSheet,
                  onClearSearch: () async {
                    _searchController.clear();
                    await controller.applySearch('');
                  },
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                child: _TypeTabs(
                  selected: _selectedType,
                  onSelected: (String value) async {
                    setState(() {
                      _selectedType = value;
                    });
                    await _persistUiPreferences(controller);
                  },
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
                child: _SortBar(
                  sortKey: _sortKey,
                  onSortChanged: (_SortKey value) async {
                    setState(() {
                      _sortKey = value;
                    });
                    await _persistUiPreferences(controller);
                  },
                  productCount: state.meta?.total,
                ),
              ),
            ),
            if (state.isInitialLoading && state.items.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator()),
              )
            else if (state.errorMessage != null && state.items.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _CategoryProductsError(
                  message: state.errorMessage!,
                  onRetry: () => ref
                      .read(categoryDetailControllerProvider(widget.categoryId)
                          .notifier)
                      .loadFirstPage(),
                ),
              )
            else if (visibleItems.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _CategoryProductsEmpty(
                  categoryName: categoryName,
                  isSearch: controller.hasActiveSearch || hasUiFilters,
                ),
              )
            else ...<Widget>[
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                sliver: SliverList.separated(
                  itemCount: visibleItems.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final product = visibleItems[index];
                    return _CategoryProductCard(
                      product: product,
                      onTap: () {
                        final id = product.id;
                        if (id != null) {
                          context.push('/products/$id');
                        }
                      },
                    );
                  },
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                  child: _LoadMoreFooter(
                    isAppending: state.isAppending,
                    hasMore: state.hasMore,
                    errorMessage:
                        state.items.isNotEmpty ? state.errorMessage : null,
                    onRetryAppend: () => ref
                        .read(
                            categoryDetailControllerProvider(widget.categoryId)
                                .notifier)
                        .loadNextPage(),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _resolveCategoryName(WidgetRef ref, int categoryId) {
    final items =
        ref.watch(categoryListProvider).valueOrNull ?? const <CategoryDto>[];
    for (final item in items) {
      if (item.id == categoryId) {
        return item.name;
      }
    }
    return 'Category #$categoryId';
  }

  List<ProductDto> _applyUiFilters(List<ProductDto> items) {
    final filtered = items.where((product) {
      final rawType = product.productType.trim().toLowerCase();
      if (_selectedType == 'Physical' && rawType != 'physical') {
        return false;
      }
      if (_selectedType == 'Digital' && rawType != 'digital') {
        return false;
      }
      if (_selectedType == 'Manual' && rawType != 'manual') {
        return false;
      }
      if (_onlyWithImages && product.primaryImageUrl == null) {
        return false;
      }
      if (_onlyWithRating && _ratingValue(product) == null) {
        return false;
      }
      return true;
    }).toList();

    switch (_sortKey) {
      case _SortKey.relevance:
        return filtered;
      case _SortKey.priceLowHigh:
        filtered.sort(
            (a, b) => (_priceValue(a) ?? 0).compareTo(_priceValue(b) ?? 0));
      case _SortKey.priceHighLow:
        filtered.sort(
            (a, b) => (_priceValue(b) ?? 0).compareTo(_priceValue(a) ?? 0));
      case _SortKey.ratingHighLow:
        filtered.sort(
            (a, b) => (_ratingValue(b) ?? -1).compareTo(_ratingValue(a) ?? -1));
    }
    return filtered;
  }

  Future<void> _openFilterSheet() async {
    final result = await showModalBottomSheet<_FilterDraft>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return _FilterSheet(
          initialOnlyWithImages: _onlyWithImages,
          initialOnlyWithRating: _onlyWithRating,
        );
      },
    );
    if (!mounted || result == null) {
      return;
    }
    setState(() {
      _onlyWithImages = result.onlyWithImages;
      _onlyWithRating = result.onlyWithRating;
      if (result.resetAll) {
        _selectedType = 'All';
        _sortKey = _SortKey.relevance;
      }
    });
    await _persistUiPreferences(
        ref.read(categoryDetailControllerProvider(widget.categoryId).notifier));
  }

  Future<void> _persistUiPreferences(CategoryDetailController controller) {
    return controller.updateUiPreferences(
      selectedType: _selectedType,
      sort: _sortKeyToStorage(_sortKey),
      onlyWithImages: _onlyWithImages,
      onlyWithRating: _onlyWithRating,
    );
  }
}

class _SearchFilterBar extends StatelessWidget {
  const _SearchFilterBar({
    required this.categoryName,
    required this.searchController,
    required this.hasActiveSearch,
    required this.hasActiveFilters,
    required this.onSearchChanged,
    required this.onOpenFilter,
    required this.onClearSearch,
  });

  final String categoryName;
  final TextEditingController searchController;
  final bool hasActiveSearch;
  final bool hasActiveFilters;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback onOpenFilter;
  final Future<void> Function() onClearSearch;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        Expanded(
          child: TextField(
            controller: searchController,
            onChanged: onSearchChanged,
            decoration: InputDecoration(
              hintText: 'Search $categoryName...',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: hasActiveSearch
                  ? IconButton(
                      onPressed: onClearSearch,
                      icon: const Icon(Icons.close_rounded),
                    )
                  : null,
            ),
          ),
        ),
        const SizedBox(width: 10),
        SizedBox(
          height: 48,
          child: FilledButton.tonalIcon(
            onPressed: onOpenFilter,
            icon: const Icon(Icons.tune_rounded, size: 18),
            label: const Text('Filter'),
            style: FilledButton.styleFrom(
              foregroundColor: cs.onSurface,
              backgroundColor: hasActiveFilters
                  ? cs.primaryContainer
                  : cs.surfaceContainerHigh,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ),
      ],
    );
  }
}

class _TypeTabs extends StatelessWidget {
  const _TypeTabs({
    required this.selected,
    required this.onSelected,
  });

  final String selected;
  final ValueChanged<String> onSelected;

  static const List<String> _tabs = <String>[
    'All',
    'Physical',
    'Digital',
    'Manual'
  ];

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return SizedBox(
      height: 38,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _tabs.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final label = _tabs[index];
          final isSelected = label == selected;
          return InkWell(
            onTap: () => onSelected(label),
            borderRadius: BorderRadius.circular(999),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: isSelected ? cs.primaryContainer : cs.surface,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(
                  color: isSelected
                      ? cs.primary.withValues(alpha: 0.55)
                      : cs.outlineVariant,
                ),
              ),
              child: Text(
                label,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: isSelected ? cs.primary : cs.onSurfaceVariant,
                    ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _SortBar extends StatelessWidget {
  const _SortBar({
    required this.sortKey,
    required this.onSortChanged,
    required this.productCount,
  });

  final _SortKey sortKey;
  final ValueChanged<_SortKey> onSortChanged;
  final int? productCount;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        Text(
          'Sort by:',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.w700,
                color: cs.onSurfaceVariant,
              ),
        ),
        const SizedBox(width: 10),
        _SortPill(
          label: _sortLabel(sortKey),
          isPrimary: true,
          onTap: () => _openSortMenu(context),
          trailing: const Icon(Icons.keyboard_arrow_down_rounded, size: 16),
        ),
        const SizedBox(width: 8),
        _SortPill(
          label: 'Price',
          isPrimary: sortKey == _SortKey.priceLowHigh ||
              sortKey == _SortKey.priceHighLow,
          onTap: () => onSortChanged(
            sortKey == _SortKey.priceLowHigh
                ? _SortKey.priceHighLow
                : _SortKey.priceLowHigh,
          ),
        ),
        const SizedBox(width: 8),
        _SortPill(
          label: 'Rating',
          isPrimary: sortKey == _SortKey.ratingHighLow,
          onTap: () => onSortChanged(_SortKey.ratingHighLow),
        ),
        const Spacer(),
        if (productCount != null)
          Text(
            '$productCount',
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: cs.onSurfaceVariant,
                ),
          ),
      ],
    );
  }

  Future<void> _openSortMenu(BuildContext context) async {
    final selected = await showMenu<_SortKey>(
      context: context,
      position: const RelativeRect.fromLTRB(80, 210, 20, 0),
      items: const <PopupMenuEntry<_SortKey>>[
        PopupMenuItem<_SortKey>(
            value: _SortKey.relevance, child: Text('Relevance')),
        PopupMenuItem<_SortKey>(
            value: _SortKey.priceLowHigh, child: Text('Price: Low to High')),
        PopupMenuItem<_SortKey>(
            value: _SortKey.priceHighLow, child: Text('Price: High to Low')),
        PopupMenuItem<_SortKey>(
            value: _SortKey.ratingHighLow, child: Text('Rating: High to Low')),
      ],
    );
    if (selected != null) {
      onSortChanged(selected);
    }
  }
}

class _SortPill extends StatelessWidget {
  const _SortPill({
    required this.label,
    this.onTap,
    this.trailing,
    this.isPrimary = false,
  });

  final String label;
  final VoidCallback? onTap;
  final Widget? trailing;
  final bool isPrimary;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: isPrimary
              ? cs.primaryContainer.withValues(alpha: 0.6)
              : cs.surface,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: isPrimary
                ? cs.primary.withValues(alpha: 0.35)
                : cs.outlineVariant,
          ),
        ),
        child: Row(
          children: <Widget>[
            Text(
              label,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: isPrimary ? cs.primary : cs.onSurface,
                  ),
            ),
            if (trailing != null) ...<Widget>[
              const SizedBox(width: 2),
              trailing!,
            ],
          ],
        ),
      ),
    );
  }
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({
    required this.initialOnlyWithImages,
    required this.initialOnlyWithRating,
  });

  final bool initialOnlyWithImages;
  final bool initialOnlyWithRating;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late bool _onlyWithImages = widget.initialOnlyWithImages;
  late bool _onlyWithRating = widget.initialOnlyWithRating;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Row(
              children: <Widget>[
                Text('Filters',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800)),
                const Spacer(),
                TextButton(
                  onPressed: () {
                    Navigator.of(context).pop(const _FilterDraft(
                      onlyWithImages: false,
                      onlyWithRating: false,
                      resetAll: true,
                    ));
                  },
                  child: const Text('Reset all'),
                ),
              ],
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _onlyWithImages,
              onChanged: (value) => setState(() => _onlyWithImages = value),
              title: const Text('Only products with images'),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _onlyWithRating,
              onChanged: (value) => setState(() => _onlyWithRating = value),
              title: const Text('Only products with ratings'),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () {
                  Navigator.of(context).pop(_FilterDraft(
                    onlyWithImages: _onlyWithImages,
                    onlyWithRating: _onlyWithRating,
                    resetAll: false,
                  ));
                },
                child: const Text('Apply filters'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FilterDraft {
  const _FilterDraft({
    required this.onlyWithImages,
    required this.onlyWithRating,
    required this.resetAll,
  });

  final bool onlyWithImages;
  final bool onlyWithRating;
  final bool resetAll;
}

class _CategoryProductCard extends StatelessWidget {
  const _CategoryProductCard({
    required this.product,
    required this.onTap,
  });

  final ProductDto product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final rating = _ratingLabel(product);
    final hasRating = rating != null;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: cs.surface,
            borderRadius: BorderRadius.circular(18),
            border:
                Border.all(color: cs.outlineVariant.withValues(alpha: 0.32)),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: cs.shadow.withValues(alpha: 0.05),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Row(
            children: <Widget>[
              _Thumb(imageUrl: product.primaryImageUrl),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      product.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        height: 1.15,
                        letterSpacing: -0.1,
                      ),
                    ),
                    const SizedBox(height: 9),
                    Row(
                      children: <Widget>[
                        Text(
                          product.priceLabel,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: cs.primary,
                          ),
                        ),
                        if (hasRating) ...<Widget>[
                          const SizedBox(width: 10),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFF7ED),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: <Widget>[
                                const Icon(Icons.star_rounded,
                                    size: 14, color: Color(0xFFF59E0B)),
                                const SizedBox(width: 4),
                                Text(
                                  rating,
                                  style: theme.textTheme.labelMedium?.copyWith(
                                    color: const Color(0xFFB45309),
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Thumb extends StatelessWidget {
  const _Thumb({required this.imageUrl});

  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: SizedBox(
        width: 86,
        height: 86,
        child: imageUrl == null
            ? Container(
                color: cs.surfaceContainerHighest,
                alignment: Alignment.center,
                child:
                    Icon(Icons.image_not_supported_outlined, color: cs.outline),
              )
            : Image.network(
                imageUrl!,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => Container(
                  color: cs.surfaceContainerHighest,
                  alignment: Alignment.center,
                  child: Icon(Icons.broken_image_outlined, color: cs.outline),
                ),
              ),
      ),
    );
  }
}

class _CategoryProductsEmpty extends StatelessWidget {
  const _CategoryProductsEmpty({
    required this.categoryName,
    required this.isSearch,
  });

  final String categoryName;
  final bool isSearch;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.inventory_2_outlined, size: 46),
            const SizedBox(height: 10),
            Text(
              isSearch ? 'No matches.' : 'No products yet.',
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium,
            ),
          ],
        ),
      ),
    );
  }
}

class _CategoryProductsError extends StatelessWidget {
  const _CategoryProductsError({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.error_outline, size: 46),
            const SizedBox(height: 10),
            Text('Load failed', style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

class _LoadMoreFooter extends StatelessWidget {
  const _LoadMoreFooter({
    required this.isAppending,
    required this.hasMore,
    required this.errorMessage,
    required this.onRetryAppend,
  });

  final bool isAppending;
  final bool hasMore;
  final String? errorMessage;
  final VoidCallback onRetryAppend;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    if (isAppending) {
      return const Center(child: CircularProgressIndicator());
    }
    if (errorMessage != null && errorMessage!.isNotEmpty) {
      return Center(
        child: Column(
          children: <Widget>[
            Text(
              errorMessage!,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(color: cs.error),
            ),
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: onRetryAppend,
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
            ),
          ],
        ),
      );
    }
    if (!hasMore) {
      return Center(
        child: Text(
          'End of list.',
          style:
              theme.textTheme.bodySmall?.copyWith(color: cs.onSurfaceVariant),
        ),
      );
    }
    return const SizedBox.shrink();
  }
}

String? _ratingLabel(ProductDto product) {
  final candidates = <dynamic>[
    product.raw['rating'],
    product.raw['rating_avg'],
    product.raw['average_rating'],
  ];
  for (final value in candidates) {
    if (value == null) {
      continue;
    }
    if (value is num) {
      return value.toStringAsFixed(1);
    }
    final parsed = double.tryParse(value.toString());
    if (parsed != null) {
      return parsed.toStringAsFixed(1);
    }
  }
  return null;
}

double? _ratingValue(ProductDto product) {
  final text = _ratingLabel(product);
  if (text == null) {
    return null;
  }
  return double.tryParse(text);
}

double? _priceValue(ProductDto product) {
  final raw = product.raw['base_price'] ??
      product.raw['price'] ??
      product.raw['amount'];
  if (raw == null) {
    return null;
  }
  if (raw is num) {
    return raw.toDouble();
  }
  return double.tryParse(raw.toString());
}

String _sortLabel(_SortKey key) {
  switch (key) {
    case _SortKey.relevance:
      return 'Relevance';
    case _SortKey.priceLowHigh:
      return 'Price: Low';
    case _SortKey.priceHighLow:
      return 'Price: High';
    case _SortKey.ratingHighLow:
      return 'Rating';
  }
}

String _sortKeyToStorage(_SortKey key) {
  switch (key) {
    case _SortKey.relevance:
      return 'relevance';
    case _SortKey.priceLowHigh:
      return 'price_low_high';
    case _SortKey.priceHighLow:
      return 'price_high_low';
    case _SortKey.ratingHighLow:
      return 'rating_high_low';
  }
}

_SortKey _sortKeyFromStorage(String value) {
  switch (value) {
    case 'price_low_high':
      return _SortKey.priceLowHigh;
    case 'price_high_low':
      return _SortKey.priceHighLow;
    case 'rating_high_low':
      return _SortKey.ratingHighLow;
    case 'relevance':
    default:
      return _SortKey.relevance;
  }
}

enum _SortKey {
  relevance,
  priceLowHigh,
  priceHighLow,
  ratingHighLow,
}
