import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../disputes/application/dispute_list_controller.dart';
import '../../disputes/data/dispute_repository.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

enum _SellerDisputeTab { open, review, resolved }

class SellerDisputesManagementScreen extends ConsumerStatefulWidget {
  const SellerDisputesManagementScreen({super.key});

  @override
  ConsumerState<SellerDisputesManagementScreen> createState() =>
      _SellerDisputesManagementScreenState();
}

class _SellerDisputesManagementScreenState
    extends ConsumerState<SellerDisputesManagementScreen> {
  final ScrollController _scroll = ScrollController();

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    Future<void>.microtask(
      () => ref.read(disputeListControllerProvider.notifier).refreshIfStale(),
    );
  }

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(() async {
      await ref.read(disputeListControllerProvider.notifier).initialize();
    });
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  void _onScroll() {
    ref
        .read(disputeListControllerProvider.notifier)
        .updateScrollOffset(_scroll.offset);
    if (_scroll.hasClients &&
        _scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
      ref.read(disputeListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(disputeListControllerProvider);
    final openC = state.items
        .where((e) => _matchesTab(_SellerDisputeTab.open, _stage(e)))
        .length;
    final reviewC = state.items
        .where((e) => _matchesTab(_SellerDisputeTab.review, _stage(e)))
        .length;
    final resolvedC = state.items
        .where((e) => _matchesTab(_SellerDisputeTab.resolved, _stage(e)))
        .length;

    return SellerScaffold(
      selectedNavIndex: null,
      appBar: AppBar(
        title: const Text('Disputes'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Refresh',
            onPressed: () {
              HapticFeedback.selectionClick();
              ref.read(disputeListControllerProvider.notifier).refresh();
            },
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(disputeListControllerProvider.notifier).refresh(),
        child: CustomScrollView(
          controller: _scroll,
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: <Widget>[
            SliverToBoxAdapter(
              child: _DisputesHeader(
                total: state.items.length,
                openCount: openC,
                reviewCount: reviewC,
                resolvedCount: resolvedC,
              ),
            ),
            if (state.isInitialLoading && state.items.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator()),
              )
            else if (state.errorMessage != null && state.items.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _DisputeErrorState(
                  message: state.errorMessage!,
                  onRetry: () => ref
                      .read(disputeListControllerProvider.notifier)
                      .loadFirstPage(),
                ),
              )
            else if (state.items.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: _SellerDisputeEmptyState(),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
                sliver: SliverList.separated(
                  itemCount: state.items.length + 1,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (BuildContext context, int i) {
                    if (i == state.items.length) {
                      return _LoadMoreFooter(isAppending: state.isAppending);
                    }
                    return _SellerDisputeRow(dispute: state.items[i]);
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _DisputesHeader extends StatelessWidget {
  const _DisputesHeader({
    required this.total,
    required this.openCount,
    required this.reviewCount,
    required this.resolvedCount,
  });

  final int total;
  final int openCount;
  final int reviewCount;
  final int resolvedCount;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: kSellerPrimaryGradient,
              borderRadius: BorderRadius.circular(24),
              boxShadow: <BoxShadow>[sellerGradientShadow()],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                            color: Colors.white.withValues(alpha: 0.14)),
                      ),
                      child:
                          const Icon(Icons.gavel_rounded, color: Colors.white),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            'DISPUTES MANAGEMENT',
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.72),
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.8,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '$total Disputes',
                            style: theme.textTheme.headlineSmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: const Color(0xFF14B8A6).withValues(alpha: 0.16),
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(
                          color:
                              const Color(0xFF5EEAD4).withValues(alpha: 0.32),
                        ),
                      ),
                      child: Text(
                        '${openCount + reviewCount} Active',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: const Color(0xFFCCFBF1),
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    _SummaryChip(
                      label: 'Open',
                      value: openCount,
                      color: const Color(0xFFF97316),
                    ),
                    _SummaryChip(
                      label: 'Review',
                      value: reviewCount,
                      color: const Color(0xFF38BDF8),
                    ),
                    _SummaryChip(
                      label: 'Resolved',
                      value: resolvedCount,
                      color: const Color(0xFF22C55E),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryChip extends StatelessWidget {
  const _SummaryChip({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            '$label $value',
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: Colors.white.withValues(alpha: 0.84),
                  fontWeight: FontWeight.w800,
                ),
          ),
        ],
      ),
    );
  }
}

_DisputeStage _stage(DisputeDto d) {
  final s = d.status.toLowerCase();
  if (s.contains('review')) return _DisputeStage.review;
  if (s.contains('resolv') || s.contains('close') || s.contains('settl')) {
    return _DisputeStage.resolved;
  }
  if (s.contains('open') || s.contains('new') || s.contains('pending')) {
    return _DisputeStage.open;
  }
  return _DisputeStage.other;
}

enum _DisputeStage { open, review, resolved, other }

bool _matchesTab(_SellerDisputeTab tab, _DisputeStage stage) {
  switch (tab) {
    case _SellerDisputeTab.open:
      return stage == _DisputeStage.open || stage == _DisputeStage.other;
    case _SellerDisputeTab.review:
      return stage == _DisputeStage.review;
    case _SellerDisputeTab.resolved:
      return stage == _DisputeStage.resolved;
  }
}

class _SellerDisputeRow extends StatelessWidget {
  const _SellerDisputeRow({required this.dispute});
  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    final stage = _stage(dispute);
    final style = _statusStyle(stage);
    final orderLabel =
        dispute.orderId != null ? 'ORD-${dispute.orderId}' : 'Order';
    final customer = (dispute.raw['customer_name'] ??
            dispute.raw['buyer_name'] ??
            'Customer')
        .toString();
    final dateStr = dispute.createdAt != null
        ? sellerShortDate(dispute.createdAt!)
        : dispute.createdDateLabel;

    return Container(
      decoration: sellerCardDecoration(Theme.of(context).colorScheme).copyWith(
        borderRadius: BorderRadius.circular(20),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () {
          HapticFeedback.lightImpact();
          final id = dispute.id;
          if (id != null) context.push('/seller/disputes/$id');
        },
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: style.bg,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: style.fg.withValues(alpha: 0.16)),
                ),
                child: Icon(style.icon, color: style.fg),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            orderLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w900),
                          ),
                        ),
                        _StagePill(style: style),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      customer,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      dispute.summary,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: kSellerMuted),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: <Widget>[
                        Icon(Icons.schedule_rounded,
                            size: 15,
                            color: kSellerMuted.withValues(alpha: 0.82)),
                        const SizedBox(width: 5),
                        Text(
                          dateStr,
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall
                              ?.copyWith(
                                  color: kSellerMuted,
                                  fontWeight: FontWeight.w700),
                        ),
                        const Spacer(),
                        Icon(Icons.chevron_right_rounded,
                            color: kSellerMuted.withValues(alpha: 0.72)),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StagePill extends StatelessWidget {
  const _StagePill({required this.style});

  final _StageStyle style;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: style.bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: style.fg.withValues(alpha: 0.18)),
      ),
      child: Text(
        style.label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: style.fg,
              fontWeight: FontWeight.w900,
            ),
      ),
    );
  }
}

