import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/address_book_controller.dart';
import 'cart_ui.dart';

class AddEditAddressScreen extends ConsumerStatefulWidget {
  const AddEditAddressScreen({super.key, this.addressId});

  final String? addressId;

  @override
  ConsumerState<AddEditAddressScreen> createState() => _AddEditAddressScreenState();
}

class _AddEditAddressScreenState extends ConsumerState<AddEditAddressScreen> {
  bool _default = true;
  final TextEditingController _title = TextEditingController();
  final TextEditingController _fullName = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _line1 = TextEditingController();
  final TextEditingController _line2 = TextEditingController();
  final TextEditingController _area = TextEditingController();
  final TextEditingController _city = TextEditingController();
  final TextEditingController _postalCode = TextEditingController();
  final TextEditingController _country = TextEditingController(text: 'Bangladesh');
  bool _loaded = false;

  @override
  void dispose() {
    _title.dispose();
    _fullName.dispose();
    _phone.dispose();
    _line1.dispose();
    _line2.dispose();
    _area.dispose();
    _city.dispose();
    _postalCode.dispose();
    _country.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final existing = ref.read(savedAddressesProvider.notifier).byId(widget.addressId);
    if (!_loaded) {
      _loaded = true;
      if (existing != null) {
        _title.text = existing.title;
        _fullName.text = existing.fullName;
        _phone.text = existing.phone;
        _line1.text = existing.line1;
        _line2.text = existing.line2;
        _area.text = existing.area;
        _city.text = existing.city;
        _postalCode.text = existing.postalCode;
        _country.text = existing.country;
        _default = existing.isDefault;
      } else {
        _title.text = 'Home';
      }
    }

    Future<void> save() async {
      final title = _title.text.trim();
      final fullName = _fullName.text.trim();
      final phone = _phone.text.trim();
      final line1 = _line1.text.trim();
      final area = _area.text.trim();
      final city = _city.text.trim();
      final postal = _postalCode.text.trim();
      final country = _country.text.trim();
      if (title.isEmpty || fullName.isEmpty || phone.isEmpty || line1.isEmpty || area.isEmpty || city.isEmpty || postal.isEmpty || country.isEmpty) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please fill all required fields.')));
        }
        return;
      }

      final id = existing?.id ?? 'addr_${DateTime.now().millisecondsSinceEpoch}';
      await ref.read(savedAddressesProvider.notifier).upsert(
            CheckoutAddress(
              id: id,
              title: title,
              fullName: fullName,
              phone: phone,
              line1: line1,
              line2: _line2.text.trim(),
              area: area,
              city: city,
              postalCode: postal,
              country: country,
              isDefault: _default,
            ),
          );
      if (context.mounted) context.pop();
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Text(existing == null ? 'Add Address' : 'Edit Address', style: cartSectionHeading(Theme.of(context).textTheme)),
        centerTitle: true,
        actions: <Widget>[
          TextButton(
            onPressed: save,
            child: const Text('Save', style: TextStyle(fontWeight: FontWeight.w800)),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: <Widget>[
            _AddressField(label: 'Title', hint: 'Home', controller: _title),
            _AddressField(label: 'Full Name', hint: 'Mohammad Ashikur Rahman', controller: _fullName),
            _AddressField(label: 'Phone', hint: '01912-345678', controller: _phone),
            _AddressField(label: 'Address Line 1', hint: '123 Green Road', controller: _line1),
            _AddressField(label: 'Address Line 2 (Optional)', hint: 'House 10, Flat 3B', controller: _line2),
            _AddressField(label: 'Area / Street', hint: 'Dhanmondi', controller: _area),
            Row(
              children: <Widget>[
                Expanded(child: _AddressField(label: 'City', hint: 'Dhaka', controller: _city)),
                SizedBox(width: 10),
                Expanded(child: _AddressField(label: 'Postal Code', hint: '1205', controller: _postalCode)),
              ],
            ),
            _AddressField(label: 'Country', hint: 'Bangladesh', trailing: Icons.keyboard_arrow_down_rounded, controller: _country),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6),
              child: Row(
                children: <Widget>[
                  Text(
                    'Set as default address',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700, color: kCartNavy),
                  ),
                  const Spacer(),
                  Switch(
                    value: _default,
                    onChanged: (v) => setState(() => _default = v),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AddressField extends StatelessWidget {
  const _AddressField({
    required this.label,
    required this.hint,
    required this.controller,
    this.trailing,
  });

  final String label;
  final String hint;
  final TextEditingController controller;
  final IconData? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kCartMuted, fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          TextField(
            controller: controller,
            decoration: InputDecoration(
              hintText: hint,
              suffixIcon: trailing == null ? null : Icon(trailing),
            ),
          ),
        ],
      ),
    );
  }
}
