import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/util/debouncer.dart';
import '../application/seller_business_controller.dart';
import '../data/seller_form_draft_store.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerShippingSettingsScreen extends ConsumerStatefulWidget {
  const SellerShippingSettingsScreen({super.key});

  @override
  ConsumerState<SellerShippingSettingsScreen> createState() => _SellerShippingSettingsScreenState();
}

class _SellerShippingSettingsScreenState extends ConsumerState<SellerShippingSettingsScreen> {
  final TextEditingController _insideLabel = TextEditingController();
  final TextEditingController _insideFee = TextEditingController();
  final TextEditingController _outsideLabel = TextEditingController();
  final TextEditingController _outsideFee = TextEditingController();
  bool _cod = true;
  String _processing = '1-2 Business Days';
  bool _seeded = false;
  bool _draftChecked = false;
  bool _listenersAttached = false;
  final Debouncer _draftDebouncer = Debouncer(duration: const Duration(milliseconds: 450));

  @override
  void dispose() {
    _draftDebouncer.dispose();
    _insideLabel.dispose();
    _insideFee.dispose();
    _outsideLabel.dispose();
    _outsideFee.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_listenersAttached) {
      return;
    }
    _listenersAttached = true;
    void persistDraft() {
      _draftDebouncer.run(() {
        final inside = double.tryParse(_insideFee.text.trim());
        final outside = double.tryParse(_outsideFee.text.trim());
        ref.read(sellerFormDraftStoreProvider).saveShippingDraft(<String, dynamic>{
          'insideDhakaLabel': _insideLabel.text,
          'insideDhakaFee': inside,
          'outsideDhakaLabel': _outsideLabel.text,
          'outsideDhakaFee': outside,
          'cashOnDeliveryEnabled': _cod,
          'processingTimeLabel': _processing,
        });
      });
    }

