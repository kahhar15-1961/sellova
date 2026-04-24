import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/order_list_controller.dart';
import '../data/order_repository.dart';
import '../domain/order_ui_stage.dart';

const Color _kNavy = Color(0xFF0B1A60);
const Color _kMuted = Color(0xFF64748B);

class OrderListScreen extends ConsumerStatefulWidget {
  const OrderListScreen({super.key});

  @override
  ConsumerState<OrderListScreen> createState() => _OrderListScreenState();
}

class _OrderListScreenState extends ConsumerState<OrderListScreen> {
  final ScrollController _scrollController = ScrollController();
  _OrderTab _tab = _OrderTab.all;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    Future<void>.microtask(() => ref.read(orderListControllerProvider.notifier).refreshIfStale());
  }

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(() async {
      await ref.read(orderListControllerProvider.notifier).initialize();
      final saved = ref.read(orderListControllerProvider.notifier).scrollOffset;
      if (saved > 0 && mounted) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (_scrollController.hasClients) {
            _scrollController.jumpTo(saved.clamp(0, _scrollController.position.maxScrollExtent));
          }
        });
      }
    });
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  void _onScroll() {
    ref.read(orderListControllerProvider.notifier).updateScrollOffset(_scrollController.offset);
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      ref.read(orderListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(orderListControllerProvider);
    final cs = Theme.of(context).colorScheme;
    final filtered = state.items.where((e) => _matchesTab(_tab, _orderStageFromOrder(e))).toList();

    if (state.isInitialLoading && state.items.isEmpty) {
      return const _OrderListSkeleton();
    }
    if (state.errorMessage != null && state.items.isEmpty) {
      return _OrderErrorState(
        message: state.errorMessage!,
        onRetry: () => ref.read(orderListControllerProvider.notifier).loadFirstPage(),
      );
    }

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF4F6FC), Color(0xFFF8F9FE)],
          ),
        ),
        child: SafeArea(
          bottom: false,
          child: Column(
            children: <Widget>[
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                child: _HeaderRow(
                  onReset: () => ref.read(orderListControllerProvider.notifier).clearPersistedState(),
                ),
              ),
              const SizedBox(height: 8),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: _OrderTabs(
                  current: _tab,
                  onChanged: (v) => setState(() => _tab = v),
                ),
              ),
              const SizedBox(height: 8),
              Expanded(
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 220),
                  switchInCurve: Curves.easeOutCubic,
                  switchOutCurve: Curves.easeInCubic,
                  child: filtered.isEmpty
                      ? const _OrderEmptyState()
                      : RefreshIndicator(
                          onRefresh: () => ref.read(orderListControllerProvider.notifier).refresh(),
                          child: ListView.builder(
                            key: ValueKey<String>('orders-${filtered.length}-${_tab.name}'),
                            controller: _scrollController,
                            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                            itemCount: filtered.length + 1,
                            itemBuilder: (context, index) {
                              if (index == filtered.length) {
                                return _LoadMoreFooter(isAppending: state.isAppending, hasMore: state.hasMore);
                              }
                              return _OrderCard(order: filtered[index]);
                            },
                          ),
                        ),
                ),
              ),
              Container(
                height: 1,
                color: cs.outlineVariant.withValues(alpha: 0.3),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HeaderRow extends StatelessWidget {
  const _HeaderRow({required this.onReset});
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            'Orders',
            style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
          ),
        ),
        TextButton.icon(
          onPressed: onReset,
          icon: Icon(Icons.restart_alt_rounded, color: cs.primary),
          label: Text(
            'Reset',
            style: theme.textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
        ),
      ],
    );
  }
}

class _OrderTabs extends StatelessWidget {
  const _OrderTabs({required this.current, required this.onChanged});
  final _OrderTab current;
  final ValueChanged<_OrderTab> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surface.withValues(alpha: 0.94),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: theme.colorScheme.outlineVariant.withValues(alpha: 0.3)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(4),
        child: Row(
          children: _OrderTab.values.map((tab) {
            final selected = tab == current;
            return Expanded(
              child: GestureDetector(
                onTap: () {
                  HapticFeedback.selectionClick();
                  onChanged(tab);
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  decoration: BoxDecoration(
                    color: selected ? theme.colorScheme.primary.withValues(alpha: 0.12) : Colors.transparent,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    tab.label,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.labelMedium?.copyWith(
                      fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                      color: selected ? _kNavy : _kMuted,
                    ),
                  ),
                ),
              ),
            );
          }).toList(),
        ),
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});
  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final stage = _orderStageFromOrder(order);
    final style = _stageStyle(stage);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: cs.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.05),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () {
          HapticFeedback.lightImpact();
          final id = order.id;
          if (id != null) {
            context.push('/orders/$id');
          }
        },
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      order.orderNumber.startsWith('#') ? order.orderNumber : '#${order.orderNumber}',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: style.bg,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      style.label,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(color: style.fg, fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                _niceDate(order.createdAt),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: _kMuted, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      _totalMoney(order),
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            fontSize: 32 - 10,
                            fontWeight: FontWeight.w900,
                            color: _kNavy,
                          ),
                    ),
                  ),
                  Text(
                    order.itemSummary,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(color: _kMuted, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LoadMoreFooter extends StatelessWidget {
  const _LoadMoreFooter({required this.isAppending, required this.hasMore});
  final bool isAppending;
  final bool hasMore;

  @override
  Widget build(BuildContext context) {
    if (isAppending) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (!hasMore) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Center(
          child: Text(
            'No more orders',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(color: _kMuted),
          ),
        ),
      );
    }
    return const SizedBox(height: 8);
  }
}

