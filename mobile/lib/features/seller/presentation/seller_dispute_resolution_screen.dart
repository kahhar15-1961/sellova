import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class SellerDisputeResolutionScreen extends StatelessWidget {
  const SellerDisputeResolutionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Dispute #DSP-2025-000034')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
        children: <Widget>[
          const CircleAvatar(radius: 42, backgroundColor: Color(0xFFDCFCE7), child: Icon(Icons.check_rounded, color: Color(0xFF16A34A), size: 44)),
          const SizedBox(height: 12),
          const Center(child: Text('Dispute Resolved', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w900))),
          const SizedBox(height: 4),
          const Center(child: Text('30 May 2025, 02:30 PM')),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: const Color(0xFFECFDF5), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFBBF7D0))),
            child: const Text('Decision\nRefund to Buyer\nFull refund has been issued to the buyer.', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
          const SizedBox(height: 14),
          const Text('Refund Summary', style: TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          const _Line('Order Amount', '৳2,450.00'),
          const _Line('Refund Amount', '৳2,450.00'),
          const _Line('Refund To', 'bKash (01*********)'),
          const _Line('Escrow Release', 'No funds released to seller'),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: () => context.push('/seller/what-next'),
            child: const Text('View What Happens Next'),
          ),
        ],
      ),
    );
  }
}

class _Line extends StatelessWidget {
  const _Line(this.k, this.v);
  final String k;
  final String v;
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(children: <Widget>[Expanded(child: Text(k)), Text(v, style: const TextStyle(fontWeight: FontWeight.w700))]),
    );
  }
}
