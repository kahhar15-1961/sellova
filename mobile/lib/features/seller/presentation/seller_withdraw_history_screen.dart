import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../withdrawals/application/withdrawal_list_controller.dart';
import 'seller_ui.dart';

class SellerWithdrawHistoryScreen extends ConsumerStatefulWidget {
  const SellerWithdrawHistoryScreen({super.key});

  @override
  ConsumerState<SellerWithdrawHistoryScreen> createState() => _SellerWithdrawHistoryScreenState();
}

class _SellerWithdrawHistoryScreenState extends ConsumerState<SellerWithdrawHistoryScreen> {
  final ScrollController _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(() async {
      await ref.read(withdrawalListControllerProvider.notifier).initialize();
    });
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll.removeListener(_onScroll);
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    ref.read(withdrawalListControllerProvider.notifier).updateScrollOffset(_scroll.offset);
    if (_scroll.hasClients && _scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
      ref.read(withdrawalListControllerProvider.notifier).loadNextPage();
    }
  }

  String _statusDisplay(String status) {
    final s = status.toLowerCase();
    if (s.contains('complete') || s.contains('paid') || s.contains('success')) return 'Completed';
    if (s.contains('pend') || s.contains('process')) return 'Pending';
    return status;
  }

  Color _statusColor(String display) {
    if (display == 'Completed') return const Color(0xFF10B981);
    if (display == 'Pending') return const Color(0xFFF59E0B);
    return kSellerMuted;
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(withdrawalListControllerProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FB),
      appBar: AppBar(
        title: const Text('Withdraw History'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: state.isInitialLoading && state.items.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : state.errorMessage != null && state.items.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Text(state.errorMessage!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: () => ref.read(withdrawalListControllerProvider.notifier).loadFirstPage(),
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : state.items.isEmpty
                  ? Center(
                      child: Text('No withdrawals yet.', style: Theme.of(context).textTheme.titleMedium),
                    )
                  : RefreshIndicator(
                      onRefresh: () => ref.read(withdrawalListControllerProvider.notifier).refresh(),
                      child: ListView.builder(
                        controller: _scroll,
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                        itemCount: state.items.length + (state.hasMore || state.isAppending ? 1 : 0),
                        itemBuilder: (BuildContext context, int i) {
                          if (i >= state.items.length) {
                            return Padding(
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              child: Center(
                                child: state.isAppending ? const CircularProgressIndicator() : const SizedBox.shrink(),
                              ),
                            );
                          }
                          final w = state.items[i];
                          final date = w.createdAt != null ? sellerShortDate(w.createdAt!) : w.createdDateLabel;
                          final display = _statusDisplay(w.status);
                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: const Color(0xFFE5E7EB)),
                              boxShadow: <BoxShadow>[
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.04),
                                  blurRadius: 12,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: InkWell(
                              onTap: () {
                                final id = w.id;
                                if (id != null) context.push('/withdrawals/$id');
                              },
                              borderRadius: BorderRadius.circular(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  Row(
                                    children: <Widget>[
                                      Text(date, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
                                      const Spacer(),
                                      Text(
                                        w.netLabel.contains('N/A') ? w.amountLabel : w.netLabel,
                                        style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900, color: const Color(0xFF1A1C2E)),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 10),
                                  Row(
                                    children: <Widget>[
                                      Expanded(
                                        child: Text(
                                          w.payoutMethodLabel,
                                          style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF1A1C2E)),
                                        ),
                                      ),
                                      Text(
                                        display,
                                        style: TextStyle(fontWeight: FontWeight.w700, color: _statusColor(display)),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
