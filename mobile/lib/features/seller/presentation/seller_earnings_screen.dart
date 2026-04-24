import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerEarningsScreen extends StatefulWidget {
  const SellerEarningsScreen({super.key});

  @override
  State<SellerEarningsScreen> createState() => _SellerEarningsScreenState();
}

class _SellerEarningsScreenState extends State<SellerEarningsScreen> {
  String _periodLabel = 'This Month';

  static const List<double> _chartHeights = <double>[0.35, 0.55, 0.42, 0.7, 0.5, 0.62, 0.48];

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    return SellerScaffold(
      selectedNavIndex: 3,
      appBar: AppBar(
        title: const Text('Earnings'),
        actions: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: PopupMenuButton<String>(
              onSelected: (String v) => setState(() => _periodLabel = v),
              itemBuilder: (BuildContext context) => const <PopupMenuEntry<String>>[
                PopupMenuItem<String>(value: 'This Week', child: Text('This Week')),
                PopupMenuItem<String>(value: 'This Month', child: Text('This Month')),
                PopupMenuItem<String>(value: 'Last 3 Months', child: Text('Last 3 Months')),
              ],
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Text(_periodLabel, style: tt.labelLarge),
                    const Icon(Icons.expand_more_rounded, size: 20),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: <Widget>[
          Container(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: <Color>[Color(0xFF6B52E8), Color(0xFF5E49D1)],
              ),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: kSellerAccent.withValues(alpha: 0.35),
                  blurRadius: 20,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('Total Earnings', style: tt.bodySmall?.copyWith(color: Colors.white70)),
                const SizedBox(height: 6),
                Text('৳ 45,230.00', style: tt.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w900)),
                const SizedBox(height: 16),
                SizedBox(
                  height: 88,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: List<Widget>.generate(_chartHeights.length, (int i) {
                      return Expanded(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 3),
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.35 + i * 0.02),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: SizedBox(height: 88 * _chartHeights[i]),
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
                    Text('1 May', style: tt.bodySmall?.copyWith(color: Colors.white60, fontSize: 11)),
                    Text('8 May', style: tt.bodySmall?.copyWith(color: Colors.white60, fontSize: 11)),
                    Text('15 May', style: tt.bodySmall?.copyWith(color: Colors.white60, fontSize: 11)),
                    Text('22 May', style: tt.bodySmall?.copyWith(color: Colors.white60, fontSize: 11)),
                    Text('29 May', style: tt.bodySmall?.copyWith(color: Colors.white60, fontSize: 11)),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _balanceRow(context, 'Available Balance', '৳ 45,230.00'),
          const SizedBox(height: 10),
          _balanceRow(context, 'Pending Balance', '৳ 12,430.00'),
          const SizedBox(height: 10),
          _balanceRow(context, 'Total Withdrawn', '৳ 85,600.00'),
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
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50)),
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
          Expanded(child: Text(label, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kSellerMuted, fontWeight: FontWeight.w600))),
          Text(value, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900, color: kSellerNavy)),
        ],
      ),
    );
  }
}
