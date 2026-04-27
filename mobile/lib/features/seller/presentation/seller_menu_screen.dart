import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/application/auth_session_controller.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerMenuScreen extends ConsumerWidget {
  const SellerMenuScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return SellerScaffold(
      selectedNavIndex: 4,
      appBar: AppBar(title: const Text('Menu')),
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
                        radius: 28, child: Icon(Icons.person_rounded)),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text('Ashikur Rahman',
                              style: Theme.of(context)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900)),
                          Text('Seller',
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
          _menuTile(context, Icons.person_outline_rounded, 'Store Profile',
              () => context.push('/seller/store-profile')),
          _menuTile(context, Icons.notifications_none_rounded, 'Notifications',
              () => context.push('/seller/notifications')),
          _menuTile(
              context,
              Icons.account_balance_wallet_outlined,
              'Bank & Payment Methods',
              () => context.push('/seller/bank-payment-methods')),
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
          _menuTile(context, Icons.history_toggle_off_rounded,
              'Return & Refund Policy', () => _stub(context)),
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

  void _stub(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Policy editor opens here in a future release.')));
  }

  Widget _menuTile(
      BuildContext context, IconData icon, String label, VoidCallback onTap,
      {bool destructive = false}) {
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
