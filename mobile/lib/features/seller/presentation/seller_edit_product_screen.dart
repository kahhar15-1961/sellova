import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../application/seller_product_controller.dart';
import '../domain/seller_models.dart';
import 'seller_feedback_widgets.dart';

class SellerEditProductScreen extends ConsumerStatefulWidget {
  const SellerEditProductScreen({super.key, required this.productId});
  final int productId;

  @override
  ConsumerState<SellerEditProductScreen> createState() => _SellerEditProductScreenState();
}

class _SellerEditProductScreenState extends ConsumerState<SellerEditProductScreen> {
  final _name = TextEditingController();
  final _price = TextEditingController();
  final _category = TextEditingController();
  final _stock = TextEditingController();
  final _description = TextEditingController();
  bool _seeded = false;

  @override
  void dispose() {
    _name.dispose();
    _price.dispose();
    _category.dispose();
    _stock.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = ref.watch(sellerProductsProvider.notifier).byId(widget.productId);
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    if (p == null) return const Scaffold(body: Center(child: Text('Product not found')));
    if (!_seeded) {
      _seeded = true;
      _name.text = p.name;
      _price.text = p.price.toStringAsFixed(0);
      _category.text = p.category;
      _stock.text = p.stock.toString();
      _description.text = p.description;
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Edit Product')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          if (error != null) ...<Widget>[
            SellerInlineFeedback(message: error),
            const SizedBox(height: 10),
          ],
          _f('Product Name', _name),
          _f('Price (৳)', _price, keyboard: TextInputType.number),
          _f('Category', _category),
          _f('Stock Quantity', _stock, keyboard: TextInputType.number),
          _f('Description', _description, lines: 4),
          FilledButton(
            onPressed: busy
                ? null
                : () async {
                    final price = double.tryParse(_price.text.trim());
                    final stock = int.tryParse(_stock.text.trim());
                    if (price == null || stock == null || _name.text.trim().isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please provide valid values.')));
                      return;
                    }
                    final next = p.copyWith(
                      name: _name.text.trim(),
                      price: price,
                      category: _category.text.trim(),
                      stock: stock,
                      description: _description.text.trim(),
                      status: stock <= 0 ? SellerProductStatus.outOfStock : p.status,
                    );
                    await ref.read(sellerProductsProvider.notifier).updateProduct(next);
                    if (context.mounted) {
                      showSellerSuccessToast(context, 'Product updated successfully.');
                      context.go('/seller/products/${widget.productId}');
                    }
                  },
            child: const Text('Update Product'),
          ),
          if (busy) const Padding(padding: EdgeInsets.only(top: 10), child: LinearProgressIndicator()),
        ],
      ),
    );
  }

  Widget _f(String label, TextEditingController c, {TextInputType keyboard = TextInputType.text, int lines = 1}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
        Text(label),
        const SizedBox(height: 6),
        TextField(controller: c, keyboardType: keyboard, maxLines: lines, decoration: const InputDecoration(border: OutlineInputBorder())),
      ]),
    );
  }
}
