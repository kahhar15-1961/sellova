import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/payment_methods_controller.dart';
import '../../auth/presentation/auth_ui_constants.dart';

class PaymentMethodsScreen extends ConsumerWidget {
  const PaymentMethodsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final state = ref.watch(paymentMethodsControllerProvider);

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('Payment Methods'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 26),
        children: <Widget>[
          Text(
            'Your saved payment options',
            style: theme.textTheme.bodyMedium?.copyWith(color: const Color(0xFF64748B)),
          ),
          const SizedBox(height: 12),
          ...state.when(
            data: (methods) => methods
                .map(
                  (m) => Card(
                    elevation: 0,
                    color: const Color(0xFFF8F8FC),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    child: ListTile(
                      leading: _MethodIcon(kind: m.kind),
                      title: Text(m.label, style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: Text(m.subtitle),
                      trailing: PopupMenuButton<String>(
                        onSelected: (v) async {
                          if (v == 'default') await ref.read(paymentMethodsControllerProvider.notifier).setDefault(m.id);
                          if (v == 'remove') await ref.read(paymentMethodsControllerProvider.notifier).remove(m.id);
                        },
                        itemBuilder: (_) => <PopupMenuEntry<String>>[
                          if (!m.isDefault) const PopupMenuItem<String>(value: 'default', child: Text('Set as default')),
                          const PopupMenuItem<String>(value: 'remove', child: Text('Remove')),
                        ],
                      ),
                    ),
                  ),
                )
                .toList(),
            loading: () => const <Widget>[Center(child: Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator()))],
            error: (e, _) => <Widget>[
              Container(
                padding: const EdgeInsets.all(12),
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.red.shade100),
                ),
                child: Text('Failed to load payment methods: $e'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            style: FilledButton.styleFrom(
              backgroundColor: kAuthAccentPurple,
              minimumSize: const Size.fromHeight(52),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            onPressed: () async {
              await ref.read(paymentMethodsControllerProvider.notifier).addMockCard();
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('New payment method added.')),
                );
              }
            },
            icon: const Icon(Icons.add),
            label: const Text('Add payment method'),
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF5F3FF),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE9D5FF)),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Icon(Icons.lock_outline, color: kAuthAccentPurple),
                SizedBox(width: 10),
                Expanded(
                  child: Text('Payment details are tokenized and securely handled by our payment providers.'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MethodIcon extends StatelessWidget {
  const _MethodIcon({required this.kind});

  final String kind;

  @override
  Widget build(BuildContext context) {
    if (kind.toLowerCase() == 'bkash') {
      return const CircleAvatar(backgroundColor: Color(0xFFE2136E), child: Text('B', style: TextStyle(color: Colors.white)));
    }
    return const CircleAvatar(backgroundColor: Color(0xFF1D4ED8), child: Icon(Icons.credit_card, color: Colors.white, size: 20));
  }
}
