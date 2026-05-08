import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../../auth/application/auth_session_controller.dart';
import '../data/profile_repository.dart';

class AdminProfileScreen extends ConsumerStatefulWidget {
  const AdminProfileScreen({super.key});

  @override
  ConsumerState<AdminProfileScreen> createState() => _AdminProfileScreenState();
}

class _AdminProfileScreenState extends ConsumerState<AdminProfileScreen> {
  bool _loading = true;
  String? _error;
  ActorProfileDto? _profile;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_load);
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final me = await ref.read(profileRepositoryProvider).getMe();
      if (!mounted) {
        return;
      }
      setState(() {
        _profile = me;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  bool get _sessionStaff {
    final session = ref.read(authSessionControllerProvider).session;
    return session?.isPlatformStaff ?? false;
  }

  bool get _allowed {
    if (_sessionStaff) {
      return true;
    }
    final p = _profile;
    return p != null && p.isPlatformStaff;
  }

  Future<void> _logout() async {
    final ok = await showDialog<bool>(
          context: context,
          builder: (BuildContext context) => AlertDialog(
            title: const Text('Sign out?'),
            content: const Text(
                'You will need to sign in again to access the platform.'),
            actions: <Widget>[
              TextButton(
                  onPressed: () => Navigator.of(context).pop(false),
                  child: const Text('Cancel')),
              FilledButton(
                style: FilledButton.styleFrom(
                    backgroundColor: Colors.red.shade700),
                onPressed: () => Navigator.of(context).pop(true),
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
    final theme = Theme.of(context);
    final cs = theme.colorScheme;

    if (_loading) {
      return Scaffold(
        appBar: AppBar(
          backgroundColor: Colors.white.withValues(alpha: 0.94),
          surfaceTintColor: Colors.transparent,
          title: const Text('Staff'),
        ),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null) {
      return Scaffold(
        appBar: AppBar(
          backgroundColor: Colors.white.withValues(alpha: 0.94),
          surfaceTintColor: Colors.transparent,
          title: const Text('Staff'),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(Icons.error_outline, size: 48, color: cs.error),
                const SizedBox(height: 12),
                Text(_error!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton(onPressed: _load, child: const Text('Retry')),
              ],
            ),
          ),
        ),
      );
    }

    if (!_allowed) {
      return Scaffold(
        appBar: AppBar(
          backgroundColor: Colors.white.withValues(alpha: 0.94),
          surfaceTintColor: Colors.transparent,
          title: const Text('Staff'),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(Icons.lock_outline, size: 48, color: cs.outline),
                const SizedBox(height: 12),
                Text(
                  'Staff access only.',
                  style: theme.textTheme.titleMedium,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 20),
                OutlinedButton(
                    onPressed: () => context.pop(), child: const Text('Back')),
              ],
            ),
          ),
        ),
      );
    }

    final p = _profile!;
    final primaryRole = p.roleCodes.contains('admin') ? 'Admin' : 'Adjudicator';

    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        title: const Text('Staff'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/profile'),
        ),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: <Widget>[
            Card(
              elevation: 0,
              color: cs.primaryContainer.withValues(alpha: 0.28),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(18)),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  children: <Widget>[
                    CircleAvatar(
                      radius: 32,
                      backgroundColor: cs.primary.withValues(alpha: 0.15),
                      child: Icon(Icons.admin_panel_settings_rounded,
                          size: 36, color: cs.primary),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(primaryRole,
                              style: theme.textTheme.titleLarge
                                  ?.copyWith(fontWeight: FontWeight.w800)),
                          const SizedBox(height: 4),
                          Text(
                            'Access',
                            style: theme.textTheme.bodySmall
                                ?.copyWith(color: cs.onSurfaceVariant),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            Text('Profile',
                style: theme.textTheme.titleSmall
                    ?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            _InfoTile(
                icon: Icons.badge_outlined,
                label: 'ID',
                value: '${p.id ?? '—'}'),
            _InfoTile(
                icon: Icons.alternate_email,
                label: 'Email',
                value: p.email.isEmpty ? '—' : p.email),
            _InfoTile(
                icon: Icons.phone_outlined,
                label: 'Phone',
                value: p.phone.isEmpty ? '—' : p.phone),
            _InfoTile(
                icon: Icons.verified_user_outlined,
                label: 'State',
                value: (p.raw['status'] ?? '—').toString()),
            const SizedBox(height: 20),
            FilledButton.tonalIcon(
              onPressed: () => context.push('/profile/admin/returns'),
              icon: const Icon(Icons.assignment_return_rounded),
              label: const Text('Returns'),
            ),
            const SizedBox(height: 20),
            Text('Roles',
                style: theme.textTheme.titleSmall
                    ?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            if (p.roleCodes.isEmpty)
              Text('No roles.', style: theme.textTheme.bodyMedium)
            else
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: p.roleCodes
                    .map(
                      (String code) => Chip(
                        label: Text(code),
                        visualDensity: VisualDensity.compact,
                      ),
                    )
                    .toList(),
              ),
            const SizedBox(height: 28),
            FilledButton.icon(
              style: FilledButton.styleFrom(
                backgroundColor: Colors.red.shade700,
                foregroundColor: Colors.white,
                minimumSize: const Size.fromHeight(52),
              ),
              onPressed: _logout,
              icon: const Icon(Icons.logout_rounded),
              label: const Text('Sign out'),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, size: 22, color: theme.colorScheme.primary),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(label,
                    style: theme.textTheme.labelMedium
                        ?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
                Text(value,
                    style: theme.textTheme.bodyLarge
                        ?.copyWith(fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
