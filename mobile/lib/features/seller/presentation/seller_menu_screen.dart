import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/application/auth_session_controller.dart';
import '../application/seller_business_controller.dart';
import 'seller_access_gate.dart';
import 'seller_page_header.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerMenuScreen extends ConsumerWidget {
  const SellerMenuScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(sellerBusinessControllerProvider);
    final controller = ref.read(sellerBusinessControllerProvider.notifier);
    final unreadNotifications =
        state.notifications.where((e) => !e.read).length;
    final storeName = state.storeSettings.storeName.trim().isEmpty
        ? 'Seller account'
        : state.storeSettings.storeName.trim();

    if (!state.sellerAccessChecked || state.isLoading) {
      return const Scaffold(
        backgroundColor: Color(0xFFF8F9FE),
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (state.errorMessage != null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8F9FE),
        appBar: const SellerPanelAppBar(title: 'Menu', showMenu: false),
        body: SellerAccessGate(
          title: 'Seller menu unavailable',
          message:
              'The seller workspace could not be loaded just now. Retry to restore access.',
          errorMessage: state.errorMessage,
          primaryActionLabel: 'Try again',
          onPrimaryAction: () => controller.load(),
          secondaryActionLabel: 'Back to profile',
          onSecondaryAction: () => context.go('/profile'),
        ),
      );
    }

    if (!state.hasSellerProfile) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8F9FE),
        appBar: const SellerPanelAppBar(title: 'Menu', showMenu: false),
        body: SellerAccessGate(
          title: 'Seller profile required',
          message:
              'The seller menu is available after your seller profile is created. Start onboarding to unlock store tools, payouts, and notifications.',
          primaryActionLabel: 'Start onboarding',
          onPrimaryAction: () => context.push('/seller/onboarding'),
          secondaryActionLabel: 'Back to profile',
          onSecondaryAction: () => context.go('/profile'),
        ),
      );
    }

    return SellerScaffold(
      selectedNavIndex: 4,
      appBar: const SellerPanelAppBar(title: 'Menu', showMenu: false),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: <Widget>[
          Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => context.push('/seller/store-profile'),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: <Widget>[
                    const CircleAvatar(
                        radius: 28, child: Icon(Icons.storefront_rounded)),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(storeName,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900)),
                          Text('Seller account',
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(color: kSellerMuted)),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded,
                        color: Color(0xFFCBD5E1)),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 14),
          _menuTile(context, Icons.shopping_bag_outlined, 'Buyer home',
              () => context.go('/home')),
          _menuTile(context, Icons.person_outline_rounded, 'Store Profile',
              () => context.push('/seller/store-profile')),
          _menuTile(context, Icons.rocket_launch_outlined, 'Onboarding',
              () => context.push('/seller/onboarding')),
          _menuTile(context, Icons.badge_outlined, 'KYC',
              () => context.push('/seller/kyc')),
          _menuTile(
            context,
            Icons.notifications_none_rounded,
            'Notifications',
            () => context.push('/seller/notifications'),
            badgeCount: unreadNotifications,
          ),
          _menuTile(
              context,
              Icons.account_balance_wallet_outlined,
              'Payout Methods',
              () => context.push('/seller/bank-payment-methods')),
          _menuTile(context, Icons.warehouse_outlined, 'Warehouse Management',
              () => context.push('/seller/warehouses')),
          _menuTile(context, Icons.history_rounded, 'Withdraw history',
              () => context.push('/seller/withdraw/history')),
          _menuTile(context, Icons.gavel_rounded, 'Disputes',
              () => context.push('/seller/disputes')),
          _menuTile(context, Icons.star_rate_rounded, 'Reviews',
              () => context.push('/seller/reviews')),
          _menuTile(context, Icons.local_shipping_outlined, 'Shipping Settings',
              () => context.push('/seller/shipping-settings')),
          _menuTile(context, Icons.assignment_return_outlined,
              'Returns & Refund Queue', () => context.push('/seller/returns')),
          _menuTile(
              context,
              Icons.history_toggle_off_rounded,
              'Return & Refund Policy',
              () => context.push('/seller/help-support')),
          _menuTile(context, Icons.settings_outlined, 'Store Settings',
              () => context.push('/seller/store-settings')),
          _menuTile(context, Icons.help_outline_rounded, 'Help & Support',
              () => context.push('/seller/help-support')),
          const SizedBox(height: 8),
          _menuTile(
            context,
            Icons.logout_rounded,
            'Logout',
            () => ref.read(authSessionControllerProvider.notifier).logout(),
            destructive: true,
          ),
        ],
      ),
    );
  }

  Widget _menuTile(
      BuildContext context, IconData icon, String label, VoidCallback onTap,
      {bool destructive = false, int? badgeCount}) {
    final color = destructive ? const Color(0xFFDC2626) : kSellerNavy;
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
            child: Row(
              children: <Widget>[
                Icon(icon, color: color),
                const SizedBox(width: 14),
                Expanded(
                    child: Text(label,
                        style: TextStyle(
                            fontWeight: FontWeight.w600, color: color))),
                if ((badgeCount ?? 0) > 0) ...<Widget>[
                  const SizedBox(width: 8),
                  _UnreadBadgeBubble(count: badgeCount!),
                ],
                const SizedBox(width: 8),
                Icon(Icons.chevron_right_rounded,
                    color: destructive
                        ? color.withValues(alpha: 0.5)
                        : const Color(0xFFCBD5E1)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _UnreadBadgeBubble extends StatelessWidget {
  const _UnreadBadgeBubble({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) {
    final label = count > 99 ? '99+' : '$count';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        gradient: kSellerPrimaryGradient,
        borderRadius: BorderRadius.circular(999),
        boxShadow: <BoxShadow>[
          sellerGradientShadow(alpha: 0.16),
        ],
      ),
      child: Text(
        '$label unread',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.1,
            ),
      ),
    );
  }
}
