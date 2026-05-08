import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../shell/presentation/buyer_page_header.dart';
import '../application/category_list_provider.dart';
import '../data/category_repository.dart';

class CategoryListScreen extends ConsumerWidget {
  const CategoryListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final categoriesAsync = ref.watch(categoryListProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFD),
      body: SafeArea(
        bottom: false,
        child: Column(
          children: <Widget>[
            const Padding(
              padding: EdgeInsets.fromLTRB(10, 12, 10, 0),
              child: BuyerPageHeader(
                title: 'Categories',
                showSearch: false,
                showFilter: false,
              ),
            ),
            const SizedBox(height: 12),
            Expanded(
              child: categoriesAsync.when(
                data: (items) {
                  if (items.isEmpty) {
                    return const _CategoryEmptyState();
                  }
                  return ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                    itemCount: items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final category = items[index];
                      return _CategoryTile(
                        category: category,
                        onTap: () {
                          final id = category.id;
                          if (id != null) {
                            context.push('/categories/$id');
                          }
                        },
                      );
                    },
                  );
                },
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => _CategoryErrorState(
                  message: error.toString(),
                  onRetry: () => ref.invalidate(categoryListProvider),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CategoryTile extends StatelessWidget {
  const _CategoryTile({
    required this.category,
    required this.onTap,
  });

  final CategoryDto category;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Ink(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: cs.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
        ),
        child: Row(
          children: <Widget>[
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: _categoryTint(category.name),
                borderRadius: BorderRadius.circular(12),
              ),
              alignment: Alignment.center,
              child: Icon(_categoryIcon(category.name), color: cs.primary),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    category.name,
                    style: theme.textTheme.titleSmall
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${category.productsCount} Products',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: cs.onSurfaceVariant),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: cs.outline),
          ],
        ),
      ),
    );
  }
}

class _CategoryErrorState extends StatelessWidget {
  const _CategoryErrorState({
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
            const Icon(Icons.category_outlined, size: 40),
            const SizedBox(height: 10),
            Text('Failed to load categories',
                style: theme.textTheme.titleMedium),
            const SizedBox(height: 6),
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

class _CategoryEmptyState extends StatelessWidget {
  const _CategoryEmptyState();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.grid_view_outlined, size: 40),
            const SizedBox(height: 10),
            Text('No categories available', style: theme.textTheme.titleMedium),
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
  if (normalized.contains('beauty') || normalized.contains('health')) {
    return Icons.spa_outlined;
  }
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
  if (normalized.contains('beauty') || normalized.contains('health')) {
    return const Color(0xFFF2EAFF);
  }
  return const Color(0xFFF0F2F6);
}
