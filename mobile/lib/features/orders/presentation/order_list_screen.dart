import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../shell/presentation/buyer_page_header.dart';
import '../application/order_list_controller.dart';
import '../data/order_repository.dart';
import '../domain/order_ui_stage.dart';

const Color _kNavy = Color(0xFF0B1A60);
const Color _kMuted = Color(0xFF64748B);
const Color _kOrderBlue = Color(0xFF29459E);

class OrderListScreen extends ConsumerStatefulWidget {
  const OrderListScreen({super.key});

  @override
  ConsumerState<OrderListScreen> createState() => _OrderListScreenState();
}

class _OrderListScreenState extends ConsumerState<OrderListScreen>
    with WidgetsBindingObserver {
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();
  _OrderTab _tab = _OrderTab.all;
  bool _showSearch = false;
  String _searchQuery = '';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    Future<void>.microtask(
        () => ref.read(orderListControllerProvider.notifier).refreshIfStale());
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    Future<void>.microtask(() async {
      await ref.read(orderListControllerProvider.notifier).initialize();
      final saved = ref.read(orderListControllerProvider.notifier).scrollOffset;
      if (saved > 0 && mounted) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (_scrollController.hasClients) {
            _scrollController.jumpTo(
                saved.clamp(0, _scrollController.position.maxScrollExtent));
          }
        });
      }
    });
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    _searchController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && mounted) {
      Future<void>.microtask(
        () => ref.read(orderListControllerProvider.notifier).refresh(),
      );
    }
  }

  void _onScroll() {
    ref
        .read(orderListControllerProvider.notifier)
        .updateScrollOffset(_scrollController.offset);
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(orderListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(orderListControllerProvider);
    final filtered = state.items
        .where((e) => _matchesTab(_tab, _orderStageFromOrder(e)))
        .where((e) => _matchesSearch(e, _searchQuery))
        .toList();

    if (state.isInitialLoading && state.items.isEmpty) {
      return const _OrderListSkeleton();
    }
    if (state.errorMessage != null && state.items.isEmpty) {
      return _OrderErrorState(
        message: state.errorMessage!,
        onRetry: () =>
            ref.read(orderListControllerProvider.notifier).loadFirstPage(),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFD),
      body: DecoratedBox(
        decoration: const BoxDecoration(color: Color(0xFFF8FAFD)),
        child: SafeArea(
          bottom: false,
          child: Column(
            children: <Widget>[
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 12, 10, 0),
                child: BuyerPageHeader(
                  title: _showSearch ? 'Search Orders' : 'Orders',
                  showFilter: true,
                  isSearchActive: _showSearch,
                  isFilterActive: _tab != _OrderTab.all,
                  onSearch: () {
                    HapticFeedback.selectionClick();
                    setState(() => _showSearch = !_showSearch);
                  },
                  onFilter: _showFilterSheet,
                ),
              ),
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 180),
                child: _showSearch
                    ? Padding(
                        key: const ValueKey<String>('order-search'),
                        padding: const EdgeInsets.fromLTRB(10, 8, 10, 0),
                        child: _OrderSearchField(
                          controller: _searchController,
                          onChanged: (value) =>
                              setState(() => _searchQuery = value),
                          onClear: () {
                            _searchController.clear();
                            setState(() => _searchQuery = '');
                          },
                        ),
                      )
                    : const SizedBox.shrink(),
              ),
              const SizedBox(height: 12),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10),
                child: _OrderTabs(
                  current: _tab,
                  onChanged: (v) => setState(() => _tab = v),
                ),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 220),
                  switchInCurve: Curves.easeOutCubic,
                  switchOutCurve: Curves.easeInCubic,
                  child: filtered.isEmpty
                      ? const _OrderEmptyState()
                      : RefreshIndicator(
                          onRefresh: () => ref
                              .read(orderListControllerProvider.notifier)
                              .refresh(),
                          child: ListView.builder(
                            key: ValueKey<String>(
                              'orders-${filtered.length}-${_tab.name}-$_searchQuery',
                            ),
                            controller: _scrollController,
                            padding: const EdgeInsets.fromLTRB(6, 0, 6, 24),
                            itemCount: filtered.length + 1,
                            itemBuilder: (context, index) {
                              if (index == filtered.length) {
                                return _LoadMoreFooter(
                                    isAppending: state.isAppending,
                                    hasMore: state.hasMore);
                              }
                              return _OrderCard(order: filtered[index]);
                            },
                          ),
                        ),
                ),
              ),
              Container(
                height: 1,
                color: const Color(0xFFE8ECF3),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showFilterSheet() async {
    HapticFeedback.selectionClick();
    final selected = await showModalBottomSheet<_OrderTab>(
      context: context,
      showDragHandle: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                'Filter orders',
                style: Theme.of(sheetContext).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: const Color(0xFF111827),
                    ),
              ),
              const SizedBox(height: 12),
              ..._OrderTab.values.map((tab) {
                final selected = tab == _tab;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    onTap: () => Navigator.of(sheetContext).pop(tab),
                    selected: selected,
                    selectedTileColor: const Color(0xFFEFF6FF),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                      side: BorderSide(
                        color: selected
                            ? const Color(0xFFBFDBFE)
                            : const Color(0xFFE5E7EB),
                      ),
                    ),
                    title: Text(
                      tab.label,
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        color: selected ? _kOrderBlue : const Color(0xFF374151),
                      ),
                    ),
                    trailing: selected
                        ? const Icon(Icons.check_circle_rounded,
                            color: _kOrderBlue)
                        : null,
                  ),
                );
              }),
              const SizedBox(height: 4),
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(sheetContext).pop(_OrderTab.all);
                },
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Show All Orders'),
              ),
            ],
          ),
        ),
      ),
    );

    if (selected != null && mounted) {
      setState(() => _tab = selected);
    }
  }
}

