import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../application/seller_business_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_access_gate.dart';
import 'seller_page_header.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerDashboardScreen extends ConsumerStatefulWidget {
  const SellerDashboardScreen({super.key});

  @override
  ConsumerState<SellerDashboardScreen> createState() =>
      _SellerDashboardScreenState();
}

class _SellerDashboardScreenState extends ConsumerState<SellerDashboardScreen>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    Future<void>.microtask(() {
      ref.read(sellerOrdersProvider.notifier).refresh();
      ref.read(sellerProductsProvider.notifier).refresh();
      ref.read(sellerBusinessControllerProvider.notifier).load();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && mounted) {
      Future<void>.microtask(() {
        ref.read(sellerOrdersProvider.notifier).refresh();
        ref.read(sellerProductsProvider.notifier).refresh();
        ref.read(sellerBusinessControllerProvider.notifier).load();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final orders = ref.watch(sellerOrdersProvider);
    final products = ref.watch(sellerProductsProvider);
    final reviews = ref.watch(sellerReviewsProvider);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    final sellerState = ref.watch(sellerBusinessControllerProvider);
    final sellerController =
        ref.read(sellerBusinessControllerProvider.notifier);
    final storeName = ref
        .watch(sellerBusinessControllerProvider)
        .storeSettings
        .storeName
        .trim();
    final greetingName = storeName.isEmpty ? 'Seller' : storeName;
    final stats = _SellerDashboardStats.from(
      orders: orders,
      products: products,
      reviews: reviews,
    );

    if (!sellerState.sellerAccessChecked || sellerState.isLoading) {
      return const Scaffold(
        backgroundColor: Color(0xFFF8F9FE),
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (sellerState.errorMessage != null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8F9FE),
        appBar: const SellerPanelAppBar(title: 'Dashboard'),
        body: SellerAccessGate(
          title: 'Seller workspace unavailable',
          message:
              'We could not load the seller workspace right now. Try again to continue.',
          errorMessage: sellerState.errorMessage,
          primaryActionLabel: 'Try again',
          onPrimaryAction: () => sellerController.load(),
          secondaryActionLabel: 'Back to profile',
          onSecondaryAction: () => context.go('/profile'),
        ),
      );
    }

    if (!sellerState.hasSellerProfile) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8F9FE),
        appBar: const SellerPanelAppBar(title: 'Dashboard'),
        body: SellerAccessGate(
          title: 'Seller workspace unavailable',
          message:
              'This account does not have an active seller profile yet. Create your seller profile first, then complete onboarding and KYC to unlock the seller dashboard.',
          primaryActionLabel: 'Start onboarding',
          onPrimaryAction: () => context.push('/seller/onboarding'),
          secondaryActionLabel: 'Back to profile',
          onSecondaryAction: () => context.go('/profile'),
        ),
      );
    }

    return SellerScaffold(
      selectedNavIndex: 0,
      appBar: SellerPanelAppBar(
        title: 'Dashboard',
        extraActions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.account_balance_wallet_outlined,
              tooltip: 'Earnings',
              onTap: () => context.push('/seller/earnings'),
            ),
          ),
        ],
      ),
      bottomSheet: busy ? const LinearProgressIndicator(minHeight: 2) : null,
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
          children: <Widget>[
            if (error != null) ...<Widget>[
              SellerInlineFeedback(
                message: error,
                onRetry: () =>
                    ref.read(sellerOrdersProvider.notifier).refresh(),
              ),
              const SizedBox(height: 10),
            ],
            if (busy && orders.isEmpty) ...<Widget>[
              const SellerCardSkeleton(),
              const SellerCardSkeleton(),
              const SellerCardSkeleton(),
              const SizedBox(height: 8),
            ],
            Container(
              padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
              decoration: BoxDecoration(
                gradient: kSellerPrimaryGradient,
                borderRadius: BorderRadius.circular(24),
                boxShadow: <BoxShadow>[sellerGradientShadow()],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          'Hello, $greetingName',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style:
                              Theme.of(context).textTheme.titleLarge?.copyWith(
                                    fontSize: 25,
                                    fontWeight: FontWeight.w900,
                                    color: Colors.white,
                                  ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Icon(
                        Icons.shield_outlined,
                        color: Colors.white.withValues(alpha: 0.58),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Your store at a glance',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Colors.white.withValues(alpha: 0.82),
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: 26),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: _MiniStat(
                          label: 'Balance',
                          value: _moneyLabel(stats.availableBalance),
                          icon: Icons.info_outline_rounded,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MiniStat(
                            label: 'Orders', value: '${stats.orders}'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 26),
            GridView.count(
              crossAxisCount: 2,
              crossAxisSpacing: 18,
              mainAxisSpacing: 18,
              childAspectRatio: 1.12,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              children: <Widget>[
                _MetricCard(
                    title: 'Sales',
                    value: _moneyLabel(stats.sales),
                    delta: stats.orders == 0 ? 'No sales yet' : 'This month',
                    icon: Icons.trending_up_rounded,
                    iconColor: const Color(0xFF4F46E5),
                    onTap: () => context.push('/seller/earnings')),
                _MetricCard(
                    title: 'Orders',
                    value: '${stats.orders}',
                    delta: stats.orders == 0 ? 'No orders yet' : 'All orders',
                    icon: Icons.format_list_bulleted_rounded,
                    iconColor: const Color(0xFF6366F1),
                    onTap: () => context.push('/seller/orders')),
                _MetricCard(
                    title: 'Products',
                    value: '${stats.liveProducts}',
                    delta:
                        stats.liveProducts == 0 ? 'No live products' : 'Live',
                    icon: Icons.inventory_2_outlined,
                    iconColor: const Color(0xFF10B981),
                    onTap: () => context.push('/seller/products')),
                _MetricCard(
                    title: 'Rating',
                    value: stats.reviewCount == 0
                        ? '0.0'
                        : stats.rating.toStringAsFixed(1),
                    delta: stats.reviewCount == 0 ? 'No reviews' : 'Reviews',
                    icon: Icons.star_border_rounded,
                    iconColor: const Color(0xFFF59E0B),
                    onTap: () => context.push('/seller/reviews')),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: <Widget>[
                Expanded(
                    child: Text('Recent orders',
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900))),
                TextButton(
                    onPressed: () => context.push('/seller/orders'),
                    child: const Text('All')),
              ],
            ),
            if (orders.isEmpty)
              SellerEmptyState(
                title: 'No orders yet',
                subtitle:
                    'New orders will appear here as buyers purchase from your store.',
                ctaLabel: 'View products',
                onTap: () => context.push('/seller/products'),
              )
            else
              ...orders.take(3).map((e) => _RecentOrderTile(order: e)),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: () => context.push('/seller/orders'),
              style: FilledButton.styleFrom(
                  backgroundColor: kSellerAccent,
                  minimumSize: const Size.fromHeight(52)),
              child: const Text('Orders'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () => context.push('/seller/disputes'),
              style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(50)),
              child: const Text('Disputes'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () => context.push('/seller/withdraw'),
              style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(50)),
              child: const Text('Withdraw'),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.title,
    required this.value,
    required this.delta,
    required this.icon,
    required this.iconColor,
    this.onTap,
  });

  final String title;
  final String value;
  final String delta;
  final IconData icon;
  final Color iconColor;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final deltaColor =
        delta.contains('%') || delta == 'Active' || delta == 'Live'
            ? const Color(0xFF16A34A)
            : kSellerAccent;
    final child = Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: kSellerMuted,
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              const SizedBox(width: 8),
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: iconColor.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: iconColor, size: 21),
              ),
            ],
          ),
          const Spacer(),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: const Color(0xFF1F2937),
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 2),
          Text(
            delta,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: deltaColor,
              fontWeight: FontWeight.w800,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          decoration:
              sellerCardDecoration(Theme.of(context).colorScheme).copyWith(
            borderRadius: BorderRadius.circular(16),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: const Color(0xFF64748B).withValues(alpha: 0.08),
                blurRadius: 20,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: child,
        ),
      ),
    );
  }
}

