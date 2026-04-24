import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerDashboardScreen extends ConsumerWidget {
  const SellerDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orders = ref.watch(sellerOrdersProvider);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    return SellerScaffold(
      selectedNavIndex: 0,
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => context.push('/seller/notifications'),
            icon: const Icon(Icons.notifications_none_rounded),
          ),
          IconButton(
            tooltip: 'Earnings',
            onPressed: () => context.push('/seller/earnings'),
            icon: const Icon(Icons.account_balance_wallet_outlined),
          ),
        ],
      ),
      bottomSheet: busy ? const LinearProgressIndicator(minHeight: 2) : null,
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          if (error != null) ...<Widget>[
            SellerInlineFeedback(
              message: error,
              onRetry: () => ref.read(sellerOrdersProvider.notifier).refresh(),
            ),
            const SizedBox(height: 10),
          ],
          if (busy && orders.isEmpty) ...<Widget>[
            const SellerCardSkeleton(),
            const SellerCardSkeleton(),
            const SellerCardSkeleton(),
            const SizedBox(height: 8),
          ],
          Text('Hello, Ashikur', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900, color: kSellerNavy)),
          const SizedBox(height: 4),
          Text('Welcome back to your store', style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kSellerMuted)),
          const SizedBox(height: 12),
          InkWell(
            onTap: () => context.push('/seller/earnings'),
            borderRadius: BorderRadius.circular(16),
            child: Ink(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: <Color>[Color(0xFF5B4AD9), Color(0xFF4F46E5)]),
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text('Total Balance', style: TextStyle(color: Colors.white70)),
                  SizedBox(height: 6),
                  Text('৳ 45,230.00', style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900)),
                  SizedBox(height: 4),
                  Text('Available for Withdrawal', style: TextStyle(color: Colors.white70)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          GridView.count(
            crossAxisCount: 2,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            children: <Widget>[
              _MetricCard(title: 'Total Sales', value: '৳ 125,430', delta: '+12.5%', onTap: () => context.push('/seller/earnings')),
              _MetricCard(title: 'Total Orders', value: '312', delta: '+8.3%', onTap: () => context.push('/seller/orders')),
              _MetricCard(title: 'Products', value: '24', delta: 'Active', onTap: () => context.push('/seller/products')),
              _MetricCard(title: 'Rating', value: '4.8', delta: 'View reviews', onTap: () => context.push('/seller/reviews')),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(child: Text('Recent Orders', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900))),
              TextButton(onPressed: () => context.push('/seller/orders'), child: const Text('View All')),
            ],
          ),
          ...orders.take(3).map((e) => _RecentOrderTile(order: e)),
          const SizedBox(height: 14),
          FilledButton(
            onPressed: () => context.push('/seller/orders'),
            style: FilledButton.styleFrom(backgroundColor: kSellerAccent, minimumSize: const Size.fromHeight(52)),
            child: const Text('Manage orders'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () => context.push('/seller/disputes'),
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
            child: const Text('Disputes management'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: () => context.push('/seller/withdraw'),
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
            child: const Text('Withdraw funds'),
          ),
        ],
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.title, required this.value, required this.delta, this.onTap});
  final String title;
  final String value;
  final String delta;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) {
    final deltaColor = delta.contains('%') || delta == 'Active' ? const Color(0xFF16A34A) : kSellerAccent;
    final child = Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(title, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
          const Spacer(),
          Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
          Text(delta, style: TextStyle(color: deltaColor, fontWeight: FontWeight.w700)),
        ],
      ),
    );
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          decoration: sellerCardDecoration(Theme.of(context).colorScheme),
          child: child,
        ),
      ),
    );
  }
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
                Expanded(child: Text(order.orderNumber, style: const TextStyle(fontWeight: FontWeight.w700))),
                Text(order.totalLabel, style: const TextStyle(fontWeight: FontWeight.w900)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
