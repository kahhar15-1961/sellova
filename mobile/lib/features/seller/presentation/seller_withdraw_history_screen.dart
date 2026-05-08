import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../withdrawals/application/withdrawal_list_controller.dart';
import '../../withdrawals/data/withdrawal_repository.dart';
import 'seller_ui.dart';

enum _HistoryFilter { all, completed, pending, underReview }

class SellerWithdrawHistoryScreen extends ConsumerStatefulWidget {
  const SellerWithdrawHistoryScreen({super.key});

  @override
  ConsumerState<SellerWithdrawHistoryScreen> createState() =>
      _SellerWithdrawHistoryScreenState();
}

class _SellerWithdrawHistoryScreenState
    extends ConsumerState<SellerWithdrawHistoryScreen> {
  final ScrollController _scroll = ScrollController();
  _HistoryFilter _filter = _HistoryFilter.all;

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
    _scroll
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  void _onScroll() {
    ref
        .read(withdrawalListControllerProvider.notifier)
        .updateScrollOffset(_scroll.offset);
    if (_scroll.hasClients &&
        _scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
      ref.read(withdrawalListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(withdrawalListControllerProvider);
    final items = state.items
        .where((withdrawal) => _matchesFilter(_filter, withdrawal.status))
        .toList();

    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFB),
      body: SafeArea(
        bottom: false,
        child: Column(
          children: <Widget>[
            _HistoryHeader(
              onBack: () => context.pop(),
              onFilter: () {
                HapticFeedback.selectionClick();
                _showFilterSheet(context);
              },
            ),
            _FilterBar(
              selected: _filter,
              onChanged: (filter) {
                HapticFeedback.selectionClick();
                setState(() => _filter = filter);
              },
            ),
            Expanded(
              child: state.isInitialLoading && state.items.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : state.errorMessage != null && state.items.isEmpty
                      ? _HistoryErrorState(
                          message: state.errorMessage!,
                          onRetry: () => ref
                              .read(withdrawalListControllerProvider.notifier)
                              .loadFirstPage(),
                        )
                      : RefreshIndicator(
                          onRefresh: () => ref
                              .read(withdrawalListControllerProvider.notifier)
                              .refresh(),
                          child: items.isEmpty
                              ? _HistoryEmptyState(filter: _filter)
                              : ListView.separated(
                                  controller: _scroll,
                                  physics:
                                      const AlwaysScrollableScrollPhysics(),
                                  padding:
                                      const EdgeInsets.fromLTRB(14, 18, 14, 24),
                                  itemCount: items.length +
                                      (state.hasMore || state.isAppending
                                          ? 1
                                          : 0),
                                  separatorBuilder: (_, __) =>
                                      const SizedBox(height: 12),
                                  itemBuilder: (BuildContext context, int i) {
                                    if (i >= items.length) {
                                      return _LoadMoreFooter(
                                          isAppending: state.isAppending);
                                    }
                                    return _WithdrawalHistoryCard(
                                      withdrawal: items[i],
                                      onTap: () {
                                        final id = items[i].id;
                                        if (id != null) {
                                          context.push('/withdrawals/$id');
                                        }
                                      },
                                    );
                                  },
                                ),
                        ),
            ),
          ],
        ),
      ),
    );
  }

  void _showFilterSheet(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Filter withdraw history',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 12),
                ..._HistoryFilter.values.map(
                  (filter) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(filter.label),
                    trailing:
                        _filter == filter ? const Icon(Icons.check) : null,
                    onTap: () {
                      setState(() => _filter = filter);
                      Navigator.of(context).pop();
                    },
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _HistoryHeader extends StatelessWidget {
  const _HistoryHeader({
    required this.onBack,
    required this.onFilter,
  });

  final VoidCallback onBack;
  final VoidCallback onFilter;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 10, 14, 8),
      child: Row(
        children: <Widget>[
          _CircleIconButton(
            icon: Icons.arrow_back_ios_new_rounded,
            onTap: onBack,
          ),
          Expanded(
            child: Text(
              'Withdraw History',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: const Color(0xFF171717),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
            ),
          ),
          _CircleIconButton(
            icon: Icons.tune_rounded,
            onTap: onFilter,
          ),
        ],
      ),
    );
  }
}

class _CircleIconButton extends StatelessWidget {
  const _CircleIconButton({
    required this.icon,
    required this.onTap,
  });

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFFAFAFA),
      shape: const CircleBorder(
        side: BorderSide(color: Color(0xFFEDEEF1)),
      ),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 42,
          height: 42,
          child: Icon(icon, size: 18, color: const Color(0xFF1F2937)),
        ),
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.selected,
    required this.onChanged,
  });

  final _HistoryFilter selected;
  final ValueChanged<_HistoryFilter> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.fromLTRB(18, 0, 18, 6),
        itemCount: _HistoryFilter.values.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final filter = _HistoryFilter.values[index];
          final active = selected == filter;
          return _FilterPill(
            label: filter.label,
            active: active,
            onTap: () => onChanged(filter),
          );
        },
      ),
    );
  }
}

class _FilterPill extends StatelessWidget {
  const _FilterPill({
    required this.label,
    required this.active,
    required this.onTap,
  });

  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: active ? const Color(0xFF171717) : Colors.white,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(horizontal: 17, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: active ? const Color(0xFF171717) : const Color(0xFFE3E4E8),
            ),
          ),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: active ? Colors.white : const Color(0xFF6B7280),
                  fontWeight: FontWeight.w900,
                ),
          ),
        ),
      ),
    );
  }
}

class _WithdrawalHistoryCard extends StatelessWidget {
  const _WithdrawalHistoryCard({
    required this.withdrawal,
    required this.onTap,
  });

