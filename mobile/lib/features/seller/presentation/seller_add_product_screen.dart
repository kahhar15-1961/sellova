import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import '../application/seller_product_controller.dart';
import 'seller_feedback_widgets.dart';

class SellerAddProductScreen extends ConsumerStatefulWidget {
  const SellerAddProductScreen({super.key, required this.productType});
  final String productType;

  @override
  ConsumerState<SellerAddProductScreen> createState() => _SellerAddProductScreenState();
}

class _SellerAddProductScreenState extends ConsumerState<SellerAddProductScreen> {
  final _name = TextEditingController();
  final _price = TextEditingController();
  final _category = TextEditingController();
  final _stock = TextEditingController();
  final _description = TextEditingController();

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
    final busy = ref.watch(sellerBusyProvider);
    final error = ref.watch(sellerErrorProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Add Product')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Container(
            height: 160,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
            ),
            child: const Center(child: Text('Tap to upload product image')),
          ),
          const SizedBox(height: 12),
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
                    final name = _name.text.trim();
                    final price = double.tryParse(_price.text.trim());
                    final stock = int.tryParse(_stock.text.trim());
                    if (name.isEmpty || price == null || stock == null || _category.text.trim().isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please fill required fields correctly.')));
                      return;
                    }
                    await ref.read(sellerProductsProvider.notifier).createProduct(
                          name: name,
                          price: price,
                          stock: stock,
                          category: _category.text.trim(),
                          description: _description.text.trim(),
                          productType: widget.productType,
                        );
                    if (context.mounted) {
                      showSellerSuccessToast(context, 'Product saved successfully.');
                      context.go('/seller/products');
                    }
                  },
            child: const Text('Save Product'),
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
