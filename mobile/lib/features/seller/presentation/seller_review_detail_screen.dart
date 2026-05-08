import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';

class SellerReviewDetailScreen extends ConsumerWidget {
  const SellerReviewDetailScreen({super.key, required this.reviewId});
  final int reviewId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final review = ref.watch(sellerReviewsProvider.notifier).byId(reviewId);
    if (review == null) return const Scaffold(body: Center(child: Text('Review not found')));
    return Scaffold(
      appBar: AppBar(title: const Text('Review Details')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Row(children: <Widget>[
            const CircleAvatar(child: Icon(Icons.person)),
            const SizedBox(width: 10),
            Expanded(child: Text(review.buyerName, style: const TextStyle(fontWeight: FontWeight.w800))),
            if (review.isVerifiedBuyer) const Chip(label: Text('Verified Buyer'), backgroundColor: Color(0xFFECFDF5)),
          ]),
          const SizedBox(height: 10),
          Text(review.productName, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
          Text('Order: ${review.orderNumber}'),
          const SizedBox(height: 8),
          Row(children: List<Widget>.generate(5, (i) => Icon(i < review.rating ? Icons.star_rounded : Icons.star_border_rounded, color: const Color(0xFFF59E0B)))),
          const SizedBox(height: 8),
          Text(review.comment, style: const TextStyle(height: 1.4)),
          const SizedBox(height: 10),
          Row(children: review.photoUrls.map((_) => Container(width: 74, height: 74, margin: const EdgeInsets.only(right: 8), color: Colors.black12)).toList()),
          if (review.sellerReply != null) ...<Widget>[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE2E8F0))),
              child: Text('Seller Reply\n${review.sellerReply!}', style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
          ],
          if (review.moderationState != null) ...<Widget>[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFFFFBEB), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFDE68A))),
              child: const Text('Under Review\nThis review is being reviewed by our team.', style: TextStyle(color: Color(0xFF92400E), fontWeight: FontWeight.w700)),
            ),
          ],
          const SizedBox(height: 14),
          FilledButton(
            onPressed: () => context.push('/seller/reviews/$reviewId/reply'),
            child: Text(review.sellerReply == null ? 'Reply to Review' : 'Edit Reply'),
          ),
        ],
      ),
    );
  }
}
