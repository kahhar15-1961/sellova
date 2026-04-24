import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Shell for primary seller tabs (matches dashboard bottom navigation pattern).
class SellerScaffold extends StatelessWidget {
  const SellerScaffold({
    super.key,
    required this.body,
    this.appBar,
    this.selectedNavIndex,
    this.bottomSheet,
    this.floatingActionButton,
  });

  final PreferredSizeWidget? appBar;
  final Widget body;
  final int? selectedNavIndex;
  final Widget? bottomSheet;
  final Widget? floatingActionButton;

  static void navigateTab(BuildContext context, int index) {
    switch (index) {
      case 0:
        context.go('/seller/dashboard');
        break;
      case 1:
        context.go('/seller/orders');
        break;
      case 2:
        context.go('/seller/products');
        break;
      case 3:
        context.go('/seller/earnings');
        break;
      case 4:
        context.go('/seller/menu');
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: appBar,
      body: body,
      bottomSheet: bottomSheet,
      floatingActionButton: floatingActionButton,
      bottomNavigationBar: selectedNavIndex == null
          ? null
          : NavigationBar(
              selectedIndex: selectedNavIndex!,
              height: 72,
              labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
              onDestinationSelected: (int i) => navigateTab(context, i),
              destinations: const <NavigationDestination>[
                NavigationDestination(
                  icon: Icon(Icons.home_outlined),
                  selectedIcon: Icon(Icons.home_rounded),
                  label: 'Dashboard',
                ),
                NavigationDestination(
                  icon: Icon(Icons.receipt_long_outlined),
                  selectedIcon: Icon(Icons.receipt_long_rounded),
                  label: 'Orders',
                ),
                NavigationDestination(
                  icon: Icon(Icons.inventory_2_outlined),
                  selectedIcon: Icon(Icons.inventory_2_rounded),
                  label: 'Products',
                ),
                NavigationDestination(
                  icon: Icon(Icons.account_balance_wallet_outlined),
                  selectedIcon: Icon(Icons.account_balance_wallet_rounded),
                  label: 'Earnings',
                ),
                NavigationDestination(
                  icon: Icon(Icons.menu_rounded),
                  selectedIcon: Icon(Icons.menu_open_rounded),
                  label: 'Menu',
                ),
              ],
            ),
    );
  }
}
