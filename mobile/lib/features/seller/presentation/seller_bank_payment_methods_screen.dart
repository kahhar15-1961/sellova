import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/util/debouncer.dart';
import '../application/seller_business_controller.dart';
import '../data/seller_form_draft_store.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerBankPaymentMethodsScreen extends ConsumerStatefulWidget {
  const SellerBankPaymentMethodsScreen({super.key});

  @override
  ConsumerState<SellerBankPaymentMethodsScreen> createState() => _SellerBankPaymentMethodsScreenState();
}

class _SellerBankPaymentMethodsScreenState extends ConsumerState<SellerBankPaymentMethodsScreen> {
  final _nameCtrl = TextEditingController();
  final _numberCtrl = TextEditingController();
  final _bankCtrl = TextEditingController();
  SellerPayoutMethodType _type = SellerPayoutMethodType.bkash;
  bool _asDefault = true;
  bool _draftChecked = false;
  bool _listenersAttached = false;
  final Debouncer _draftDebouncer = Debouncer(duration: const Duration(milliseconds: 450));

  @override
  void dispose() {
    _draftDebouncer.dispose();
    _nameCtrl.dispose();
    _numberCtrl.dispose();
    _bankCtrl.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_listenersAttached) {
      return;
    }
    _listenersAttached = true;
    void persist() {
      _draftDebouncer.run(() {
        ref.read(sellerFormDraftStoreProvider).saveBankPaymentDraft(<String, dynamic>{
          'type': _type.apiValue,
          'name': _nameCtrl.text,
          'number': _numberCtrl.text,
          'bank': _bankCtrl.text,
          'asDefault': _asDefault,
        });
      });
    }

    _nameCtrl.addListener(persist);
    _numberCtrl.addListener(persist);
    _bankCtrl.addListener(persist);
  }

  Future<void> _saveMethod() async {
    if (_nameCtrl.text.trim().isEmpty || _numberCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please provide account details.')));
      return;
    }
    await ref.read(sellerBusinessControllerProvider.notifier).addOrUpdatePayoutMethod(
          type: _type,
          accountName: _nameCtrl.text,
          accountNumber: _numberCtrl.text,
          bankName: _type == SellerPayoutMethodType.bankTransfer ? _bankCtrl.text : null,
          asDefault: _asDefault,
        );
    if (!mounted) return;
    final state = ref.read(sellerBusinessControllerProvider);
    final msg = state.failure?.message ?? state.successMessage ?? 'Request completed.';
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    if (state.failure == null) {
      await ref.read(sellerFormDraftStoreProvider).clearBankPaymentDraft();
      _numberCtrl.clear();
      _bankCtrl.clear();
      _asDefault = false;
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final drafts = ref.read(sellerFormDraftStoreProvider);
    if (!_draftChecked) {
      _draftChecked = true;
      final d = drafts.loadBankPaymentDraft();
      if (d != null) {
        final t = (d['type'] ?? '').toString();
        if (t == 'nagad') {
          _type = SellerPayoutMethodType.nagad;
        } else if (t == 'bank_transfer') {
          _type = SellerPayoutMethodType.bankTransfer;
        } else {
          _type = SellerPayoutMethodType.bkash;
        }
        _nameCtrl.text = d['name']?.toString() ?? '';
        _numberCtrl.text = d['number']?.toString() ?? '';
        _bankCtrl.text = d['bank']?.toString() ?? '';
        if (d['asDefault'] is bool) {
          _asDefault = d['asDefault']! as bool;
        }
      }
    }
    final state = ref.watch(sellerBusinessControllerProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Bank & Payment Methods'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: <Widget>[
          Text('Saved methods', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          if (state.payoutMethods.isEmpty)
            const Text('No payout methods yet.')
          else
            ...state.payoutMethods.map((m) => _savedMethodTile(context, m)),
          const SizedBox(height: 20),
          Text('Add / Update method', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          SegmentedButton<SellerPayoutMethodType>(
            segments: const <ButtonSegment<SellerPayoutMethodType>>[
              ButtonSegment<SellerPayoutMethodType>(value: SellerPayoutMethodType.bkash, label: Text('bKash')),
              ButtonSegment<SellerPayoutMethodType>(value: SellerPayoutMethodType.nagad, label: Text('Nagad')),
              ButtonSegment<SellerPayoutMethodType>(value: SellerPayoutMethodType.bankTransfer, label: Text('Bank')),
            ],
            selected: <SellerPayoutMethodType>{_type},
            onSelectionChanged: (v) {
              setState(() => _type = v.first);
              ref.read(sellerFormDraftStoreProvider).saveBankPaymentDraft(<String, dynamic>{
                'type': _type.apiValue,
                'name': _nameCtrl.text,
                'number': _numberCtrl.text,
                'bank': _bankCtrl.text,
                'asDefault': _asDefault,
              });
            },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _nameCtrl,
            decoration: const InputDecoration(
              labelText: 'Account name',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _numberCtrl,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Account number',
              border: OutlineInputBorder(),
            ),
          ),
          if (_type == SellerPayoutMethodType.bankTransfer) ...<Widget>[
            const SizedBox(height: 10),
            TextField(
              controller: _bankCtrl,
              decoration: const InputDecoration(
                labelText: 'Bank name',
                border: OutlineInputBorder(),
              ),
            ),
          ],
          const SizedBox(height: 10),
          SwitchListTile(
            value: _asDefault,
            onChanged: (v) {
              setState(() => _asDefault = v);
              ref.read(sellerFormDraftStoreProvider).saveBankPaymentDraft(<String, dynamic>{
                'type': _type.apiValue,
                'name': _nameCtrl.text,
                'number': _numberCtrl.text,
                'bank': _bankCtrl.text,
                'asDefault': _asDefault,
              });
            },
            title: const Text('Set as default method'),
            contentPadding: EdgeInsets.zero,
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: state.isSaving ? null : _saveMethod,
            style: FilledButton.styleFrom(backgroundColor: kSellerAccent, minimumSize: const Size.fromHeight(52)),
            child: state.isSaving
                ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Save Method'),
          ),
        ],
      ),
    );
  }

  Widget _savedMethodTile(BuildContext context, SellerPayoutMethod method) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Row(
        children: <Widget>[
          CircleAvatar(
            backgroundColor: const Color(0xFFEDE9FE),
            child: Icon(
              method.type == SellerPayoutMethodType.bankTransfer ? Icons.account_balance_rounded : Icons.phone_android_rounded,
              color: kSellerAccent,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(method.type.label, style: const TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(method.accountNumberMasked, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
              ],
            ),
          ),
          if (method.isDefault)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: const Color(0xFFECFDF5), borderRadius: BorderRadius.circular(999)),
              child: const Text('Default', style: TextStyle(color: Color(0xFF15803D), fontWeight: FontWeight.w700)),
            ),
        ],
      ),
    );
  }
}
