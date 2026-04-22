import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/product_list_controller.dart';
import '../data/product_repository.dart';

class ProductListScreen extends ConsumerStatefulWidget {
  const ProductListScreen({super.key});

  @override
  ConsumerState<ProductListScreen> createState() => _ProductListScreenState();
}

class _ProductListScreenState extends ConsumerState<ProductListScreen> {
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();
  int? _categoryId;
  int? _storefrontId;
  _SortOption _sortOption = _SortOption.latest;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
      () async {
        await ref.read(productListControllerProvider.notifier).initialize();
        final saved = ref.read(productListControllerProvider.notifier).scrollOffset;
        if (saved > 0 && mounted) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (_scrollController.hasClients) {
              _scrollController.jumpTo(saved.clamp(0, _scrollController.position.maxScrollExtent));
            }
          });
        }
      },
    );
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _onScroll() {
    ref.read(productListControllerProvider.notifier).updateScrollOffset(_scrollController.offset);
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      ref.read(productListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(productListControllerProvider);
    final controller = ref.read(productListControllerProvider.notifier);
    final isWide = MediaQuery.sizeOf(context).width >= 680;
    final crossAxisCount = isWide ? 3 : 2;

    if (_searchController.text != controller.search) {
      _searchController.text = controller.search;
    }
    _categoryId = controller.categoryId;
    _storefrontId = controller.storefrontId;
    final matchedSort = _SortOption.values.where((e) => e.name == controller.sort);
    _sortOption = matchedSort.isEmpty ? _SortOption.latest : matchedSort.first;

    if (state.isInitialLoading && state.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.errorMessage != null && state.items.isEmpty) {
      return _CatalogErrorState(
        message: state.errorMessage!,
        onRetry: () => ref.read(productListControllerProvider.notifier).loadFirstPage(),
      );
    }

    if (state.items.isEmpty) {
      return _CatalogEmptyState(hasActiveQuery: controller.hasActiveQuery);
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(productListControllerProvider.notifier).refresh(),
      child: CustomScrollView(
        controller: _scrollController,
        slivers: <Widget>[
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    'Product Catalog',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 12),
                  _SearchFilterBar(
                    searchController: _searchController,
                    onSubmitSearch: () async {
                      await ref.read(productListControllerProvider.notifier).applyQuery(
                            search: _searchController.text,
                            categoryId: _categoryId,
                            storefrontId: _storefrontId,
                          );
                    },
                    onOpenFilters: () async {
                      final result = await _showFiltersSheet(
                        context: context,
                        initialCategoryId: _categoryId,
                        initialStorefrontId: _storefrontId,
                      );
                      if (result == null) {
                        return;
                      }
                      setState(() {
                        _categoryId = result.categoryId;
                        _storefrontId = result.storefrontId;
                      });
                      await ref.read(productListControllerProvider.notifier).applyQuery(
                            search: _searchController.text,
                            categoryId: _categoryId,
                            storefrontId: _storefrontId,
                          );
                    },
                    sortOption: _sortOption,
                    onSortChanged: (value) {
                      setState(() => _sortOption = value);
                      ref.read(productListControllerProvider.notifier).applyQuery(
                            search: _searchController.text,
                            categoryId: _categoryId,
                            storefrontId: _storefrontId,
                            sort: value.name,
                          );
                      if (value != _SortOption.latest) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text('Backend sort parameter is not available yet.'),
                          ),
                        );
                      }
                    },
                  ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton.icon(
                      onPressed: () => ref.read(productListControllerProvider.notifier).clearPersistedState(),
                      icon: const Icon(Icons.restart_alt),
                      label: const Text('Reset'),
                    ),
                  ),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: <Widget>[
                      if (controller.search.isNotEmpty)
                        _FilterChipLabel(label: 'Search: ${controller.search}'),
                      if (controller.categoryId != null)
                        _FilterChipLabel(label: 'Category #${controller.categoryId}'),
                      if (controller.storefrontId != null)
                        _FilterChipLabel(label: 'Storefront #${controller.storefrontId}'),
                    ],
                  ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            sliver: SliverGrid(
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  final item = state.items[index];
                  return _ProductCard(
                    product: item,
                    onTap: () {
                      final id = item.id;
                      if (id != null) {
                        context.push('/products/$id');
                      }
                    },
                  );
                },
                childCount: state.items.length,
              ),
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: crossAxisCount,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 0.68,
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              child: _LoadMoreFooter(
                isAppending: state.isAppending,
                hasMore: state.hasMore,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({
    required this.product,
    required this.onTap,
  });

  final ProductDto product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final imageUrl = product.primaryImageUrl;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Ink(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest.withOpacity(0.35),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: Theme.of(context).colorScheme.outlineVariant.withOpacity(0.45),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: ClipRRect(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                child: imageUrl == null
                    ? Container(
                        color: Theme.of(context).colorScheme.surfaceContainer,
                        alignment: Alignment.center,
                        child: const Icon(Icons.image_not_supported_outlined),
                      )
                    : Image.network(
                        imageUrl,
                        fit: BoxFit.cover,
                        width: double.infinity,
                        errorBuilder: (_, __, ___) => Container(
                          color: Theme.of(context).colorScheme.surfaceContainer,
                          alignment: Alignment.center,
                          child: const Icon(Icons.broken_image_outlined),
                        ),
                      ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
              child: Text(
                product.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Text(
                product.shortInfo,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
            const Spacer(),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
              child: Text(
                product.priceLabel,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ),
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
  });

  final bool isAppending;
  final bool hasMore;

  @override
  Widget build(BuildContext context) {
    if (isAppending) {
      return const Center(child: CircularProgressIndicator());
    }
    if (!hasMore) {
      return Center(
        child: Text(
          'You have reached the end.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
      );
    }
    return const SizedBox.shrink();
  }
}

class _CatalogEmptyState extends StatelessWidget {
  const _CatalogEmptyState({
    required this.hasActiveQuery,
  });

  final bool hasActiveQuery;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.inventory_2_outlined, size: 48),
            const SizedBox(height: 12),
            Text(
              hasActiveQuery ? 'No products match your filters.' : 'No products available yet.',
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ],
        ),
      ),
    );
  }
}

