import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';
import 'seller_scaffold.dart';
import 'seller_ui.dart';

class SellerReviewsScreen extends ConsumerStatefulWidget {
  const SellerReviewsScreen({super.key});

  @override
  ConsumerState<SellerReviewsScreen> createState() =>
      _SellerReviewsScreenState();
}

class _SellerReviewsScreenState extends ConsumerState<SellerReviewsScreen> {
  final TextEditingController _searchController = TextEditingController();
  int? _ratingFilter;
  int _visibleCount = 5;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reviews = ref.watch(sellerReviewsProvider);
    final total = reviews.length;
    final average =
        total == 0 ? 0.0 : reviews.fold<int>(0, (s, e) => s + e.rating) / total;
    final query = _searchController.text.trim().toLowerCase();
    final filtered = reviews.where((review) {
      final matchesQuery = query.isEmpty ||
          review.productName.toLowerCase().contains(query) ||
          review.buyerName.toLowerCase().contains(query) ||
          review.comment.toLowerCase().contains(query);
      final matchesRating =
          _ratingFilter == null || review.rating == _ratingFilter;
      return matchesQuery && matchesRating;
    }).toList();
    final visible = filtered.take(_visibleCount).toList();

    return SellerScaffold(
      selectedNavIndex: 4,
      appBar: AppBar(
        title: const Text('Vendor Reputation'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Refresh',
            onPressed: () => ref.read(sellerReviewsProvider.notifier).refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.read(sellerReviewsProvider.notifier).refresh(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 22),
          children: <Widget>[
            Text(
              'Verified reviews from completed escrow transactions.',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: kSellerMuted,
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 12),
            _ScoreCard(reviews: reviews, average: average),
            const SizedBox(height: 12),
            _ReviewToolbar(
              controller: _searchController,
              ratingFilter: _ratingFilter,
              onChanged: () => setState(() => _visibleCount = 5),
              onRatingChanged: (value) {
                setState(() {
                  _ratingFilter = value;
                  _visibleCount = 5;
                });
              },
            ),
            const SizedBox(height: 10),
            if (visible.isEmpty)
              const SellerEmptyState(
                title: 'No reviews yet',
                subtitle: 'Verified buyer reviews will appear here.',
              )
            else
              ...visible.asMap().entries.map(
                    (entry) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _ReviewCard(
                        review: entry.value,
                        highlighted: entry.key == 3,
                        onTap: () =>
                            context.push('/seller/reviews/${entry.value.id}'),
                      ),
                    ),
                  ),
            if (filtered.length > visible.length)
              TextButton(
                onPressed: () => setState(() => _visibleCount += 5),
                child: const Text(
                  'Load More Reviews',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({
    required this.review,
    required this.onTap,
    required this.highlighted,
  });

  final SellerReview review;
  final VoidCallback onTap;
  final bool highlighted;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Ink(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: highlighted ? const Color(0xFFF8FAFC) : cs.surface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE5EAF2)),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: const Color(0xFF0F172A).withValues(alpha: 0.04),
                blurRadius: 14,
                offset: const Offset(0, 7),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(review.productName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: highlighted
                                  ? kSellerAccent
                                  : const Color(0xFF0F172A),
                              fontWeight: FontWeight.w900,
                              fontSize: 15,
                            )),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 7,
                          runSpacing: 4,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: <Widget>[
                            Text(review.buyerName,
                                style: const TextStyle(
                                    color: Color(0xFF334155),
                                    fontWeight: FontWeight.w700)),
                            const Text('•',
                                style: TextStyle(color: Color(0xFFCBD5E1))),
                            Text(sellerShortDate(review.date),
                                style: const TextStyle(
                                    color: Color(0xFF64748B),
                                    fontWeight: FontWeight.w700)),
                            if (review.isVerifiedBuyer) const _VerifiedBadge(),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFFBEB),
                      borderRadius: BorderRadius.circular(9),
                      border: Border.all(color: const Color(0xFFFDE68A)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        const Icon(Icons.star_rounded,
                            color: Color(0xFFF59E0B), size: 16),
                        const SizedBox(width: 3),
                        Text(
                          '${review.rating}',
                          style: const TextStyle(
                              color: Color(0xFFD97706),
                              fontWeight: FontWeight.w900,
                              fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                review.comment.isEmpty ? 'No written comment.' : review.comment,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: const Color(0xFF334155),
                      height: 1.35,
                    ),
              ),
              if ((review.sellerReply ?? '').isNotEmpty) ...<Widget>[
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Text.rich(
                    TextSpan(
                      text: 'Seller reply: ',
                      style: const TextStyle(
                          color: Color(0xFF0F172A),
                          fontWeight: FontWeight.w900),
                      children: <InlineSpan>[
                        TextSpan(
                          text: review.sellerReply,
                          style: const TextStyle(
                              color: Color(0xFF475569),
                              fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 11),
              Row(
                children: <Widget>[
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                    decoration: BoxDecoration(
                      color: highlighted
                          ? const Color(0xFFEEF2FF)
                          : const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Icon(Icons.thumb_up_alt_outlined,
                            color: highlighted
                                ? kSellerAccent
                                : const Color(0xFF64748B),
                            size: 15),
                        const SizedBox(width: 5),
                        Text(
                          'Helpful (${review.helpfulCount})',
                          style: TextStyle(
                            color: highlighted
                                ? kSellerAccent
                                : const Color(0xFF64748B),
                            fontWeight: FontWeight.w900,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),
                  if ((review.sellerReply ?? '').isNotEmpty)
                    const Text('Replied',
                        style: TextStyle(
                            color: Color(0xFF059669),
                            fontWeight: FontWeight.w900,
                            fontSize: 12)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ScoreCard extends StatelessWidget {
  const _ScoreCard({required this.reviews, required this.average});

  final List<SellerReview> reviews;
  final double average;

  @override
  Widget build(BuildContext context) {
    final total = reviews.length;
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.05),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF2FF),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: const Icon(Icons.star_rounded,
                    color: kSellerAccent, size: 21),
              ),
              const SizedBox(width: 10),
              const Text('Review Score',
                  style: TextStyle(
                      color: Color(0xFF0F172A),
                      fontWeight: FontWeight.w900,
                      fontSize: 17)),
            ],
          ),
          const SizedBox(height: 22),
          Text(average.toStringAsFixed(1),
              style: const TextStyle(
                  color: Color(0xFF0F172A),
                  fontSize: 54,
                  height: 0.95,
                  fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          Row(
            children: <Widget>[
              _Stars(rating: average),
              const SizedBox(width: 8),
              Text('Based on $total reviews',
                  style: const TextStyle(
                      color: Color(0xFF64748B), fontWeight: FontWeight.w800)),
            ],
          ),
          const SizedBox(height: 18),
          ...<int>[5, 4, 3, 2, 1].map((rating) {
            final count = reviews.where((r) => r.rating == rating).length;
            final progress = total == 0 ? 0.0 : count / total;
            return Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: Row(
                children: <Widget>[
                  SizedBox(
                    width: 34,
                    child: Row(
                      children: <Widget>[
                        Text('$rating',
                            style: const TextStyle(
                                color: Color(0xFF475569),
                                fontWeight: FontWeight.w900)),
                        const Icon(Icons.star_rounded,
                            color: Color(0xFF475569), size: 13),
                      ],
                    ),
                  ),
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(99),
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 7,
                        backgroundColor: const Color(0xFFF1F5F9),
                        valueColor: const AlwaysStoppedAnimation<Color>(
                            Color(0xFFFBBF24)),
                      ),
                    ),
                  ),
                  SizedBox(
                    width: 28,
                    child: Text('$count',
                        textAlign: TextAlign.right,
                        style: const TextStyle(
                            color: Color(0xFF94A3B8),
                            fontWeight: FontWeight.w800,
                            fontSize: 12)),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }
}

class _ReviewToolbar extends StatelessWidget {
  const _ReviewToolbar({
    required this.controller,
    required this.ratingFilter,
    required this.onChanged,
    required this.onRatingChanged,
  });

  final TextEditingController controller;
  final int? ratingFilter;
  final VoidCallback onChanged;
  final ValueChanged<int?> onRatingChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: const Color(0xFFECFDF5),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: const Icon(Icons.chat_bubble_outline_rounded,
                    color: Color(0xFF059669)),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text('All Reviews',
                    style: TextStyle(
                        color: Color(0xFF0F172A),
                        fontWeight: FontWeight.w900,
                        fontSize: 17)),
              ),
              PopupMenuButton<int?>(
                tooltip: 'Filter',
                initialValue: ratingFilter,
                onSelected: onRatingChanged,
                itemBuilder: (context) => <PopupMenuEntry<int?>>[
                  const PopupMenuItem<int?>(value: null, child: Text('All')),
                  ...<int>[5, 4, 3, 2, 1].map(
                    (rating) => PopupMenuItem<int?>(
                      value: rating,
                      child: Text('$rating Star'),
                    ),
                  ),
                ],
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                  decoration: BoxDecoration(
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    children: <Widget>[
                      const Icon(Icons.tune_rounded, size: 18),
                      const SizedBox(width: 6),
                      Text(ratingFilter == null ? 'Filter' : '$ratingFilter'),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          TextField(
            controller: controller,
            onChanged: (_) => onChanged(),
            decoration: InputDecoration(
              hintText: 'Search reviews...',
              prefixIcon: const Icon(Icons.search_rounded),
              filled: true,
              fillColor: Colors.white,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Stars extends StatelessWidget {
  const _Stars({required this.rating});

  final double rating;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List<Widget>.generate(5, (index) {
        final filled = index < rating.round();
        return Icon(Icons.star_rounded,
            color: filled ? const Color(0xFFFBBF24) : const Color(0xFFE2E8F0),
            size: 18);
      }),
    );
  }
}

class _VerifiedBadge extends StatelessWidget {
  const _VerifiedBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: const Color(0xFFECFDF5),
        borderRadius: BorderRadius.circular(999),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(Icons.check_circle_outline_rounded,
              color: Color(0xFF059669), size: 13),
          SizedBox(width: 3),
          Text('Verified',
              style: TextStyle(
                  color: Color(0xFF059669),
                  fontWeight: FontWeight.w900,
                  fontSize: 12)),
        ],
      ),
    );
  }
}
