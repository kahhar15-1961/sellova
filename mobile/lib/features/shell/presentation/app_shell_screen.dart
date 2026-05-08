import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/app_providers.dart';
import '../../../core/realtime/notification_realtime_binding.dart';
import '../../../core/push/push_registration_binding.dart';
import '../../../features/auth/application/auth_session_controller.dart';
import '../../../features/orders/application/chat_unread_provider.dart';
import '../../../features/profile/application/notifications_controller.dart';

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
    final location = GoRouterState.of(context).uri.path;
    final selectedIndex = _selectedIndex(location);
    final inSellerArea = location.startsWith('/seller/');
    final session = ref.watch(authSessionControllerProvider).session;
    ref.watch(notificationRealtimeBindingProvider);
    ref.watch(pushRegistrationBindingProvider);
    final unreadNotifications =
        ref.watch(notificationUnreadCountProvider).valueOrNull ??
            ref.watch(notificationsControllerProvider).unreadCount;
    final chatUnread = ref.watch(chatUnreadCountProvider).valueOrNull ?? 0;
    final hideShellAppBar = inSellerArea ||
        location.startsWith('/profile/admin') ||
        location == '/profile' ||
        location.startsWith('/profile/help') ||
        location.startsWith('/profile/personal') ||
        location.startsWith('/profile/notifications') ||
        location.startsWith('/profile/wishlist') ||
        location.startsWith('/profile/reviews') ||
        location.startsWith('/profile/payment-methods') ||
        location.startsWith('/profile/wallet') ||
        location.startsWith('/chats') ||
        location.startsWith('/home') ||
        location.startsWith('/categories') ||
        location.startsWith('/products') ||
        location.startsWith('/cart') ||
        location == '/orders' ||
        location.startsWith('/checkout/') ||
        location.startsWith('/addresses') ||
        location.startsWith('/order-success') ||
        location.contains('/review') ||
        location.contains('/confirm-delivery') ||
        location.contains('/chat') ||
        location.contains('/track') ||
        location.startsWith('/disputes/create');
    final trackingActive = location.contains('/track');
    final showDynamicIsland = !hideShellAppBar &&
        (chatUnread > 0 || unreadNotifications > 0 || trackingActive);
    final hideBottomNav = inSellerArea ||
        location.startsWith('/profile/help') ||
        location.startsWith('/profile/personal') ||
        location.startsWith('/profile/notifications') ||
        location.startsWith('/profile/wishlist') ||
        location.startsWith('/profile/reviews') ||
        location.startsWith('/profile/payment-methods') ||
        location.startsWith('/chats') ||
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
              centerTitle: false,
              toolbarHeight: 68,
              titleSpacing: 20,
              title: const Text('Sellova'),
              actions: <Widget>[
                if (session?.isPlatformStaff ?? false)
                  _ShellActionButton(
                    tooltip: 'Staff profile',
                    onPressed: () => context.push('/profile/admin'),
                    icon: Icons.admin_panel_settings_outlined,
                  ),
                _ShellActionButton(
                  tooltip: 'Messages',
                  onPressed: () => go('/chats?panel=buyer'),
                  icon: Icons.chat_bubble_outline_rounded,
                  showBadge: chatUnread > 0,
                  badgeColor: const Color(0xFFEA580C),
                ),
                _ShellActionButton(
                  tooltip: 'Notifications',
                  onPressed: () => go('/profile/notifications'),
                  icon: Icons.notifications_none_rounded,
                  showBadge: unreadNotifications > 0,
                  badgeColor: const Color(0xFFDC2626),
                ),
                _ShellActionButton(
                  tooltip: 'Seller Profile',
                  onPressed: () => go('/profile/seller'),
                  icon: Icons.store_outlined,
                ),
                PopupMenuButton<String>(
                  tooltip: 'More',
                  onSelected: (value) async {
                    switch (value) {
                      case '/profile':
                        await go('/profile');
                        return;
                      case 'logout':
                        await ref
                            .read(authSessionControllerProvider.notifier)
                            .logout();
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
      body: Stack(
        children: <Widget>[
          const _ShellBackdrop(),
          child,
          if (showDynamicIsland)
            Positioned(
              top: 10,
              left: 16,
              right: 16,
              child: Center(
                child: _DynamicIslandPill(
                  chatUnread: chatUnread,
                  notificationUnread: unreadNotifications,
                  trackingActive: trackingActive,
                  onOpen: () {
                    if (chatUnread > 0) {
                      go('/chats?panel=buyer');
                    } else if (trackingActive) {
                      go('/orders');
                    } else {
                      go('/profile/notifications');
                    }
                  },
                ),
              ),
            ),
        ],
      ),
      bottomNavigationBar: hideBottomNav
          ? null
          : NavigationBar(
              selectedIndex: selectedIndex,
              height: 72,
              labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
              elevation: 0,
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

class _ShellBackdrop extends StatelessWidget {
  const _ShellBackdrop();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: <Color>[
              Theme.of(context).colorScheme.surface,
              const Color(0xFFF8FAFF),
            ],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: const SizedBox.expand(),
      ),
    );
  }
}

class _ShellActionButton extends StatelessWidget {
  const _ShellActionButton({
    required this.tooltip,
    required this.onPressed,
    required this.icon,
    this.showBadge = false,
    this.badgeColor = const Color(0xFFEA580C),
  });

  final String tooltip;
  final VoidCallback onPressed;
  final IconData icon;
  final bool showBadge;
  final Color badgeColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 2),
      child: Stack(
        clipBehavior: Clip.none,
        children: <Widget>[
          Material(
            color: Theme.of(context)
                .colorScheme
                .surfaceContainerHighest
                .withValues(alpha: 0.72),
            shape: const CircleBorder(),
            child: IconButton(
              tooltip: tooltip,
              onPressed: onPressed,
              icon: Icon(icon),
            ),
          ),
          if (showBadge)
            Positioned(
              right: 6,
              top: 6,
              child: Container(
                width: 8,
                height: 8,
                decoration:
                    BoxDecoration(color: badgeColor, shape: BoxShape.circle),
              ),
            ),
        ],
      ),
    );
  }
}

