import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/product_detail_provider.dart';
import '../data/product_repository.dart';

class ProductDetailScreen extends ConsumerWidget {
  const ProductDetailScreen({
    super.key,
    required this.productId,
  });

  final int productId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(productDetailProvider(productId));
    return Scaffold(
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _ProductDetailError(
          message: error.toString(),
          onRetry: () => ref.refresh(productDetailProvider(productId)),
        ),
        data: (product) => _ProductDetailContent(product: product),
      ),
    );
  }
}

class _ProductDetailContent extends StatelessWidget {
  const _ProductDetailContent({required this.product});

  final ProductDto product;

  @override
  Widget build(BuildContext context) {
    final images = product.imageUrls;
    return CustomScrollView(
      slivers: <Widget>[
        SliverAppBar(
          pinned: true,
          expandedHeight: 280,
          flexibleSpace: FlexibleSpaceBar(
            background: images.isEmpty
                ? Container(
                    color: Theme.of(context).colorScheme.surfaceContainerHighest,
                    child: const Center(child: Icon(Icons.image_outlined, size: 54)),
                  )
                : PageView.builder(
                    itemCount: images.length,
                    itemBuilder: (context, index) {
                      final imageUrl = images[index];
                      return Image.network(
                        imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => Container(
                          color: Theme.of(context).colorScheme.surfaceContainerHighest,
                          child: const Center(child: Icon(Icons.broken_image_outlined, size: 54)),
                        ),
                      );
                    },
                  ),
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.all(16),
          sliver: SliverList.list(
            children: <Widget>[
              Text(
                product.title,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                product.priceLabel,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: 16),
              Text(
                product.description.isEmpty
                    ? 'No product description available.'
                    : product.description,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 20),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text('Seller', style: Theme.of(context).textTheme.titleSmall),
                      const SizedBox(height: 6),
                      Text(product.sellerLabel),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ProductDetailError extends StatelessWidget {
  const _ProductDetailError({
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
            const Icon(Icons.error_outline, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
