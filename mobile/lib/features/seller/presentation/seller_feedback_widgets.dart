import 'package:flutter/material.dart';

void showSellerSuccessToast(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(message),
      behavior: SnackBarBehavior.floating,
      backgroundColor: const Color(0xFF166534),
    ),
  );
}

class SellerInlineFeedback extends StatelessWidget {
  const SellerInlineFeedback({
    super.key,
    required this.message,
    this.onRetry,
  });

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F2),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFDA4AF)),
      ),
      child: Row(
        children: <Widget>[
          const Icon(Icons.error_outline_rounded, color: Color(0xFF9F1239)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: Color(0xFF9F1239), fontWeight: FontWeight.w600),
            ),
          ),
          if (onRetry != null)
            TextButton(
              onPressed: onRetry,
              child: const Text('Retry'),
            ),
        ],
      ),
    );
  }
}

class SellerListSkeleton extends StatelessWidget {
  const SellerListSkeleton({super.key, this.count = 4});
  final int count;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
      itemCount: count,
      itemBuilder: (_, __) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: cs.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
        ),
        child: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            _SkeletonLine(width: 150, height: 14),
            SizedBox(height: 8),
            _SkeletonLine(width: 190, height: 12),
            SizedBox(height: 10),
            _SkeletonLine(width: 100, height: 16),
          ],
        ),
      ),
    );
  }
}

class SellerCardSkeleton extends StatelessWidget {
  const SellerCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: cs.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          _SkeletonLine(width: 150, height: 14),
          SizedBox(height: 8),
          _SkeletonLine(width: 190, height: 12),
          SizedBox(height: 10),
          _SkeletonLine(width: 100, height: 16),
        ],
      ),
    );
  }
}

class _SkeletonLine extends StatelessWidget {
  const _SkeletonLine({required this.width, required this.height});
  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(999),
      ),
    );
  }
}

class SellerEmptyState extends StatelessWidget {
  const SellerEmptyState({
    super.key,
    required this.title,
    required this.subtitle,
    this.ctaLabel,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final String? ctaLabel;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.45),
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.inventory_2_outlined, size: 38, color: Theme.of(context).colorScheme.primary),
            ),
            const SizedBox(height: 14),
            Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            Text(subtitle, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium),
            if (ctaLabel != null && onTap != null) ...<Widget>[
              const SizedBox(height: 14),
              FilledButton(onPressed: onTap, child: Text(ctaLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}
