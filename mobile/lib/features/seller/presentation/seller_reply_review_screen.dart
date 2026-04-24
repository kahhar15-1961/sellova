import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import 'seller_feedback_widgets.dart';

class SellerReplyReviewScreen extends ConsumerStatefulWidget {
  const SellerReplyReviewScreen({super.key, required this.reviewId});
  final int reviewId;

  @override
  ConsumerState<SellerReplyReviewScreen> createState() => _SellerReplyReviewScreenState();
}

class _SellerReplyReviewScreenState extends ConsumerState<SellerReplyReviewScreen> {
  final TextEditingController _reply = TextEditingController();

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final review = ref.watch(sellerReviewsProvider.notifier).byId(widget.reviewId);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    if (review == null) return const Scaffold(body: Center(child: Text('Review not found')));
    if (_reply.text.isEmpty) {
      _reply.text = review.sellerReply ?? 'Thank you so much for your kind words! We\'re happy that you liked our product.';
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Reply to Review')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Text(error, style: const TextStyle(color: Color(0xFF9F1239))),
            ),
          const Text('Your Public Reply', style: TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          TextField(
            controller: _reply,
            maxLines: 5,
            maxLength: 500,
            decoration: const InputDecoration(border: OutlineInputBorder()),
          ),
          const SizedBox(height: 4),
          const Text('This reply will be visible publicly under the review.', style: TextStyle(color: Colors.black54)),
          const SizedBox(height: 14),
          FilledButton(
            onPressed: busy
                ? null
                : () async {
              try {
                await ref.read(sellerReviewsProvider.notifier).postReply(widget.reviewId, _reply.text);
                if (context.mounted) {
                  showSellerSuccessToast(context, 'Reply posted successfully.');
                  context.go('/seller/reviews/${widget.reviewId}');
                }
              } catch (_) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to post reply. Please retry.')));
                }
              }
            },
            child: const Text('Post Reply'),
          ),
          if (busy) const Padding(padding: EdgeInsets.only(top: 10), child: LinearProgressIndicator()),
        ],
      ),
    );
  }
}