class _SellerDashboardStats {
  const _SellerDashboardStats({
    required this.sales,
    required this.availableBalance,
    required this.orders,
    required this.liveProducts,
    required this.rating,
    required this.reviewCount,
  });

  final double sales;
  final double availableBalance;
  final int orders;
  final int liveProducts;
  final double rating;
  final int reviewCount;

  factory _SellerDashboardStats.from({
    required List<SellerOrder> orders,
    required List<SellerProduct> products,
    required List<SellerReview> reviews,
  }) {
    final delivered =
        orders.where((order) => order.status == SellerOrderStatus.delivered);
    final sales = delivered.fold<double>(
      0,
      (sum, order) => sum + order.totalAmount,
    );
    final rating = reviews.isEmpty
        ? 0.0
        : reviews.fold<int>(0, (sum, review) => sum + review.rating) /
            reviews.length;

    return _SellerDashboardStats(
      sales: sales,
      availableBalance: sales,
      orders: orders.length,
      liveProducts: products
          .where((product) => product.status == SellerProductStatus.active)
          .length,
      rating: rating,
      reviewCount: reviews.length,
    );
  }
}

String _moneyLabel(double value) {
  final rounded = value.round();
  return '৳ ${_withCommas(rounded)}';
}

String _withCommas(int value) {
  final raw = value.toString();
  final buffer = StringBuffer();
  for (var i = 0; i < raw.length; i += 1) {
    final remaining = raw.length - i;
    buffer.write(raw[i]);
    if (remaining > 1 && remaining % 3 == 1) {
      buffer.write(',');
    }
  }
  return buffer.toString();
}

class _RecentOrderTile extends StatelessWidget {
  const _RecentOrderTile({required this.order});
  final SellerOrder order;
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.push('/seller/orders/${order.id}'),
          borderRadius: BorderRadius.circular(16),
          child: Ink(
            padding: const EdgeInsets.all(12),
            decoration: sellerCardDecoration(Theme.of(context).colorScheme),
            child: Row(
              children: <Widget>[
                const CircleAvatar(child: Icon(Icons.shopping_bag_outlined)),
                const SizedBox(width: 10),
                Expanded(
                    child: Text(order.orderNumber,
                        style: const TextStyle(fontWeight: FontWeight.w700))),
                Text(order.totalLabel,
                    style: const TextStyle(fontWeight: FontWeight.w900)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({
    required this.label,
    required this.value,
    this.icon,
  });

  final String label;
  final String value;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.white.withValues(alpha: 0.14)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (icon != null)
                Icon(icon,
                    color: Colors.white.withValues(alpha: 0.5), size: 15),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}
