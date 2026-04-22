import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/withdrawal_list_controller.dart';
import '../data/withdrawal_repository.dart';

class WithdrawalListScreen extends ConsumerStatefulWidget {
  const WithdrawalListScreen({super.key});

  @override
  ConsumerState<WithdrawalListScreen> createState() => _WithdrawalListScreenState();
}

class _WithdrawalListScreenState extends ConsumerState<WithdrawalListScreen> {
  final ScrollController _scrollController = ScrollController();

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    Future<void>.microtask(
      () => ref.read(withdrawalListControllerProvider.notifier).refreshIfStale(),
    );
  }

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
      () async {
        await ref.read(withdrawalListControllerProvider.notifier).initialize();
        final saved = ref.read(withdrawalListControllerProvider.notifier).scrollOffset;
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
    ref.read(withdrawalListControllerProvider.notifier).updateScrollOffset(_scrollController.offset);
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      ref.read(withdrawalListControllerProvider.notifier).loadNextPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(withdrawalListControllerProvider);

    if (state.isInitialLoading && state.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.errorMessage != null && state.items.isEmpty) {
      return _WithdrawalErrorState(
        message: state.errorMessage!,
        onRetry: () => ref.read(withdrawalListControllerProvider.notifier).loadFirstPage(),
      );
    }

    if (state.items.isEmpty) {
      return const _WithdrawalEmptyState();
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(withdrawalListControllerProvider.notifier).refresh(),
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.all(16),
        itemCount: state.items.length + 2,
        itemBuilder: (context, index) {
          if (index == 0) {
            return Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: () => ref.read(withdrawalListControllerProvider.notifier).clearPersistedState(),
                icon: const Icon(Icons.restart_alt),
                label: const Text('Reset state'),
              ),
            );
          }
          final itemIndex = index - 1;
          if (itemIndex == state.items.length) {
            return _LoadMoreFooter(
              isAppending: state.isAppending,
              hasMore: state.hasMore,
            );
          }
          final withdrawal = state.items[itemIndex];
          return _WithdrawalCard(withdrawal: withdrawal);
        },
      ),
    );
  }
}

class _WithdrawalCard extends StatelessWidget {
  const _WithdrawalCard({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          final id = withdrawal.id;
          if (id != null) {
            context.push('/withdrawals/$id');
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
                      'Withdrawal #${withdrawal.id ?? 'unknown'}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  Chip(label: Text(withdrawal.status)),
                ],
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 12,
                runSpacing: 8,
                children: <Widget>[
                  Text('Amount: ${withdrawal.amountLabel}'),
                  Text('Fee: ${withdrawal.feeLabel}'),
                  Text('Net: ${withdrawal.netLabel}'),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Created: ${withdrawal.createdDateLabel}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
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

class _WithdrawalEmptyState extends StatelessWidget {
  const _WithdrawalEmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Icon(Icons.account_balance_wallet_outlined, size: 50),
          const SizedBox(height: 12),
          Text('No withdrawals yet.', style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
    );
  }
}

class _WithdrawalErrorState extends StatelessWidget {
  const _WithdrawalErrorState({
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
