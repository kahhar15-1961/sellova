import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/application/auth_session_controller.dart';
import '../../auth/presentation/auth_ui_constants.dart';
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
    Future<void>.microtask(() => ref.read(myProfileControllerProvider.notifier).load());
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
    final parts = name.split(RegExp(r'\s+')).where((s) => s.isNotEmpty).toList();
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
              TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: Colors.red.shade700),
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
    final staff = ref.watch(authSessionControllerProvider).session?.isPlatformStaff ?? false;
    final theme = Theme.of(context);

    if (state.isLoading && profile == null) {
      return Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent,
          title: const Text('My Profile'),
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (state.errorMessage != null && profile == null) {
      return Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent,
          title: const Text('My Profile'),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(Icons.error_outline, size: 48, color: theme.colorScheme.error),
                const SizedBox(height: 12),
                Text(state.errorMessage!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => ref.read(myProfileControllerProvider.notifier).load(),
                  child: const Text('Try again'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.go('/home'),
        ),
        title: const Text('My Profile'),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.read(myProfileControllerProvider.notifier).load(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
          children: <Widget>[
            const SizedBox(height: 8),
            Center(
              child: CircleAvatar(
                radius: 48,
                backgroundColor: kAuthAccentPurple.withValues(alpha: 0.15),
                child: Text(
                  _initials(profile),
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: kAuthAccentPurple,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Center(
              child: Text(
                _displayName(profile),
                style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
            ),
            const SizedBox(height: 6),
            Center(
              child: Text(
                _phoneLine(profile),
                style: theme.textTheme.bodyMedium?.copyWith(color: const Color(0xFF64748B)),
              ),
            ),
            const SizedBox(height: 28),
            if (staff)
              _ProfileMenuTile(
                icon: Icons.admin_panel_settings_outlined,
                title: 'Staff profile',
                subtitle: 'Admin & adjudicator',
                onTap: () => context.push('/profile/admin'),
              ),
            _ProfileMenuTile(
              icon: Icons.person_outline_rounded,
              title: 'Personal Information',
              onTap: () => context.push('/profile/personal'),
            ),
            _ProfileMenuTile(
              icon: Icons.location_on_outlined,
              title: 'Address Book',
              onTap: () => context.push('/addresses'),
            ),
            _ProfileMenuTile(
              icon: Icons.credit_card_outlined,
              title: 'Payment Methods',
              onTap: () => context.push('/profile/payment-methods'),
            ),
            _ProfileMenuTile(
              icon: Icons.star_border_rounded,
              title: 'My Reviews',
              onTap: () => context.push('/profile/reviews'),
            ),
            _ProfileMenuTile(
              icon: Icons.favorite_border_rounded,
              title: 'Wishlist',
              onTap: () => context.push('/profile/wishlist'),
            ),
            _ProfileMenuTile(
              icon: Icons.help_outline_rounded,
              title: 'Help & Support',
              onTap: () => context.push('/profile/help'),
            ),
            _ProfileMenuTile(
              icon: Icons.storefront_outlined,
              title: 'Seller center',
              subtitle: 'Manage your store',
              onTap: () => context.push('/profile/seller'),
            ),
            const Divider(height: 32),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.logout_rounded, color: Colors.red.shade700),
              title: Text(
                'Logout',
                style: TextStyle(color: Colors.red.shade700, fontWeight: FontWeight.w700),
              ),
              onTap: _logout,
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileMenuTile extends StatelessWidget {
  const _ProfileMenuTile({
    required this.icon,
    required this.title,
    this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(vertical: 4),
      leading: Icon(icon, color: const Color(0xFF334155), size: 26),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      subtitle: subtitle == null ? null : Text(subtitle!, style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
      trailing: const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8)),
      onTap: onTap,
    );
  }
}
