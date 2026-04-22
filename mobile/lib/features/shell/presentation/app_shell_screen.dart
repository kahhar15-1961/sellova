import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/app_providers.dart';
import '../../../features/auth/application/auth_session_controller.dart';

class AppShellScreen extends ConsumerWidget {
  const AppShellScreen({
    super.key,
    required this.child,
  });

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Sellova'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Products',
            onPressed: () async {
              await ref.read(navigationStatePersistenceProvider).saveLastRoute('/home');
              if (context.mounted) context.go('/home');
            },
            icon: const Icon(Icons.storefront_outlined),
          ),
          IconButton(
            tooltip: 'Orders',
            onPressed: () async {
              await ref.read(navigationStatePersistenceProvider).saveLastRoute('/orders');
              if (context.mounted) context.go('/orders');
            },
            icon: const Icon(Icons.receipt_long_outlined),
          ),
          IconButton(
            tooltip: 'Disputes',
            onPressed: () async {
              await ref.read(navigationStatePersistenceProvider).saveLastRoute('/disputes');
              if (context.mounted) context.go('/disputes');
            },
            icon: const Icon(Icons.gavel_outlined),
          ),
          IconButton(
            tooltip: 'My Profile',
            onPressed: () async {
              await ref.read(navigationStatePersistenceProvider).saveLastRoute('/profile');
              if (context.mounted) context.go('/profile');
            },
            icon: const Icon(Icons.person_outline),
          ),
          IconButton(
            tooltip: 'Seller Profile',
            onPressed: () async {
              await ref.read(navigationStatePersistenceProvider).saveLastRoute('/profile/seller');
              if (context.mounted) context.go('/profile/seller');
            },
            icon: const Icon(Icons.store_outlined),
          ),
          IconButton(
            tooltip: 'Withdrawals',
            onPressed: () async {
              await ref.read(navigationStatePersistenceProvider).saveLastRoute('/withdrawals');
              if (context.mounted) context.go('/withdrawals');
            },
            icon: const Icon(Icons.account_balance_wallet_outlined),
          ),
          TextButton(
            onPressed: () => ref.read(authSessionControllerProvider.notifier).logout(),
            child: const Text('Logout'),
          ),
        ],
      ),
      body: child,
    );
  }
}
