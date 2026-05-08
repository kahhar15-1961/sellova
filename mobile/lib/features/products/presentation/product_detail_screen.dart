import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/api_exception.dart';
import '../../cart/application/cart_controller.dart';
import '../../cart/application/checkout_draft_controller.dart';
import '../../cart/domain/cart_line.dart';
import '../../profile/application/seller_profile_controller.dart';
import '../../profile/application/wishlist_controller.dart';
import '../application/product_detail_provider.dart';
import '../data/product_repository.dart';

/// Reference-aligned palette (does not depend on seed drift).
const Color _kNavy = Color(0xFF0B1A60);
const Color _kSubtitle = Color(0xFF64748B);
const Color _kBestSellerBg = Color(0xFFFBEFD8);
const Color _kBestSellerText = Color(0xFFB45309);
const Color _kDiscountBg = Color(0xFFFFE4E6);
const Color _kDiscountText = Color(0xFFBE123C);
const Color _kDigitalChipBg = Color(0xFFEDE9FE);
const Color _kDigitalChipFg = Color(0xFF5B21B6);
const Color _kEscrowBg = Color(0xFFFFFBF5);
const Color _kEscrowBorder = Color(0xFFF2DAB5);
const Color _kEscrowIcon = Color(0xFFE39A2D);
const Color _kDigitalGradientA = Color(0xFF0B1A60);
const Color _kDigitalGradientB = Color(0xFF4338CA);

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
      backgroundColor: const Color(0xFFF5F5FA),
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _ProductDetailError(
          message: _productDetailErrorMessage(error),
          onRetry: () => ref.refresh(productDetailProvider(productId)),
        ),
        data: (product) => _ProductDetailBody(product: product),
      ),
    );
  }
}

String _productDetailErrorMessage(Object error) {
  if (error is ApiException) {
    switch (error.type) {
      case ApiExceptionType.network:
        return 'Network issue. Check your connection and try again.';
      case ApiExceptionType.notFound:
        return 'This product is no longer available.';
      case ApiExceptionType.unauthenticated:
        return 'Your session expired. Please sign in again.';
      case ApiExceptionType.internalError:
        return 'Server error. Please try again shortly.';
      case ApiExceptionType.forbidden:
      case ApiExceptionType.validationFailed:
      case ApiExceptionType.conflict:
      case ApiExceptionType.invalidStateTransition:
      case ApiExceptionType.unknown:
        return error.message.isNotEmpty
            ? error.message
            : 'Something went wrong.';
    }
  }
  return 'Something went wrong. Please try again.';
}

class _ProductDetailBody extends ConsumerWidget {
  const _ProductDetailBody({required this.product});

  final ProductDto product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final kind = _ProductKind.from(product.productType);
    final isSaved = ref.watch(wishlistControllerProvider).maybeWhen(
          data: (items) =>
              product.id != null &&
              items.any((item) => item.productId == product.id),
          orElse: () => false,
        );
    final bottomInset = MediaQuery.paddingOf(context).bottom;

    return Column(
      children: <Widget>[
        SafeArea(
          bottom: false,
          child: _TopBar(
            productId: product.id,
            isSaved: isSaved,
          ),
        ),
        Expanded(
          child: SingleChildScrollView(
            padding: EdgeInsets.fromLTRB(16, 4, 16, 12 + bottomInset),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                _ProductMedia(product: product, kind: kind),
                const SizedBox(height: 18),
                _ProductHeadline(product: product, kind: kind),
                const SizedBox(height: 18),
                _SellerSummarySection(product: product),
                const SizedBox(height: 18),
                _ProductOverviewSection(product: product, kind: kind),
                const SizedBox(height: 18),
                _ProductDetailsSection(product: product, kind: kind),
                const SizedBox(height: 18),
                _FulfillmentDetailsSection(product: product, kind: kind),
                const SizedBox(height: 18),
                _ReviewSummarySection(product: product),
                const SizedBox(height: 18),
                _FeatureList(kind: kind),
                const SizedBox(height: 18),
                _EscrowNotice(kind: kind),
              ],
            ),
          ),
        ),
        _BottomActions(product: product, kind: kind, bottomInset: bottomInset),
      ],
    );
  }
}

class _TopBar extends ConsumerWidget {
  const _TopBar({
    required this.productId,
    required this.isSaved,
  });

  final int? productId;
  final bool isSaved;

