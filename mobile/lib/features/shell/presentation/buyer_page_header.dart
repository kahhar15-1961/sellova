import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../features/auth/application/auth_session_controller.dart';
import '../../../features/orders/application/chat_unread_provider.dart';
import '../../../features/profile/application/notifications_controller.dart';

const Color kBuyerHeaderPrimary = Color(0xFF29459E);

class BuyerPageHeader extends ConsumerWidget {
  const BuyerPageHeader({
    super.key,
    required this.title,
    this.leading,
    this.onSearch,
    this.onFilter,
    this.isSearchActive = false,
    this.isFilterActive = false,
    this.showSearch = true,
    this.showFilter = false,
    this.showMessages = true,
    this.showNotifications = true,
    this.showSeller = true,
    this.showMore = true,
  });

  final String title;
  final Widget? leading;
  final VoidCallback? onSearch;
  final VoidCallback? onFilter;
  final bool isSearchActive;
  final bool isFilterActive;
  final bool showSearch;
  final bool showFilter;
  final bool showMessages;
  final bool showNotifications;
  final bool showSeller;
  final bool showMore;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unreadNotifications =
        ref.watch(notificationUnreadCountProvider).valueOrNull ??
            ref.watch(notificationsControllerProvider).unreadCount;
    final chatUnread = ref.watch(chatUnreadCountProvider).valueOrNull ?? 0;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: <Widget>[
        if (leading != null) ...<Widget>[
          leading!,
          const SizedBox(width: 8),
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
        if (showSearch) ...<Widget>[
          BuyerHeaderActionButton(
            icon: isSearchActive ? Icons.close_rounded : Icons.search_rounded,
            onTap: onSearch ?? () {},
            tooltip: isSearchActive ? 'Close search' : 'Search',
            isActive: isSearchActive,
          ),
          const SizedBox(width: 6),
        ],
        if (showFilter) ...<Widget>[
          BuyerHeaderActionButton(
            icon: Icons.tune_rounded,
            onTap: onFilter ?? () {},
            tooltip: 'Filter',
            isActive: isFilterActive,
          ),
          const SizedBox(width: 6),
        ],
        if (showMessages) ...<Widget>[
          BuyerHeaderActionButton(
            icon: Icons.chat_bubble_outline_rounded,
            onTap: () => context.push('/chats?panel=buyer'),
            tooltip: 'Messages',
            showBadge: chatUnread > 0,
            badgeColor: const Color(0xFFEA580C),
          ),
          const SizedBox(width: 6),
        ],
        if (showNotifications) ...<Widget>[
          BuyerHeaderActionButton(
            icon: Icons.notifications_none_rounded,
            onTap: () => context.push('/profile/notifications'),
            tooltip: 'Notifications',
            showBadge: unreadNotifications > 0,
            badgeColor: const Color(0xFFDC2626),
          ),
          const SizedBox(width: 6),
        ],
        if (showSeller) ...<Widget>[
          BuyerHeaderActionButton(
            icon: Icons.store_outlined,
            onTap: () => context.push('/profile/seller'),
            tooltip: 'Seller profile',
          ),
          const SizedBox(width: 6),
        ],
        if (showMore) const BuyerHeaderMoreButton(),
      ],
    );
  }
}

class BuyerHeaderActionButton extends StatelessWidget {
  const BuyerHeaderActionButton({
    super.key,
    required this.icon,
    required this.onTap,
    required this.tooltip,
    this.isActive = false,
    this.showBadge = false,
    this.badgeColor = const Color(0xFFEA580C),
  });

  final IconData icon;
  final VoidCallback onTap;
  final String tooltip;
  final bool isActive;
  final bool showBadge;
  final Color badgeColor;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: isActive ? kBuyerHeaderPrimary : Colors.white,
        shape: const CircleBorder(),
        elevation: 0,
        shadowColor: const Color(0xFF0F172A).withValues(alpha: 0.12),
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
                    color: isActive
                        ? kBuyerHeaderPrimary
                        : const Color(0xFFE2E8F0),
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
                    color: badgeColor,
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

class BuyerHeaderMoreButton extends ConsumerWidget {
  const BuyerHeaderMoreButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return PopupMenuButton<String>(
      tooltip: 'More',
      offset: const Offset(0, 38),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      onSelected: (value) async {
        switch (value) {
          case 'profile':
            context.push('/profile');
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
          value: 'profile',
          child: Text('My Profile'),
        ),
        PopupMenuDivider(),
        PopupMenuItem<String>(
          value: 'logout',
          child: Text('Logout'),
        ),
      ],
      child: Container(
        width: 32,
        height: 32,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(color: const Color(0xFFE2E8F0)),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: const Color(0xFF0F172A).withValues(alpha: 0.05),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: const Icon(
          Icons.more_horiz_rounded,
          size: 18,
          color: Color(0xFF1F2937),
        ),
      ),
    );
  }
}
