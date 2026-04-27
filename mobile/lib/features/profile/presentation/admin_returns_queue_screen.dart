import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../../orders/data/returns_repository.dart';

class AdminReturnsQueueScreen extends ConsumerStatefulWidget {
  const AdminReturnsQueueScreen({super.key});

  @override
  ConsumerState<AdminReturnsQueueScreen> createState() =>
      _AdminReturnsQueueScreenState();
}

class _AdminReturnsQueueScreenState
    extends ConsumerState<AdminReturnsQueueScreen> {
  late Future<List<ReturnRequestDto>> _future = _load();

  Future<List<ReturnRequestDto>> _load() async {
    return ref.read(returnsRepositoryProvider).listAdminReturns();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Admin Returns Queue')),
      body: FutureBuilder<List<ReturnRequestDto>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
                child: Text('Failed to load admin queue: ${snapshot.error}'));
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
                title: Text('Return #${item.id} • Order #${item.orderId}'),
                subtitle: Text(
                    '${item.status} • ${item.reasonCode} • SLA: ${item.slaStatus}'),
                onTap: () => context.push('/returns/${item.id}'),
                trailing:
                    (item.status == 'requested' || item.status == 'approved')
                        ? TextButton(
                            onPressed: () async {
                              await ref
                                  .read(returnsRepositoryProvider)
                                  .escalateReturn(returnId: item.id);
                              if (!mounted) return;
                              setState(() => _future = _load());
                            },
                            child: const Text('Escalate'),
                          )
                        : null,
              );
            },
          );
        },
      ),
    );
  }
}