  static const double _circleSize = 44;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 4, 8, 8),
      child: Row(
        children: <Widget>[
          IconButton(
            visualDensity: VisualDensity.compact,
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
            onPressed: () => Navigator.of(context).maybePop(),
            icon: Icon(Icons.arrow_back_ios_new_rounded,
                size: 20, color: cs.onSurface),
          ),
          const Spacer(),
          _CircleIconButton(
            size: _circleSize,
            icon: isSaved
                ? Icons.favorite_rounded
                : Icons.favorite_border_rounded,
            iconSize: 21,
            onPressed: () async {
              final id = productId;
              if (id == null || id <= 0) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Unable to save this item.')),
                );
                return;
              }
              try {
                final notifier = ref.read(wishlistControllerProvider.notifier);
                if (isSaved) {
                  await notifier.remove(id);
                } else {
                  await notifier.add(id);
                }
                if (!context.mounted) {
                  return;
                }
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      isSaved ? 'Removed from wishlist' : 'Saved to wishlist',
                    ),
                  ),
                );
              } catch (e) {
                if (!context.mounted) {
                  return;
                }
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Unable to update wishlist: $e')),
                );
              }
            },
          ),
          const SizedBox(width: 10),
          _CircleIconButton(
            size: _circleSize,
            icon: Icons.ios_share_rounded,
            iconSize: 20,
            onPressed: () {},
          ),
        ],
      ),
    );
  }
}

class _CircleIconButton extends StatelessWidget {
  const _CircleIconButton({
    required this.size,
    required this.icon,
    required this.iconSize,
    required this.onPressed,
  });

  final double size;
  final IconData icon;
  final double iconSize;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: cs.surface,
      elevation: 0,
      shadowColor: Colors.black26,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onPressed,
        child: SizedBox(
          width: size,
          height: size,
          child: Icon(icon, size: iconSize, color: cs.onSurface),
        ),
      ),
    );
  }
}

class _ProductMedia extends StatelessWidget {
  const _ProductMedia({
    required this.product,
    required this.kind,
  });

  final ProductDto product;
  final _ProductKind kind;

  @override
  Widget build(BuildContext context) {
    final showBestSeller = product.raw['is_best_seller'] == true;

    return switch (kind) {
      _ProductKind.digital => _DigitalHeroCard(product: product),
      _ProductKind.manual => _PhysicalStyleHero(
          product: product,
          aspectRatio: 1.08,
          borderRadius: 20,
          showBestSellerBadge: false,
        ),
      _ProductKind.physical => _PhysicalStyleHero(
          product: product,
          aspectRatio: 1.02,
          borderRadius: 20,
          showBestSellerBadge: showBestSeller,
        ),
    };
  }
}

class _PhysicalStyleHero extends StatefulWidget {
  const _PhysicalStyleHero({
    required this.product,
    required this.aspectRatio,
    required this.borderRadius,
    required this.showBestSellerBadge,
  });

  final ProductDto product;
  final double aspectRatio;
  final double borderRadius;
  final bool showBestSellerBadge;

  @override
  State<_PhysicalStyleHero> createState() => _PhysicalStyleHeroState();
}