    _insideLabel.addListener(persistDraft);
    _insideFee.addListener(persistDraft);
    _outsideLabel.addListener(persistDraft);
    _outsideFee.addListener(persistDraft);
  }

  Future<void> _save() async {
    final insideFee = double.tryParse(_insideFee.text.trim());
    final outsideFee = double.tryParse(_outsideFee.text.trim());
    if (insideFee == null || outsideFee == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter valid fees.')));
      return;
    }
    await ref.read(sellerBusinessControllerProvider.notifier).saveShippingSettings(
          SellerShippingSettings(
            insideDhakaLabel: _insideLabel.text.trim(),
            insideDhakaFee: insideFee,
            outsideDhakaLabel: _outsideLabel.text.trim(),
            outsideDhakaFee: outsideFee,
            cashOnDeliveryEnabled: _cod,
            processingTimeLabel: _processing,
          ),
        );
    if (!mounted) return;
    final next = ref.read(sellerBusinessControllerProvider);
    final message = next.failure?.message ?? next.successMessage ?? 'Changes processed.';
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final drafts = ref.read(sellerFormDraftStoreProvider);
    if (!_draftChecked) {
      _draftChecked = true;
      final draft = drafts.loadShippingDraft();
      if (draft != null) {
        final il = draft['insideDhakaLabel']?.toString();
        final inf = draft['insideDhakaFee'];
        final ol = draft['outsideDhakaLabel']?.toString();
        final onf = draft['outsideDhakaFee'];
        if (il != null && il.isNotEmpty) {
          _insideLabel.text = il;
        }
        if (ol != null && ol.isNotEmpty) {
          _outsideLabel.text = ol;
        }
        if (inf != null) {
          _insideFee.text = (num.tryParse(inf.toString())?.toStringAsFixed(0)) ?? _insideFee.text;
        }
        if (onf != null) {
          _outsideFee.text = (num.tryParse(onf.toString())?.toStringAsFixed(0)) ?? _outsideFee.text;
        }
        if (draft['cashOnDeliveryEnabled'] is bool) {
          _cod = draft['cashOnDeliveryEnabled']! as bool;
        }
        if (draft['processingTimeLabel'] != null) {
          _processing = draft['processingTimeLabel'].toString();
        }
        _seeded = true;
      }
    }

    final state = ref.watch(sellerBusinessControllerProvider);
    if (!_seeded) {
      final settings = state.shippingSettings;
      _insideLabel.text = settings.insideDhakaLabel;
      _insideFee.text = settings.insideDhakaFee.toStringAsFixed(0);
      _outsideLabel.text = settings.outsideDhakaLabel;
      _outsideFee.text = settings.outsideDhakaFee.toStringAsFixed(0);
      _cod = settings.cashOnDeliveryEnabled;
      _processing = settings.processingTimeLabel;
      _seeded = true;
    }
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Shipping Settings'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: <Widget>[
          _sectionLabel('Default Shipping Method'),
          const SizedBox(height: 8),
          Row(
            children: <Widget>[
              Expanded(child: TextField(controller: _insideLabel, decoration: const InputDecoration(border: OutlineInputBorder()))),
              const SizedBox(width: 10),
              SizedBox(
                width: 100,
                child: TextField(
                  controller: _insideFee,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(border: OutlineInputBorder(), prefixText: '৳ '),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              Expanded(child: TextField(controller: _outsideLabel, decoration: const InputDecoration(border: OutlineInputBorder()))),
              const SizedBox(width: 10),
              SizedBox(
                width: 100,
                child: TextField(
                  controller: _outsideFee,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(border: OutlineInputBorder(), prefixText: '৳ '),
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          _sectionLabel('Cash on Delivery'),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(border: Border.all(color: const Color(0xFFE5E7EB)), borderRadius: BorderRadius.circular(12)),
            child: Row(
              children: <Widget>[
                const Expanded(child: Text('Enable COD', style: TextStyle(fontWeight: FontWeight.w700))),
                Switch(
                  value: _cod,
                  onChanged: (v) {
                    setState(() => _cod = v);
                    ref.read(sellerFormDraftStoreProvider).saveShippingDraft(<String, dynamic>{
                      'insideDhakaLabel': _insideLabel.text,
                      'insideDhakaFee': double.tryParse(_insideFee.text.trim()),
                      'outsideDhakaLabel': _outsideLabel.text,
                      'outsideDhakaFee': double.tryParse(_outsideFee.text.trim()),
                      'cashOnDeliveryEnabled': _cod,
                      'processingTimeLabel': _processing,
                    });
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 22),
          _sectionLabel('Processing Time'),
          const SizedBox(height: 8),
          InkWell(
            onTap: () async {
              final choice = await showModalBottomSheet<String>(
                context: context,
                showDragHandle: true,
                builder: (BuildContext ctx) => SafeArea(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      ListTile(title: const Text('Same day'), onTap: () => Navigator.pop(ctx, 'Same day')),
                      ListTile(title: const Text('1-2 Business Days'), onTap: () => Navigator.pop(ctx, '1-2 Business Days')),
                      ListTile(title: const Text('3-5 Business Days'), onTap: () => Navigator.pop(ctx, '3-5 Business Days')),
                    ],
                  ),
                ),
              );
              if (choice != null) {
                setState(() => _processing = choice);
                ref.read(sellerFormDraftStoreProvider).saveShippingDraft(<String, dynamic>{
                  'insideDhakaLabel': _insideLabel.text,
                  'insideDhakaFee': double.tryParse(_insideFee.text.trim()),
                  'outsideDhakaLabel': _outsideLabel.text,
                  'outsideDhakaFee': double.tryParse(_outsideFee.text.trim()),
                  'cashOnDeliveryEnabled': _cod,
                  'processingTimeLabel': _processing,
                });
              }
            },
            borderRadius: BorderRadius.circular(12),
            child: InputDecorator(
              decoration: const InputDecoration(border: OutlineInputBorder(), suffixIcon: Icon(Icons.expand_more_rounded)),
              child: Text(_processing),
            ),
          ),
          const SizedBox(height: 28),
          FilledButton(
            onPressed: state.isSaving ? null : _save,
            style: FilledButton.styleFrom(backgroundColor: kSellerAccent, minimumSize: const Size.fromHeight(52)),
            child: state.isSaving ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Save Changes'),
          ),
        ],
      ),
    );
  }

  Widget _sectionLabel(String t) {
    return Text(t, style: const TextStyle(color: kSellerMuted, fontWeight: FontWeight.w600, fontSize: 13));
  }
}
