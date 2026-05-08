import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/withdrawal_detail_provider.dart';
import '../data/withdrawal_repository.dart';

class WithdrawalDetailScreen extends ConsumerWidget {
  const WithdrawalDetailScreen({
    super.key,
    required this.withdrawalId,
  });

  final int withdrawalId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(withdrawalDetailProvider(withdrawalId));
    return Scaffold(
      body: detailAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _WithdrawalDetailError(
          message: error.toString(),
          onRetry: () => ref.refresh(withdrawalDetailProvider(withdrawalId)),
        ),
        data: (withdrawal) => _WithdrawalDetailContent(withdrawal: withdrawal),
      ),
    );
  }
}

class _WithdrawalDetailContent extends StatelessWidget {
  const _WithdrawalDetailContent({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    final timeline = withdrawal.timeline;
    final theme = Theme.of(context);
    final cs = theme.colorScheme;

    return CustomScrollView(
      slivers: <Widget>[
        SliverAppBar(
          pinned: true,
          expandedHeight: 120,
          flexibleSpace: FlexibleSpaceBar(
            titlePadding: const EdgeInsetsDirectional.only(start: 56, bottom: 14),
            title: Text(
              'Withdrawal #${withdrawal.id ?? 'unknown'}',
              style: theme.textTheme.titleLarge?.copyWith(
                color: cs.onSurface,
                fontWeight: FontWeight.w700,
              ),
            ),
            background: Container(
              alignment: Alignment.bottomLeft,
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 48),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: <Color>[
                    cs.primaryContainer.withValues(alpha: 0.35),
                    cs.surface,
                  ],
                ),
              ),
            ),
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
          sliver: SliverList.list(
            children: <Widget>[
              const _SectionHeader(
                icon: Icons.account_balance_wallet_outlined,
                title: 'Status & amounts',
                subtitle: 'Current state and payout figures',
              ),
              const SizedBox(height: 10),
              _StatusFinancialHero(withdrawal: withdrawal),
              const SizedBox(height: 24),
              const _SectionHeader(
                icon: Icons.receipt_long_outlined,
                title: 'Amount breakdown',
                subtitle: 'Requested amount, fees, and net payout',
              ),
              const SizedBox(height: 10),
              _AmountBreakdownCard(withdrawal: withdrawal),
              const SizedBox(height: 24),
              const _SectionHeader(
                icon: Icons.summarize_outlined,
                title: 'Withdrawal summary',
                subtitle: 'Notes and outcome details',
              ),
              const SizedBox(height: 10),
              _SummarySection(withdrawal: withdrawal),
              const SizedBox(height: 24),
              const _SectionHeader(
                icon: Icons.payments_outlined,
                title: 'Payout & review',
                subtitle: 'Destination and reviewer metadata',
              ),
              const SizedBox(height: 10),
              _PayoutReviewerSection(withdrawal: withdrawal),
              const SizedBox(height: 24),
              const _SectionHeader(
                icon: Icons.history,
                title: 'Status timeline',
                subtitle: 'How this withdrawal progressed',
              ),
              const SizedBox(height: 10),
              if (timeline.isEmpty)
                const _TimelineFallback()
              else
                _TimelineSection(events: timeline),
            ],
          ),
        ),
      ],
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(icon, size: 22, color: theme.colorScheme.primary),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _StatusFinancialHero extends StatelessWidget {
  const _StatusFinancialHero({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final tier = _WithdrawalTimelineTier.fromStatus(withdrawal.status);
    final createdFull = _formatDetailDateTime(withdrawal.raw['created_at'] ?? withdrawal.raw['createdAt']);
    final updatedRaw = withdrawal.raw['updated_at'] ?? withdrawal.raw['updatedAt'];
    final updatedFull = updatedRaw != null ? _formatDetailDateTime(updatedRaw.toString()) : '';

    return Card(
      elevation: 0,
      color: cs.surfaceContainerHighest.withValues(alpha: 0.45),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.6)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            _StatusChip(
              label: _humanizeStatus(withdrawal.status),
              color: tier.accentColor(context),
              icon: tier.icon,
            ),
            const SizedBox(height: 20),
            Text(
              'Requested amount',
              style: theme.textTheme.labelLarge?.copyWith(
                color: cs.onSurfaceVariant,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              withdrawal.amountLabel,
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
                letterSpacing: -0.5,
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: cs.primaryContainer.withValues(alpha: 0.35),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: cs.primary.withValues(alpha: 0.2)),
              ),
              child: Row(
                children: <Widget>[
                  Icon(Icons.savings_outlined, color: cs.primary, size: 26),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Net payout',
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: cs.onSurfaceVariant,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          withdrawal.netLabel,
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w900,
                            color: cs.onSurface,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: <Widget>[
                Expanded(
                  child: _HeroMetric(
                    label: 'Fee',
                    value: withdrawal.feeLabel,
                    icon: Icons.percent_outlined,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _HeroMetric(
                    label: 'Currency',
                    value: withdrawal.currency.isEmpty ? '—' : withdrawal.currency,
                    icon: Icons.currency_exchange,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Divider(height: 1, color: cs.outlineVariant.withValues(alpha: 0.5)),
            const SizedBox(height: 12),
            _HeroMetaRow(icon: Icons.event_outlined, label: 'Created', value: createdFull),
            if (updatedFull.isNotEmpty && updatedFull != createdFull) ...<Widget>[
              const SizedBox(height: 8),
              _HeroMetaRow(icon: Icons.update, label: 'Last updated', value: updatedFull),
            ],
          ],
        ),
      ),
    );
  }
}

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: cs.surface.withValues(alpha: 0.65),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.45)),
      ),
      child: Row(
        children: <Widget>[
          Icon(icon, size: 20, color: cs.primary),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  label,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: cs.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  value,
                  style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroMetaRow extends StatelessWidget {
  const _HeroMetaRow({
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
    final cs = theme.colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(icon, size: 20, color: cs.onSurfaceVariant),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                label,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: cs.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                value,
                style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({
    required this.label,
    required this.color,
    required this.icon,
  });

  final String label;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.45)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 20, color: color),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              label,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: color,
                  ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

class _AmountBreakdownCard extends StatelessWidget {
  const _AmountBreakdownCard({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        child: Column(
          children: <Widget>[
            _BreakdownRow(
              label: 'Requested',
              value: withdrawal.amountLabel,
              emphasize: false,
            ),
            Divider(height: 20, color: cs.outlineVariant.withValues(alpha: 0.45)),
            _BreakdownRow(
              label: 'Fee',
              value: withdrawal.feeLabel,
              emphasize: false,
            ),
            Divider(height: 20, color: cs.outlineVariant.withValues(alpha: 0.45)),
            _BreakdownRow(
              label: 'Net payout',
              value: withdrawal.netLabel,
              emphasize: true,
            ),
          ],
        ),
      ),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  const _BreakdownRow({
    required this.label,
    required this.value,
    required this.emphasize,
  });

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Expanded(
          flex: 2,
          child: Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        Expanded(
          flex: 3,
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: emphasize
                ? theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)
                : theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    );
  }
}

class _SummarySection extends StatelessWidget {
  const _SummarySection({required this.withdrawal});

  final WithdrawalDto withdrawal;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final notes = (withdrawal.raw['notes'] ?? withdrawal.raw['reason'] ?? '').toString().trim();
    final rejectReason = (withdrawal.raw['reject_reason'] ?? withdrawal.raw['rejectReason'] ?? '')
        .toString()
        .trim();

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            if (rejectReason.isNotEmpty) ...<Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Icon(Icons.info_outline, size: 22, color: cs.error),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Rejection reason',
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: cs.onSurfaceVariant,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          rejectReason,
                          style: theme.textTheme.bodyLarge?.copyWith(
                            height: 1.45,
                            color: cs.onSurface,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              if (notes.isNotEmpty) const SizedBox(height: 16),
            ],
            if (notes.isNotEmpty)
              Text(
                notes,
                style: theme.textTheme.bodyLarge?.copyWith(height: 1.45),
              ),
            if (notes.isEmpty && rejectReason.isEmpty)
              Text(
                'No additional notes were provided for this withdrawal.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: cs.onSurfaceVariant,
                  height: 1.4,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _PayoutReviewerSection extends StatelessWidget {
  const _PayoutReviewerSection({required this.withdrawal});

  final WithdrawalDto withdrawal;

  static const String _payoutUnavailable = 'Payout method unavailable';
  static const String _reviewerUnavailable = 'Reviewer unavailable';

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final payoutOk = withdrawal.payoutMethodLabel != _payoutUnavailable;
    final payoutExtras = _payoutExtras(withdrawal.raw);
    final reviewerOk = withdrawal.reviewerLabel != _reviewerUnavailable;
    final reviewedAtRaw =
        (withdrawal.raw['reviewed_at'] ?? withdrawal.raw['reviewedAt'] ?? '').toString().trim();
    final reviewedAt =
        reviewedAtRaw.isEmpty ? '' : _formatDetailDateTime(reviewedAtRaw);
    final reviewedByRaw = withdrawal.raw['reviewed_by'] ?? withdrawal.raw['reviewedBy'];
    final reviewedByLine = _reviewerReferenceLine(reviewerOk, withdrawal.reviewerLabel, reviewedByRaw);

    return Column(
      children: <Widget>[
        Card(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    CircleAvatar(
                      backgroundColor: cs.secondaryContainer,
                      child: Icon(Icons.account_balance_outlined, color: cs.onSecondaryContainer),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Payout destination',
                        style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                if (payoutOk) ...<Widget>[
                  Text(
                    withdrawal.payoutMethodLabel,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                      height: 1.35,
                    ),
                  ),
                  ...payoutExtras.map(
                    (line) => Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        line,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: cs.onSurfaceVariant,
                          height: 1.35,
                        ),
                      ),
                    ),
                  ),
                ] else
                  const _MetadataFallback(
                    message:
                        'No payout destination details were returned for this request. If you expected bank or wallet info here, it may be stored server-side only.',
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Card(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.5)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    CircleAvatar(
                      backgroundColor: cs.tertiaryContainer,
                      child: Icon(Icons.verified_user_outlined, color: cs.onTertiaryContainer),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Review',
                        style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                if (reviewerOk || reviewedAt.isNotEmpty || reviewedByLine != null) ...<Widget>[
                  if (reviewerOk)
                    Text(
                      withdrawal.reviewerLabel,
                      style: theme.textTheme.bodyLarge?.copyWith(
                        fontWeight: FontWeight.w600,
                        height: 1.35,
                      ),
                    ),
                  if (reviewedByLine != null) ...<Widget>[
                    if (reviewerOk) const SizedBox(height: 8),
                    Text(
                      reviewedByLine,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: cs.onSurfaceVariant,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  if (reviewedAt.isNotEmpty && reviewedAt != 'Date unavailable') ...<Widget>[
                    const SizedBox(height: 8),
                    Text(
                      'Reviewed at · $reviewedAt',
                      style: theme.textTheme.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                    ),
                  ],
                ] else
                  const _MetadataFallback(
                    message:
                        'No reviewer information was returned. Staff review metadata may appear after the request is processed.',
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

List<String> _payoutExtras(Map<String, dynamic> raw) {
  final lines = <String>[];
  void addIf(String key, String label) {
    final v = raw[key];
    if (v == null) {
      return;
    }
    final s = v.toString().trim();
    if (s.isEmpty) {
      return;
    }
    lines.add('$label: $s');
  }

  addIf('payout_method_type', 'Method type');
  addIf('payout_method', 'Method');
  addIf('destination', 'Destination');
  addIf('bank_account_masked', 'Account');
  addIf('bank_name', 'Bank');
  return lines;
}

String? _reviewerReferenceLine(bool reviewerNameOk, String reviewerLabel, Object? reviewedByRaw) {
  if (reviewedByRaw == null) {
    return null;
  }
  final s = reviewedByRaw.toString().trim();
  if (s.isEmpty) {
    return null;
  }
  if (reviewerNameOk && reviewerLabel == s) {
    return null;
  }
  final isNumeric = int.tryParse(s) != null;
  if (isNumeric) {
    return 'Reviewer account ID · $s';
  }
  return 'Reference · $s';
}

class _MetadataFallback extends StatelessWidget {
  const _MetadataFallback({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(Icons.info_outline, color: Theme.of(context).colorScheme.outline),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            message,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
          ),
        ),
      ],
    );
  }
}

class _TimelineFallback extends StatelessWidget {
  const _TimelineFallback();

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.35),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: <Widget>[
            Icon(Icons.info_outline, color: Theme.of(context).colorScheme.outline),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'No detailed timeline was returned for this withdrawal. Status and timestamps above reflect the latest known state.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TimelineSection extends StatelessWidget {
  const _TimelineSection({required this.events});

  final List<Map<String, dynamic>> events;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(
          color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(8, 12, 12, 12),
        child: Column(
          children: List<Widget>.generate(events.length, (index) {
            final event = events[index];
            final isLast = index == events.length - 1;
            return _TimelineRow(
              event: event,
              showConnectorBelow: !isLast,
            );
          }),
        ),
      ),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({
    required this.event,
    required this.showConnectorBelow,
  });

  final Map<String, dynamic> event;
  final bool showConnectorBelow;

  @override
  Widget build(BuildContext context) {
    final status = (event['status'] ?? event['state'] ?? 'update').toString();
    final tier = _WithdrawalTimelineTier.fromStatus(status);
    final atRaw = (event['created_at'] ?? event['at'] ?? event['timestamp'] ?? '').toString();
    final note = (event['note'] ?? event['reason'] ?? event['message'] ?? '').toString();
    final formatted = _formatTimelineDate(atRaw);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 40,
            child: Column(
              children: <Widget>[
                Container(
                  width: 14,
                  height: 14,
                  decoration: BoxDecoration(
                    color: tier.accentColor(context),
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: Theme.of(context).colorScheme.surface,
                      width: 2,
                    ),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: tier.accentColor(context).withValues(alpha: 0.35),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                ),
                if (showConnectorBelow)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.only(top: 2),
                      color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.55),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Icon(tier.icon, size: 18, color: tier.accentColor(context)),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          _humanizeStatus(status),
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    formatted,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                  if (note.isNotEmpty) ...<Widget>[
                    const SizedBox(height: 8),
                    Text(
                      note,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

String _humanizeStatus(String raw) {
  final s = raw.replaceAll('_', ' ').trim();
  if (s.isEmpty) {
    return 'Update';
  }
  return s.split(' ').map((w) {
    if (w.isEmpty) {
      return w;
    }
    return '${w[0].toUpperCase()}${w.length > 1 ? w.substring(1).toLowerCase() : ''}';
  }).join(' ');
}

String _formatTimelineDate(String raw) {
  if (raw.isEmpty) {
    return 'Time not recorded';
  }
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) {
    return raw;
  }
  final local = parsed.toLocal();
  final y = local.year.toString().padLeft(4, '0');
  final m = local.month.toString().padLeft(2, '0');
  final d = local.day.toString().padLeft(2, '0');
  final h = local.hour.toString().padLeft(2, '0');
  final min = local.minute.toString().padLeft(2, '0');
  return '$y-$m-$d · $h:$min';
}

String _formatDetailDateTime(String raw) {
  if (raw.isEmpty) {
    return 'Date unavailable';
  }
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) {
    return raw;
  }
  final local = parsed.toLocal();
  final y = local.year.toString().padLeft(4, '0');
  final m = local.month.toString().padLeft(2, '0');
  final d = local.day.toString().padLeft(2, '0');
  final h = local.hour.toString().padLeft(2, '0');
  final min = local.minute.toString().padLeft(2, '0');
  return '$y-$m-$d at $h:$min';
}

enum _WithdrawalTimelineTier {
  requested,
  underReview,
  approved,
  processing,
  completed,
  rejected,
  other;

  static _WithdrawalTimelineTier fromStatus(String raw) {
    final s = raw.toLowerCase().trim();
    switch (s) {
      case 'requested':
        return _WithdrawalTimelineTier.requested;
      case 'under_review':
        return _WithdrawalTimelineTier.underReview;
      case 'approved':
        return _WithdrawalTimelineTier.approved;
      case 'processing_payout':
        return _WithdrawalTimelineTier.processing;
      case 'paid_out':
        return _WithdrawalTimelineTier.completed;
      case 'rejected':
        return _WithdrawalTimelineTier.rejected;
      default:
        break;
    }
    if (s.contains('reject') || s.contains('denied')) {
      return _WithdrawalTimelineTier.rejected;
    }
    if (s.contains('paid') || s.contains('complete') || s == 'success') {
      return _WithdrawalTimelineTier.completed;
    }
    if (s.contains('process')) {
      return _WithdrawalTimelineTier.processing;
    }
    if (s.contains('approv')) {
      return _WithdrawalTimelineTier.approved;
    }
    if (s.contains('review')) {
      return _WithdrawalTimelineTier.underReview;
    }
    if (s.contains('request') || s.contains('pending') || s.contains('submitted')) {
      return _WithdrawalTimelineTier.requested;
    }
    return _WithdrawalTimelineTier.other;
  }

  Color accentColor(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    switch (this) {
      case _WithdrawalTimelineTier.requested:
        return cs.primary;
      case _WithdrawalTimelineTier.underReview:
        return cs.secondary;
      case _WithdrawalTimelineTier.approved:
        return cs.tertiary;
      case _WithdrawalTimelineTier.processing:
        return cs.primary;
      case _WithdrawalTimelineTier.completed:
        return cs.tertiary;
      case _WithdrawalTimelineTier.rejected:
        return cs.error;
      case _WithdrawalTimelineTier.other:
        return cs.outline;
    }
  }

  IconData get icon {
    switch (this) {
      case _WithdrawalTimelineTier.requested:
        return Icons.outgoing_mail;
      case _WithdrawalTimelineTier.underReview:
        return Icons.visibility_outlined;
      case _WithdrawalTimelineTier.approved:
        return Icons.task_alt;
      case _WithdrawalTimelineTier.processing:
        return Icons.sync;
      case _WithdrawalTimelineTier.completed:
        return Icons.check_circle_outline;
      case _WithdrawalTimelineTier.rejected:
        return Icons.cancel_outlined;
      case _WithdrawalTimelineTier.other:
        return Icons.circle_outlined;
    }
  }
}

class _WithdrawalDetailError extends StatelessWidget {
  const _WithdrawalDetailError({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.error_outline, size: 48),
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
    );
  }
}
