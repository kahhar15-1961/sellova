import 'package:flutter/material.dart';

class SellerInventoryFilterScreen extends StatefulWidget {
  const SellerInventoryFilterScreen({super.key});

  @override
  State<SellerInventoryFilterScreen> createState() => _SellerInventoryFilterScreenState();
}

class _SellerInventoryFilterScreenState extends State<SellerInventoryFilterScreen> {
  int _movement = 0;
  int _range = 2;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Filter'),
        actions: <Widget>[
          TextButton(onPressed: () => setState(() {
            _movement = 0;
            _range = 2;
          }), child: const Text('Reset')),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          const Text('Movement Type', style: TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            children: <Widget>[
              ChoiceChip(label: const Text('All'), selected: _movement == 0, onSelected: (_) => setState(() => _movement = 0)),
              ChoiceChip(label: const Text('Stock In'), selected: _movement == 1, onSelected: (_) => setState(() => _movement = 1)),
              ChoiceChip(label: const Text('Stock Out'), selected: _movement == 2, onSelected: (_) => setState(() => _movement = 2)),
              ChoiceChip(label: const Text('Adjustments'), selected: _movement == 3, onSelected: (_) => setState(() => _movement = 3)),
            ],
          ),
          const SizedBox(height: 16),
          const Text('Date Range', style: TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            children: <Widget>[
              ChoiceChip(label: const Text('Today'), selected: _range == 0, onSelected: (_) => setState(() => _range = 0)),
              ChoiceChip(label: const Text('This Week'), selected: _range == 1, onSelected: (_) => setState(() => _range = 1)),
              ChoiceChip(label: const Text('This Month'), selected: _range == 2, onSelected: (_) => setState(() => _range = 2)),
              ChoiceChip(label: const Text('Custom'), selected: _range == 3, onSelected: (_) => setState(() => _range = 3)),
            ],
          ),
          const SizedBox(height: 10),
          const TextField(decoration: InputDecoration(hintText: '01 May 2025 - 31 May 2025')),
          const SizedBox(height: 14),
          const TextField(decoration: InputDecoration(hintText: 'Select product')),
          const SizedBox(height: 10),
          const TextField(decoration: InputDecoration(hintText: 'All Warehouses')),
          const SizedBox(height: 10),
          const TextField(decoration: InputDecoration(hintText: 'Enter reference ID')),
          const SizedBox(height: 10),
          const TextField(decoration: InputDecoration(hintText: 'Select reason')),
          const SizedBox(height: 18),
          FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Apply Filters')),
        ],
      ),
    );
  }
}
