import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../cart/application/cart_controller.dart';
import '../../cart/application/checkout_draft_controller.dart';
import '../../cart/domain/cart_line.dart';
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
          message: error.toString(),
          onRetry: () => ref.refresh(productDetailProvider(productId)),
        ),
        data: (product) => _ProductDetailBody(product: product),
      ),
    );
  }
}

class _ProductDetailBody extends ConsumerWidget {
  const _ProductDetailBody({required this.product});

  final ProductDto product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final kind = _ProductKind.from(product.productType);
    final bottomInset = MediaQuery.paddingOf(context).bottom;

    return Column(
      children: <Widget>[
        SafeArea(
          bottom: false,
          child: const _TopBar(),
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

class _TopBar extends StatelessWidget {
  const _TopBar();

  static const double _circleSize = 44;

  @override
  Widget build(BuildContext context) {
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
            icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: cs.onSurface),
          ),
          const Spacer(),
          _CircleIconButton(
            size: _circleSize,
            icon: Icons.favorite_border_rounded,
            iconSize: 21,
            onPressed: () {},
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

class _PhysicalStyleHero extends StatelessWidget {
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
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final image = product.primaryImageUrl;

    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: AspectRatio(
        aspectRatio: aspectRatio,
        child: Stack(
          fit: StackFit.expand,
          children: <Widget>[
            if (image != null)
              Image.network(
                image,
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
                  child: Icon(Icons.broken_image_outlined, color: cs.outline, size: 44),
                ),
              )
            else
              ColoredBox(
                color: cs.surfaceContainerHighest,
                child: Icon(Icons.image_not_supported_outlined, color: cs.outline, size: 44),
              ),
            if (showBestSellerBadge)
              Positioned(
                left: 14,
                bottom: 14,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
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
        ? 'Premium digital listing'
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
                    border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: image != null && image.toLowerCase().startsWith('http')
                      ? Image.network(image, fit: BoxFit.cover)
                      : Icon(Icons.layers_outlined, size: 36, color: Colors.white.withValues(alpha: 0.9)),
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
    final subtitle = product.heroHighlight.trim().isEmpty ? product.description.trim() : product.heroHighlight.trim();
    final reviews = _toInt(product.raw['reviews_count'] ?? product.raw['review_count']);
    final sold = _toInt(product.raw['sold_count'] ?? product.raw['sales_count']);
    final rating = _toDouble(product.raw['rating'] ?? product.raw['rating_avg'] ?? product.raw['average_rating']);
    final compare = product.compareAtLabel;
    final hasCompare = product.hasMeaningfulComparePrice && compare != null;
    final discount = _discountPercent(product);
    final typeLabel = switch (kind) {
      _ProductKind.physical => 'Physical Product',
      _ProductKind.digital => 'Digital Product',
      _ProductKind.manual => 'Manual / Custom Service',
    };
    final showBestSellerChip = product.raw['is_best_seller'] == true && kind == _ProductKind.digital;

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
                  if (subtitle.isNotEmpty && kind != _ProductKind.digital) ...<Widget>[
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      maxLines: 2,
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
              const Icon(Icons.star_rounded, size: 17, color: Color(0xFFF5A524)),
              const SizedBox(width: 4),
              Text(
                rating.toStringAsFixed(1),
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: _kNavy,
                  fontSize: 15,
                ),
              ),
              if (reviews != null) ...<Widget>[
                Text(
                  ' ($reviews reviews)',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: _kSubtitle,
                    fontSize: 13,
                  ),
                ),
              ],
            ],
            if (sold != null) ...<Widget>[
              if (rating != null) const SizedBox(width: 14),
              Icon(Icons.shopping_bag_outlined, size: 15, color: _kSubtitle),
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
            color: kind == _ProductKind.digital ? _kDigitalChipBg : cs.surfaceContainerHigh.withValues(alpha: 0.65),
            borderRadius: BorderRadius.circular(10),
            border: kind == _ProductKind.digital
                ? Border.all(color: _kDigitalChipFg.withValues(alpha: 0.12))
                : Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
          ),
          child: Text(
            typeLabel,
            style: theme.textTheme.labelLarge?.copyWith(
              fontWeight: FontWeight.w700,
              color: kind == _ProductKind.digital ? _kDigitalChipFg : _kSubtitle,
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
          (icon: Icons.local_shipping_outlined, label: 'Free Shipping'),
          (icon: Icons.schedule_outlined, label: '7 Days Return'),
          (icon: Icons.verified_user_outlined, label: '1 Year Warranty'),
        ],
      _ProductKind.digital => <({IconData icon, String label})>[
          (icon: Icons.download_outlined, label: 'Instant Download'),
          (icon: Icons.all_inclusive_rounded, label: 'Lifetime Access'),
          (icon: Icons.info_outline_rounded, label: 'Commercial Use'),
        ],
      _ProductKind.manual => <({IconData icon, String label})>[
          (icon: Icons.chat_bubble_outline_rounded, label: '1-on-1 Discussion'),
          (icon: Icons.autorenew_rounded, label: 'Unlimited Revisions'),
          (icon: Icons.timer_outlined, label: 'On-time Delivery'),
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
            child: const Icon(Icons.shield_outlined, color: _kEscrowIcon, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Protected by Escrow',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: _kNavy,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  switch (kind) {
                    _ProductKind.physical =>
                      'Your payment is held securely. Seller gets paid only after you confirm.',
                    _ProductKind.digital => 'Your payment is held securely. You will get access after payment.',
                    _ProductKind.manual => 'Payment is held securely. Release after you approve delivery.',
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

    void addToCart() {
      ref.read(cartControllerProvider.notifier).addFromProduct(product);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Added to cart')),
      );
    }

    void buyNow() {
      ref.read(checkoutDraftProvider.notifier).beginFromCart(<CartLine>[CartLine.fromProduct(product, quantity: 1)]);
      context.push('/checkout/shipping');
    }

    final outlinedStyle = OutlinedButton.styleFrom(
      minimumSize: const Size(0, _btnHeight),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(_radius)),
      side: BorderSide(color: _kNavy.withValues(alpha: 0.35), width: 1.2),
      foregroundColor: _kNavy,
      textStyle: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
    );

    final filledStyle = FilledButton.styleFrom(
      minimumSize: const Size(0, _btnHeight),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(_radius)),
      backgroundColor: cs.primary,
      foregroundColor: cs.onPrimary,
      elevation: 0,
      textStyle: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
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
                        const SnackBar(content: Text('Messaging is not available yet.')),
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
  final base = _toDouble(product.raw['base_price'] ?? product.raw['price'] ?? product.raw['amount']);
  final compare = _toDouble(product.raw['compare_at_price'] ?? product.raw['list_price'] ?? product.raw['original_price']);
  if (base == null || compare == null || compare <= 0 || compare <= base) {
    return null;
  }
  return ((compare - base) / compare * 100).round();
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