class _OrderEmptyState extends StatelessWidget {
  const _OrderEmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 82,
              height: 82,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.5),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.receipt_long_outlined, size: 36, color: Theme.of(context).colorScheme.primary),
            ),
            const SizedBox(height: 14),
            Text(
              'No orders in this tab',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
            ),
          ],
        ),
      ),
    );
  }
}

class _OrderErrorState extends StatelessWidget {
  const _OrderErrorState({required this.message, required this.onRetry});
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
            const Icon(Icons.error_outline, size: 44),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }
}

class _OrderListSkeleton extends StatelessWidget {
  const _OrderListSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: List<Widget>.generate(
        4,
        (_) => Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.3)),
          ),
          child: const Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              _SkeletonLine(width: 170, height: 16),
              SizedBox(height: 10),
              _SkeletonLine(width: 120, height: 12),
              SizedBox(height: 10),
              _SkeletonLine(width: 110, height: 22),
            ],
          ),
        ),
      ),
    );
  }
}

class _SkeletonLine extends StatelessWidget {
  const _SkeletonLine({
    required this.width,
    required this.height,
  });

  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(999),
      ),
    );
  }
}

enum _OrderTab {
  all('All'),
  toPay('To Pay'),
  processing('Processing'),
  shipped('Shipped'),
  delivered('Delivered');

  const _OrderTab(this.label);
  final String label;
}

class _StageStyle {
  const _StageStyle(this.label, this.bg, this.fg);
  final String label;
  final Color bg;
  final Color fg;
}

_StageStyle _stageStyle(OrderUiStage stage) {
  return switch (stage) {
    OrderUiStage.escrow => const _StageStyle('Paid in Escrow', Color(0xFFEFFCF3), Color(0xFF15803D)),
    OrderUiStage.processing => const _StageStyle('Processing', Color(0xFFEFF6FF), Color(0xFF1D4ED8)),
    OrderUiStage.shipped => const _StageStyle('Shipped', Color(0xFFEDE9FE), Color(0xFF6D28D9)),
    OrderUiStage.delivered => const _StageStyle('Delivered', Color(0xFFECFDF5), Color(0xFF059669)),
    OrderUiStage.completed => const _StageStyle('Completed', Color(0xFFDCFCE7), Color(0xFF15803D)),
    OrderUiStage.disputed => const _StageStyle('Dispute Open', Color(0xFFFFF1F2), Color(0xFFDC2626)),
    OrderUiStage.toPay => const _StageStyle('Awaiting Payment', Color(0xFFFFFBEB), Color(0xFFB45309)),
    OrderUiStage.cancelled => const _StageStyle('Cancelled', Color(0xFFF1F5F9), Color(0xFF475569)),
    OrderUiStage.other => const _StageStyle('Updated', Color(0xFFF1F5F9), Color(0xFF475569)),
  };
}

bool _matchesTab(_OrderTab tab, OrderUiStage stage) {
  switch (tab) {
    case _OrderTab.all:
      return true;
    case _OrderTab.toPay:
      return stage == OrderUiStage.toPay;
    case _OrderTab.processing:
      return stage == OrderUiStage.processing || stage == OrderUiStage.escrow;
    case _OrderTab.shipped:
      return stage == OrderUiStage.shipped;
    case _OrderTab.delivered:
      return stage == OrderUiStage.delivered || stage == OrderUiStage.completed;
  }
}

OrderUiStage _orderStageFromOrder(OrderDto o) => inferOrderUiStage(o);

String _totalMoney(OrderDto order) {
  final currency = (order.raw['currency'] ?? '').toString().toUpperCase();
  final total = order.raw['total_amount'] ?? order.raw['gross_amount'] ?? order.raw['net_amount'] ?? order.raw['total'];
  if (total == null) return order.totalLabel;
  final n = num.tryParse(total.toString());
  if (n == null) return currency.isEmpty ? total.toString() : '$currency $total';
  final t = n.toStringAsFixed(2);
  return currency == 'USD' ? '\$$t' : (currency.isEmpty ? t : '$currency $t');
}

String _niceDate(DateTime? date) {
  if (date == null) return 'Date unavailable';
  final d = date.toLocal();
  final now = DateTime.now();
  final isToday = d.year == now.year && d.month == now.month && d.day == now.day;
  final hh = d.hour > 12 ? d.hour - 12 : (d.hour == 0 ? 12 : d.hour);
  final mm = d.minute.toString().padLeft(2, '0');
  final amPm = d.hour >= 12 ? 'PM' : 'AM';
  if (isToday) return 'Today, $hh:$mm $amPm';
  const months = <String>['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return '${months[d.month - 1]} ${d.day}, ${d.year}';
}
