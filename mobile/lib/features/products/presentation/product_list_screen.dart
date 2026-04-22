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

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
      () => ref.read(productListControllerProvider.notifier).loadFirstPage(),
    );
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      ref.read(productListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(productListControllerProvider);
    final isWide = MediaQuery.sizeOf(context).width >= 680;
    final crossAxisCount = isWide ? 3 : 2;

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
      return const _CatalogEmptyState();
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(productListControllerProvider.notifier).refresh(),
      child: CustomScrollView(
        controller: _scrollController,
        slivers: <Widget>[
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            sliver: SliverToBoxAdapter(
              child: Text(
                'Product Catalog',
                style: Theme.of(context).textTheme.headlineSmall,
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
  const _CatalogEmptyState();

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
            Text('No products available yet.', style: Theme.of(context).textTheme.titleMedium),
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