class _DynamicIslandPill extends StatefulWidget {
  const _DynamicIslandPill({
    required this.chatUnread,
    required this.notificationUnread,
    required this.trackingActive,
    required this.onOpen,
  });

  final int chatUnread;
  final int notificationUnread;
  final bool trackingActive;
  final VoidCallback onOpen;

  @override
  State<_DynamicIslandPill> createState() => _DynamicIslandPillState();
}

class _DynamicIslandPillState extends State<_DynamicIslandPill>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulseController;
  bool _expanded = false;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 360), value: 0);
  }

  @override
  void didUpdateWidget(covariant _DynamicIslandPill oldWidget) {
    super.didUpdateWidget(oldWidget);
    final oldTotal = oldWidget.chatUnread + oldWidget.notificationUnread;
    final nextTotal = widget.chatUnread + widget.notificationUnread;
    if (nextTotal > oldTotal) {
      _pulseController
        ..forward(from: 0)
        ..reverse();
    }
  }

  @override
  void dispose() {
    _pulseController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final total = widget.chatUnread + widget.notificationUnread;
    final activity = widget.trackingActive
        ? _IslandActivity.shipping
        : (widget.chatUnread > 0
            ? _IslandActivity.chat
            : _IslandActivity.notification);
    final label = switch (activity) {
      _IslandActivity.chat =>
        '${widget.chatUnread} new message${widget.chatUnread == 1 ? '' : 's'}',
      _IslandActivity.shipping => 'Live order tracking',
      _IslandActivity.notification =>
        '${widget.notificationUnread} new notification${widget.notificationUnread == 1 ? '' : 's'}',
    };
    final icon = switch (activity) {
      _IslandActivity.chat => Icons.chat_bubble_outline_rounded,
      _IslandActivity.shipping => Icons.local_shipping_outlined,
      _IslandActivity.notification => Icons.notifications_none_rounded,
    };
    final glow = Tween<double>(begin: 0, end: 6).animate(
        CurvedAnimation(parent: _pulseController, curve: Curves.easeOut));

    return AnimatedBuilder(
      animation: _pulseController,
      builder: (context, child) {
        return Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.24),
                blurRadius: glow.value + 10,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: child,
        );
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 260),
        curve: Curves.easeOutCubic,
        constraints: BoxConstraints(maxWidth: _expanded ? 360 : 320),
        child: Material(
          color: const Color(0xFF0F172A),
          borderRadius: BorderRadius.circular(999),
          child: InkWell(
            borderRadius: BorderRadius.circular(999),
            onTap: () {
              if (!_expanded) {
                setState(() => _expanded = true);
                return;
              }
              widget.onOpen();
            },
            onLongPress: () => setState(() => _expanded = !_expanded),
            child: AnimatedSize(
              duration: const Duration(milliseconds: 220),
              curve: Curves.easeOutCubic,
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 200),
                      transitionBuilder: (child, animation) =>
                          FadeTransition(opacity: animation, child: child),
                      child: Icon(icon,
                          key: ValueKey<IconData>(icon),
                          color: Colors.white,
                          size: 16),
                    ),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        label,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: Colors.white, fontWeight: FontWeight.w700),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        total == 0 ? 'LIVE' : '$total',
                        style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                            fontSize: 12),
                      ),
                    ),
                    if (_expanded) ...<Widget>[
                      const SizedBox(width: 8),
                      const Icon(Icons.open_in_new_rounded,
                          size: 14, color: Colors.white70),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

enum _IslandActivity { chat, shipping, notification }
