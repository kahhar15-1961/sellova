import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerProductDetailScreen extends ConsumerWidget {
  const SellerProductDetailScreen({super.key, required this.productId});
  final int productId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = ref.watch(sellerProductsProvider.notifier).byId(productId);
    if (p == null) {
      return SellerScaffold(
        selectedNavIndex: 2,
        appBar: AppBar(
          title: const Text('Product Details'),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded),
            onPressed: () => context.canPop()
                ? context.pop()
                : context.go('/seller/products'),
          ),
        ),
        body: const Center(child: Text('Product not found')),
      );
    }
    final galleryUrls = _productGalleryUrls(p);
    final productType = p.isInstantDelivery
        ? 'Instant Delivery'
        : _productTypeLabel(p.productType);
    final stockTone = p.stock <= 0
        ? const Color(0xFFDC2626)
        : p.stock <= 5
            ? const Color(0xFFB45309)
            : const Color(0xFF15803D);
    return SellerScaffold(
      selectedNavIndex: 2,
      appBar: AppBar(
        title: const Text('Product Details'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/seller/products'),
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Products',
            onPressed: () => context.go('/seller/products'),
            icon: const Icon(Icons.storefront_outlined),
          ),
          IconButton(
            tooltip: 'Inventory',
            onPressed: () => context.go('/seller/inventory'),
            icon: const Icon(Icons.inventory_2_outlined),
          ),
          IconButton(
            tooltip: 'Seller home',
            onPressed: () => context.go('/seller/dashboard'),
            icon: const Icon(Icons.home_outlined),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          _ProductHeroGallery(urls: galleryUrls),
          const SizedBox(height: 14),
          _ProductQuickNav(
            onProducts: () => context.go('/seller/products'),
            onInventory: () => context.go('/seller/inventory'),
            onDashboard: () => context.go('/seller/dashboard'),
          ),
          const SizedBox(height: 18),
          Text(p.name,
              style: Theme.of(context)
                  .textTheme
                  .headlineSmall
                  ?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              Expanded(
                child: Text(p.priceLabel,
                    style: Theme.of(context)
                        .textTheme
                        .headlineSmall
                        ?.copyWith(fontWeight: FontWeight.w900)),
              ),
              _StatusPill(label: p.status.label, color: stockTone),
            ],
          ),
          const SizedBox(height: 14),
          _MetricsStrip(product: p, stockTone: stockTone),
          const SizedBox(height: 14),
          _DetailsCard(
            title: 'Listing Details',
            children: <Widget>[
              _line('Status', p.status.label),
              _line('Product Type', productType),
              _line('Category', p.category),
              _line('SKU', p.sku),
              _line('Views', '${p.views}'),
              _line('Sold', '${p.sold}'),
            ],
          ),
          const SizedBox(height: 12),
          _DetailsCard(
            title: 'Inventory',
            trailing: TextButton.icon(
              onPressed: () => context.go('/seller/inventory'),
              icon: const Icon(Icons.inventory_2_outlined, size: 18),
              label: const Text('Open'),
            ),
            children: <Widget>[
              _line('Total Stock', '${p.stock}'),
              ..._warehouseRows(p),
            ],
          ),
          const SizedBox(height: 12),
          _DetailsCard(
            title: 'Description',
            children: <Widget>[
              Text(
                p.description.trim().isEmpty
                    ? 'No product description has been added yet.'
                    : p.description.trim(),
                style: TextStyle(
                  color: p.description.trim().isEmpty
                      ? kSellerMuted
                      : Theme.of(context).colorScheme.onSurface,
                  height: 1.35,
                ),
              ),
            ],
          ),
          if (_attributeRows(p).isNotEmpty) ...<Widget>[
            const SizedBox(height: 12),
            _DetailsCard(
              title: 'Attributes',
              children: _attributeRows(p),
            ),
          ],
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                child: FilledButton(
                  onPressed: () =>
                      context.push('/seller/products/$productId/edit'),
                  child: const Text('Edit Product'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton(
                  onPressed: () async {
                    final deactivate = p.status == SellerProductStatus.active;
                    await ref
                        .read(sellerProductsProvider.notifier)
                        .toggleProductActive(productId, !deactivate);
                    if (context.mounted) {
                      showSellerSuccessToast(
                          context,
                          deactivate
                              ? 'Product deactivated.'
                              : 'Product activated.');
                    }
                  },
                  child: Text(p.status == SellerProductStatus.active
                      ? 'Deactivate'
                      : 'Activate'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: <Widget>[
              Expanded(
                child: OutlinedButton(
                  onPressed: () => context.push(
                      '/seller/inventory/add-stock-in?productId=$productId'),
                  child: const Text('Stock In'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton(
                  onPressed: () => context.push(
                      '/seller/inventory/add-stock-out?productId=$productId'),
                  child: const Text('Stock Out'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () => context
                .push('/seller/inventory/add-adjustment?productId=$productId'),
            icon: const Icon(Icons.tune_rounded),
            label: const Text('Add Adjustment'),
          ),
        ],
      ),
    );
  }
}

Widget _line(String k, String v) => Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(
              child: Text(k, style: const TextStyle(color: Colors.black54))),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              v,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );

class _ProductHeroGallery extends StatefulWidget {
  const _ProductHeroGallery({required this.urls});

  final List<String> urls;

  @override
  State<_ProductHeroGallery> createState() => _ProductHeroGalleryState();
}

class _ProductHeroGalleryState extends State<_ProductHeroGallery> {
  late final PageController _controller;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _controller = PageController();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final urls = widget.urls.where((url) => url.trim().isNotEmpty).toList();
    if (urls.isEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: AspectRatio(aspectRatio: 16 / 9, child: _placeholder(context)),
      );
    }
    return Column(
      children: <Widget>[
        ClipRRect(
          borderRadius: BorderRadius.circular(18),
          child: AspectRatio(
            aspectRatio: 16 / 9,
            child: Stack(
              fit: StackFit.expand,
              children: <Widget>[
                PageView.builder(
                  controller: _controller,
                  itemCount: urls.length,
                  onPageChanged: (value) => setState(() => _page = value),
                  itemBuilder: (context, index) => Image.network(
                    urls[index],
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _placeholder(context),
                  ),
                ),
                if (urls.length > 1) ...<Widget>[
                  Positioned(
                    top: 10,
                    right: 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.58),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        '${_page + 1}/${urls.length}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 10,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List<Widget>.generate(
                        urls.length,
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
              ],
            ),
          ),
        ),
        if (urls.length > 1) ...<Widget>[
          const SizedBox(height: 10),
          SizedBox(
            height: 54,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: urls.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) => InkWell(
                borderRadius: BorderRadius.circular(10),
                onTap: () {
                  _controller.animateToPage(
                    index,
                    duration: const Duration(milliseconds: 220),
                    curve: Curves.easeOut,
                  );
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: 54,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: index == _page
                          ? Theme.of(context).colorScheme.primary
                          : Theme.of(context).colorScheme.outlineVariant,
                      width: index == _page ? 2 : 1,
                    ),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.network(
                      urls[index],
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _placeholder(context),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }

  Widget _placeholder(BuildContext context) {
    return ColoredBox(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: const Center(child: Icon(Icons.inventory_2_outlined, size: 56)),
    );
  }
}

class _ProductQuickNav extends StatelessWidget {
  const _ProductQuickNav({
    required this.onProducts,
    required this.onInventory,
    required this.onDashboard,
  });

  final VoidCallback onProducts;
  final VoidCallback onInventory;
  final VoidCallback onDashboard;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: OutlinedButton.icon(
            onPressed: onProducts,
            icon: const Icon(Icons.storefront_outlined, size: 18),
            label: const Text('Products'),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: FilledButton.icon(
            onPressed: onInventory,
            icon: const Icon(Icons.inventory_2_outlined, size: 18),
            label: const Text('Inventory'),
          ),
        ),
        const SizedBox(width: 8),
        IconButton.filledTonal(
          tooltip: 'Dashboard',
          onPressed: onDashboard,
          icon: const Icon(Icons.home_outlined),
        ),
      ],
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontWeight: FontWeight.w900),
      ),
    );
  }
}

class _MetricsStrip extends StatelessWidget {
  const _MetricsStrip({required this.product, required this.stockTone});

  final SellerProduct product;
  final Color stockTone;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(child: _MetricBox('Stock', '${product.stock}', stockTone)),
        const SizedBox(width: 8),
        Expanded(
            child: _MetricBox('Views', '${product.views}',
                Theme.of(context).colorScheme.primary)),
        const SizedBox(width: 8),
        Expanded(
            child:
                _MetricBox('Sold', '${product.sold}', const Color(0xFF7C3AED))),
      ],
    );
  }
}

class _MetricBox extends StatelessWidget {
  const _MetricBox(this.label, this.value, this.color);

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label, style: const TextStyle(color: kSellerMuted)),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w900,
              fontSize: 20,
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailsCard extends StatelessWidget {
  const _DetailsCard({
    required this.title,
    required this.children,
    this.trailing,
  });

  final String title;
  final List<Widget> children;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
          const SizedBox(height: 8),
          ...children,
        ],
      ),
    );
  }
}

