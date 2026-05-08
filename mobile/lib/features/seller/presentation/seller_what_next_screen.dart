import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';

class SellerWhatNextScreen extends StatelessWidget {
  const SellerWhatNextScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('What Happens Next')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
        children: <Widget>[
          const Icon(Icons.inventory_2_rounded,
              size: 92, color: Color(0xFF4F46E5)),
          const SizedBox(height: 10),
          const _Item('Buyer has been refunded',
              '৳2,450.00 has been refunded to the buyer.'),
          const _Item('Order closed', 'This dispute case is now closed.'),
          const _Item('Product return (if applicable)',
              'Buyer will return the product to you.'),
          const _Item('Need Help?',
              'If you have any question, contact our support team.'),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: () => context.push('/seller/help-support'),
            child: const Text('Contact Support'),
          ),
        ],
      ),
    );
  }
}

class _Item extends StatelessWidget {
  const _Item(this.t, this.s);
  final String t;
  final String s;
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child:
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
        const Padding(
          padding: EdgeInsets.only(top: 2),
          child: Icon(Icons.check_circle_outline_rounded,
              color: Color(0xFF4F46E5)),
        ),
        const SizedBox(width: 10),
        Expanded(
            child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
              Text(t, style: const TextStyle(fontWeight: FontWeight.w800)),
              Text(s, style: const TextStyle(color: Colors.black54)),
            ])),
      ]),
    );
  }
}