class _PhysicalStyleHeroState extends State<_PhysicalStyleHero> {
  late final PageController _controller;
  Timer? _autoSlideTimer;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _controller = PageController();
    _syncAutoSlide();
  }

  @override
  void didUpdateWidget(covariant _PhysicalStyleHero oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.product.imageUrls.length != widget.product.imageUrls.length) {
      _page = 0;
      _syncAutoSlide();
    }
  }

  @override
  void dispose() {
    _autoSlideTimer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _syncAutoSlide() {
    _autoSlideTimer?.cancel();
    if (widget.product.imageUrls.length < 2) {
      return;
    }
    _autoSlideTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      final images = widget.product.imageUrls;
      if (!mounted || !_controller.hasClients || images.length < 2) {
        return;
      }
      final next = (_page + 1) % images.length;
      _controller.animateToPage(
        next,
        duration: const Duration(milliseconds: 460),
        curve: Curves.easeOutCubic,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final images = widget.product.imageUrls;

    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.borderRadius),
      child: AspectRatio(
        aspectRatio: widget.aspectRatio,
        child: Stack(
          fit: StackFit.expand,
          children: <Widget>[
            if (images.isNotEmpty)
              PageView.builder(
                controller: _controller,
                itemCount: images.length,
                onPageChanged: (value) => setState(() => _page = value),
                itemBuilder: (context, index) => Image.network(
                  images[index],
                  fit: BoxFit.cover,
                  loadingBuilder: (_, Widget child, ImageChunkEvent? progress) {
                    if (progress == null) {
                      return child;
                    }
                    return ColoredBox(
                      color: cs.surfaceContainerHighest,
                      child: Center(
                        child: SizedBox(
                          width: 28,
                          height: 28,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: cs.primary.withValues(alpha: 0.7),
                          ),
                        ),
                      ),
                    );
                  },
                  errorBuilder: (_, __, ___) => ColoredBox(
                    color: cs.surfaceContainerHighest,
                    child: Icon(Icons.broken_image_outlined,
                        color: cs.outline, size: 44),
                  ),
                ),
              )
            else
              ColoredBox(
                color: cs.surfaceContainerHighest,
                child: Icon(Icons.image_not_supported_outlined,
                    color: cs.outline, size: 44),
              ),
            if (images.length > 1) ...<Widget>[
              Positioned(
                right: 12,
                top: 12,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.58),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '${_page + 1}/${images.length}',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                        ),
                  ),
                ),
              ),
              Positioned(
                left: 0,
                right: 0,
                bottom: 14,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List<Widget>.generate(
                    images.length,
                    (index) => AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      width: index == _page ? 18 : 7,
                      height: 7,
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      decoration: BoxDecoration(
                        color: index == _page
                            ? Colors.white
                            : Colors.white.withValues(alpha: 0.58),
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                ),
              ),
            ],
            if (widget.showBestSellerBadge)
              Positioned(
                left: 14,
                bottom: 14,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
                  decoration: BoxDecoration(
                    color: _kBestSellerBg,
                    borderRadius: BorderRadius.circular(8),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.06),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Text(
                    'Best Seller',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: _kBestSellerText,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.2,
                        ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _DigitalHeroCard extends StatelessWidget {
  const _DigitalHeroCard({required this.product});

  final ProductDto product;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final subtitle = product.heroHighlight.trim().isEmpty
        ? 'Digital listing'
        : product.heroHighlight.trim();
    final image = product.primaryImageUrl;

    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: AspectRatio(
        aspectRatio: 2.35,
        child: DecoratedBox(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[_kDigitalGradientA, _kDigitalGradientB],
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 16, 18),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: <Widget>[
                      Text(
                        product.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleLarge?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          height: 1.2,
                          fontSize: 22,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        subtitle,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: Colors.white.withValues(alpha: 0.88),
                          height: 1.35,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.22),
                    borderRadius: BorderRadius.circular(16),
                    border:
                        Border.all(color: Colors.white.withValues(alpha: 0.2)),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: image != null && image.toLowerCase().startsWith('http')
                      ? Image.network(image, fit: BoxFit.cover)
                      : Icon(Icons.layers_outlined,
                          size: 36, color: Colors.white.withValues(alpha: 0.9)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ProductHeadline extends StatelessWidget {
  const _ProductHeadline({
    required this.product,
    required this.kind,
  });

  final ProductDto product;
  final _ProductKind kind;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final subtitle = product.heroHighlight.trim().isEmpty
        ? product.description.trim()
        : product.heroHighlight.trim();
    final reviews = product.reviewCount;
    final sold =
        _toInt(product.raw['sold_count'] ?? product.raw['sales_count']);
    final rating = product.rating;
    final compare = product.compareAtLabel;
    final hasCompare = product.hasMeaningfulComparePrice && compare != null;
    final discount = _discountPercent(product);
    final typeLabel = switch (kind) {
      _ProductKind.physical => 'Physical Product',
      _ProductKind.digital => 'Digital Product',
      _ProductKind.manual => 'Manual / Custom Service',
    };
    final showBestSellerChip =
        product.raw['is_best_seller'] == true && kind == _ProductKind.digital;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        if (showBestSellerChip) ...<Widget>[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: _kBestSellerBg,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              'Best Seller',
              style: theme.textTheme.labelMedium?.copyWith(
                color: _kBestSellerText,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    product.title,
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: _kNavy,
                      fontWeight: FontWeight.w800,
                      height: 1.2,
                      fontSize: 22,
                      letterSpacing: -0.2,
                    ),
                  ),
                  if (subtitle.isNotEmpty &&
                      kind != _ProductKind.digital) ...<Widget>[
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyLarge?.copyWith(
                        color: _kSubtitle,
                        fontWeight: FontWeight.w500,
                        height: 1.35,
                        fontSize: 15,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            IconButton(
              visualDensity: VisualDensity.compact,
              padding: const EdgeInsets.only(top: 2),
              constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
              onPressed: () {},
              icon: Icon(Icons.share_outlined, size: 20, color: cs.primary),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 0,
          runSpacing: 6,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: <Widget>[
            if (rating != null) ...<Widget>[
              const Icon(Icons.star_rounded,
                  size: 17, color: Color(0xFFF5A524)),
              const SizedBox(width: 4),
              Text(
                rating.toStringAsFixed(1),
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: _kNavy,
                  fontSize: 15,
                ),
              ),
              if (reviews > 0) ...<Widget>[
                Text(
                  ' ($reviews)',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: _kSubtitle,
                    fontSize: 13,
                  ),
                ),
              ],
            ],
            if (sold != null) ...<Widget>[
              if (rating != null) const SizedBox(width: 14),
              const Icon(Icons.shopping_bag_outlined,
                  size: 15, color: _kSubtitle),
              const SizedBox(width: 4),
              Text(
                '$sold sold',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: _kSubtitle,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 14),
        Row(
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: <Widget>[
            Text(
              product.priceLabel,
              style: theme.textTheme.headlineSmall?.copyWith(
                color: _kNavy,
                fontWeight: FontWeight.w900,
                letterSpacing: -0.6,
                fontSize: 28,
                height: 1.05,
              ),
            ),
            if (hasCompare) ...<Widget>[
              const SizedBox(width: 10),
              Text(
                compare,
                style: theme.textTheme.titleSmall?.copyWith(
                  color: _kSubtitle,
                  decoration: TextDecoration.lineThrough,
                  fontWeight: FontWeight.w500,
                  fontSize: 15,
                ),
              ),
            ],
            if (discount != null) ...<Widget>[
              const SizedBox(width: 10),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _kDiscountBg,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  '-$discount%',
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: _kDiscountText,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: kind == _ProductKind.digital
                ? _kDigitalChipBg
                : cs.surfaceContainerHigh.withValues(alpha: 0.65),
            borderRadius: BorderRadius.circular(10),
            border: kind == _ProductKind.digital
                ? Border.all(color: _kDigitalChipFg.withValues(alpha: 0.12))
                : Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
          ),
          child: Text(
            typeLabel,
            style: theme.textTheme.labelLarge?.copyWith(
              fontWeight: FontWeight.w700,
              color:
                  kind == _ProductKind.digital ? _kDigitalChipFg : _kSubtitle,
              fontSize: 13,
            ),
          ),
        ),
      ],
    );
  }
}

class _FeatureList extends StatelessWidget {
  const _FeatureList({required this.kind});

  final _ProductKind kind;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final rows = switch (kind) {
      _ProductKind.physical => <({IconData icon, String label})>[
          (icon: Icons.local_shipping_outlined, label: 'Shipping'),
          (icon: Icons.schedule_outlined, label: 'Returns'),
          (icon: Icons.verified_user_outlined, label: 'Warranty'),
        ],
      _ProductKind.digital => <({IconData icon, String label})>[
          (icon: Icons.download_outlined, label: 'Instant access'),
          (icon: Icons.all_inclusive_rounded, label: 'Lifetime access'),
          (icon: Icons.info_outline_rounded, label: 'Commercial use'),
        ],
      _ProductKind.manual => <({IconData icon, String label})>[
          (icon: Icons.chat_bubble_outline_rounded, label: 'Chat'),
          (icon: Icons.autorenew_rounded, label: 'Revisions'),
          (icon: Icons.timer_outlined, label: 'On time'),
        ],
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: rows
          .map(
            (({IconData icon, String label}) row) => Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  SizedBox(
                    width: 24,
                    child: Icon(row.icon, size: 20, color: _kSubtitle),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      row.label,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: _kSubtitle,
                        height: 1.4,
                        fontWeight: FontWeight.w500,
                        fontSize: 15,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          )
          .toList(),
    );
  }
}

class _ProductOverviewSection extends StatelessWidget {
  const _ProductOverviewSection({
    required this.product,
    required this.kind,
  });

  final ProductDto product;
  final _ProductKind kind;

  @override
  Widget build(BuildContext context) {
    final description = product.description.trim();
    final highlights = _highlights(product, kind);

    return _DetailSection(
      title: 'Product Overview',
      icon: Icons.article_outlined,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            description.isEmpty
                ? switch (kind) {
                    _ProductKind.digital =>
                      'This digital product is delivered through protected escrow chat after payment is secured.',
                    _ProductKind.manual =>
                      'This service is completed through seller updates, proof, and buyer approval.',
                    _ProductKind.physical =>
                      'This item is sold through protected checkout with escrow-held payment.',
                  }
                : description,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: _kSubtitle,
                  height: 1.55,
                  fontWeight: FontWeight.w500,
                ),
          ),
          if (highlights.isNotEmpty) ...<Widget>[
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: highlights
                  .map((item) =>
                      _MetaChip(label: item, icon: Icons.check_rounded))
                  .toList(),
            ),
          ],
        ],
      ),
    );
  }

  List<String> _highlights(ProductDto product, _ProductKind kind) {
    final attrs = product.attributes;
    final tags = attrs['tags'];
    final values = <String>[];
    if (tags is List) {
      values.addAll(
          tags.map((e) => e.toString()).where((e) => e.trim().isNotEmpty));
    }
    for (final key in [
      'brand',
      'condition',
      'platform',
      'license_type',
      'access_type'
    ]) {
      final value = (attrs[key] ?? product.raw[key])?.toString().trim();
      if (value != null && value.isNotEmpty) values.add(_titleize(value));
    }
    if (values.isEmpty) {
      values.addAll(switch (kind) {
        _ProductKind.digital => [
            'Proof delivery',
            'Buyer review',
            'Escrow protected'
          ],
        _ProductKind.manual => [
            'Progress updates',
            'Approval required',
            'Escrow protected'
          ],
        _ProductKind.physical => [
            'Seller shipped',
            'Buyer confirms',
            'Escrow protected'
          ],
      });
    }
    return values.toSet().take(5).toList();
  }
}

class _ProductDetailsSection extends StatelessWidget {
  const _ProductDetailsSection({
    required this.product,
    required this.kind,
  });

  final ProductDto product;
  final _ProductKind kind;

  @override
  Widget build(BuildContext context) {
    final attrs = product.attributes;
    final rows = <({String label, String value})>[
      (label: 'Category', value: _value(product.raw['category_name'])),
      (label: 'Product Type', value: _typeLabel(kind, product.productType)),
      if (kind == _ProductKind.physical) ...<({String label, String value})>[
        (label: 'Brand', value: _value(attrs['brand'] ?? product.raw['brand'])),
        (
          label: 'Condition',
          value: _value(attrs['condition'] ?? product.raw['condition'])
        ),
        (
          label: 'Warranty',
          value:
              _value(attrs['warranty_status'] ?? product.raw['warranty_status'])
        ),
        (
          label: 'Location',
          value: _value(
              attrs['product_location'] ?? product.raw['product_location'])
        ),
        (label: 'Stock', value: _stockLabel(product.raw['stock'])),
      ] else ...<({String label, String value})>[
        (label: 'Platform', value: _value(attrs['platform'])),
        (label: 'Access Type', value: _value(attrs['access_type'])),
        (label: 'License', value: _value(attrs['license_type'])),
        (label: 'Duration', value: _value(attrs['subscription_duration'])),
        (label: 'Region', value: _value(attrs['account_region'])),
      ],
      (label: 'Published', value: product.publishedLabel),
      (label: 'Updated', value: product.updatedLabel),
    ].where((row) => row.value != '—').toList();

    return _DetailSection(
      title: 'Details',
      icon: Icons.tune_rounded,
      child: Column(
        children: rows
            .map((row) => _SpecRow(label: row.label, value: row.value))
            .toList(),
      ),
    );
  }
}

class _FulfillmentDetailsSection extends StatelessWidget {
  const _FulfillmentDetailsSection({
    required this.product,
    required this.kind,
  });

  final ProductDto product;
  final _ProductKind kind;

  @override
  Widget build(BuildContext context) {
    final rows = switch (kind) {
      _ProductKind.physical => <({IconData icon, String title, String body})>[
          (
            icon: Icons.local_shipping_outlined,
            title: 'Shipping workflow',
            body:
                'Seller prepares shipment, adds courier and tracking, then marks the order shipped.'
          ),
          (
            icon: Icons.inventory_2_outlined,
            title: 'Receipt confirmation',
            body:
                'Escrow stays held until you confirm the item was received or a dispute is resolved.'
          ),
          (
            icon: Icons.support_agent_outlined,
            title: 'Dispute window',
            body:
                'If the item is wrong, missing, or damaged, open a dispute before escrow release.'
          ),
        ],
      _ProductKind.digital => <({IconData icon, String title, String body})>[
          (
            icon: Icons.mark_chat_read_outlined,
            title: 'Escrow delivery chat',
            body:
                'Files, credentials, instructions, and screenshots are delivered in the protected order chat.'
          ),
          (
            icon: Icons.fact_check_outlined,
            title: 'Proof-based delivery',
            body:
                'Seller must submit delivery proof before buyer review and escrow release can begin.'
          ),
          (
            icon: Icons.timer_outlined,
            title: 'Buyer review timer',
            body:
                'After proof is submitted, you can validate the delivery, confirm completion, or open a dispute.'
          ),
        ],
      _ProductKind.manual => <({IconData icon, String title, String body})>[
          (
            icon: Icons.task_alt_rounded,
            title: 'Proof of work',
            body:
                'Seller shares progress, files, and completion proof through escrow chat.'
          ),
          (
            icon: Icons.rate_review_outlined,
            title: 'Buyer approval',
            body:
                'Escrow releases only after approval, admin resolution, or a configured timeout rule.'
          ),
          (
            icon: Icons.gpp_maybe_outlined,
            title: 'Dispute protection',
            body:
                'Chat messages and attachments become structured evidence if the order is disputed.'
          ),
        ],
    };

    return _DetailSection(
      title: 'Delivery & Escrow',
      icon: Icons.shield_outlined,
      child: Column(
        children: rows
            .map((row) => _FulfillmentRow(
                  icon: row.icon,
                  title: row.title,
                  body: row.body,
                ))
            .toList(),
      ),
    );
  }
}

class _DetailSection extends StatelessWidget {
  const _DetailSection({
    required this.title,
    required this.icon,
    required this.child,
  });

  final String title;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icon, size: 20, color: _kNavy),
              const SizedBox(width: 8),
              Text(
                title,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: _kNavy,
                      fontWeight: FontWeight.w900,
                    ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _SpecRow extends StatelessWidget {
  const _SpecRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: _kSubtitle,
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: _kNavy,
                    fontWeight: FontWeight.w800,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FulfillmentRow extends StatelessWidget {
  const _FulfillmentRow({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 20, color: _kNavy),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: _kNavy,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 3),
                Text(
                  body,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: _kSubtitle,
                        height: 1.45,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EscrowNotice extends StatelessWidget {
  const _EscrowNotice({required this.kind});

  final _ProductKind kind;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        color: _kEscrowBg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _kEscrowBorder),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _kEscrowIcon.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.shield_outlined,
                color: _kEscrowIcon, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Escrow',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: _kNavy,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  switch (kind) {
                    _ProductKind.physical => 'Paid after you confirm.',
                    _ProductKind.digital => 'Access after payment.',
                    _ProductKind.manual => 'Release after approval.',
                  },
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: _kSubtitle,
                    height: 1.45,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SellerSummarySection extends StatelessWidget {
  const _SellerSummarySection({required this.product});

  final ProductDto product;

  @override
  Widget build(BuildContext context) {
    final seller = product.sellerSummary;
    final storefrontId =
        product.storefrontId ?? (seller?['storefront_id'] as num?)?.toInt();
    final sellerName =
        (seller?['display_name'] ?? product.sellerLabel).toString().trim();
    final storeName = (seller?['store_name'] ?? sellerName).toString().trim();
    final verification =
        (seller?['verification_status'] ?? '').toString().trim();
    final country = (seller?['country_code'] ?? '').toString().trim();
    final statusLabel =
        verification.isEmpty ? 'Seller' : verification.replaceAll('_', ' ');
    final hasStorefront = storefrontId != null && storefrontId > 0;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const CircleAvatar(
                radius: 22,
                backgroundColor: Color(0xFFF1F5F9),
                child: Icon(Icons.storefront_rounded, color: Color(0xFF334155)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      storeName.isEmpty ? 'Seller' : storeName,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900, color: _kNavy),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      sellerName.isEmpty ? 'Marketplace seller' : sellerName,
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: _kSubtitle),
                    ),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  statusLabel,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: const Color(0xFF334155),
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              if (country.isNotEmpty)
                _MetaChip(label: country, icon: Icons.public_outlined),
              if (hasStorefront)
                _MetaChip(
                    label: 'Storefront #$storefrontId',
                    icon: Icons.store_mall_directory_outlined),
            ],
          ),
          if (hasStorefront) ...<Widget>[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => context.push('/storefronts/$storefrontId'),
                icon: const Icon(Icons.storefront_rounded),
                label: const Text('Visit Storefront'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ReviewSummarySection extends StatelessWidget {
  const _ReviewSummarySection({required this.product});

  final ProductDto product;

  @override
  Widget build(BuildContext context) {
    final summary = product.reviewSummary;
    final avg = _toDouble(summary?['average_rating']);
    final count = _toInt(summary?['review_count']) ?? 0;
    final latest = (summary?['latest_reviews'] as List?)
            ?.whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList() ??
        const <Map<String, dynamic>>[];

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(Icons.star_rounded, color: Color(0xFFF59E0B)),
              const SizedBox(width: 8),
              Text(
                'Reviews',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              Text(
                avg == null ? '—' : avg.toStringAsFixed(1),
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
              ),
              const SizedBox(width: 8),
              if (avg != null) ...<Widget>[
                _RatingStars(value: avg),
                const SizedBox(width: 8),
              ],
              Text('$count',
                  style: Theme.of(context)
                      .textTheme
                      .bodyMedium
                      ?.copyWith(color: _kSubtitle)),
            ],
          ),
          const SizedBox(height: 12),
          if (latest.isEmpty)
            Text(
              'No reviews yet.',
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium
                  ?.copyWith(color: _kSubtitle, height: 1.45),
            )
          else
            Column(
              children: latest.map((item) {
                final rating = _toInt(item['rating']) ?? 0;
                final comment = (item['comment'] ?? '').toString().trim();
                final buyer = (item['buyer_name'] ?? 'Buyer').toString().trim();
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Row(
                          children: <Widget>[
                            Expanded(
                              child: Text(
                                buyer,
                                style: Theme.of(context)
                                    .textTheme
                                    .bodyMedium
                                    ?.copyWith(
                                        fontWeight: FontWeight.w800,
                                        color: _kNavy),
                              ),
                            ),
                            _RatingStars(value: rating.toDouble()),
                          ],
                        ),
                        if (comment.isNotEmpty) ...<Widget>[
                          const SizedBox(height: 6),
                          Text(
                            comment,
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium
                                ?.copyWith(color: _kSubtitle, height: 1.45),
                          ),
                        ],
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
        ],
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.label, required this.icon});

  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 16, color: _kSubtitle),
          const SizedBox(width: 6),
          Text(label,
              style: Theme.of(context)
                  .textTheme
                  .labelMedium
                  ?.copyWith(color: _kSubtitle, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

class _RatingStars extends StatelessWidget {
  const _RatingStars({required this.value});

  final double value;

  @override
  Widget build(BuildContext context) {
    final full = value.floor().clamp(0, 5);
    final half = (value - full) >= 0.5 && full < 5;
    final count = full + (half ? 1 : 0);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        for (var i = 0; i < full; i++)
          const Icon(Icons.star_rounded, size: 16, color: Color(0xFFF59E0B)),
        if (half)
          const Icon(Icons.star_half_rounded,
              size: 16, color: Color(0xFFF59E0B)),
        for (var i = count; i < 5; i++)
          const Icon(Icons.star_border_rounded,
              size: 16, color: Color(0xFFF59E0B)),
      ],
    );
  }
}

class _BottomActions extends ConsumerWidget {
  const _BottomActions({
    required this.product,
    required this.kind,
    required this.bottomInset,
  });

  final ProductDto product;
  final _ProductKind kind;
  final double bottomInset;

  static const double _btnHeight = 52;
  static const double _radius = 14;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final theme = Theme.of(context);
    final sellerState = ref.watch(sellerProfileControllerProvider);
    final currentSellerId = sellerState.profile?.id;
    final isOwnListing =
        currentSellerId != null && currentSellerId == product.sellerProfileId;

    if (!sellerState.isLoading &&
        sellerState.profile == null &&
        sellerState.hasSellerProfile &&
        sellerState.errorMessage == null) {
      Future<void>.microtask(
        () => ref.read(sellerProfileControllerProvider.notifier).load(),
      );
    }

    if (isOwnListing) {
      return Material(
        color: cs.surface,
        elevation: 8,
        shadowColor: Colors.black.withValues(alpha: 0.08),
        child: Padding(
          padding: EdgeInsets.fromLTRB(16, 12, 16, 12 + bottomInset),
          child: Row(
            children: <Widget>[
              Expanded(
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFFBEB),
                    borderRadius: BorderRadius.circular(_radius),
                    border: Border.all(color: const Color(0xFFFDE68A)),
                  ),
                  child: Text(
                    'This is your listing. Buyers can order it, but your own account cannot.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: const Color(0xFF92400E),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              FilledButton(
                onPressed: product.id == null
                    ? null
                    : () => context.push('/seller/products/${product.id}'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size(0, _btnHeight),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(_radius),
                  ),
                ),
                child: const Text('Manage'),
              ),
            ],
          ),
        ),
      );
    }

    void addToCart() {
      ref.read(cartControllerProvider.notifier).addFromProduct(product);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Added to cart')),
      );
    }

    void buyNow() {
      final line = CartLine.fromProduct(product, quantity: 1);
      ref.read(checkoutDraftProvider.notifier).beginFromCart(<CartLine>[line]);
      context
          .push(line.isPhysical ? '/checkout/shipping' : '/checkout/payment');
    }

    final outlinedStyle = OutlinedButton.styleFrom(
      minimumSize: const Size(0, _btnHeight),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      shape:
          RoundedRectangleBorder(borderRadius: BorderRadius.circular(_radius)),
      side: BorderSide(color: _kNavy.withValues(alpha: 0.35), width: 1.2),
      foregroundColor: _kNavy,
      textStyle:
          theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
    );

    final filledStyle = FilledButton.styleFrom(
      minimumSize: const Size(0, _btnHeight),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      shape:
          RoundedRectangleBorder(borderRadius: BorderRadius.circular(_radius)),
      backgroundColor: cs.primary,
      foregroundColor: cs.onPrimary,
      elevation: 0,
      textStyle:
          theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
    );

    return Material(
      color: cs.surface,
      elevation: 8,
      shadowColor: Colors.black.withValues(alpha: 0.08),
      child: Padding(
        padding: EdgeInsets.fromLTRB(16, 12, 16, 12 + bottomInset),
        child: Row(
          children: switch (kind) {
            _ProductKind.digital => <Widget>[
                Expanded(
                  child: FilledButton(
                    onPressed: buyNow,
                    style: filledStyle,
                    child: const Text('Buy Now'),
                  ),
                ),
              ],
            _ProductKind.physical => <Widget>[
                Expanded(
                  child: OutlinedButton(
                    onPressed: addToCart,
                    style: outlinedStyle,
                    child: const Text('Add to Cart'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    onPressed: buyNow,
                    style: filledStyle,
                    child: const Text('Buy Now'),
                  ),
                ),
              ],
            _ProductKind.manual => <Widget>[
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                            content: Text('Messaging is not available yet.')),
                      );
                    },
                    style: outlinedStyle,
                    child: const Text('Contact Seller'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    onPressed: buyNow,
                    style: filledStyle,
                    child: const Text('Buy Now'),
                  ),
                ),
              ],
          },
        ),
      ),
    );
  }
}

