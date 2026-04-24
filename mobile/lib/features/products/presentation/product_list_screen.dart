import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../categories/application/category_list_provider.dart';
import '../../categories/data/category_repository.dart';
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
  Timer? _searchDebounce;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    Future<void>.microtask(
      () => ref.read(productListControllerProvider.notifier).refreshIfStale(),
    );
  }

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
    _searchDebounce?.cancel();
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

  Future<void> _submitSearch() async {
    await ref.read(productListControllerProvider.notifier).applyQuery(
          search: _searchController.text,
          categoryId: ref.read(productListControllerProvider.notifier).categoryId,
          storefrontId: null,
        );
  }

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 320), () {
      if (!mounted) {
        return;
      }
      _submitSearch();
    });
  }

  Future<void> _applyCategoryFilter(int? categoryId) async {
    await ref.read(productListControllerProvider.notifier).applyQuery(
          search: _searchController.text,
          categoryId: categoryId,
          storefrontId: null,
        );
    if (mounted && _scrollController.hasClients) {
      _scrollController.jumpTo(0);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(productListControllerProvider);
    final controller = ref.read(productListControllerProvider.notifier);
    final categoriesAsync = ref.watch(categoryListProvider);
    final activeCategoryId = controller.categoryId;
    String? activeCategoryName;
    final loadedCategories = categoriesAsync.valueOrNull ?? const <CategoryDto>[];
    if (activeCategoryId != null) {
      for (final category in loadedCategories) {
        if (category.id == activeCategoryId) {
          activeCategoryName = category.name;
          break;
        }
      }
    }

    if (_searchController.text != controller.search) {
      _searchController.value = TextEditingValue(
        text: controller.search,
        selection: TextSelection.collapsed(offset: controller.search.length),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(productListControllerProvider.notifier).refresh(),
      child: CustomScrollView(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: <Widget>[
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            sliver: SliverToBoxAdapter(
              child: _HomeTopRow(
                onNotificationsTap: () {},
                onCartTap: () => context.go('/withdrawals'),
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            sliver: SliverToBoxAdapter(
              child: _HomeSearchField(
                controller: _searchController,
                onSubmit: _submitSearch,
                onChanged: _onSearchChanged,
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
            sliver: SliverToBoxAdapter(
              child: const _EscrowPromoBanner(),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 0),
            sliver: SliverToBoxAdapter(
              child: _SectionHeading(
                title: 'Categories',
                actionLabel: 'See All',
                onActionTap: () => context.go('/categories'),
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
            sliver: SliverToBoxAdapter(
              child: _CategoriesStrip(
                categoriesAsync: categoriesAsync,
                activeCategoryId: activeCategoryId,
                onCategoryTap: _applyCategoryFilter,
              ),
            ),
          ),
          if (activeCategoryId != null)
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              sliver: SliverToBoxAdapter(
                child: _ActiveCategoryFilterPill(
                  label: activeCategoryName ?? 'Category #$activeCategoryId',
                  onClear: () => _applyCategoryFilter(null),
                ),
              ),
            ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
            sliver: SliverToBoxAdapter(
              child: _SectionHeading(
                title: 'Featured Products',
                actionLabel: 'See All',
                onActionTap: () => _scrollController.animateTo(
                  0,
                  duration: const Duration(milliseconds: 250),
                  curve: Curves.easeOut,
                ),
              ),
            ),
          ),
          if (state.isInitialLoading && state.items.isEmpty)
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
              sliver: const _HomeProductSkeletonGrid(),
            )
          else if (state.errorMessage != null && state.items.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: _CatalogErrorState(
                message: state.errorMessage!,
                onRetry: () => ref.read(productListControllerProvider.notifier).loadFirstPage(),
              ),
            )
          else if (state.items.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: _CatalogEmptyState(hasActiveQuery: controller.hasActiveQuery),
            )
          else ...<Widget>[
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              sliver: SliverGrid(
                delegate: SliverChildBuilderDelegate(
                  (context, index) {
                    final product = state.items[index];
                    return _HomeProductCard(
                      product: product,
                      onTap: () {
                        final id = product.id;
                        if (id != null) {
                          context.push('/products/$id');
                        }
                      },
                    );
                  },
                  childCount: state.items.length,
                ),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 0.75,
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                child: _LoadMoreFooter(
                  isAppending: state.isAppending,
                  hasMore: state.hasMore,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _HomeTopRow extends StatelessWidget {
  const _HomeTopRow({
    required this.onNotificationsTap,
    required this.onCartTap,
  });

  final VoidCallback onNotificationsTap;
  final VoidCallback onCartTap;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return SafeArea(
      bottom: false,
      child: Row(
      children: <Widget>[
        Icon(Icons.location_on_outlined, color: cs.primary, size: 20),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            'Dhaka, Bangladesh',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
        ),
        IconButton(
          onPressed: onNotificationsTap,
          icon: const Icon(Icons.notifications_none_outlined),
        ),
        Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
            IconButton(
              onPressed: onCartTap,
              icon: const Icon(Icons.shopping_cart_outlined),
            ),
            Positioned(
              right: 8,
              top: 8,
              child: Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  color: Colors.red,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
          ],
        ),
      ],
      ),
    );
  }
}

class _HomeSearchField extends StatelessWidget {
  const _HomeSearchField({
    required this.controller,
    required this.onSubmit,
    required this.onChanged,
  });

  final TextEditingController controller;
  final Future<void> Function() onSubmit;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return TextField(
      controller: controller,
      textInputAction: TextInputAction.search,
      onChanged: onChanged,
      onSubmitted: (_) => onSubmit(),
      decoration: InputDecoration(
        hintText: 'Search products, categories...',
        prefixIcon: const Icon(Icons.search),
        suffixIcon: IconButton(
          onPressed: onSubmit,
          icon: Icon(Icons.radio_button_unchecked, color: cs.outline),
        ),
      ),
    );
  }
}

class _EscrowPromoBanner extends StatelessWidget {
  const _EscrowPromoBanner();

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      height: 120,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: const LinearGradient(
          colors: <Color>[Color(0xFF0B1A60), Color(0xFF102A95)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: <Widget>[
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  Text(
                    'Secure Trading\nwith Escrow',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          height: 1.1,
                        ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Your money is safe with us!',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: Colors.white.withValues(alpha: 0.85),
                        ),
                  ),
                ],
              ),
            ),
            Container(
              width: 66,
              height: 66,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Icon(Icons.shield_outlined, color: cs.tertiaryContainer, size: 34),
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({
    required this.title,
    required this.actionLabel,
    required this.onActionTap,
  });

  final String title;
  final String actionLabel;
  final VoidCallback onActionTap;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
        ),
        TextButton(
          onPressed: onActionTap,
          child: Text(
            actionLabel,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(color: cs.primary),
          ),
        ),
      ],
    );
  }
}

class _CategoriesStrip extends StatelessWidget {
  const _CategoriesStrip({
    required this.categoriesAsync,
    required this.activeCategoryId,
    required this.onCategoryTap,
  });

  final AsyncValue<List<CategoryDto>> categoriesAsync;
  final int? activeCategoryId;
  final ValueChanged<int?> onCategoryTap;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final categories = categoriesAsync.valueOrNull ?? const <CategoryDto>[];
    final hasData = categories.isNotEmpty;
    return SizedBox(
      height: 92,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: hasData ? categories.length + 1 : 5,
        separatorBuilder: (_, __) => const SizedBox(width: 14),
        itemBuilder: (context, index) {
          final isLoading = !hasData;
          final isAll = hasData && index == 0;
          final item = hasData && !isAll ? categories[index - 1] : null;
          final label = isAll ? 'All' : (item?.name ?? 'Loading');
          final isSelected = isAll
              ? activeCategoryId == null
              : item != null && activeCategoryId == item.id;
          return Column(
            children: <Widget>[
              InkWell(
                borderRadius: BorderRadius.circular(999),
                onTap: isLoading
                    ? null
                    : () => onCategoryTap(isAll ? null : item?.id),
                child: Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    color: isLoading
                        ? cs.surfaceContainerHighest
                        : isAll
                            ? const Color(0xFFE9E8FF)
                            : _categoryTint(item!.name),
                    borderRadius: BorderRadius.circular(999),
                    border: isSelected
                        ? Border.all(color: cs.primary, width: 1.5)
                        : null,
                  ),
                  child: isLoading
                      ? Icon(Icons.more_horiz, color: cs.outline)
                      : Icon(
                          isAll ? Icons.grid_view_rounded : _categoryIcon(item!.name),
                          color: cs.primary,
                        ),
                ),
              ),
              const SizedBox(height: 8),
              SizedBox(
                width: 72,
                child: Text(
                  label,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ActiveCategoryFilterPill extends StatelessWidget {
  const _ActiveCategoryFilterPill({
    required this.label,
    required this.onClear,
  });

  final String label;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 6, 6, 6),
        decoration: BoxDecoration(
          color: cs.primaryContainer.withValues(alpha: 0.7),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: cs.primary.withValues(alpha: 0.28)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(Icons.tune, size: 16, color: cs.primary),
            const SizedBox(width: 6),
            Text(
              'Category: $label',
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: cs.onPrimaryContainer,
                  ),
            ),
            const SizedBox(width: 6),
            InkWell(
              onTap: onClear,
              borderRadius: BorderRadius.circular(999),
              child: Padding(
                padding: const EdgeInsets.all(2),
                child: Icon(Icons.close_rounded, size: 16, color: cs.primary),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeProductCard extends StatelessWidget {
  const _HomeProductCard({
    required this.product,
    required this.onTap,
  });

  final ProductDto product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final imageUrl = product.primaryImageUrl;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Ink(
          decoration: BoxDecoration(
            color: cs.surface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.5)),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: cs.shadow.withValues(alpha: 0.06),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: ClipRRect(
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                  child: imageUrl == null
                      ? Container(
                          color: cs.surfaceContainerHighest,
                          alignment: Alignment.center,
                          child: Icon(Icons.image_not_supported_outlined, color: cs.outline),
                        )
                      : Image.network(
                          imageUrl,
                          fit: BoxFit.cover,
                          width: double.infinity,
                          errorBuilder: (_, __, ___) => Container(
                            color: cs.surfaceContainerHighest,
                            alignment: Alignment.center,
                            child: Icon(Icons.broken_image_outlined, color: cs.outline),
                          ),
                        ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 10, 10, 4),
                child: Text(
                  product.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10),
                child: Text(
                  'Home',
                  style: theme.textTheme.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 6, 10, 10),
                child: Row(
                  children: <Widget>[
                    Text(
                      product.priceLabel,
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const Spacer(),
                    const Icon(Icons.star, size: 14, color: Color(0xFFFFB800)),
                    const SizedBox(width: 4),
                    Text('4.8', style: theme.textTheme.bodySmall),
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

class _HomeProductSkeletonGrid extends StatelessWidget {
  const _HomeProductSkeletonGrid();

  @override
  Widget build(BuildContext context) {
    return SliverGrid(
      delegate: SliverChildBuilderDelegate(
        (context, _) => const _HomeProductSkeletonCard(),
        childCount: 6,
      ),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.75,
      ),
    );
  }
}

class _HomeProductSkeletonCard extends StatelessWidget {
  const _HomeProductSkeletonCard();

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.5)),
        color: cs.surface,
      ),
      child: Column(
        children: <Widget>[
          Expanded(
            child: Container(
              decoration: BoxDecoration(
                color: cs.surfaceContainerHighest,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              children: <Widget>[
                Container(height: 12, color: cs.surfaceContainerHighest),
                const SizedBox(height: 6),
                Container(height: 10, color: cs.surfaceContainerHighest),
                const SizedBox(height: 8),
                Container(height: 14, color: cs.surfaceContainerHighest),
              ],
            ),
          ),
        ],
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
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
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
            Icon(Icons.inventory_2_outlined, size: 52, color: Theme.of(context).colorScheme.outline),
            const SizedBox(height: 14),
            Text(
              hasActiveQuery ? 'No products match your filters.' : 'No products available yet.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
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
            const Icon(Icons.error_outline, size: 46),
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

IconData _categoryIcon(String label) {
  final normalized = label.toLowerCase();
  if (normalized.contains('electronic')) return Icons.computer_outlined;
  if (normalized.contains('fashion')) return Icons.checkroom_outlined;
  if (normalized.contains('digital')) return Icons.tablet_android_outlined;
  if (normalized.contains('home')) return Icons.home_outlined;
  if (normalized.contains('book')) return Icons.menu_book_outlined;
  if (normalized.contains('sport')) return Icons.sports_basketball_outlined;
  if (normalized.contains('auto')) return Icons.directions_car_outlined;
  if (normalized.contains('beauty') || normalized.contains('health')) return Icons.spa_outlined;
  return Icons.category_outlined;
}

Color _categoryTint(String label) {
  final normalized = label.toLowerCase();
  if (normalized.contains('electronic')) return const Color(0xFFEAF1FF);
  if (normalized.contains('fashion')) return const Color(0xFFFFEDEE);
  if (normalized.contains('digital')) return const Color(0xFFEFF5FF);
  if (normalized.contains('home')) return const Color(0xFFFFF3E6);
  if (normalized.contains('book')) return const Color(0xFFEDEBFF);
  if (normalized.contains('sport')) return const Color(0xFFEAFCEF);
  if (normalized.contains('auto')) return const Color(0xFFFFEAEA);
  if (normalized.contains('beauty') || normalized.contains('health')) return const Color(0xFFF2EAFF);
  return const Color(0xFFF0F2F6);
}
