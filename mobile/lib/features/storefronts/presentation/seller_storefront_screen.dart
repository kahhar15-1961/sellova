import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/state/paginated_state.dart';
import '../../products/data/product_repository.dart';
import '../application/storefront_browse_controller.dart';

/// Browse published products for a single storefront (`storefront_id` query on catalog list).
class SellerStorefrontScreen extends ConsumerStatefulWidget {
  const SellerStorefrontScreen({
    super.key,
    required this.storefrontId,
  });

  final int storefrontId;

  @override
  ConsumerState<SellerStorefrontScreen> createState() => _SellerStorefrontScreenState();
}

class _SellerStorefrontScreenState extends ConsumerState<SellerStorefrontScreen> {
  final ScrollController _scrollController = ScrollController();
  late final TextEditingController _searchController;
  Timer? _searchDebounce;
  bool _didSyncSearchField = false;
  bool _didRestoreScroll = false;

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController();
    _scrollController.addListener(_onScroll);
  }

  @override
  void didUpdateWidget(covariant SellerStorefrontScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.storefrontId != widget.storefrontId) {
      _didSyncSearchField = false;
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
    ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier).updateScrollOffset(pos.pixels);
    if (pos.pixels >= pos.maxScrollExtent - 200) {
      ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier).loadNextPage();
    }
  }

  Future<void> _submitSearch(String value) async {
    final n = ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier);
    await n.applySearch(value);
    if (mounted) {
      _scrollController.jumpTo(0);
    }
  }

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 320), () {
      if (!mounted) {
        return;
      }
      _submitSearch(value);
    });
  }

  Future<void> _clearSearch() async {
    final n = ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier);
    _searchController.clear();
    await n.clearSearch();
    if (mounted) {
      _scrollController.jumpTo(0);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(storefrontBrowseControllerProvider(widget.storefrontId));

    ref.listen(storefrontBrowseControllerProvider(widget.storefrontId), (Object? previous, Object? next) {
      final n = ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier);
      if (next is! PaginatedState<ProductDto>) {
        return;
      }
      final s = next;
      if (!_didSyncSearchField && !s.isInitialLoading) {
        _didSyncSearchField = true;
        final q = n.search;
        if (_searchController.text != q) {
          _searchController.value = TextEditingValue(
            text: q,
            selection: TextSelection.collapsed(offset: q.length),
          );
        }
      }
      if (!_didRestoreScroll && !s.isInitialLoading && s.items.isNotEmpty) {
        _didRestoreScroll = true;
        final offset = n.scrollOffset;
        if (offset > 0) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (!mounted || !_scrollController.hasClients) {
              return;
            }
            _scrollController.jumpTo(
              offset.clamp(0, _scrollController.position.maxScrollExtent),
            );
          });
        }
      }
    });

    if (state.isInitialLoading && state.items.isEmpty) {
      return Scaffold(
        body: CustomScrollView(
          slivers: <Widget>[
            _StorefrontAppBar(storefrontId: widget.storefrontId),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: _StorefrontHeroHeader(
                  displayName: 'Storefront #${widget.storefrontId}',
                  subtitle: 'Loading products for this storefront…',
                  sellerLine: null,
                  storefrontId: widget.storefrontId,
                  productCount: null,
                  searchController: _searchController,
                  hasActiveSearch: false,
                  searchEnabled: false,
                  onSearchSubmitted: _submitSearch,
                  onSearchChanged: _onSearchChanged,
                  onClearSearch: _clearSearch,
                ),
              ),
            ),
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(child: CircularProgressIndicator()),
            ),
          ],
        ),
      );
    }

    if (state.errorMessage != null && state.items.isEmpty) {
      return Scaffold(
        body: CustomScrollView(
          slivers: <Widget>[
            _StorefrontAppBar(storefrontId: widget.storefrontId),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: _StorefrontHeroHeader(
                  displayName: 'Storefront #${widget.storefrontId}',
                  subtitle: 'Something went wrong while loading this storefront.',
                  sellerLine: null,
                  storefrontId: widget.storefrontId,
                  productCount: null,
                  searchController: _searchController,
                  hasActiveSearch: ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier).hasActiveSearch,
                  searchEnabled: true,
                  onSearchSubmitted: _submitSearch,
                  onSearchChanged: _onSearchChanged,
                  onClearSearch: _clearSearch,
                ),
              ),
            ),
            SliverFillRemaining(
              hasScrollBody: false,
              child: _StorefrontErrorState(
                message: state.errorMessage!,
                onRetry: () => ref
                    .read(storefrontBrowseControllerProvider(widget.storefrontId).notifier)
                    .loadFirstPage(),
              ),
            ),
          ],
        ),
      );
    }

    if (state.items.isEmpty) {
      final n = ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier);
      return Scaffold(
        body: CustomScrollView(
          slivers: <Widget>[
            _StorefrontAppBar(storefrontId: widget.storefrontId),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: _StorefrontHeroHeader(
                  displayName: 'Storefront #${widget.storefrontId}',
                  subtitle: 'Search or browse published listings for this storefront.',
                  sellerLine: null,
                  storefrontId: widget.storefrontId,
                  productCount: state.meta?.total,
                  searchController: _searchController,
                  hasActiveSearch: n.hasActiveSearch,
                  searchEnabled: true,
                  onSearchSubmitted: _submitSearch,
                  onSearchChanged: _onSearchChanged,
                  onClearSearch: _clearSearch,
                ),
              ),
            ),
            SliverFillRemaining(
              hasScrollBody: false,
              child: _StorefrontEmptyState(
                storefrontId: widget.storefrontId,
                isSearch: n.hasActiveSearch,
              ),
            ),
          ],
        ),
      );
    }

    final n = ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier);
    final displayName = _storefrontDisplayName(state.items) ?? 'Storefront #${widget.storefrontId}';
    final subtitle = _storefrontSubtitle(state.items, widget.storefrontId);
    final sellerLine = _sellerLine(state.items);

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(storefrontBrowseControllerProvider(widget.storefrontId).notifier).refresh(),
        child: CustomScrollView(
          controller: _scrollController,
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: <Widget>[
            _StorefrontAppBar(storefrontId: widget.storefrontId),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                child: _StorefrontHeroHeader(
                  displayName: displayName,
                  subtitle: subtitle,
                  sellerLine: sellerLine,
                  storefrontId: widget.storefrontId,
                  productCount: state.meta?.total,
                  searchController: _searchController,
                  hasActiveSearch: n.hasActiveSearch,
                  searchEnabled: true,
                  onSearchSubmitted: _submitSearch,
                  onSearchChanged: _onSearchChanged,
                  onClearSearch: _clearSearch,
                ),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              sliver: SliverList.separated(
                itemCount: state.items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  final product = state.items[index];
                  return _StorefrontProductRowCard(
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
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                child: _LoadMoreFooter(
                  isAppending: state.isAppending,
                  hasMore: state.hasMore,
                  errorMessage: state.items.isNotEmpty ? state.errorMessage : null,
                  onRetryAppend: () => ref
                      .read(storefrontBrowseControllerProvider(widget.storefrontId).notifier)
                      .loadNextPage(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StorefrontAppBar extends StatelessWidget {
  const _StorefrontAppBar({required this.storefrontId});

  final int storefrontId;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return SliverAppBar(
      pinned: true,
      leading: IconButton(
        icon: const Icon(Icons.arrow_back),
        onPressed: () => context.pop(),
      ),
      expandedHeight: 88,
      flexibleSpace: FlexibleSpaceBar(
        titlePadding: const EdgeInsetsDirectional.only(start: 48, bottom: 14),
        title: Text(
          'Storefront',
          style: theme.textTheme.titleLarge?.copyWith(
            color: cs.onSurface,
            fontWeight: FontWeight.w700,
          ),
        ),
        background: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: <Color>[
                cs.primaryContainer.withValues(alpha: 0.35),
                cs.surface,
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StorefrontHeroHeader extends StatelessWidget {
  const _StorefrontHeroHeader({
    required this.displayName,
    required this.subtitle,
    required this.sellerLine,
    required this.storefrontId,
    required this.productCount,
    required this.searchController,
    required this.hasActiveSearch,
    required this.searchEnabled,
    required this.onSearchSubmitted,
    required this.onSearchChanged,
    required this.onClearSearch,
  });

  final String displayName;
  final String subtitle;
  final String? sellerLine;
  final int storefrontId;
  final int? productCount;
  final TextEditingController searchController;
  final bool hasActiveSearch;
  final bool searchEnabled;
  final Future<void> Function(String value) onSearchSubmitted;
  final ValueChanged<String> onSearchChanged;
  final Future<void> Function() onClearSearch;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;

    return Card(
      elevation: 0,
      color: cs.surfaceContainerHighest.withValues(alpha: 0.42),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.55)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                CircleAvatar(
                  radius: 28,
                  backgroundColor: cs.primaryContainer,
                  child: Icon(Icons.storefront, color: cs.onPrimaryContainer, size: 30),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        displayName,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          height: 1.15,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        subtitle,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: cs.onSurfaceVariant,
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (sellerLine != null && sellerLine!.trim().isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              Text(
                sellerLine!.trim(),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: cs.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 8,
              children: <Widget>[
                _MetaChip(
                  icon: Icons.tag,
                  label: 'ID $storefrontId',
                ),
                if (productCount != null)
                  _MetaChip(
                    icon: Icons.inventory_2_outlined,
                    label: '$productCount listing${productCount == 1 ? '' : 's'}',
                  ),
              ],
            ),
            const SizedBox(height: 18),
            Divider(height: 1, color: cs.outlineVariant.withValues(alpha: 0.5)),
            const SizedBox(height: 14),
            Text(
              'Search this storefront',
              style: theme.textTheme.labelLarge?.copyWith(
                color: cs.onSurfaceVariant,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: searchController,
              enabled: searchEnabled,
              textInputAction: TextInputAction.search,
              onChanged: searchEnabled ? onSearchChanged : null,
              onSubmitted: searchEnabled ? (String v) => onSearchSubmitted(v) : null,
              decoration: InputDecoration(
                hintText: 'Search products in this store…',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: hasActiveSearch
                    ? IconButton(
                        tooltip: 'Clear search',
                        icon: const Icon(Icons.close),
                        onPressed: searchEnabled ? () => onClearSearch() : null,
                      )
                    : null,
                filled: true,
                fillColor: cs.surface.withValues(alpha: 0.85),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.6)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.6)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: cs.primary, width: 1.5),
                ),
              ),
            ),
            if (hasActiveSearch) ...<Widget>[
              const SizedBox(height: 10),
              TextButton(
                onPressed: searchEnabled ? () => onClearSearch() : null,
                child: const Text('Show all products'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: cs.surface.withValues(alpha: 0.75),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 16, color: cs.primary),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

class _StorefrontProductRowCard extends StatelessWidget {
  const _StorefrontProductRowCard({
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
    final meta = _productRowMetaLine(product);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            color: cs.surfaceContainerHighest.withValues(alpha: 0.35),
            border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.5)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(14),
                  child: SizedBox(
                    width: 108,
                    height: 108,
                    child: imageUrl == null || !imageUrl.toLowerCase().startsWith('http')
                        ? ColoredBox(
                            color: cs.surfaceContainerHighest,
                            child: Icon(Icons.image_not_supported_outlined, color: cs.outline, size: 36),
                          )
                        : Image.network(
                            imageUrl,
                            fit: BoxFit.cover,
                            loadingBuilder: (_, child, progress) {
                              if (progress == null) {
                                return child;
                              }
                              return ColoredBox(
                                color: cs.surfaceContainerHighest,
                                child: const Center(
                                  child: SizedBox(
                                    width: 24,
                                    height: 24,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  ),
                                ),
                              );
                            },
                            errorBuilder: (_, __, ___) => ColoredBox(
                              color: cs.surfaceContainerHighest,
                              child: Icon(Icons.broken_image_outlined, color: cs.outline, size: 36),
                            ),
                          ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        product.title,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          height: 1.2,
                        ),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        product.priceLabel,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w900,
                          color: cs.primary,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        meta,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: cs.onSurfaceVariant,
                          height: 1.3,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: cs.outline),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

String _productRowMetaLine(ProductDto product) {
  final parts = <String>[];
  final cur = (product.raw['currency'] ?? '').toString().trim().toUpperCase();
  if (cur.isNotEmpty) {
    parts.add(cur);
  }
  final status = (product.raw['status'] ?? '').toString().trim();
  if (status.isNotEmpty) {
    parts.add(_humanizeToken(status));
  }
  final pub = _shortDateFromIso(product.raw['published_at'] ?? product.raw['publishedAt']);
  if (pub != null) {
    parts.add('Listed $pub');
  }
  if (parts.isEmpty) {
    return 'Published product';
  }
  return parts.join(' · ');
}

String? _shortDateFromIso(Object? raw) {
  if (raw == null) {
    return null;
  }
  final s = raw.toString().trim();
  if (s.isEmpty) {
    return null;
  }
  final d = DateTime.tryParse(s);
  if (d == null) {
    return null;
  }
  final l = d.toLocal();
  final y = l.year.toString().padLeft(4, '0');
  final m = l.month.toString().padLeft(2, '0');
  final day = l.day.toString().padLeft(2, '0');
  return '$y-$m-$day';
}

String? _storefrontDisplayName(List<ProductDto> items) {
  for (final p in items) {
    for (final k in <String>['storefront_title', 'store_title', 'store_name', 'storefront_name']) {
      final v = p.raw[k];
      if (v != null && v.toString().trim().isNotEmpty) {
        return v.toString().trim();
      }
    }
  }
  return null;
}

String _storefrontSubtitle(List<ProductDto> items, int storefrontId) {
  final desc = _firstStorefrontDescription(items);
  if (desc != null && desc.isNotEmpty) {
    if (desc.length > 180) {
      return '${desc.substring(0, 177)}…';
    }
    return desc;
  }
  return 'Published listings for storefront ID $storefrontId. '
      'Store titles are shown when the API includes them on product rows.';
}

String? _firstStorefrontDescription(List<ProductDto> items) {
  for (final k in <String>['storefront_description', 'store_description', 'storefront_subtitle']) {
    for (final p in items) {
      final v = p.raw[k];
      if (v != null && v.toString().trim().isNotEmpty) {
        return v.toString().trim();
      }
    }
  }
  return null;
}

String? _sellerLine(List<ProductDto> items) {
  for (final p in items) {
    final label = p.sellerLabel;
    if (label != 'Seller unavailable') {
      return label;
    }
  }
  final profile = items.isNotEmpty ? items.first.sellerProfileId : null;
  if (profile != null) {
    return 'Seller profile #$profile';
  }
  return null;
}

String _humanizeToken(String raw) {
  final s = raw.replaceAll('_', ' ').trim();
  if (s.isEmpty) {
    return raw;
  }
  return s.split(' ').map((w) {
    if (w.isEmpty) {
      return w;
    }
    return '${w[0].toUpperCase()}${w.length > 1 ? w.substring(1).toLowerCase() : ''}';
  }).join(' ');
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
    if (isAppending) {
      return const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()));
    }
    if (errorMessage != null) {
      return Column(
        children: <Widget>[
          Text(
            errorMessage!,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
          TextButton(onPressed: onRetryAppend, child: const Text('Retry loading more')),
        ],
      );
    }
    if (!hasMore) {
      return Center(
        child: Text(
          'End of storefront catalog.',
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
        ),
      );
    }
    return const SizedBox.shrink();
  }
}

class _StorefrontErrorState extends StatelessWidget {
  const _StorefrontErrorState({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          const Icon(Icons.error_outline, size: 48),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton(onPressed: onRetry, child: const Text('Retry')),
        ],
      ),
    );
  }
}

class _StorefrontEmptyState extends StatelessWidget {
  const _StorefrontEmptyState({
    required this.storefrontId,
    required this.isSearch,
  });

  final int storefrontId;
  final bool isSearch;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Icon(isSearch ? Icons.search_off_outlined : Icons.inventory_2_outlined, size: 48),
          const SizedBox(height: 12),
          Text(
            isSearch
                ? 'No products match your search in this storefront.'
                : 'No published products for storefront #$storefrontId.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          Text(
            isSearch
                ? 'Try different keywords or tap “Show all products” above.'
                : 'Try another storefront ID or check back after the seller publishes inventory.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
          ),
        ],
      ),
    );
  }
}
