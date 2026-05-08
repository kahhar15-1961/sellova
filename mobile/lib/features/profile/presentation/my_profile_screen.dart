import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/application/auth_session_controller.dart';
import '../application/notifications_controller.dart';
import '../application/my_profile_controller.dart';
import '../data/profile_repository.dart';

class MyProfileScreen extends ConsumerStatefulWidget {
  const MyProfileScreen({super.key});

  @override
  ConsumerState<MyProfileScreen> createState() => _MyProfileScreenState();
}

class _MyProfileScreenState extends ConsumerState<MyProfileScreen> {
  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
        () => ref.read(myProfileControllerProvider.notifier).load());
  }

  String _displayName(ActorProfileDto? p) {
    if (p == null) {
      return 'User';
    }
    final n = p.displayName.trim();
    if (n.isEmpty || n == 'Unnamed user') {
      final e = p.email;
      if (e.contains('@')) {
        return e.split('@').first;
      }
      return 'User';
    }
    return n;
  }

  String _phoneLine(ActorProfileDto? p) {
    if (p == null) {
      return '';
    }
    final ph = p.phone.trim();
    if (ph.isEmpty) {
      return 'No phone on file';
    }
    return ph;
  }

  String _initials(ActorProfileDto? p) {
    final name = _displayName(p);
    final parts =
        name.split(RegExp(r'\s+')).where((s) => s.isNotEmpty).toList();
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    if (name.isNotEmpty) {
      return name.substring(0, 1).toUpperCase();
    }
    return 'U';
  }

  Future<void> _logout() async {
    final ok = await showDialog<bool>(
          context: context,
          builder: (BuildContext ctx) => AlertDialog(
            title: const Text('Sign out?'),
            content: const Text('You will need to sign in again.'),
            actions: <Widget>[
              TextButton(
                  onPressed: () => Navigator.of(ctx).pop(false),
                  child: const Text('Cancel')),
              FilledButton(
                style: FilledButton.styleFrom(
                    backgroundColor: Colors.red.shade700),
                onPressed: () => Navigator.of(ctx).pop(true),
                child: const Text('Sign out'),
              ),
            ],
          ),
        ) ??
        false;
    if (!ok || !mounted) {
      return;
    }
    await ref.read(authSessionControllerProvider.notifier).logout();
    if (!mounted) {
      return;
    }
    context.go('/sign-in');
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(myProfileControllerProvider);
    final profile = state.profile;
    final staff =
        ref.watch(authSessionControllerProvider).session?.isPlatformStaff ??
            false;
    final unreadNotifications =
        ref.watch(notificationsControllerProvider).unreadCount;
    final theme = Theme.of(context);

    if (state.isLoading && profile == null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF4F7FD),
        appBar: AppBar(
          title: const Text('My Profile'),
          centerTitle: false,
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (state.errorMessage != null && profile == null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF4F7FD),
        appBar: AppBar(
          title: const Text('My Profile'),
          centerTitle: false,
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: const Color(0xFFD9E2EF)),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 24,
                    offset: const Offset(0, 12),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Icon(Icons.error_outline,
                      size: 48, color: theme.colorScheme.error),
                  const SizedBox(height: 12),
                  Text(state.errorMessage!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () =>
                        ref.read(myProfileControllerProvider.notifier).load(),
                    child: const Text('Try again'),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF4F7FD),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.go('/home'),
        ),
        title: const Text('My Profile'),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: <Color>[
              Color(0xFFF4F7FD),
              Color(0xFFF8FAFF),
              Color(0xFFFFFFFF),
            ],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: RefreshIndicator(
          onRefresh: () =>
              ref.read(myProfileControllerProvider.notifier).load(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            children: <Widget>[
              _ProfileHeroCard(
                profile: profile,
                initials: _initials(profile),
                displayName: _displayName(profile),
                phoneLine: _phoneLine(profile),
                unreadNotifications: unreadNotifications,
                isStaff: staff,
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.96),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: const Color(0xFFD9E2EF)),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.05),
                      blurRadius: 22,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Column(
                  children: <Widget>[
                    if (staff)
                      _ProfileMenuTile(
                        icon: Icons.admin_panel_settings_outlined,
                        title: 'Staff',
                        subtitle: 'Admin access',
                        onTap: () => context.push('/profile/admin'),
                      ),
                    _ProfileMenuTile(
                      icon: Icons.person_outline_rounded,
                      title: 'Personal Info',
                      onTap: () => context.push('/profile/personal'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.location_on_outlined,
                      title: 'Address Book',
                      onTap: () => context.push('/addresses'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.credit_card_outlined,
                      title: 'Payments',
                      onTap: () => context.push('/profile/payment-methods'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.account_balance_wallet_outlined,
                      title: 'Wallet',
                      subtitle: 'Balance and history',
                      onTap: () => context.push('/profile/wallet'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.star_border_rounded,
                      title: 'Reviews',
                      onTap: () => context.push('/profile/reviews'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.favorite_border_rounded,
                      title: 'Wishlist',
                      onTap: () => context.push('/profile/wishlist'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.notifications_none_rounded,
                      title: 'Alerts',
                      badgeCount: unreadNotifications,
                      onTap: () => context.push('/profile/notifications'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.chat_bubble_outline_rounded,
                      title: 'Chats',
                      onTap: () => context.push('/chats'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.help_outline_rounded,
                      title: 'Help',
                      onTap: () => context.push('/profile/help'),
                    ),
                    _ProfileMenuTile(
                      icon: Icons.storefront_outlined,
                      title: 'Seller',
                      subtitle: 'Store tools',
                      onTap: () => context.push('/profile/seller'),
                    ),
                    const SizedBox(height: 8),
                    const Divider(height: 24),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(Icons.logout_rounded,
                          color: Colors.red.shade700),
                      title: Text(
                        'Logout',
                        style: TextStyle(
                            color: Colors.red.shade700,
                            fontWeight: FontWeight.w700),
                      ),
                      onTap: _logout,
                    ),
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

class _ProfileHeroCard extends StatelessWidget {
  const _ProfileHeroCard({
    required this.profile,
    required this.initials,
    required this.displayName,
    required this.phoneLine,
    required this.unreadNotifications,
    required this.isStaff,
  });

  final ActorProfileDto? profile;
  final String initials;
  final String displayName;
  final String phoneLine;
  final int unreadNotifications;
  final bool isStaff;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: const LinearGradient(
          colors: <Color>[
            Color(0xFF123C8D),
            Color(0xFF1D4ED8),
            Color(0xFF3B82F6),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF123C8D).withValues(alpha: 0.22),
            blurRadius: 28,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              CircleAvatar(
                radius: 30,
                backgroundColor: Colors.white.withValues(alpha: 0.16),
                child: Text(
                  initials,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Dashboard',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                            color: Colors.white.withValues(alpha: 0.82),
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      displayName,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    if (phoneLine.isNotEmpty) ...<Widget>[
                      const SizedBox(height: 2),
                      Text(
                        phoneLine,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.84),
                            ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: <Widget>[
              _HeroStatPill(
                key: const ValueKey('profile-role'),
                icon: isStaff
                    ? Icons.verified_user_outlined
                    : Icons.shopping_bag_outlined,
                label: isStaff ? 'Staff' : 'Buyer',
              ),
              _HeroStatPill(
                key: const ValueKey('profile-alerts'),
                icon: Icons.notifications_none_rounded,
                label: unreadNotifications > 0
                    ? '$unreadNotifications new'
                    : 'Clear',
              ),
              const _HeroStatPill(
                key: ValueKey('profile-protected'),
                icon: Icons.security_outlined,
                label: 'Secure',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeroStatPill extends StatelessWidget {
  const _HeroStatPill({
    super.key,
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 14, color: Colors.white),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
          ),
        ],
      ),
    );
  }
}

class _ProfileMenuTile extends StatelessWidget {
  const _ProfileMenuTile({
    required this.icon,
    required this.title,
    this.subtitle,
    this.badgeCount,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final int? badgeCount;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(vertical: 4),
      leading: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: const Color(0xFFF3F6FF),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Icon(icon, color: const Color(0xFF334155), size: 22),
      ),
      title: Text(
        title,
        style: const TextStyle(
          fontWeight: FontWeight.w600,
          color: Color(0xFF0F172A),
        ),
      ),
      subtitle: subtitle == null
          ? null
          : Text(
              subtitle!,
              style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
            ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if ((badgeCount ?? 0) > 0) ...<Widget>[
            _UnreadBadgeBubble(count: badgeCount!),
            const SizedBox(width: 10),
          ],
          const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8)),
        ],
      ),
      onTap: onTap,
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
        gradient: const LinearGradient(
          colors: <Color>[Color(0xFF1D4ED8), Color(0xFF2563EB)],
        ),
        borderRadius: BorderRadius.circular(999),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF1D4ED8).withValues(alpha: 0.22),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
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
