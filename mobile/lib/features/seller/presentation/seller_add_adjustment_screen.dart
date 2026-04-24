import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../application/seller_inventory_controller.dart';
import '../application/seller_product_controller.dart';
import 'seller_feedback_widgets.dart';

class SellerAddAdjustmentScreen extends ConsumerStatefulWidget {
  const SellerAddAdjustmentScreen({super.key, required this.productId});
  final int productId;

  @override
  ConsumerState<SellerAddAdjustmentScreen> createState() => _SellerAddAdjustmentScreenState();
}

class _SellerAddAdjustmentScreenState extends ConsumerState<SellerAddAdjustmentScreen> {
  final TextEditingController _change = TextEditingController(text: '-2');
  final TextEditingController _reason = TextEditingController(text: 'Damaged Items');
  final TextEditingController _note = TextEditingController(text: '2 units found damaged during quality check.');
  String _warehouse = 'Main Warehouse';

  @override
  void dispose() {
    _change.dispose();
    _reason.dispose();
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final busy = ref.watch(sellerBusyProvider);
    final product = ref.watch(sellerProductsProvider.notifier).byId(widget.productId);
    if (product == null) return const Scaffold(body: Center(child: Text('Product not found')));
    final currentWarehouseStock = product.warehouseStocks[_warehouse] ?? 0;
    return Scaffold(
      appBar: AppBar(title: const Text('Add Adjustment')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          ListTile(
            contentPadding: const EdgeInsets.all(12),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Theme.of(context).colorScheme.outlineVariant)),
            leading: const CircleAvatar(child: Icon(Icons.inventory_2_outlined)),
            title: Text(product.name, style: const TextStyle(fontWeight: FontWeight.w800)),
            subtitle: Text('SKU: ${product.sku}\nCurrent Stock: $currentWarehouseStock'),
          ),
          const SizedBox(height: 10),
          _warehouseField(),
          _line('Quantity Change (+/-)', _change.text, editable: _change),
          _line('Reason', _reason.text, editable: _reason),
          const SizedBox(height: 10),
          const Text('Notes (Optional)'),
          const SizedBox(height: 6),
          TextField(controller: _note, maxLines: 4, maxLength: 200, decoration: const InputDecoration(border: OutlineInputBorder())),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: busy
                ? null
                : () async {
                    final q = int.tryParse(_change.text.trim());
                    if (q == null || q == 0) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a valid adjustment amount (e.g. -2 or +5).')));
                      return;
                    }
                    if (currentWarehouseStock + q < 0) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Adjustment cannot reduce stock below zero.')));
                      return;
                    }
                    await ref.read(sellerInventoryProvider.notifier).addAdjustment(
                          product: product,
                          quantityChange: q,
                          warehouse: _warehouse,
                          reason: _reason.text.trim(),
                          note: _note.text.trim(),
                        );
                    if (context.mounted) {
                      showSellerSuccessToast(context, 'Adjustment saved successfully.');
                      context.go('/seller/inventory/history');
                    }
                  },
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }

  Widget _warehouseField() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: <Widget>[
          const Expanded(child: Text('Warehouse', style: TextStyle(color: Colors.black54))),
          DropdownButton<String>(
            value: _warehouse,
            items: kSellerWarehouses.where((e) => e != 'All Warehouses').map((e) => DropdownMenuItem<String>(value: e, child: Text(e))).toList(),
            onChanged: (v) {
              if (v != null) setState(() => _warehouse = v);
            },
          ),
        ],
      ),
    );
  }

  Widget _line(String k, String v, {TextEditingController? editable}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: <Widget>[
          Expanded(child: Text(k, style: const TextStyle(color: Colors.black54))),
          if (editable == null)
            Text(v, style: const TextStyle(fontWeight: FontWeight.w700))
          else
            SizedBox(
              width: 140,
              child: TextField(
                controller: editable,
                textAlign: TextAlign.right,
                keyboardType: const TextInputType.numberWithOptions(signed: true),
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(isDense: true, border: OutlineInputBorder()),
              ),
            ),
        ],
      ),
    );
  }
}
