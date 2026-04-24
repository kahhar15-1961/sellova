import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/dispute_detail_provider.dart';
import '../data/dispute_repository.dart';

class DisputeDetailScreen extends ConsumerWidget {
  const DisputeDetailScreen({
    super.key,
    required this.disputeId,
  });

  final int disputeId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(disputeDetailProvider(disputeId));
    return Scaffold(
      body: detailAsync.when(
        loading: () => const _DisputeDetailSkeleton(),
        error: (error, _) => _DisputeDetailError(
          message: error.toString(),
          onRetry: () => ref.refresh(disputeDetailProvider(disputeId)),
        ),
        data: (dispute) => _DisputeDetailContent(dispute: dispute),
      ),
    );
  }
}

class _DisputeDetailContent extends StatelessWidget {
  const _DisputeDetailContent({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    final timeline = dispute.timeline;
    final evidence = dispute.evidence;
    final isResolved = _isResolvedStatus(dispute.status);
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
              'Dispute #${dispute.id ?? 'unknown'}',
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
              _SectionHeader(
                icon: Icons.summarize_outlined,
                title: 'Dispute summary',
                subtitle: 'Reason and context',
              ),
              const SizedBox(height: 10),
              _SummarySection(dispute: dispute),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.receipt_long_outlined,
                title: 'Related order',
                subtitle: 'Linked purchase',
              ),
              const SizedBox(height: 10),
              _RelatedOrderSection(dispute: dispute),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.flag_outlined,
                title: 'Current status',
                subtitle: 'Where this case stands today',
              ),
              const SizedBox(height: 10),
              _StatusResolutionHero(dispute: dispute),
              if (isResolved) ...<Widget>[
                const SizedBox(height: 16),
                _SectionHeader(
                  icon: Icons.gavel,
                  title: 'Final outcome',
                  subtitle: 'Resolution result',
                ),
                const SizedBox(height: 10),
                _OutcomeHighlight(dispute: dispute),
              ],
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.history,
                title: 'Status timeline',
                subtitle: 'How this case progressed',
              ),
              const SizedBox(height: 10),
              if (timeline.isEmpty)
                const _TimelineFallback()
              else
                _TimelineSection(events: timeline),
              const SizedBox(height: 24),
              _SectionHeader(
                icon: Icons.folder_open_outlined,
                title: 'Evidence',
                subtitle: 'Attachments submitted for review',
              ),
              const SizedBox(height: 10),
              if (evidence.isEmpty)
                const _EvidenceFallback()
              else
                ...evidence.map((item) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _EvidenceCard(item: item),
                    )),
              const SizedBox(height: 10),
              FilledButton(
                onPressed: () {
                  final orderId = dispute.orderId;
                  if (orderId != null) {
                    HapticFeedback.lightImpact();
                    context.push('/orders/$orderId/chat');
                  }
                },
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('View Conversation'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

bool _isResolvedStatus(String status) {
  final s = status.toLowerCase();
  return s.contains('resolved') || s.contains('closed') || s.contains('settled');
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

class _StatusResolutionHero extends StatelessWidget {
  const _StatusResolutionHero({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final tier = _TimelineEventTier.fromStatus(dispute.status);
    final resolved = _isResolvedStatus(dispute.status);

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
            Row(
              children: <Widget>[
                Expanded(
                  child: _StatusChip(
                    label: dispute.status,
                    color: tier.accentColor(context),
                    icon: tier.icon,
                  ),
                ),
              ],
            ),
            if (!resolved) ...<Widget>[
              const SizedBox(height: 12),
              Text(
                'This dispute is still open or in progress. Check the timeline below for the latest updates.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: cs.onSurfaceVariant,
                  height: 1.4,
                ),
              ),
            ],
          ],
        ),
      ),
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

class _SummarySection extends StatelessWidget {
  const _SummarySection({required this.dispute});

  final DisputeDto dispute;

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
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            _MetaRow(label: 'Created', value: dispute.createdDateLabel),
            const SizedBox(height: 12),
            Text(
              dispute.summary,
              style: theme.textTheme.bodyLarge?.copyWith(height: 1.45),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          width: 88,
          child: Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    );
  }
}

class _RelatedOrderSection extends StatelessWidget {
  const _RelatedOrderSection({required this.dispute});

  final DisputeDto dispute;

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
        padding: const EdgeInsets.all(18),
        child: Row(
          children: <Widget>[
            CircleAvatar(
              backgroundColor: cs.primaryContainer,
              child: Icon(Icons.shopping_bag_outlined, color: cs.onPrimaryContainer),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    'Order #${dispute.orderId ?? 'unknown'}',
                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'This dispute is tied to the order above.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: cs.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
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
                'No detailed timeline was returned for this dispute. Status updates may still appear in the summary above.',
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
    final tier = _TimelineEventTier.fromStatus(status);
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

enum _TimelineEventTier {
  opened,
  underReview,
  escalated,
  resolved,
  other;

  static _TimelineEventTier fromStatus(String raw) {
    final s = raw.toLowerCase();
    if (s.contains('open') || s.contains('init')) {
      return _TimelineEventTier.opened;
    }
    if (s.contains('review')) {
      return _TimelineEventTier.underReview;
    }
    if (s.contains('escalat')) {
      return _TimelineEventTier.escalated;
    }
    if (s.contains('resolv') || s.contains('closed') || s.contains('settl')) {
      return _TimelineEventTier.resolved;
    }
    return _TimelineEventTier.other;
  }

  Color accentColor(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    switch (this) {
      case _TimelineEventTier.opened:
        return cs.primary;
      case _TimelineEventTier.underReview:
        return cs.secondary;
      case _TimelineEventTier.escalated:
        return cs.error;
      case _TimelineEventTier.resolved:
        return cs.tertiary;
      case _TimelineEventTier.other:
        return cs.outline;
    }
  }

  IconData get icon {
    switch (this) {
      case _TimelineEventTier.opened:
        return Icons.flag_outlined;
      case _TimelineEventTier.underReview:
        return Icons.visibility_outlined;
      case _TimelineEventTier.escalated:
        return Icons.trending_up;
      case _TimelineEventTier.resolved:
        return Icons.check_circle_outline;
      case _TimelineEventTier.other:
        return Icons.circle_outlined;
    }
  }
}

class _OutcomeHighlight extends StatelessWidget {
  const _OutcomeHighlight({required this.dispute});

  final DisputeDto dispute;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return Card(
      elevation: 0,
      color: cs.tertiaryContainer.withValues(alpha: 0.45),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: cs.tertiary.withValues(alpha: 0.35)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(Icons.emoji_events_outlined, color: cs.tertiary, size: 32),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                dispute.outcome,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                  height: 1.25,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EvidenceFallback extends StatelessWidget {
  const _EvidenceFallback();

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.35),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: <Widget>[
            Icon(Icons.attach_file, color: Theme.of(context).colorScheme.outline),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'No evidence items were returned. If evidence exists server-side, it may appear after additional processing.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EvidenceCard extends StatelessWidget {
  const _EvidenceCard({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final kind = _EvidenceKind.fromItem(item);
    final type = (item['evidence_type'] ?? item['type'] ?? 'Evidence').toString();
    final content = (item['content_text'] ?? item['description'] ?? '').toString();
    final path = (item['storage_path'] ?? item['url'] ?? item['file_url'] ?? '').toString();
    final checksum = (item['checksum_sha256'] ?? item['checksum'] ?? '').toString();
    final fileName = _fileNameFromPath(path);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(
          color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.55),
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (kind == _EvidenceKind.image && path.isNotEmpty)
            AspectRatio(
              aspectRatio: 16 / 9,
              child: Image.network(
                path,
                fit: BoxFit.cover,
                loadingBuilder: (_, child, progress) {
                  if (progress == null) {
                    return child;
                  }
                  return Container(
                    color: Theme.of(context).colorScheme.surfaceContainerHighest,
                    alignment: Alignment.center,
                    child: const SizedBox(
                      width: 28,
                      height: 28,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  );
                },
                errorBuilder: (_, __, ___) => _EvidencePreviewFallback(kind: kind),
              ),
            )
          else
            _EvidencePreviewFallback(kind: kind),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Icon(kind.icon, size: 18, color: Theme.of(context).colorScheme.primary),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        type,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                    ),
                  ],
                ),
                if (fileName.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 6),
                  Text(
                    fileName,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w600,
                        ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
                if (checksum.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 6),
                  Text(
                    'SHA-256: $checksum',
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          fontFamily: 'monospace',
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
                if (content.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 10),
                  Text(
                    content,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.45),
                  ),
                ],
                if (path.isNotEmpty && kind != _EvidenceKind.image) ...<Widget>[
                  const SizedBox(height: 8),
                  SelectableText(
                    path,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: Theme.of(context).colorScheme.primary,
                        ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

String _fileNameFromPath(String path) {
  if (path.isEmpty) {
    return '';
  }
  try {
    final uri = Uri.parse(path);
    if (uri.pathSegments.isNotEmpty) {
      return uri.pathSegments.last;
    }
  } catch (_) {
    // ignore
  }
  final idx = path.lastIndexOf('/');
  if (idx >= 0 && idx < path.length - 1) {
    return path.substring(idx + 1);
  }
  return path;
}

enum _EvidenceKind {
  image,
  document,
  text,
  unknown;

  static _EvidenceKind fromItem(Map<String, dynamic> item) {
    final type = (item['evidence_type'] ?? item['type'] ?? '').toString().toLowerCase();
    final path = (item['storage_path'] ?? item['url'] ?? item['file_url'] ?? '').toString();
    if (type.contains('image') || type.contains('photo') || _looksLikeImageUrl(path)) {
      return _EvidenceKind.image;
    }
    if (type.contains('text') || type.contains('note')) {
      return _EvidenceKind.text;
    }
    if (path.isNotEmpty) {
      return _EvidenceKind.document;
    }
    return _EvidenceKind.unknown;
  }

  IconData get icon {
    switch (this) {
      case _EvidenceKind.image:
        return Icons.image_outlined;
      case _EvidenceKind.document:
        return Icons.description_outlined;
      case _EvidenceKind.text:
        return Icons.notes_outlined;
      case _EvidenceKind.unknown:
        return Icons.insert_drive_file_outlined;
    }
  }
}

bool _looksLikeImageUrl(String url) {
  final lower = url.toLowerCase();
  if (!lower.startsWith('http')) {
    return false;
  }
  return lower.endsWith('.png') ||
      lower.endsWith('.jpg') ||
      lower.endsWith('.jpeg') ||
      lower.endsWith('.webp') ||
      lower.endsWith('.gif') ||
      lower.contains('image');
}

class _EvidencePreviewFallback extends StatelessWidget {
  const _EvidencePreviewFallback({required this.kind});

  final _EvidenceKind kind;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      height: 120,
      color: cs.surfaceContainerHighest.withValues(alpha: 0.5),
      alignment: Alignment.center,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(kind.icon, size: 40, color: cs.outline),
          const SizedBox(height: 8),
          Text(
            kind == _EvidenceKind.image ? 'Preview unavailable' : 'No inline preview',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(color: cs.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

class _DisputeDetailError extends StatelessWidget {
  const _DisputeDetailError({
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

class _DisputeDetailSkeleton extends StatelessWidget {
  const _DisputeDetailSkeleton();

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 48, 16, 24),
      children: <Widget>[
        Container(
          height: 110,
          decoration: BoxDecoration(
            color: cs.surfaceContainerHighest.withValues(alpha: 0.5),
            borderRadius: BorderRadius.circular(16),
          ),
        ),
        const SizedBox(height: 14),
        Container(
          height: 180,
          decoration: BoxDecoration(
            color: cs.surfaceContainerHighest.withValues(alpha: 0.5),
            borderRadius: BorderRadius.circular(16),
          ),
        ),
        const SizedBox(height: 14),
        Container(
          height: 140,
          decoration: BoxDecoration(
            color: cs.surfaceContainerHighest.withValues(alpha: 0.5),
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ],
    );
  }
}
