import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_profile_controller.dart';
import '../../seller/presentation/seller_access_gate.dart';
import '../../seller/presentation/seller_ui.dart';

class SellerProfileScreen extends ConsumerStatefulWidget {
  const SellerProfileScreen({super.key});

  @override
  ConsumerState<SellerProfileScreen> createState() =>
      _SellerProfileScreenState();
}

class _SellerProfileScreenState extends ConsumerState<SellerProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _displayNameController = TextEditingController();
  final _legalNameController = TextEditingController();
  bool _seeded = false;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
        () => ref.read(sellerProfileControllerProvider.notifier).load());
  }

  @override
  void dispose() {
    _displayNameController.dispose();
    _legalNameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerProfileControllerProvider);
    final profile = state.profile;
    final cs = Theme.of(context).colorScheme;
    final verificationStatus = profile?.verificationStatus ?? 'unknown';
    final isVerified = verificationStatus.toLowerCase() == 'verified';
    final readinessScore = profile == null ? 0 : (isVerified ? 100 : 60);

    if (profile != null && !_seeded) {
      _displayNameController.text = profile.displayName;
      _legalNameController.text = profile.legalName;
      _seeded = true;
    }

    if (!state.hasSellerProfile &&
        !state.isLoading &&
        state.errorMessage == null) {
      return Scaffold(
        backgroundColor: Colors.transparent,
        appBar: AppBar(
          backgroundColor: Colors.white.withValues(alpha: 0.94),
          surfaceTintColor: Colors.transparent,
          title: const Text('Seller Profile'),
        ),
        body: SellerAccessGate(
          title: 'Seller profile required',
          message:
              'Create your seller profile to unlock store tools, onboarding, and KYC.',
          primaryActionLabel: 'Start onboarding',
          onPrimaryAction: () => context.push('/seller/onboarding'),
          secondaryActionLabel: 'Back to profile',
          onSecondaryAction: () => context.go('/profile'),
        ),
      );
    }

    if (state.isLoading && profile == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (state.errorMessage != null && profile == null) {
      return _SellerError(
        message: state.errorMessage!,
        onRetry: () =>
            ref.read(sellerProfileControllerProvider.notifier).load(),
      );
    }

    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.go('/profile'),
        ),
        title: const Text('Seller Profile'),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: RefreshIndicator(
          onRefresh: () =>
              ref.read(sellerProfileControllerProvider.notifier).load(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: <Widget>[
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: cs.surface,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                      color: cs.outlineVariant.withValues(alpha: 0.35)),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: cs.shadow.withValues(alpha: 0.05),
                      blurRadius: 16,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text('Seller',
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    Text(
                        'Country: ${profile?.country.isEmpty ?? true ? 'N/A' : profile!.country}'),
                    Text(
                        'Currency: ${profile?.currency.isEmpty ?? true ? 'N/A' : profile!.currency}'),
                    const SizedBox(height: 8),
                    Text(
                      'KYC: ${profile == null ? 'Unknown' : profile.latestKycStatus.replaceAll('_', ' ').toUpperCase()}',
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: isVerified ? Colors.green : kSellerNavy,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Readiness: $readinessScore%',
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: kSellerNavy,
                          ),
                    ),
                    if (profile != null && isVerified) ...<Widget>[
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          onPressed: () => context.go('/seller/dashboard'),
                          icon: const Icon(Icons.dashboard_customize_outlined),
                          label: const Text('Open seller dashboard'),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: cs.surface,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                      color: cs.outlineVariant.withValues(alpha: 0.35)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text('Operations',
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 10),
                    _ProfileActionTile(
                      icon: Icons.warehouse_outlined,
                      title: 'Warehouse Management',
                      subtitle: 'Manage stock locations for inventory.',
                      onTap: () => context.push('/seller/warehouses'),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              if (profile != null && !isVerified)
                _Banner(
                  text: 'Complete KYC to get verified.',
                  icon: Icons.rocket_launch_outlined,
                  color: const Color(0xFF7C3AED),
                  actionLabel: 'Open KYC',
                  onAction: () => context.push('/seller/kyc'),
                ),
              const SizedBox(height: 14),
              if (state.successMessage != null)
                _Banner(
                  text: state.successMessage!,
                  icon: Icons.check_circle_outline,
                  color: Colors.green,
                ),
              if (state.errorMessage != null)
                _Banner(
                  text: state.errorMessage!,
                  icon: Icons.error_outline,
                  color: Colors.red,
                ),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: cs.surface,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                      color: cs.outlineVariant.withValues(alpha: 0.35)),
                ),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text('Edit',
                          style: Theme.of(context)
                              .textTheme
                              .titleSmall
                              ?.copyWith(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _displayNameController,
                        decoration: const InputDecoration(
                          labelText: 'Display name',
                          border: OutlineInputBorder(),
                        ),
                        validator: (value) {
                          if ((value ?? '').trim().isEmpty) {
                            return 'Display name is required';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _legalNameController,
                        decoration: const InputDecoration(
                          labelText: 'Legal name',
                          border: OutlineInputBorder(),
                        ),
                        validator: (value) {
                          if ((value ?? '').trim().isEmpty) {
                            return 'Legal name is required';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          onPressed: state.isSaving
                              ? null
                              : () async {
                                  if (!(_formKey.currentState?.validate() ??
                                      false)) {
                                    return;
                                  }
                                  await ref
                                      .read(sellerProfileControllerProvider
                                          .notifier)
                                      .update(
                                        displayName:
                                            _displayNameController.text,
                                        legalName: _legalNameController.text,
                                      );
                                },
                          child: state.isSaving
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child:
                                      CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Text('Save'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProfileActionTile extends StatelessWidget {
  const _ProfileActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: <Widget>[
              Icon(icon, color: kSellerNavy),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(title,
                        style: const TextStyle(fontWeight: FontWeight.w900)),
                    Text(subtitle, style: const TextStyle(color: kSellerMuted)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: kSellerMuted),
            ],
          ),
        ),
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({
    required this.text,
    required this.icon,
    required this.color,
    this.actionLabel,
    this.onAction,
  });

  final String text;
  final IconData icon;
  final Color color;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          color: color.withValues(alpha: 0.12),
        ),
        child: Row(
          children: <Widget>[
            Icon(icon, color: color),
            const SizedBox(width: 8),
            Expanded(child: Text(text)),
            if (actionLabel != null && onAction != null) ...<Widget>[
              const SizedBox(width: 8),
              TextButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}

class _SellerError extends StatelessWidget {
  const _SellerError({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        title: const Text('Seller Profile'),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(Icons.error_outline, size: 44, color: cs.error),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: onRetry,
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