class _OrderSearchField extends StatelessWidget {
  const _OrderSearchField({
    required this.controller,
    required this.onChanged,
    required this.onClear,
  });

  final TextEditingController controller;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      onChanged: onChanged,
      textInputAction: TextInputAction.search,
      decoration: InputDecoration(
        hintText: 'Search by order ID, amount, or status',
        prefixIcon: const Icon(Icons.search_rounded),
        suffixIcon: controller.text.isEmpty
            ? null
            : IconButton(
                onPressed: onClear,
                icon: const Icon(Icons.close_rounded),
              ),
        filled: true,
        fillColor: Colors.white,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: _kOrderBlue, width: 1.2),
        ),
      ),
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
    return SizedBox(
      height: 43,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _OrderTab.values.length,
        separatorBuilder: (_, __) => const SizedBox(width: 9),
        itemBuilder: (context, index) {
          final tab = _OrderTab.values[index];
          final selected = tab == current;
          return GestureDetector(
            onTap: () {
              HapticFeedback.selectionClick();
              onChanged(tab);
            },
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              curve: Curves.easeOutCubic,
              constraints: const BoxConstraints(minWidth: 84),
              padding: const EdgeInsets.symmetric(horizontal: 18),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: selected ? Colors.white : Colors.transparent,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color:
                      selected ? const Color(0xFFE2E8F0) : Colors.transparent,
                ),
                boxShadow: selected
                    ? <BoxShadow>[
                        BoxShadow(
                          color:
                              const Color(0xFF0F172A).withValues(alpha: 0.06),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ]
                    : null,
              ),
              child: Text(
                tab.label,
                textAlign: TextAlign.center,
                style: theme.textTheme.labelLarge?.copyWith(
                  fontWeight: selected ? FontWeight.w900 : FontWeight.w700,
                  color: selected ? const Color(0xFF111827) : _kMuted,
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});
  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final stage = _orderStageFromOrder(order);
    final style = _stageStyle(stage);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: const Color(0xFFE7ECF3)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.055),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(15),
        onTap: () {
          HapticFeedback.lightImpact();
          final id = order.id;
          if (id != null) {
            context.push('/orders/$id');
          }
        },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(17, 16, 14, 15),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      order.orderNumber.startsWith('#')
                          ? order.orderNumber
                          : '#${order.orderNumber}',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w900,
                            color: const Color(0xFF1F2937),
                            height: 1.05,
                          ),
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
                    decoration: BoxDecoration(
                      color: style.bg,
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(
                        color: style.fg.withValues(alpha: 0.16),
                      ),
                    ),
                    child: Text(
                      style.label,
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                            color: style.fg,
                            fontWeight: FontWeight.w900,
                            height: 1,
                          ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                _niceDate(order.createdAt),
                style: Theme.of(context)
                    .textTheme
                    .bodySmall
                    ?.copyWith(color: _kMuted, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 22),
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      _totalMoney(order),
                      style:
                          Theme.of(context).textTheme.headlineSmall?.copyWith(
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                                color: _kNavy,
                                height: 1,
                              ),
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        const Icon(
                          Icons.inventory_2_outlined,
                          size: 15,
                          color: Color(0xFF64748B),
                        ),
                        const SizedBox(width: 5),
                        Text(
                          order.itemSummary,
                          style:
                              Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: _kMuted,
                                    fontWeight: FontWeight.w800,
                                  ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 9),
                  const Icon(
                    Icons.chevron_right_rounded,
                    size: 22,
                    color: Color(0xFFCBD5E1),
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
            style:
                Theme.of(context).textTheme.bodySmall?.copyWith(color: _kMuted),
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
                color: Theme.of(context)
                    .colorScheme
                    .primaryContainer
                    .withValues(alpha: 0.5),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.receipt_long_outlined,
                  size: 36, color: Theme.of(context).colorScheme.primary),
            ),
            const SizedBox(height: 14),
            Text(
              'No orders in this tab',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
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
            border: Border.all(
                color: Theme.of(context)
                    .colorScheme
                    .outlineVariant
                    .withValues(alpha: 0.3)),
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
        color: Theme.of(context)
            .colorScheme
            .surfaceContainerHighest
            .withValues(alpha: 0.55),
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
    OrderUiStage.escrow =>
      const _StageStyle('Paid in Escrow', Color(0xFFEFFCF3), Color(0xFF15803D)),
    OrderUiStage.processing =>
      const _StageStyle('Processing', Color(0xFFEFF6FF), Color(0xFF1D4ED8)),
    OrderUiStage.shipped =>
      const _StageStyle('Shipped', Color(0xFFEDE9FE), Color(0xFF6D28D9)),
    OrderUiStage.delivered =>
      const _StageStyle('Delivered', Color(0xFFECFDF5), Color(0xFF059669)),
    OrderUiStage.completed =>
      const _StageStyle('Completed', Color(0xFFDCFCE7), Color(0xFF15803D)),
    OrderUiStage.disputed =>
      const _StageStyle('Dispute Open', Color(0xFFFFF1F2), Color(0xFFDC2626)),
    OrderUiStage.toPay => const _StageStyle(
        'Awaiting Payment', Color(0xFFFFFBEB), Color(0xFFB45309)),
    OrderUiStage.cancelled =>
      const _StageStyle('Cancelled', Color(0xFFF1F5F9), Color(0xFF475569)),
    OrderUiStage.other =>
      const _StageStyle('Updated', Color(0xFFF1F5F9), Color(0xFF475569)),
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

bool _matchesSearch(OrderDto order, String query) {
  final q = query.trim().toLowerCase();
  if (q.isEmpty) {
    return true;
  }
  final stage = _stageStyle(_orderStageFromOrder(order)).label.toLowerCase();
  final haystack = <String>[
    order.orderNumber,
    order.itemSummary,
    order.totalLabel,
    _totalMoney(order),
    stage,
    (order.raw['payment_status'] ?? '').toString(),
    (order.raw['status'] ?? '').toString(),
  ].join(' ').toLowerCase();
  return haystack.contains(q);
}

OrderUiStage _orderStageFromOrder(OrderDto o) => inferOrderUiStage(o);

String _totalMoney(OrderDto order) {
  final currency = (order.raw['currency'] ?? '').toString().toUpperCase();
  final total = order.raw['total_amount'] ??
      order.raw['gross_amount'] ??
      order.raw['net_amount'] ??
      order.raw['total'];
  if (total == null) return order.totalLabel;
  final n = num.tryParse(total.toString());
  if (n == null) {
    return currency.isEmpty ? total.toString() : '$currency $total';
  }
  final t = n.toStringAsFixed(2);
  return currency == 'USD' ? '\$$t' : (currency.isEmpty ? t : '$currency $t');
}

String _niceDate(DateTime? date) {
  if (date == null) return 'Date unavailable';
  final d = date.toLocal();
  final now = DateTime.now();
  final isToday =
      d.year == now.year && d.month == now.month && d.day == now.day;
  final hh = d.hour > 12 ? d.hour - 12 : (d.hour == 0 ? 12 : d.hour);
  final mm = d.minute.toString().padLeft(2, '0');
  final amPm = d.hour >= 12 ? 'PM' : 'AM';
  if (isToday) return 'Today, $hh:$mm $amPm';
  const months = <String>[
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec'
  ];
  return '${months[d.month - 1]} ${d.day}, ${d.year}';
}
