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
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        title: const Text('Returns'),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: FutureBuilder<List<ReturnRequestDto>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('Load failed: ${snapshot.error}',
                      textAlign: TextAlign.center),
                ),
              );
            }
            final items = snapshot.data ?? const <ReturnRequestDto>[];
            if (items.isEmpty) {
              return Center(
                child: Text('No returns',
                    style: TextStyle(color: cs.onSurfaceVariant)),
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final item = items[index];
                return Card(
                  elevation: 0,
                  color: cs.surface,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16)),
                  child: ListTile(
                    onTap: () => context.push('/returns/${item.id}'),
                    title: Text('Return #${item.id} • Order #${item.orderId}'),
                    subtitle: Text(
                        '${item.status} • ${item.reasonCode} • ${item.slaStatus}'),
                    trailing: (item.status == 'requested' ||
                            item.status == 'approved')
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
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
