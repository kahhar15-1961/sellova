import 'package:flutter/material.dart';

import 'seller_ui.dart';

class _ReviewItem {
  const _ReviewItem({required this.name, required this.stars, required this.text, required this.date, required this.product});
  final String name;
  final double stars;
  final String text;
  final String date;
  final String product;
}

class SellerReviewsScreen extends StatefulWidget {
  const SellerReviewsScreen({super.key});

  @override
  State<SellerReviewsScreen> createState() => _SellerReviewsScreenState();
}

class _SellerReviewsScreenState extends State<SellerReviewsScreen> {
  int _tab = 0;

  static const List<_ReviewItem> _all = <_ReviewItem>[
    _ReviewItem(name: 'Ahammad Uddin', stars: 5, text: 'Great product quality and fast delivery.', date: '29 May, 2025', product: 'Wireless Noise Cancelling Headphones'),
    _ReviewItem(name: 'Riad Hossain', stars: 4, text: 'Good value. Packaging could be better.', date: '22 May, 2025', product: 'USB-C Hub Pro'),
    _ReviewItem(name: 'Nusrat Jahan', stars: 2, text: 'Arrived late.', date: '18 May, 2025', product: 'Phone Case Matte'),
  ];

  @override
  Widget build(BuildContext context) {
    final filtered = _tab == 0
        ? _all
        : _tab == 1
            ? _all.where((e) => e.stars >= 4).toList()
            : _all.where((e) => e.stars <= 2).toList();
    const int total = 128;
    const int positive = 112;
    const int negative = 16;
    const breakdown = <int>[86, 28, 8, 4, 2];

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Reviews'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => Navigator.of(context).maybePop()),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text('4.8', style: Theme.of(context).textTheme.displaySmall?.copyWith(fontWeight: FontWeight.w900, color: kSellerNavy)),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(children: List<Widget>.generate(5, (_) => const Icon(Icons.star_rounded, color: Color(0xFFEAB308), size: 26))),
                  Text('($total Reviews)', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 20),
          for (int i = 0; i < 5; i++) _starBarRow(context, 5 - i, breakdown[i], total),
          const SizedBox(height: 20),
          Row(
            children: <Widget>[
              _reviewTab(context, 0, 'All ($total)'),
              const SizedBox(width: 8),
              _reviewTab(context, 1, 'Positive ($positive)'),
              const SizedBox(width: 8),
              _reviewTab(context, 2, 'Negative ($negative)'),
            ],
          ),
          const SizedBox(height: 16),
          ...filtered.map((e) => _reviewCard(context, e)),
        ],
      ),
    );
  }

  Widget _reviewTab(BuildContext context, int index, String label) {
    final selected = _tab == index;
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => _tab = index),
        borderRadius: BorderRadius.circular(10),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: selected ? const Color(0xFFF3F0FF) : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
            border: Border(
              bottom: BorderSide(color: selected ? kSellerAccent : Colors.transparent, width: 2),
            ),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: selected ? kSellerAccent : kSellerMuted,
                ),
          ),
        ),
      ),
    );
  }

  Widget _starBarRow(BuildContext context, int stars, int count, int total) {
    final ratio = total == 0 ? 0.0 : count / total;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: <Widget>[
          SizedBox(
            width: 36,
            child: Row(
              children: <Widget>[
                Text('$stars', style: const TextStyle(fontWeight: FontWeight.w700)),
                const Icon(Icons.star_rounded, size: 16, color: Color(0xFFEAB308)),
              ],
            ),
          ),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(99),
              child: LinearProgressIndicator(
                value: ratio,
                minHeight: 8,
                backgroundColor: const Color(0xFFF1F5F9),
                color: kSellerAccent,
              ),
            ),
          ),
          SizedBox(width: 36, child: Text('$count', textAlign: TextAlign.end, style: Theme.of(context).textTheme.bodySmall)),
        ],
      ),
    );
  }

  Widget _reviewCard(BuildContext context, _ReviewItem e) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFAFAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const CircleAvatar(radius: 22, child: Icon(Icons.person_rounded)),
              const SizedBox(width: 10),
              Expanded(child: Text(e.name, style: const TextStyle(fontWeight: FontWeight.w800))),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: const Color(0xFFECFDF5), borderRadius: BorderRadius.circular(8)),
                child: Text('${e.stars.toStringAsFixed(1)} ★', style: const TextStyle(color: Color(0xFF15803D), fontWeight: FontWeight.w800, fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(e.text, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 6),
          Text(e.date, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE5E7EB))),
            child: Row(
              children: <Widget>[
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(8)),
                  child: const Icon(Icons.headphones_rounded),
                ),
                const SizedBox(width: 10),
                Expanded(child: Text(e.product, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