List<String> _productGalleryUrls(SellerProduct p) {
  final urls = <String>[];
  for (final value in <String>[
    ...p.imageUrls,
    if ((p.imageUrl ?? '').trim().isNotEmpty) p.imageUrl!,
  ]) {
    final trimmed = value.trim();
    if (trimmed.isNotEmpty && !urls.contains(trimmed)) {
      urls.add(trimmed);
    }
  }
  return urls;
}

List<Widget> _warehouseRows(SellerProduct p) {
  if (p.warehouseStocks.isEmpty) {
    return <Widget>[_line('Main Warehouse', '${p.stock}')];
  }
  return p.warehouseStocks.entries
      .map((entry) => _line(entry.key, '${entry.value}'))
      .toList();
}

List<Widget> _attributeRows(SellerProduct p) {
  final rows = <Widget>[];
  for (final entry in p.attributes.entries) {
    final value = _displayAttribute(entry.value);
    if (value.isEmpty) {
      continue;
    }
    rows.add(_line(_labelFromKey(entry.key), value));
  }
  return rows;
}

String _displayAttribute(Object? raw) {
  if (raw == null) {
    return '';
  }
  if (raw is Iterable) {
    return raw
        .map((e) => e.toString().trim())
        .where((e) => e.isNotEmpty)
        .join(', ');
  }
  return raw.toString().trim();
}

String _labelFromKey(String key) {
  return key
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

String _productTypeLabel(String value) {
  return switch (value) {
    'manual_delivery' => 'Service',
    'service' => 'Service',
    'digital' => 'Digital',
    'physical' => 'Physical',
    _ => _labelFromKey(value),
  };
}
