import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/dispute_list_controller.dart';
import '../data/dispute_repository.dart';

const Color _kNavy = Color(0xFF0B1A60);
const Color _kMuted = Color(0xFF64748B);

class DisputeListScreen extends ConsumerStatefulWidget {
  const DisputeListScreen({super.key});

  @override
  ConsumerState<DisputeListScreen> createState() => _DisputeListScreenState();
}

class _DisputeListScreenState extends ConsumerState<DisputeListScreen> {
  final ScrollController _scrollController = ScrollController();
  _DisputeTab _tab = _DisputeTab.open;

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
    Future<void>.microtask(
      () async {
        await ref.read(disputeListControllerProvider.notifier).initialize();
        final saved = ref.read(disputeListControllerProvider.notifier).scrollOffset;
        if (saved > 0 && mounted) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (_scrollController.hasClients) {
              _scrollController.jumpTo(saved.clamp(0, _scrollController.position.maxScrollExtent));
            }
          });
        }
      },
    );
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
    ref.read(disputeListControllerProvider.notifier).updateScrollOffset(_scrollController.offset);
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      ref.read(disputeListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(disputeListControllerProvider);
    final filtered = state.items.where((e) => _matchesTab(_tab, _stageFromDispute(e))).toList();

    if (state.isInitialLoading && state.items.isEmpty) {
      return const _DisputeListSkeleton();
    }

    if (state.errorMessage != null && state.items.isEmpty) {
      return _DisputeErrorState(
        message: state.errorMessage!,
        onRetry: () => ref.read(disputeListControllerProvider.notifier).loadFirstPage(),
      );
    }

    if (state.items.isEmpty) {
      return const _DisputeEmptyState();
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      body: SafeArea(
        bottom: false,
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      'Disputes',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
                    ),
                  ),
                  TextButton.icon(
                    onPressed: () => ref.read(disputeListControllerProvider.notifier).clearPersistedState(),
                    icon: const Icon(Icons.restart_alt),
                    label: const Text('Reset'),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
              child: _DisputeTabs(
                current: _tab,
                onChanged: (v) => setState(() => _tab = v),
              ),
            ),
            Expanded(
              child: filtered.isEmpty
                  ? const _DisputeEmptyState()
                  : RefreshIndicator(
                      onRefresh: () => ref.read(disputeListControllerProvider.notifier).refresh(),
                      child: ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                        itemCount: filtered.length + 1,
                        itemBuilder: (context, index) {
                          if (index == filtered.length) {
                            return _LoadMoreFooter(
                              isAppending: state.isAppending,
                              hasMore: state.hasMore,
                            );
                          }
                          return _DisputeCard(dispute: filtered[index]);
                        },
                      ),
                    ),
            ),
            Padding(
              padding: EdgeInsets.fromLTRB(16, 8, 16, 12 + MediaQuery.paddingOf(context).bottom),
              child: FilledButton(
                onPressed: () => context.push('/disputes/create'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Create New Dispute'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DisputeTabs extends StatelessWidget {
  const _DisputeTabs({required this.current, required this.onChanged});

  final _DisputeTab current;
  final ValueChanged<_DisputeTab> onChanged;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: cs.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(4),
        child: Row(
          children: _DisputeTab.values.map((tab) {
            final selected = tab == current;
            return Expanded(
              child: GestureDetector(
                onTap: () {
                  HapticFeedback.selectionClick();
                  onChanged(tab);
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 140),
                  padding: const EdgeInsets.symmetric(vertical: 9),
                  decoration: BoxDecoration(
                    color: selected ? cs.primary.withValues(alpha: 0.12) : Colors.transparent,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    tab.label,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
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

class _DisputeCard extends StatelessWidget {
  const _DisputeCard({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    final style = _pillStyle(_stageFromDispute(dispute));
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () {
          HapticFeedback.lightImpact();
          final id = dispute.id;
          if (id != null) {
            context.push('/disputes/$id');
          }
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      '#DP${(dispute.id ?? 0).toString().padLeft(4, '0')}',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
                    ),
                  ),
                  _StatusPill(label: style.label, bg: style.bg, fg: style.fg),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Order #${dispute.orderId ?? 'unknown'}',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: _kMuted),
              ),
              const SizedBox(height: 6),
              Text(
                dispute.summary,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 10),
              Text(
                _niceDate(dispute.createdAt),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: _kMuted, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({
    required this.label,
    required this.bg,
    required this.fg,
  });

  final String label;
  final Color bg;
  final Color fg;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: bg,
        border: Border.all(color: fg.withValues(alpha: 0.25)),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: fg,
              fontWeight: FontWeight.w700,
            ),
      ),
    );
  }
}

class _LoadMoreFooter extends StatelessWidget {
  const _LoadMoreFooter({
    required this.isAppending,
    required this.hasMore,
  });

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
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Center(
          child: Text(
            'You have reached the end.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ),
      );
    }
    return const SizedBox(height: 12);
  }
}

class _DisputeEmptyState extends StatelessWidget {
  const _DisputeEmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.gavel_outlined, size: 50),
            const SizedBox(height: 12),
            Text(
              'No disputes in this tab',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900, color: _kNavy),
            ),
          ],
        ),
      ),
    );
  }
}

enum _DisputeTab {
  open('Open'),
  inReview('In Review'),
  resolved('Resolved');

  const _DisputeTab(this.label);
  final String label;
}

enum _DisputeStage { open, review, resolved, other }

class _PillStyle {
  const _PillStyle(this.label, this.bg, this.fg);
  final String label;
  final Color bg;
  final Color fg;
}

_DisputeStage _stageFromDispute(DisputeDto d) {
  final s = d.status.toLowerCase();
  if (s.contains('review')) return _DisputeStage.review;
  if (s.contains('resolv') || s.contains('close') || s.contains('settl')) return _DisputeStage.resolved;
  if (s.contains('open') || s.contains('new') || s.contains('pending')) return _DisputeStage.open;
  return _DisputeStage.other;
}

bool _matchesTab(_DisputeTab tab, _DisputeStage stage) {
  switch (tab) {
    case _DisputeTab.open:
      return stage == _DisputeStage.open || stage == _DisputeStage.other;
    case _DisputeTab.inReview:
      return stage == _DisputeStage.review;
    case _DisputeTab.resolved:
      return stage == _DisputeStage.resolved;
  }
}

_PillStyle _pillStyle(_DisputeStage stage) {
  return switch (stage) {
    _DisputeStage.open => const _PillStyle('Open', Color(0xFFEDE9FE), Color(0xFF4F46E5)),
    _DisputeStage.review => const _PillStyle('In Review', Color(0xFFFFFBEB), Color(0xFFB45309)),
    _DisputeStage.resolved => const _PillStyle('Resolved', Color(0xFFECFDF5), Color(0xFF15803D)),
    _DisputeStage.other => const _PillStyle('Open', Color(0xFFEDE9FE), Color(0xFF4F46E5)),
  };
}

String _niceDate(DateTime? date) {
  if (date == null) return 'Date unavailable';
  final d = date.toLocal();
  const months = <String>['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return '${months[d.month - 1]} ${d.day}, ${d.year}';
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
            FilledButton(
              onPressed: onRetry,
              child: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}

class _DisputeListSkeleton extends StatelessWidget {
  const _DisputeListSkeleton();

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 24),
      children: List<Widget>.generate(
        4,
        (_) => Container(
          height: 104,
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: cs.surfaceContainerHighest.withValues(alpha: 0.5),
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
    );
  }
}
