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

  int _selectedIndex(String location) {
    if (location.startsWith('/categories')) return 1;
    if (location.startsWith('/cart')) return 2;
    if (location.startsWith('/orders')) return 3;
    if (location.startsWith('/profile')) return 4;
    return 0;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final location = GoRouterState.of(context).matchedLocation;
    final selectedIndex = _selectedIndex(location);
    final inSellerArea = location.startsWith('/seller/');
    final session = ref.watch(authSessionControllerProvider).session;
    final hideShellAppBar = inSellerArea ||
        location.startsWith('/profile/admin') ||
        location == '/profile' ||
        location.startsWith('/profile/help') ||
        location.startsWith('/profile/personal') ||
        location.startsWith('/profile/wishlist') ||
        location.startsWith('/profile/reviews') ||
        location.startsWith('/profile/payment-methods') ||
        location.startsWith('/home') ||
        location.startsWith('/products') ||
        location.startsWith('/cart') ||
        location.startsWith('/checkout/') ||
        location.startsWith('/addresses') ||
        location.startsWith('/order-success') ||
        location.contains('/review') ||
        location.contains('/confirm-delivery') ||
        location.contains('/chat') ||
        location.contains('/track') ||
        location.startsWith('/disputes/create');
    final hideBottomNav = inSellerArea ||
        location.startsWith('/profile/help') ||
        location.startsWith('/profile/personal') ||
        location.startsWith('/profile/wishlist') ||
        location.startsWith('/profile/reviews') ||
        location.startsWith('/profile/payment-methods') ||
        location.startsWith('/profile/admin') ||
        location.startsWith('/checkout/') ||
        location.startsWith('/addresses') ||
        location.startsWith('/order-success') ||
        location.contains('/review') ||
        location.contains('/confirm-delivery') ||
        location.contains('/chat') ||
        location.contains('/track') ||
        location.startsWith('/disputes/create');

    Future<void> go(String route) async {
      await ref.read(navigationStatePersistenceProvider).saveLastRoute(route);
      if (context.mounted) {
        context.go(route);
      }
    }

    return Scaffold(
      appBar: hideShellAppBar
          ? null
          : AppBar(
              title: const Text('Sellova'),
              actions: <Widget>[
                if (session?.isPlatformStaff ?? false)
                  IconButton(
                    tooltip: 'Staff profile',
                    onPressed: () => context.push('/profile/admin'),
                    icon: const Icon(Icons.admin_panel_settings_outlined),
                  ),
                IconButton(
                  tooltip: 'Seller Profile',
                  onPressed: () => go('/profile/seller'),
                  icon: const Icon(Icons.store_outlined),
                ),
                PopupMenuButton<String>(
                  tooltip: 'More',
                  onSelected: (value) async {
                    switch (value) {
                      case '/profile':
                        await go('/profile');
                        return;
                      case 'logout':
                        await ref.read(authSessionControllerProvider.notifier).logout();
                        if (context.mounted) {
                          context.go('/sign-in');
                        }
                        return;
                    }
                  },
                  itemBuilder: (_) => const <PopupMenuEntry<String>>[
                    PopupMenuItem<String>(
                      value: '/profile',
                      child: Text('My Profile'),
                    ),
                    PopupMenuDivider(),
                    PopupMenuItem<String>(
                      value: 'logout',
                      child: Text('Logout'),
                    ),
                  ],
                  icon: const Icon(Icons.more_horiz),
                ),
              ],
            ),
      body: child,
      bottomNavigationBar: hideBottomNav
          ? null
          : NavigationBar(
              selectedIndex: selectedIndex,
              height: 70,
              onDestinationSelected: (index) {
                switch (index) {
                  case 0:
                    go('/home');
                    break;
                  case 1:
                    go('/categories');
                    break;
                  case 2:
                    go('/cart');
                    break;
                  case 3:
                    go('/orders');
                    break;
                  case 4:
                    go('/profile');
                    break;
                }
              },
              destinations: const <NavigationDestination>[
                NavigationDestination(
                  icon: Icon(Icons.home_outlined),
                  selectedIcon: Icon(Icons.home),
                  label: 'Home',
                ),
                NavigationDestination(
                  icon: Icon(Icons.grid_view_outlined),
                  selectedIcon: Icon(Icons.grid_view_rounded),
                  label: 'Categories',
                ),
                NavigationDestination(
                  icon: Icon(Icons.shopping_cart_outlined),
                  selectedIcon: Icon(Icons.shopping_cart),
                  label: 'Cart',
                ),
                NavigationDestination(
                  icon: Icon(Icons.receipt_long_outlined),
                  selectedIcon: Icon(Icons.receipt_long),
                  label: 'Orders',
                ),
                NavigationDestination(
                  icon: Icon(Icons.person_outline),
                  selectedIcon: Icon(Icons.person),
                  label: 'Profile',
                ),
              ],
            ),
    );
  }
}
