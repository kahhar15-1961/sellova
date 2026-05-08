import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/seller_inventory_controller.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerStockSummaryScreen extends ConsumerStatefulWidget {
  const SellerStockSummaryScreen({super.key});

  @override
  ConsumerState<SellerStockSummaryScreen> createState() =>
      _SellerStockSummaryScreenState();
}

class _SellerStockSummaryScreenState
    extends ConsumerState<SellerStockSummaryScreen> {
  String _warehouse = 'All Warehouses';

  @override
  Widget build(BuildContext context) {
    final moves = ref.watch(sellerInventoryProvider);
    final warehouseNames =
        ref.watch(sellerWarehouseProvider.notifier).names(includeAll: true);
    final selectedWarehouse =
        warehouseNames.contains(_warehouse) ? _warehouse : warehouseNames.first;
    final scoped = selectedWarehouse == 'All Warehouses'
        ? moves
        : moves.where((m) => m.warehouse == selectedWarehouse).toList();
    final stockIn =
        scoped.where((m) => m.type == SellerMovementType.stockIn).toList();
    final stockOut =
        scoped.where((m) => m.type == SellerMovementType.stockOut).toList();
    final adjustment =
        scoped.where((m) => m.type == SellerMovementType.adjustment).toList();
    final inQty = stockIn.fold<int>(0, (s, e) => s + e.quantity.abs());
    final outQty = stockOut.fold<int>(0, (s, e) => s + e.quantity.abs());
    final adjQty = adjustment.fold<int>(0, (s, e) => s + e.quantity.abs());
    final netQty = inQty - outQty - adjQty;
    final inValue = stockIn.fold<double>(0, (s, e) => s + e.totalAmount);
    final outValue = stockOut.fold<double>(0, (s, e) => s + e.totalAmount);
    final adjValue = adjustment.fold<double>(0, (s, e) => s + e.totalAmount);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Stock Summary'),
        actions: <Widget>[
          DropdownButton<String>(
            value: selectedWarehouse,
            underline: const SizedBox.shrink(),
            items: warehouseNames
                .map((e) => DropdownMenuItem<String>(value: e, child: Text(e)))
                .toList(),
            onChanged: (v) {
              if (v != null) setState(() => _warehouse = v);
            },
          ),
          const SizedBox(width: 10),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Text('Overview',
              style: Theme.of(context)
                  .textTheme
                  .titleLarge
                  ?.copyWith(fontWeight: FontWeight.w900, color: kSellerNavy)),
          const SizedBox(height: 10),
          GridView.count(
            crossAxisCount: 2,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            children: <Widget>[
              _box('Stock In', '$inQty', inValue, const Color(0xFFECFDF5),
                  const Color(0xFF15803D)),
              _box('Stock Out', '$outQty', outValue, const Color(0xFFFFF1F2),
                  const Color(0xFFDC2626)),
              _box('Adjustments', '$adjQty', adjValue, const Color(0xFFFFFBEB),
                  const Color(0xFFB45309)),
              _box(
                  'Net Change',
                  '${netQty >= 0 ? '+' : ''}$netQty',
                  inValue - outValue - adjValue,
                  const Color(0xFFEFF6FF),
                  const Color(0xFF2563EB)),
            ],
          ),
          const SizedBox(height: 14),
          Text('Top Reasons',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          ...const <(String, int, String)>[
            ('Order Fulfilment', 85, '52.8%'),
            ('New Stock Received', 45, '27.9%'),
            ('Damaged Items', 12, '7.5%'),
            ('Return from Customer', 10, '6.2%'),
            ('Others', 9, '5.6%'),
          ].map((e) => ListTile(
                leading: const CircleAvatar(
                    child: Icon(Icons.label_outline_rounded)),
                title: Text(e.$1),
                trailing: Text('${e.$2}     ${e.$3}',
                    style: const TextStyle(fontWeight: FontWeight.w700)),
              )),
        ],
      ),
    );
  }
}

Widget _box(String title, String qty, double value, Color bg, Color fg) {
  return Container(
    padding: const EdgeInsets.all(12),
    decoration:
        BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
    child:
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
      Text(title, style: TextStyle(color: fg, fontWeight: FontWeight.w700)),
      const Spacer(),
      Text(qty,
          style:
              TextStyle(color: fg, fontSize: 30, fontWeight: FontWeight.w900)),
      Text('৳ ${value.toStringAsFixed(2)}',
          style: TextStyle(color: fg, fontWeight: FontWeight.w700)),
    ]),
  );
}