enum _ProductKind {
  physical,
  digital,
  manual;

  static _ProductKind from(String raw) {
    final s = raw.toLowerCase().trim();
    if (s == 'digital') {
      return _ProductKind.digital;
    }
    if (s == 'manual' || s == 'service') {
      return _ProductKind.manual;
    }
    return _ProductKind.physical;
  }
}

double? _toDouble(dynamic value) {
  if (value == null) {
    return null;
  }
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse(value.toString());
}

int? _toInt(dynamic value) {
  if (value == null) {
    return null;
  }
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse(value.toString());
}

int? _discountPercent(ProductDto product) {
  final base = _toDouble(product.raw['base_price'] ??
      product.raw['price'] ??
      product.raw['amount']);
  final compare = _toDouble(product.raw['compare_at_price'] ??
      product.raw['list_price'] ??
      product.raw['original_price']);
  if (base == null || compare == null || compare <= 0 || compare <= base) {
    return null;
  }
  return ((compare - base) / compare * 100).round();
}

String _value(dynamic value) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? '—' : _titleize(text);
}

String _titleize(String value) {
  return value
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.trim().isNotEmpty)
      .map((part) {
    final lower = part.toLowerCase();
    return lower.isEmpty
        ? lower
        : '${lower[0].toUpperCase()}${lower.substring(1)}';
  }).join(' ');
}

String _stockLabel(dynamic value) {
  final stock = _toInt(value);
  if (stock == null) return '—';
  if (stock <= 0) return 'Out of stock';
  if (stock <= 5) return 'Only $stock left';
  return '$stock available';
}

String _typeLabel(_ProductKind kind, String raw) {
  return switch (kind) {
    _ProductKind.physical => 'Physical Product',
    _ProductKind.digital => raw.toLowerCase() == 'instant_delivery'
        ? 'Instant Digital Delivery'
        : 'Digital Product',
    _ProductKind.manual => 'Service',
  };
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
