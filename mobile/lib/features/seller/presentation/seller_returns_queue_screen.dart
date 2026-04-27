import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../../orders/data/returns_repository.dart';

class SellerReturnsQueueScreen extends ConsumerStatefulWidget {
  const SellerReturnsQueueScreen({super.key});

  @override
  ConsumerState<SellerReturnsQueueScreen> createState() =>
      _SellerReturnsQueueScreenState();
}

class _SellerReturnsQueueScreenState
    extends ConsumerState<SellerReturnsQueueScreen> {
  late Future<List<ReturnRequestDto>> _future = _load();

  Future<List<ReturnRequestDto>> _load() =>
      ref.read(returnsRepositoryProvider).listSellerReturns();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Returns Queue')),
      body: FutureBuilder<List<ReturnRequestDto>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
                child:
                    Text('Failed to load seller returns: ${snapshot.error}'));
          }
          final items = snapshot.data ?? const <ReturnRequestDto>[];
          if (items.isEmpty) {
            return const Center(child: Text('No return requests'));
          }
          return ListView.separated(
            itemCount: items.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (context, index) {
              final item = items[index];
              return ListTile(
                title: Text('Order #${item.orderId}'),
                subtitle: Text('${item.reasonCode} • ${item.status}'),
                onTap: () => context.push('/returns/${item.id}'),
                trailing: item.status == 'requested'
                    ? Wrap(
                        spacing: 6,
                        children: <Widget>[
                          IconButton(
                            tooltip: 'Approve',
                            onPressed: () => _decide(item.id, 'approve'),
                            icon: const Icon(Icons.check_circle_outline,
                                color: Colors.green),
                          ),
                          IconButton(
                            tooltip: 'Reject',
                            onPressed: () => _decide(item.id, 'reject'),
                            icon: const Icon(Icons.cancel_outlined,
                                color: Colors.red),
                          ),
                        ],
                      )
                    : null,
              );
            },
          );
        },
      ),
    );
  }

  Future<void> _decide(int returnId, String decision) async {
    try {
      await ref
          .read(returnsRepositoryProvider)
          .decide(returnId: returnId, decision: decision);
      if (!mounted) return;
      setState(() => _future = _load());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not submit decision: $error')));
    }
  }
}
