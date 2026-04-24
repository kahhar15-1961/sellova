import 'package:flutter/material.dart';

class RateReviewScreen extends StatefulWidget {
  const RateReviewScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  State<RateReviewScreen> createState() => _RateReviewScreenState();
}

class _RateReviewScreenState extends State<RateReviewScreen> {
  int _rating = 5;
  final TextEditingController _reviewCtrl = TextEditingController(
    text: 'Great sound quality and very comfortable to use. Shipping was fast and the seller was very helpful.',
  );

  @override
  void dispose() {
    _reviewCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final count = _reviewCtrl.text.length;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: const Text('Rate & Review'),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
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
                        border: Border.all(color: const Color(0xFFBBF7D0)),
                      ),
                      child: const Row(
                        children: <Widget>[
                          Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A)),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'Your order has been completed. Share your experience with the seller.',
                              style: TextStyle(fontWeight: FontWeight.w600),
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
                        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
                      ),
                      child: Row(
                        children: <Widget>[
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Container(
                              width: 72,
                              height: 72,
                              color: cs.surfaceContainerHighest,
                              child: const Icon(Icons.headphones, size: 36),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  'Wireless Noise Cancelling Headphones',
                                  style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                                ),
                                const SizedBox(height: 4),
                                Text('Order #${widget.orderId}', style: Theme.of(context).textTheme.bodySmall),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text(
                      'How would you rate this product?',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.start,
                      children: List<Widget>.generate(5, (i) {
                        final filled = i < _rating;
                        return IconButton(
                          onPressed: () => setState(() => _rating = i + 1),
                          icon: Icon(
                            filled ? Icons.star_rounded : Icons.star_border_rounded,
                            color: const Color(0xFF4F46E5),
                            size: 36,
                          ),
                        );
                      }),
                    ),
                    Text(
                      _rating >= 5 ? 'Excellent' : (_rating >= 4 ? 'Very good' : (_rating >= 3 ? 'Good' : 'Needs improvement')),
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 18),
                    Text('Write your review', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _reviewCtrl,
                      minLines: 5,
                      maxLines: 7,
                      maxLength: 500,
                      onChanged: (_) => setState(() {}),
                      decoration: InputDecoration(
                        hintText: 'Share what you liked and what can improve...',
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
                          Icon(Icons.verified_user_outlined, color: Color(0xFF4F46E5)),
                          SizedBox(width: 10),
                          Expanded(child: Text('Your review helps other buyers make better decisions.')),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Review submitted successfully.')),
                  );
                  Navigator.of(context).maybePop();
                },
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Submit Review'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
