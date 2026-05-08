import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../../products/application/product_detail_provider.dart';
import '../../products/application/product_list_controller.dart';
import '../data/order_repository.dart';

class RateReviewScreen extends ConsumerStatefulWidget {
  const RateReviewScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  ConsumerState<RateReviewScreen> createState() => _RateReviewScreenState();
}

class _RateReviewScreenState extends ConsumerState<RateReviewScreen> {
  int _rating = 5;
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  OrderDto? _order;
  final TextEditingController _reviewCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_loadOrder);
  }

  Future<void> _loadOrder() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final order =
          await ref.read(orderRepositoryProvider).getById(widget.orderId);
      if (!mounted) return;
      setState(() {
        _order = order;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not load this order for review.';
        _loading = false;
      });
    }
  }

  @override
  void dispose() {
    _reviewCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final count = _reviewCtrl.text.length;
    final order = _order;
    final item = order?.items.isNotEmpty == true
        ? order!.items.first
        : const <String, dynamic>{};
    final productTitle = (item['title'] ??
            item['name'] ??
            order?.itemSummary ??
            'Purchased item')
        .toString();
    final productType =
        (item['product_type'] ?? order?.productType ?? 'physical').toString();

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: const Text('Rate & Review'),
        centerTitle: true,
      ),
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? _ReviewError(message: _error!, onRetry: _loadOrder)
                : Padding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                    child: Column(
                      children: <Widget>[
                        Expanded(
                          child: ListView(
                            children: <Widget>[
                              Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFECFDF5),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                      color: const Color(0xFFBBF7D0)),
                                ),
                                child: const Row(
                                  children: <Widget>[
                                    Icon(Icons.check_circle_rounded,
                                        color: Color(0xFF16A34A)),
                                    SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        'Your order has been completed. Share your experience with the seller.',
                                        style: TextStyle(
                                            fontWeight: FontWeight.w600),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 14),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: cs.surface,
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(
                                      color: cs.outlineVariant
                                          .withValues(alpha: 0.35)),
                                ),
                                child: Row(
                                  children: <Widget>[
                                    Container(
                                      width: 72,
                                      height: 72,
                                      decoration: BoxDecoration(
                                        color: cs.surfaceContainerHighest,
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: Icon(
                                        _productIcon(productType),
                                        size: 36,
                                        color: const Color(0xFF0F2A6B),
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: <Widget>[
                                          Text(
                                            productTitle,
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: Theme.of(context)
                                                .textTheme
                                                .titleSmall
                                                ?.copyWith(
                                                    fontWeight:
                                                        FontWeight.w800),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            order?.orderNumber ??
                                                '#${widget.orderId}',
                                            style: Theme.of(context)
                                                .textTheme
                                                .bodySmall
                                                ?.copyWith(
                                                    color: const Color(
                                                        0xFF64748B)),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 20),
                              Text(
                                'How would you rate this product?',
                                style: Theme.of(context)
                                    .textTheme
                                    .titleMedium
                                    ?.copyWith(fontWeight: FontWeight.w800),
                              ),
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.start,
                                children: List<Widget>.generate(5, (i) {
                                  final filled = i < _rating;
                                  return IconButton(
                                    onPressed: () =>
                                        setState(() => _rating = i + 1),
                                    icon: Icon(
                                      filled
                                          ? Icons.star_rounded
                                          : Icons.star_border_rounded,
                                      color: const Color(0xFF4F46E5),
                                      size: 36,
                                    ),
                                  );
                                }),
                              ),
                              Text(
                                _rating >= 5
                                    ? 'Excellent'
                                    : (_rating >= 4
                                        ? 'Very good'
                                        : (_rating >= 3
                                            ? 'Good'
                                            : 'Needs improvement')),
                                style: Theme.of(context)
                                    .textTheme
                                    .titleSmall
                                    ?.copyWith(fontWeight: FontWeight.w700),
                              ),
                              const SizedBox(height: 18),
                              Text('Write your review',
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleSmall
                                      ?.copyWith(fontWeight: FontWeight.w800)),
                              const SizedBox(height: 8),
                              TextField(
                                controller: _reviewCtrl,
                                minLines: 5,
                                maxLines: 7,
                                maxLength: 500,
                                onChanged: (_) => setState(() {}),
                                decoration: InputDecoration(
                                  hintText:
                                      'Share what was delivered, quality, and seller communication...',
                                  counterText: '$count/500',
                                ),
                              ),
                              const SizedBox(height: 12),
                              Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF5F3FF),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Row(
                                  children: <Widget>[
                                    Icon(Icons.verified_user_outlined,
                                        color: Color(0xFF4F46E5)),
                                    SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        'Your review helps other buyers make better decisions.',
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                        FilledButton(
                          onPressed: _submitting ? null : _submitReview,
                          style: FilledButton.styleFrom(
                            minimumSize: const Size.fromHeight(52),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14)),
                          ),
                          child: Text(
                              _submitting ? 'Submitting...' : 'Submit Review'),
                        ),
                      ],
                    ),
                  ),
      ),
    );
  }

  Future<void> _submitReview() async {
    FocusScope.of(context).unfocus();
    setState(() => _submitting = true);
    try {
      await ref.read(orderRepositoryProvider).submitReview(
            orderId: widget.orderId,
            rating: _rating,
            comment: _reviewCtrl.text,
          );
      final order = _order;
      if (order != null && order.items.isNotEmpty) {
        final productId = (order.items.first['product_id'] as num?)?.toInt();
        if (productId != null && productId > 0) {
          ref.invalidate(productDetailProvider(productId));
        }
      }
      await ref.read(productListControllerProvider.notifier).refresh();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Review submitted successfully.')),
      );
      context.go('/home');
    } catch (e) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Review submit failed: $e')),
      );
    }
  }

  IconData _productIcon(String productType) {
    return switch (productType.toLowerCase()) {
      'digital' => Icons.file_present_outlined,
      'instant_delivery' => Icons.key_rounded,
      'service' => Icons.handyman_outlined,
      _ => Icons.inventory_2_outlined,
    };
  }
}

class _ReviewError extends StatelessWidget {
  const _ReviewError({required this.message, required this.onRetry});

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
            const Icon(Icons.error_outline_rounded,
                size: 42, color: Color(0xFFB91C1C)),
            const SizedBox(height: 10),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton(onPressed: onRetry, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }
}
