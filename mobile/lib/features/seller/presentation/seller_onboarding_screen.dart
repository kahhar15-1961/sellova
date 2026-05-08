import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../profile/application/seller_profile_controller.dart';
import '../application/seller_business_controller.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerOnboardingScreen extends ConsumerWidget {
  const SellerOnboardingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileState = ref.watch(sellerProfileControllerProvider);
    final businessState = ref.watch(sellerBusinessControllerProvider);
    final profile = profileState.profile;
    final verificationStatus = profile?.verificationStatus ?? 'unknown';
    final isVerified = verificationStatus.toLowerCase() == 'verified';
    final hasStore = profileState.hasSellerProfile && profile != null;
    final kycStatus = profile?.latestKycStatus ?? 'none';
    final kycInReview = kycStatus == 'submitted' || kycStatus == 'under_review';
    final kycRejected = kycStatus == 'rejected' || kycStatus == 'expired';
    final kycCardTitle = isVerified
        ? 'Verification complete'
        : kycInReview
            ? 'Verification under review'
            : kycRejected
                ? 'KYC needs attention'
                : 'KYC required';
    final kycCardBody = isVerified
        ? 'Your seller account is ready for operations.'
        : kycInReview
            ? 'Your documents are with the admin review team.'
            : kycRejected
                ? 'Review the reason and submit updated documents.'
                : 'Submit your identity documents to unlock verification.';
    final kycActionLabel = kycInReview ? 'View status' : 'Open KYC';
    final steps = <_OnboardingStep>[
      _OnboardingStep(
        title: 'Complete profile',
        subtitle: hasStore ? 'Done' : 'Create your seller profile.',
        done: hasStore,
        actionLabel: hasStore ? 'Edit' : 'Open',
        onTap: () => context.push('/seller/store-profile'),
      ),
      _OnboardingStep(
        title: 'Set store details',
        subtitle: businessState.storeSettings.storeName.trim().isEmpty
            ? 'Add your store identity and contact details.'
            : 'Store details are in place.',
        done: businessState.storeSettings.storeName.trim().isNotEmpty,
        actionLabel: 'Open',
        onTap: () => context.push('/seller/store-settings'),
      ),
      _OnboardingStep(
        title: 'Add payout method',
        subtitle: businessState.payoutMethods.isEmpty
            ? 'Link a payout account for withdrawals.'
            : 'Payout method saved.',
        done: businessState.payoutMethods.isNotEmpty,
        actionLabel: 'Open',
        onTap: () => context.push('/seller/bank-payment-methods'),
      ),
      _OnboardingStep(
        title: 'Set shipping',
        subtitle: businessState.shippingSettings.processingTimeLabel.isEmpty
            ? 'Configure shipping fees and timing.'
            : 'Shipping is configured.',
        done: businessState.shippingSettings.processingTimeLabel.isNotEmpty,
        actionLabel: 'Open',
        onTap: () => context.push('/seller/shipping-settings'),
      ),
      _OnboardingStep(
        title: 'Get verified',
        subtitle: isVerified
            ? 'Verification approved.'
            : kycInReview
                ? 'Admin review in progress.'
                : kycRejected
                    ? 'Update documents and resubmit.'
                    : 'Submit documents for review.',
        done: isVerified,
        actionLabel: kycInReview ? 'View' : 'Open',
        onTap: () => context.push('/seller/kyc'),
      ),
    ];

    final completed = steps.where((step) => step.done).length;
    final progress = steps.isEmpty ? 0.0 : completed / steps.length;
    final readinessScore = (progress * 100).round();

    return SellerScaffold(
      selectedNavIndex: 4,
      appBar: AppBar(
        title: const Text('Seller Onboarding'),
        centerTitle: true,
      ),
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(sellerProfileControllerProvider.notifier).load(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: <Widget>[
            Container(
              padding: const EdgeInsets.all(18),
              decoration: sellerCardDecoration(Theme.of(context).colorScheme),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          color: kSellerAccent.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Icon(Icons.rocket_launch_outlined,
                            color: kSellerAccent.withValues(alpha: 0.9)),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              'Launch your store',
                              style: Theme.of(context)
                                  .textTheme
                                  .titleLarge
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                            Text(
                              hasStore
                                  ? 'Complete the setup checklist to go live.'
                                  : 'Start with your seller profile and verification.',
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(color: kSellerMuted),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(999),
                    child: LinearProgressIndicator(
                      minHeight: 8,
                      value: progress,
                      backgroundColor: const Color(0xFFE2E8F0),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    '$readinessScore% complete',
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: kSellerNavy,
                        ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: sellerCardDecoration(Theme.of(context).colorScheme),
              child: Row(
                children: <Widget>[
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: kSellerAccent.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(Icons.insights_outlined,
                        color: kSellerAccent.withValues(alpha: 0.9)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Readiness score',
                          style: Theme.of(context)
                              .textTheme
                              .titleSmall
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        Text(
                          readinessScore >= 80
                              ? 'Almost ready to launch.'
                              : 'Complete the remaining steps to go live.',
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall
                              ?.copyWith(color: kSellerMuted),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    '$readinessScore%',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                          color: kSellerNavy,
                        ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: kSellerPrimaryGradient,
                borderRadius: BorderRadius.circular(18),
                boxShadow: <BoxShadow>[sellerGradientShadow(alpha: 0.16)],
              ),
              child: Row(
                children: <Widget>[
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(Icons.rule_folder_outlined,
                        color: Colors.white),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          kycCardTitle,
                          style:
                              Theme.of(context).textTheme.titleSmall?.copyWith(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                  ),
                        ),
                        Text(
                          kycCardBody,
                          style:
                              Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: Colors.white.withValues(alpha: 0.8),
                                  ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  FilledButton(
                    onPressed: () => context.push('/seller/kyc'),
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: const Color(0xFF0F172A),
                    ),
                    child: Text(kycActionLabel),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: sellerCardDecoration(Theme.of(context).colorScheme),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text('Next actions',
                      style: Theme.of(context)
                          .textTheme
                          .titleSmall
                          ?.copyWith(fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  _ReminderRow(
                    icon: Icons.verified_user_outlined,
                    title: 'KYC status',
                    subtitle: kycStatus == 'none'
                        ? 'Not started'
                        : kycStatus.replaceAll('_', ' '),
                    action: () => context.push('/seller/kyc'),
                  ),
                  _ReminderRow(
                    icon: Icons.storefront_outlined,
                    title: 'Store profile',
                    subtitle: hasStore ? 'Created' : 'Incomplete',
                    action: () => context.push('/seller/store-profile'),
                  ),
                  _ReminderRow(
                    icon: Icons.local_shipping_outlined,
                    title: 'Shipping',
                    subtitle:
                        businessState.shippingSettings.processingTimeLabel,
                    action: () => context.push('/seller/shipping-settings'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (profileState.isLoading || businessState.isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: Center(child: CircularProgressIndicator()),
              )
            else ...<Widget>[
              _StatusCard(
                title: 'Verification',
                value: verificationStatus.replaceAll('_', ' ').toUpperCase(),
                subtitle: isVerified
                    ? 'Seller access is active.'
                    : 'Pending approval or document review.',
                tone: isVerified ? _StatusTone.good : _StatusTone.warn,
              ),
              const SizedBox(height: 12),
              _StatusCard(
                title: 'Store',
                value: businessState.storeSettings.storeName.trim().isEmpty
                    ? 'Not set'
                    : businessState.storeSettings.storeName.trim(),
                subtitle:
                    businessState.storeSettings.storeDescription.trim().isEmpty
                        ? 'Add a short brand description.'
                        : businessState.storeSettings.storeDescription.trim(),
                tone: businessState.storeSettings.storeName.trim().isEmpty
                    ? _StatusTone.warn
                    : _StatusTone.good,
              ),
              const SizedBox(height: 16),
              Text(
                'Setup checklist',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 10),
              for (final step in steps) ...<Widget>[
                _StepTile(step: step),
                const SizedBox(height: 10),
              ],
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFF3F4FF),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Need help?',
                      style: Theme.of(context)
                          .textTheme
                          .titleSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Help & Support and live chat are enough for questions, issues, and escalation. Onboarding is the guided setup path; KYC is the verification step.',
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: kSellerMuted, height: 1.45),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: FilledButton(
                            onPressed: () =>
                                context.push('/seller/help-support'),
                            child: const Text('Open support'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => context.push('/seller/menu'),
                            child: const Text('Open menu'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _OnboardingStep {
  const _OnboardingStep({
    required this.title,
    required this.subtitle,
    required this.done,
    required this.actionLabel,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final bool done;
  final String actionLabel;
  final VoidCallback onTap;
}

class _StepTile extends StatelessWidget {
  const _StepTile({required this.step});

  final _OnboardingStep step;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: step.onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: sellerCardDecoration(cs),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: step.done
                      ? const Color(0xFFDCFCE7)
                      : const Color(0xFFEFF2FF),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  step.done ? Icons.check_rounded : Icons.arrow_forward_rounded,
                  color: step.done ? const Color(0xFF15803D) : kSellerAccent,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            step.title,
                            style: Theme.of(context)
                                .textTheme
                                .titleSmall
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                        ),
                        if (step.done) const _DonePill(),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      step.subtitle,
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: kSellerMuted, height: 1.4),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      step.actionLabel,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: kSellerAccent,
                          ),
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

class _DonePill extends StatelessWidget {
  const _DonePill();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFDCFCE7),
        borderRadius: BorderRadius.circular(999),
      ),
      child: const Text(
        'Done',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: Color(0xFF15803D),
        ),
      ),
    );
  }
}

enum _StatusTone { good, warn }

class _StatusCard extends StatelessWidget {
  const _StatusCard({
    required this.title,
    required this.value,
    required this.subtitle,
    required this.tone,
  });

  final String title;
  final String value;
  final String subtitle;
  final _StatusTone tone;

  @override
  Widget build(BuildContext context) {
    final accent = tone == _StatusTone.good
        ? const Color(0xFF15803D)
        : const Color(0xFFB45309);
    final bg = tone == _StatusTone.good
        ? const Color(0xFFF0FDF4)
        : const Color(0xFFFFFBEB);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: accent.withValues(alpha: 0.18)),
      ),
      child: Row(
        children: <Widget>[
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Icon(
              tone == _StatusTone.good
                  ? Icons.verified_rounded
                  : Icons.hourglass_bottom_rounded,
              color: accent,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: kSellerMuted, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w900, color: kSellerNavy),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: kSellerMuted, height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ReminderRow extends StatelessWidget {
  const _ReminderRow({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.action,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback action;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: action,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Row(
            children: <Widget>[
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: kSellerAccent.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: kSellerAccent, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(title,
                        style: Theme.of(context)
                            .textTheme
                            .bodyMedium
                            ?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 2),
                    Text(subtitle,
                        style: Theme.of(context)
                            .textTheme
                            .bodySmall
                            ?.copyWith(color: kSellerMuted)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8)),
            ],
          ),
        ),
      ),
    );
  }
}
