import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_inventory_controller.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerInventoryHistoryScreen extends ConsumerStatefulWidget {
  const SellerInventoryHistoryScreen({super.key});

  @override
  ConsumerState<SellerInventoryHistoryScreen> createState() => _SellerInventoryHistoryScreenState();
}

class _SellerInventoryHistoryScreenState extends ConsumerState<SellerInventoryHistoryScreen> {
  SellerMovementType? _type;
  String _warehouse = 'All Warehouses';
  final TextEditingController _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final all = ref.watch(sellerInventoryProvider);
    final q = _search.text.trim().toLowerCase();
    final filtered = all.where((m) {
      if (_type != null && m.type != _type) return false;
      if (_warehouse != 'All Warehouses' && m.warehouse != _warehouse) return false;
      if (q.isNotEmpty && !m.productName.toLowerCase().contains(q) && !m.referenceId.toLowerCase().contains(q)) return false;
      return true;
    }).toList();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Inventory History'),
        actions: <Widget>[
          IconButton(onPressed: () => context.push('/seller/inventory/filter'), icon: const Icon(Icons.filter_alt_outlined)),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          TextField(
            controller: _search,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Search product or reference ID'),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            children: <Widget>[
              ChoiceChip(label: const Text('All'), selected: _type == null, onSelected: (_) => setState(() => _type = null)),
              ChoiceChip(label: const Text('Stock In'), selected: _type == SellerMovementType.stockIn, onSelected: (_) => setState(() => _type = SellerMovementType.stockIn)),
              ChoiceChip(label: const Text('Stock Out'), selected: _type == SellerMovementType.stockOut, onSelected: (_) => setState(() => _type = SellerMovementType.stockOut)),
              ChoiceChip(label: const Text('Adjustments'), selected: _type == SellerMovementType.adjustment, onSelected: (_) => setState(() => _type = SellerMovementType.adjustment)),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              const Expanded(child: Text('Warehouse', style: TextStyle(fontWeight: FontWeight.w700, color: kSellerMuted))),
              DropdownButton<String>(
                value: _warehouse,
                items: kSellerWarehouses.map((e) => DropdownMenuItem<String>(value: e, child: Text(e))).toList(),
                onChanged: (v) {
                  if (v != null) setState(() => _warehouse = v);
                },
              ),
            ],
          ),
          const SizedBox(height: 6),
          ...filtered.map((m) => Container(
                margin: const EdgeInsets.only(bottom: 10),
                decoration: sellerCardDecoration(Theme.of(context).colorScheme),
                child: ListTile(
                  onTap: () => context.push('/seller/inventory/movement/${m.id}'),
                  leading: const CircleAvatar(child: Icon(Icons.inventory_2_outlined)),
                  title: Text(m.productName, style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text('${m.type.label}\n${sellerNiceDate(m.at)}      Ref: ${m.referenceId}'),
                  isThreeLine: true,
                  trailing: Text(
                    '${m.quantity > 0 ? '+' : ''}${m.quantity}',
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      color: m.quantity > 0 ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                    ),
                  ),
                ),
              )),
        ],
      ),
    );
  }
}