  final WithdrawalDto withdrawal;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final style = _StatusStyle.from(withdrawal.status);
    final title = _compactPayoutLabel(withdrawal.payoutMethodLabel);
    final amount = withdrawal.netLabel == 'N/A'
        ? withdrawal.amountLabel
        : withdrawal.netLabel;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Ink(
          padding: const EdgeInsets.fromLTRB(16, 16, 14, 16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFEFEFF2)),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: const Color(0xFF111827).withValues(alpha: 0.055),
                blurRadius: 14,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Row(
            children: <Widget>[
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: const Color(0xFFFAFAFA),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFEDEEF1)),
                ),
                child: const Icon(
                  Icons.arrow_outward_rounded,
                  color: Color(0xFFB5B7BE),
                  size: 22,
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
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
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium
                                ?.copyWith(
                                  color: const Color(0xFF18181B),
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Text(
                          amount,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style:
                              Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: const Color(0xFF18181B),
                                    fontWeight: FontWeight.w900,
                                  ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            _formatHistoryDate(withdrawal.createdAt,
                                withdrawal.createdDateLabel),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style:
                                Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: const Color(0xFF6B7280),
                                      fontWeight: FontWeight.w700,
                                    ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        _StatusBadge(style: style),
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

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.style});

  final _StatusStyle style;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: style.background,
        borderRadius: BorderRadius.circular(5),
        border: Border.all(color: style.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(style.icon, size: 12, color: style.foreground),
          const SizedBox(width: 4),
          Text(
            style.label.toUpperCase(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: style.foreground,
                  fontSize: 10,
                  letterSpacing: 0.8,
                  fontWeight: FontWeight.w900,
                ),
          ),
        ],
      ),
    );
  }
}

class _StatusStyle {
  const _StatusStyle({
    required this.label,
    required this.background,
    required this.foreground,
    required this.border,
    required this.icon,
  });

  final String label;
  final Color background;
  final Color foreground;
  final Color border;
  final IconData icon;

  static _StatusStyle from(String raw) {
    final status = raw.toLowerCase().replaceAll('-', '_');
    if (status.contains('complete') ||
        status.contains('paid') ||
        status.contains('success')) {
      return const _StatusStyle(
        label: 'Completed',
        background: Color(0xFFE9FBF4),
        foreground: Color(0xFF047857),
        border: Color(0xFF9FE7CB),
        icon: Icons.check_circle_outline_rounded,
      );
    }
    if (status.contains('approved')) {
      return const _StatusStyle(
        label: 'Approved',
        background: Color(0xFFEAF2FF),
        foreground: Color(0xFF2563EB),
        border: Color(0xFF9EC2FF),
        icon: Icons.shield_outlined,
      );
    }
    if (status.contains('review')) {
      return const _StatusStyle(
        label: 'Under Review',
        background: Color(0xFFFFF7ED),
        foreground: Color(0xFFC2410C),
        border: Color(0xFFFDBA74),
        icon: Icons.info_outline_rounded,
      );
    }
    if (status.contains('request') || status.contains('pend')) {
      return const _StatusStyle(
        label: 'Requested',
        background: Color(0xFFF4F4F5),
        foreground: Color(0xFF52525B),
        border: Color(0xFFD4D4D8),
        icon: Icons.schedule_rounded,
      );
    }
    return const _StatusStyle(
      label: 'Pending',
      background: Color(0xFFFFF7ED),
      foreground: Color(0xFFC2410C),
      border: Color(0xFFFDBA74),
      icon: Icons.schedule_rounded,
    );
  }
}

class _HistoryEmptyState extends StatelessWidget {
  const _HistoryEmptyState({required this.filter});

  final _HistoryFilter filter;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(28, 80, 28, 24),
      children: <Widget>[
        Icon(Icons.account_balance_wallet_outlined,
            size: 46, color: kSellerMuted.withValues(alpha: 0.7)),
        const SizedBox(height: 12),
        Text(
          filter == _HistoryFilter.all
              ? 'No withdrawals yet.'
              : 'No ${filter.label.toLowerCase()} withdrawals.',
          textAlign: TextAlign.center,
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.w900),
        ),
      ],
    );
  }
}

class _HistoryErrorState extends StatelessWidget {
  const _HistoryErrorState({
    required this.message,
    required this.onRetry,
  });

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
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
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
    if (!isAppending) return const SizedBox(height: 16);
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 16),
      child: Center(child: CircularProgressIndicator()),
    );
  }
}

extension on _HistoryFilter {
  String get label {
    switch (this) {
      case _HistoryFilter.all:
        return 'All';
      case _HistoryFilter.completed:
        return 'Completed';
      case _HistoryFilter.pending:
        return 'Pending';
      case _HistoryFilter.underReview:
        return 'Under Review';
    }
  }
}

bool _matchesFilter(_HistoryFilter filter, String status) {
  final normalized = status.toLowerCase().replaceAll('-', '_');
  switch (filter) {
    case _HistoryFilter.all:
      return true;
    case _HistoryFilter.completed:
      return normalized.contains('complete') ||
          normalized.contains('paid') ||
          normalized.contains('success');
    case _HistoryFilter.pending:
      return normalized.contains('request') ||
          normalized.contains('pend') ||
          normalized.contains('approved');
    case _HistoryFilter.underReview:
      return normalized.contains('review');
  }
}

String _formatHistoryDate(DateTime? date, String fallback) {
  if (date == null) return fallback;
  final local = date.toLocal();
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
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '${local.day} ${months[local.month - 1]}, ${local.year} • $hour:$minute';
}

String _compactPayoutLabel(String value) {
  final trimmed = value.trim();
  if (trimmed.isEmpty) return 'Payout method unavailable';
  if (trimmed.length <= 22) return trimmed;
  return '${trimmed.substring(0, 20)}...';
}