class _StageStyle {
  const _StageStyle(this.label, this.bg, this.fg, this.icon);

  final String label;
  final Color bg;
  final Color fg;
  final IconData icon;
}

_StageStyle _statusStyle(_DisputeStage stage) {
  return switch (stage) {
    _DisputeStage.open => const _StageStyle(
        'Open',
        Color(0xFFFFF1E7),
        Color(0xFFEA580C),
        Icons.priority_high_rounded,
      ),
    _DisputeStage.review => const _StageStyle(
        'Review',
        Color(0xFFE0F2FE),
        Color(0xFF0369A1),
        Icons.rate_review_rounded,
      ),
    _DisputeStage.resolved => const _StageStyle(
        'Resolved',
        Color(0xFFDCFCE7),
        Color(0xFF15803D),
        Icons.verified_rounded,
      ),
    _DisputeStage.other => const _StageStyle(
        'Open',
        Color(0xFFFFF1E7),
        Color(0xFFEA580C),
        Icons.priority_high_rounded,
      ),
  };
}

class _SellerDisputeEmptyState extends StatelessWidget {
  const _SellerDisputeEmptyState();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 20, 24, 40),
      child: Center(
        child: Container(
          padding: const EdgeInsets.all(22),
          decoration: sellerCardDecoration(Theme.of(context).colorScheme),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: 70,
                height: 70,
                decoration: const BoxDecoration(
                  color: Color(0xFFEDE9FE),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.gavel_rounded,
                  color: kSellerAccent,
                  size: 34,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'No disputes yet',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: kSellerNavy,
                      fontWeight: FontWeight.w900,
                    ),
              ),
              const SizedBox(height: 6),
              Text(
                'Everything is clear in this queue.',
                textAlign: TextAlign.center,
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium
                    ?.copyWith(color: kSellerMuted),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DisputeErrorState extends StatelessWidget {
  const _DisputeErrorState({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Center(
        child: Container(
          padding: const EdgeInsets.all(22),
          decoration: sellerCardDecoration(Theme.of(context).colorScheme),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              const Icon(Icons.error_outline_rounded,
                  size: 42, color: Color(0xFFDC2626)),
              const SizedBox(height: 12),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium
                    ?.copyWith(color: kSellerMuted),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LoadMoreFooter extends StatelessWidget {
  const _LoadMoreFooter({required this.isAppending});

  final bool isAppending;

  @override
  Widget build(BuildContext context) {
    if (!isAppending) return const SizedBox(height: 8);
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 16),
      child: Center(child: CircularProgressIndicator()),
    );
  }
}
