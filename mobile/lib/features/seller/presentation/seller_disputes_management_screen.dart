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
  ConsumerState<SellerDisputesManagementScreen> createState() => _SellerDisputesManagementScreenState();
}

class _SellerDisputesManagementScreenState extends ConsumerState<SellerDisputesManagementScreen> {
  final ScrollController _scroll = ScrollController();
  _SellerDisputeTab _tab = _SellerDisputeTab.open;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    Future<void>.microtask(() => ref.read(disputeListControllerProvider.notifier).refreshIfStale());
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
    _scroll.removeListener(_onScroll);
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    ref.read(disputeListControllerProvider.notifier).updateScrollOffset(_scroll.offset);
    if (_scroll.hasClients && _scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
      ref.read(disputeListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(disputeListControllerProvider);
    final filtered = state.items.where((e) => _matchesTab(_tab, _stage(e))).toList();
    final openC = state.items.where((e) => _matchesTab(_SellerDisputeTab.open, _stage(e))).length;
    final revC = state.items.where((e) => _matchesTab(_SellerDisputeTab.review, _stage(e))).length;

    return SellerScaffold(
      selectedNavIndex: null,
      appBar: AppBar(
        title: const Text('Disputes'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(color: kSellerAccent, borderRadius: BorderRadius.circular(999)),
              child: Text(
                'DISPUTES MANAGEMENT',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w800, letterSpacing: 0.6),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
            child: Text('${state.items.length} Disputes', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900, color: kSellerNavy)),
          ),
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: <Widget>[
                _pillTab(context, _SellerDisputeTab.open, 'Open ($openC)'),
                const SizedBox(width: 8),
                _pillTab(context, _SellerDisputeTab.review, 'Under Review ($revC)'),
                const SizedBox(width: 8),
                _pillTab(context, _SellerDisputeTab.resolved, 'Resolved'),
              ],
            ),
          ),
          Expanded(
            child: state.isInitialLoading && state.items.isEmpty
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
                              FilledButton(onPressed: () => ref.read(disputeListControllerProvider.notifier).loadFirstPage(), child: const Text('Retry')),
                            ],
                          ),
                        ),
                      )
                    : filtered.isEmpty
                        ? Center(child: Text('No disputes in this tab.', style: Theme.of(context).textTheme.titleMedium?.copyWith(color: kSellerMuted)))
                        : RefreshIndicator(
                            onRefresh: () => ref.read(disputeListControllerProvider.notifier).refresh(),
                            child: ListView.builder(
                              controller: _scroll,
                              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                              itemCount: filtered.length + 1,
                              itemBuilder: (BuildContext context, int i) {
                                if (i == filtered.length) {
                                  if (state.isAppending) {
                                    return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()));
                                  }
                                  return const SizedBox(height: 8);
                                }
                                final d = filtered[i];
                                return _SellerDisputeRow(dispute: d);
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _pillTab(BuildContext context, _SellerDisputeTab tab, String label) {
    final selected = _tab == tab;
    return GestureDetector(
      onTap: () => setState(() => _tab = tab),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? kSellerAccent : Colors.transparent,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: selected ? kSellerAccent : const Color(0xFFE5E7EB)),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 13,
            color: selected ? Colors.white : kSellerMuted,
          ),
        ),
      ),
    );
  }
}

_DisputeStage _stage(DisputeDto d) {
  final s = d.status.toLowerCase();
  if (s.contains('review')) return _DisputeStage.review;
  if (s.contains('resolv') || s.contains('close') || s.contains('settl')) return _DisputeStage.resolved;
  if (s.contains('open') || s.contains('new') || s.contains('pending')) return _DisputeStage.open;
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
    final (String label, Color bg, Color fg) = switch (stage) {
      _DisputeStage.open => ('Open', const Color(0xFFFFE4E6), const Color(0xFFBE123C)),
      _DisputeStage.review => ('Under Review', const Color(0xFFE0F2FE), const Color(0xFF0369A1)),
      _DisputeStage.resolved => ('Resolved', const Color(0xFFD1FAE5), const Color(0xFF047857)),
      _DisputeStage.other => ('Open', const Color(0xFFFFE4E6), const Color(0xFFBE123C)),
    };
    final orderLabel = dispute.orderId != null ? 'ORD-${dispute.orderId}' : 'Order';
    final customer = (dispute.raw['customer_name'] ?? dispute.raw['buyer_name'] ?? 'Customer').toString();
    final dateStr = dispute.createdAt != null ? sellerShortDate(dispute.createdAt!) : dispute.createdDateLabel;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () {
          HapticFeedback.lightImpact();
          final id = dispute.id;
          if (id != null) context.push('/seller/disputes/$id');
        },
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(color: const Color(0xFFF3F4F6), borderRadius: BorderRadius.circular(12)),
                child: const Icon(Icons.headphones_rounded),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(orderLabel, style: const TextStyle(fontWeight: FontWeight.w900)),
                    const SizedBox(height: 2),
                    Text(customer, style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    Text(dispute.summary, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
                    const SizedBox(height: 6),
                    Text(dateStr, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
                child: Text(label, style: TextStyle(color: fg, fontWeight: FontWeight.w800, fontSize: 11)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
