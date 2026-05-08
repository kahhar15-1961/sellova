import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_page_header.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerEarningsScreen extends ConsumerStatefulWidget {
  const SellerEarningsScreen({super.key});

  @override
  ConsumerState<SellerEarningsScreen> createState() =>
      _SellerEarningsScreenState();
}

class _SellerEarningsScreenState extends ConsumerState<SellerEarningsScreen> {
  String _periodLabel = 'This Month';

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    final orders = ref.watch(sellerOrdersProvider);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    final metrics = _EarningsMetrics.from(orders, _periodLabel);

    return SellerScaffold(
      selectedNavIndex: 3,
      appBar: SellerPanelAppBar(
        title: 'Earnings',
        extraActions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: PopupMenuButton<String>(
              onSelected: (String v) => setState(() => _periodLabel = v),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
              itemBuilder: (BuildContext context) =>
                  const <PopupMenuEntry<String>>[
                PopupMenuItem<String>(
                    value: 'This Week', child: Text('This Week')),
                PopupMenuItem<String>(
                    value: 'This Month', child: Text('This Month')),
                PopupMenuItem<String>(
                    value: 'Last 3 Months', child: Text('Last 3 Months')),
              ],
              child: SellerHeaderActionButton(
                icon: Icons.tune_rounded,
                tooltip: _periodLabel,
                isActive: true,
                onTap: null,
              ),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
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
            const SizedBox(height: 12),
          ],
          Container(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              gradient: kSellerPrimaryGradient,
              boxShadow: <BoxShadow>[sellerGradientShadow(alpha: 0.2)],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('Total Earnings',
                    style: tt.bodySmall?.copyWith(color: Colors.white70)),
                const SizedBox(height: 6),
                Text(_moneyLabel(metrics.totalEarnings),
                    style: tt.headlineSmall?.copyWith(
                        color: Colors.white, fontWeight: FontWeight.w900)),
                const SizedBox(height: 16),
                if (metrics.totalEarnings == 0)
                  Container(
                    height: 88,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                          color: Colors.white.withValues(alpha: 0.16)),
                    ),
                    child: Text(
                      'No earnings yet',
                      style: tt.bodyMedium?.copyWith(color: Colors.white70),
                    ),
                  )
                else
                  SizedBox(
                    height: 88,
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: List<Widget>.generate(
                          metrics.chartFractions.length, (int i) {
                        return Expanded(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 3),
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                color: Colors.white
                                    .withValues(alpha: 0.35 + i * 0.02),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: SizedBox(
                                  height: 88 * metrics.chartFractions[i]),
                            ),
                          ),
                        );
                      }),
                    ),
                  ),
                const SizedBox(height: 10),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: <Widget>[
                    Text('1 May',
                        style: tt.bodySmall
                            ?.copyWith(color: Colors.white60, fontSize: 11)),
                    Text('8 May',
                        style: tt.bodySmall
                            ?.copyWith(color: Colors.white60, fontSize: 11)),
                    Text('15 May',
                        style: tt.bodySmall
                            ?.copyWith(color: Colors.white60, fontSize: 11)),
                    Text('22 May',
                        style: tt.bodySmall
                            ?.copyWith(color: Colors.white60, fontSize: 11)),
                    Text('29 May',
                        style: tt.bodySmall
                            ?.copyWith(color: Colors.white60, fontSize: 11)),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _balanceRow(context, 'Available Balance',
              _moneyLabel(metrics.availableBalance)),
          const SizedBox(height: 10),
          _balanceRow(
              context, 'Pending Balance', _moneyLabel(metrics.pendingBalance)),
          const SizedBox(height: 10),
          _balanceRow(
              context, 'Total Withdrawn', _moneyLabel(metrics.totalWithdrawn)),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: () => context.push('/seller/withdraw'),
            style: FilledButton.styleFrom(
              backgroundColor: kSellerAccent,
              minimumSize: const Size.fromHeight(52),
            ),
            icon: const Icon(Icons.payments_outlined),
            label: const Text('Withdraw funds'),
          ),
          const SizedBox(height: 10),
          OutlinedButton(
            onPressed: () => context.push('/seller/withdraw/history'),
            style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(50)),
            child: const Text('Withdraw history'),
          ),
        ],
      ),
    );
  }

  Widget _balanceRow(BuildContext context, String label, String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Row(
        children: <Widget>[
          Expanded(
              child: Text(label,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: kSellerMuted, fontWeight: FontWeight.w600))),
          Text(value,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w900, color: kSellerNavy)),
        ],
      ),
    );
  }
}

class _EarningsMetrics {
  const _EarningsMetrics({
    required this.totalEarnings,
    required this.availableBalance,
    required this.pendingBalance,
    required this.totalWithdrawn,
    required this.chartFractions,
  });

  final double totalEarnings;
  final double availableBalance;
  final double pendingBalance;
  final double totalWithdrawn;
  final List<double> chartFractions;

  factory _EarningsMetrics.from(List<SellerOrder> orders, String periodLabel) {
    final now = DateTime.now();
    final periodStart = switch (periodLabel) {
      'This Week' => now.subtract(Duration(days: now.weekday - 1)),
      'Last 3 Months' => DateTime(now.year, now.month - 2),
      _ => DateTime(now.year, now.month),
    };
    final filtered = orders.where((order) {
      final date = order.orderDate;
      return !date.isBefore(
              DateTime(periodStart.year, periodStart.month, periodStart.day)) &&
          !date.isAfter(now);
    }).toList();
    final delivered =
        filtered.where((order) => order.status == SellerOrderStatus.delivered);
    final pending = filtered.where((order) =>
        order.status != SellerOrderStatus.delivered &&
        order.status != SellerOrderStatus.cancelled);
    final total =
        delivered.fold<double>(0, (sum, order) => sum + order.totalAmount);
    final pendingTotal =
        pending.fold<double>(0, (sum, order) => sum + order.totalAmount);
    final buckets = List<double>.filled(7, 0);
    for (final order in delivered) {
      final index = order.orderDate.day.clamp(1, 31) * buckets.length ~/ 32;
      buckets[index] += order.totalAmount;
    }
    final maxBucket =
        buckets.fold<double>(0, (max, value) => value > max ? value : max);
    final chart = maxBucket == 0
        ? const <double>[0, 0, 0, 0, 0, 0, 0]
        : buckets.map((value) => (value / maxBucket).clamp(0.12, 1.0)).toList();

    return _EarningsMetrics(
      totalEarnings: total,
      availableBalance: total,
      pendingBalance: pendingTotal,
      totalWithdrawn: 0,
      chartFractions: chart,
    );
  }
}

String _moneyLabel(double value) {
  final rounded = value.toStringAsFixed(2);
  final parts = rounded.split('.');
  return '৳ ${_withCommas(int.parse(parts.first))}.${parts.last}';
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
