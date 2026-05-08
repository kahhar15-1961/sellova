import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_page_header.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerProductsScreen extends ConsumerStatefulWidget {
  const SellerProductsScreen({super.key});

  @override
  ConsumerState<SellerProductsScreen> createState() =>
      _SellerProductsScreenState();
}

class _SellerProductsScreenState extends ConsumerState<SellerProductsScreen>
    with WidgetsBindingObserver {
  SellerProductStatus? _tab;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    Future<void>.microtask(
      () => ref.read(sellerProductsProvider.notifier).refresh(),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && mounted) {
      Future<void>.microtask(
        () => ref.read(sellerProductsProvider.notifier).refresh(),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final products = ref.watch(sellerProductsProvider);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    final filtered = _tab == null
        ? products
        : products.where((e) => e.status == _tab).toList();
    return SellerScaffold(
      selectedNavIndex: 2,
      appBar: SellerPanelAppBar(
        title: 'Products',
        extraActions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.inventory_2_outlined,
              tooltip: 'Inventory',
              onTap: () => context.push('/seller/inventory'),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.search_rounded,
              tooltip: 'Search products',
              onTap: () => context.push('/seller/inventory/filter'),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.add_rounded,
              tooltip: 'Add product',
              isActive: true,
              onTap: () => context.push('/seller/products/add/type'),
            ),
          ),
        ],
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: Column(
          children: <Widget>[
            if (error != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: SellerInlineFeedback(
                  message: error,
                  onRetry: () =>
                      ref.read(sellerProductsProvider.notifier).refresh(),
                ),
              ),
            SizedBox(
              height: 48,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                children: <Widget>[
                  _tabBtn(
                      'All', _tab == null, () => setState(() => _tab = null)),
                  _tabBtn('Active', _tab == SellerProductStatus.active,
                      () => setState(() => _tab = SellerProductStatus.active)),
                  _tabBtn(
                      'Inactive',
                      _tab == SellerProductStatus.inactive,
                      () =>
                          setState(() => _tab = SellerProductStatus.inactive)),
                  _tabBtn(
                      'Out of stock',
                      _tab == SellerProductStatus.outOfStock,
                      () => setState(
                          () => _tab = SellerProductStatus.outOfStock)),
                ],
              ),
            ),
            Expanded(
              child: busy && products.isEmpty
                  ? const SellerListSkeleton()
                  : filtered.isEmpty
                      ? SellerEmptyState(
                          title: 'No products yet',
                          subtitle: 'Add your first listing.',
                          ctaLabel: 'Add',
                          onTap: () =>
                              context.push('/seller/products/add/type'),
                        )
                      : RefreshIndicator(
                          onRefresh: () => ref
                              .read(sellerProductsProvider.notifier)
                              .refresh(),
                          child: ListView.builder(
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
                            itemCount: filtered.length,
                            itemBuilder: (_, i) {
                              final p = filtered[i];
                              return Container(
                                margin: const EdgeInsets.only(bottom: 10),
                                decoration: sellerCardDecoration(
                                    Theme.of(context).colorScheme),
                                child: ListTile(
                                  onTap: () =>
                                      context.push('/seller/products/${p.id}'),
                                  leading: ClipRRect(
                                    borderRadius: BorderRadius.circular(12),
                                    child: SizedBox(
                                      width: 54,
                                      height: 54,
                                      child: _ProductThumb(url: p.imageUrl),
                                    ),
                                  ),
                                  title: Text(p.name,
                                      style: const TextStyle(
                                          fontWeight: FontWeight.w800)),
                                  subtitle: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: <Widget>[
                                      Text(_productSubtitle(p)),
                                      const SizedBox(height: 4),
                                      Wrap(
                                        spacing: 6,
                                        runSpacing: 4,
                                        children: _productChips(p),
                                      ),
                                    ],
                                  ),
                                  trailing: Switch(
                                    value:
                                        p.status == SellerProductStatus.active,
                                    onChanged: p.status ==
                                            SellerProductStatus.outOfStock
                                        ? null
                                        : (v) => ref
                                            .read(
                                                sellerProductsProvider.notifier)
                                            .toggleProductActive(p.id, v),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
            ),
            if (busy) const LinearProgressIndicator(minHeight: 2),
          ],
        ),
      ),
    );
  }

  String _productSubtitle(SellerProduct product) {
    final type = product.productType == 'digital'
        ? 'Digital'
        : product.productType == 'manual_delivery'
            ? 'Manual delivery'
            : 'Physical';
    final stock = product.productType == 'physical'
        ? 'Stock ${product.stock}'
        : 'Instant delivery';
    return '${product.priceLabel} • $stock • $type';
  }

  List<Widget> _productChips(SellerProduct product) {
    final attrs = product.attributes;
    final values = <String>[
      if ((attrs['brand'] ?? '').toString().trim().isNotEmpty)
        attrs['brand'].toString(),
      if ((attrs['condition'] ?? '').toString().trim().isNotEmpty)
        attrs['condition'].toString(),
      if ((attrs['warranty_status'] ?? '').toString().trim().isNotEmpty)
        attrs['warranty_status'].toString(),
      if ((attrs['product_location'] ?? '').toString().trim().isNotEmpty)
        attrs['product_location'].toString(),
      if ((attrs['digital_product_kind'] ?? '').toString().trim().isNotEmpty)
        attrs['digital_product_kind'].toString(),
      if ((attrs['subscription_duration'] ?? '').toString().trim().isNotEmpty)
        attrs['subscription_duration'].toString(),
    ];
    return values
        .take(4)
        .map((value) => Chip(
              label: Text(value, overflow: TextOverflow.ellipsis),
              visualDensity: VisualDensity.compact,
              materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ))
        .toList();
  }

  Widget _tabBtn(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
          label: Text(label), selected: selected, onSelected: (_) => onTap()),
    );
  }
}

class _ProductThumb extends StatelessWidget {
  const _ProductThumb({required this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    final value = (url ?? '').trim();
    if (value.isEmpty) {
      return _placeholder(context);
    }
    return Image.network(
      value,
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => _placeholder(context),
    );
  }

  Widget _placeholder(BuildContext context) {
    return ColoredBox(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: const Icon(Icons.inventory_2_outlined),
    );
  }
}
