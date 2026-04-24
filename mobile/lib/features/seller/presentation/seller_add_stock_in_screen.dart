import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../application/seller_inventory_controller.dart';
import '../application/seller_product_controller.dart';
import 'seller_feedback_widgets.dart';

class SellerAddStockInScreen extends ConsumerStatefulWidget {
  const SellerAddStockInScreen({super.key, required this.productId});
  final int productId;

  @override
  ConsumerState<SellerAddStockInScreen> createState() => _SellerAddStockInScreenState();
}

class _SellerAddStockInScreenState extends ConsumerState<SellerAddStockInScreen> {
  final TextEditingController _quantity = TextEditingController(text: '20');
  final TextEditingController _unitCost = TextEditingController(text: '1250');
  final TextEditingController _reason = TextEditingController(text: 'New Stock Received');
  final TextEditingController _note = TextEditingController(text: 'Received new stock from supplier.');
  String _warehouse = 'Main Warehouse';

  @override
  void dispose() {
    _quantity.dispose();
    _unitCost.dispose();
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
    final qty = int.tryParse(_quantity.text.trim()) ?? 0;
    final cost = double.tryParse(_unitCost.text.trim()) ?? 0;
    final total = qty * cost;
    return Scaffold(
      appBar: AppBar(title: const Text('Add Stock In')),
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
          _line('Quantity In', _quantity.text, editable: _quantity),
          _line('Unit Cost (৳)', _unitCost.text, editable: _unitCost),
          _line('Total Value', total.toStringAsFixed(2)),
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
                    final q = int.tryParse(_quantity.text.trim());
                    final c = double.tryParse(_unitCost.text.trim());
                    if (q == null || q <= 0 || c == null || c <= 0) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter valid quantity and unit cost.')));
                      return;
                    }
                    await ref.read(sellerInventoryProvider.notifier).addStockIn(
                          product: product,
                          quantity: q,
                          unitCost: c,
                          warehouse: _warehouse,
                          reason: _reason.text.trim(),
                          note: _note.text.trim(),
                        );
                    if (context.mounted) {
                      showSellerSuccessToast(context, 'Stock in saved successfully.');
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
              width: 120,
              child: TextField(
                controller: editable,
                textAlign: TextAlign.right,
                keyboardType: TextInputType.number,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(isDense: true, border: OutlineInputBorder()),
              ),
            ),
        ],
      ),
    );
  }
}
