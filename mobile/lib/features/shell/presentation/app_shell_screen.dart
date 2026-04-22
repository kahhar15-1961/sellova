import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

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
            onPressed: () => context.go('/home'),
            icon: const Icon(Icons.storefront_outlined),
          ),
          IconButton(
            tooltip: 'Orders',
            onPressed: () => context.go('/orders'),
            icon: const Icon(Icons.receipt_long_outlined),
          ),
          IconButton(
            tooltip: 'Disputes',
            onPressed: () => context.go('/disputes'),
            icon: const Icon(Icons.gavel_outlined),
          ),
          IconButton(
            tooltip: 'Withdrawals',
            onPressed: () => context.go('/withdrawals'),
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
