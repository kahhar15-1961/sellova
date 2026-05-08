import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_business_controller.dart';
import 'seller_ui.dart';

class SellerPanelAppBar extends ConsumerWidget implements PreferredSizeWidget {
  const SellerPanelAppBar({
    super.key,
    required this.title,
    this.leading,
    this.extraActions = const <Widget>[],
    this.showNotifications = true,
    this.showBuyerHome = true,
    this.showMenu = true,
  });

  final String title;
  final Widget? leading;
  final List<Widget> extraActions;
  final bool showNotifications;
  final bool showBuyerHome;
  final bool showMenu;

  @override
  Size get preferredSize => const Size.fromHeight(58);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unread = ref
        .watch(sellerBusinessControllerProvider)
        .notifications
        .where((notification) => !notification.read)
        .length;

    return AppBar(
      automaticallyImplyLeading: false,
      toolbarHeight: preferredSize.height,
      titleSpacing: 10,
      surfaceTintColor: Colors.transparent,
      backgroundColor: const Color(0xFFF8F9FE),
      leadingWidth: 0,
      leading: const SizedBox.shrink(),
      title: Row(
        children: <Widget>[
          if (leading != null) ...<Widget>[
            leading!,
            const SizedBox(width: 10),
          ],
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontSize: 25,
                    fontWeight: FontWeight.w900,
                    color: const Color(0xFF111827),
                    height: 1.05,
                  ),
            ),
          ),
        ],
      ),
      actions: <Widget>[
        ...extraActions,
        if (showNotifications)
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.notifications_none_rounded,
              tooltip: 'Notifications',
              onTap: () => context.push('/seller/notifications'),
              showBadge: unread > 0,
            ),
          ),
        if (showBuyerHome)
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: SellerHeaderActionButton(
              icon: Icons.shopping_bag_outlined,
              tooltip: 'Buyer home',
              onTap: () => context.go('/home'),
            ),
          ),
        if (showMenu)
          Padding(
            padding: const EdgeInsets.only(right: 10),
            child: SellerHeaderActionButton(
              icon: Icons.menu_rounded,
              tooltip: 'Seller menu',
              onTap: () => context.go('/seller/menu'),
            ),
          ),
      ],
    );
  }
}

class SellerHeaderActionButton extends StatelessWidget {
  const SellerHeaderActionButton({
    super.key,
    required this.icon,
    required this.tooltip,
    this.onTap,
    this.isActive = false,
    this.showBadge = false,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback? onTap;
  final bool isActive;
  final bool showBadge;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: isActive ? kSellerGradientEnd : Colors.white,
        shape: const CircleBorder(),
        child: Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
            InkWell(
              customBorder: const CircleBorder(),
              onTap: onTap,
              child: Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color:
                        isActive ? kSellerGradientEnd : const Color(0xFFE2E8F0),
                  ),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: const Color(0xFF0F172A).withValues(alpha: 0.05),
                      blurRadius: 8,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: Icon(
                  icon,
                  size: 17,
                  color: isActive ? Colors.white : const Color(0xFF1F2937),
                ),
              ),
            ),
            if (showBadge)
              Positioned(
                right: 5,
                top: 5,
                child: Container(
                  width: 7,
                  height: 7,
                  decoration: BoxDecoration(
                    color: const Color(0xFFDC2626),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 1),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