enum _SortOption { latest, priceAsc, priceDesc }

class _SearchFilterBar extends StatelessWidget {
  const _SearchFilterBar({
    required this.searchController,
    required this.onSubmitSearch,
    required this.onOpenFilters,
    required this.sortOption,
    required this.onSortChanged,
  });

  final TextEditingController searchController;
  final Future<void> Function() onSubmitSearch;
  final Future<void> Function() onOpenFilters;
  final _SortOption sortOption;
  final ValueChanged<_SortOption> onSortChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        TextField(
          controller: searchController,
          textInputAction: TextInputAction.search,
          onSubmitted: (_) => onSubmitSearch(),
          decoration: InputDecoration(
            hintText: 'Search products',
            prefixIcon: const Icon(Icons.search),
            suffixIcon: IconButton(
              onPressed: onSubmitSearch,
              icon: const Icon(Icons.arrow_forward),
            ),
            border: const OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: <Widget>[
            Expanded(
              child: OutlinedButton.icon(
                onPressed: onOpenFilters,
                icon: const Icon(Icons.tune),
                label: const Text('Filters'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: DropdownButtonFormField<_SortOption>(
                value: sortOption,
                decoration: const InputDecoration(
                  border: OutlineInputBorder(),
                  contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
                onChanged: (value) {
                  if (value != null) {
                    onSortChanged(value);
                  }
                },
                items: const <DropdownMenuItem<_SortOption>>[
                  DropdownMenuItem(
                    value: _SortOption.latest,
                    child: Text('Latest'),
                  ),
                  DropdownMenuItem(
                    value: _SortOption.priceAsc,
                    child: Text('Price low-high (soon)'),
                  ),
                  DropdownMenuItem(
                    value: _SortOption.priceDesc,
                    child: Text('Price high-low (soon)'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _FilterChipLabel extends StatelessWidget {
  const _FilterChipLabel({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Chip(label: Text(label));
  }
}

class _FilterSheetResult {
  const _FilterSheetResult({
    required this.categoryId,
    required this.storefrontId,
  });

  final int? categoryId;
  final int? storefrontId;
}

Future<_FilterSheetResult?> _showFiltersSheet({
  required BuildContext context,
  required int? initialCategoryId,
  required int? initialStorefrontId,
}) async {
  final categoryController = TextEditingController(text: initialCategoryId?.toString() ?? '');
  final storefrontController = TextEditingController(text: initialStorefrontId?.toString() ?? '');
  final result = await showModalBottomSheet<_FilterSheetResult>(
    context: context,
    isScrollControlled: true,
    builder: (context) {
      return Padding(
        padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(context).viewInsets.bottom + 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Filters', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            TextField(
              controller: categoryController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Category ID',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: storefrontController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Storefront ID',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: <Widget>[
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {
                      Navigator.of(context).pop(const _FilterSheetResult(categoryId: null, storefrontId: null));
                    },
                    child: const Text('Clear'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: () {
                      final category = int.tryParse(categoryController.text.trim());
                      final storefront = int.tryParse(storefrontController.text.trim());
                      Navigator.of(context).pop(
                        _FilterSheetResult(
                          categoryId: category,
                          storefrontId: storefront,
                        ),
                      );
                    },
                    child: const Text('Apply'),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    },
  );
  categoryController.dispose();
  storefrontController.dispose();
  return result;
}

class _CatalogErrorState extends StatelessWidget {
  const _CatalogErrorState({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.error_outline, size: 44),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}
