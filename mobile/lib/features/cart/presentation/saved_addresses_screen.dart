import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/address_book_controller.dart';
import '../application/checkout_draft_controller.dart';
import 'cart_ui.dart';

class SavedAddressesScreen extends ConsumerWidget {
  const SavedAddressesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final addresses = ref.watch(savedAddressesProvider);
    final draft = ref.watch(checkoutDraftProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Text('Saved Addresses', style: cartSectionHeading(Theme.of(context).textTheme)),
        centerTitle: true,
        actions: <Widget>[
          IconButton(
            onPressed: () => context.push('/addresses/edit'),
            icon: const Icon(Icons.add_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
          child: Column(
            children: <Widget>[
              Expanded(
                child: ListView.separated(
                  itemCount: addresses.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final a = addresses[index];
                    return Container(
                      padding: const EdgeInsets.all(14),
                      decoration: cartCardDecoration(cs),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Row(
                                  children: <Widget>[
                                    Text(a.title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900, color: kCartNavy)),
                                    if (a.isDefault) ...<Widget>[
                                      const SizedBox(width: 8),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(color: const Color(0xFFEDE9FE), borderRadius: BorderRadius.circular(999)),
                                        child: const Text('Default', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF4F46E5))),
                                      ),
                                    ],
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text(a.line1, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted)),
                                Text(
                                  <String>[a.area, a.city, a.postalCode, a.country].where((e) => e.trim().isNotEmpty).join(', '),
                                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted),
                                ),
                                const SizedBox(height: 4),
                                Text(a.phone, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted)),
                              ],
                            ),
                          ),
                          PopupMenuButton<String>(
                            onSelected: (value) async {
                              if (value == 'edit') {
                                context.push('/addresses/edit?addressId=${Uri.encodeComponent(a.id)}');
                                return;
                              }
                              if (value == 'default') {
                                await ref.read(savedAddressesProvider.notifier).setDefault(a.id);
                                return;
                              }
                              if (value == 'use') {
                                ref.read(checkoutDraftProvider.notifier).selectAddress(a.id);
                                if (context.mounted) context.pop();
                                return;
                              }
                              if (value == 'delete') {
                                await ref.read(savedAddressesProvider.notifier).remove(a.id);
                              }
                            },
                            itemBuilder: (_) => <PopupMenuEntry<String>>[
                              const PopupMenuItem<String>(value: 'edit', child: Text('Edit')),
                              if (!a.isDefault) const PopupMenuItem<String>(value: 'default', child: Text('Set as default')),
                              if (draft != null && draft.addressId != a.id) const PopupMenuItem<String>(value: 'use', child: Text('Use this address')),
                              const PopupMenuItem<String>(value: 'delete', child: Text('Delete')),
                            ],
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF5F3FF),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE9D5FF)),
                ),
                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Icon(Icons.info_outline, color: Color(0xFF4F46E5)),
                    SizedBox(width: 10),
                    Expanded(child: Text('Your addresses are saved securely and only used for your orders.')),
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
