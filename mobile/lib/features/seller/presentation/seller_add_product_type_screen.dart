import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class SellerAddProductTypeScreen extends StatefulWidget {
  const SellerAddProductTypeScreen({super.key});

  @override
  State<SellerAddProductTypeScreen> createState() =>
      _SellerAddProductTypeScreenState();
}

class _SellerAddProductTypeScreenState
    extends State<SellerAddProductTypeScreen> {
  String _type = 'physical';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Add Product')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: <Widget>[
          Row(
            children: List<Widget>.generate(
              4,
              (i) => Padding(
                padding: const EdgeInsets.only(right: 8),
                child: CircleAvatar(
                  radius: 14,
                  backgroundColor: i == 0
                      ? Theme.of(context).colorScheme.primary
                      : Theme.of(context).colorScheme.surfaceContainerHighest,
                  child: Text('${i + 1}',
                      style: TextStyle(
                          fontSize: 12,
                          color: i == 0 ? Colors.white : Colors.black54)),
                ),
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text('Product Type',
              style: TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 10),
          _tile('Physical Product', 'physical'),
          _tile('Instant Delivery', 'instant_delivery'),
          _tile('Digital Product', 'digital'),
          _tile('Manual / Custom Delivery', 'manual_delivery'),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: () => context.push('/seller/products/add?type=$_type'),
            child: const Text('Continue'),
          ),
        ],
      ),
    );
  }

  Widget _tile(String label, String value) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
            color: _type == value
                ? Theme.of(context).colorScheme.primary
                : Theme.of(context).colorScheme.outlineVariant),
      ),
      child: ListTile(
        onTap: () => setState(() => _type = value),
        leading: Icon(_type == value
            ? Icons.radio_button_checked
            : Icons.radio_button_off),
        title: Text(label),
      ),
    );
  }
}
