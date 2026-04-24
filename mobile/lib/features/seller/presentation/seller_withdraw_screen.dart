import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/util/debouncer.dart';
import '../application/seller_business_controller.dart';
import '../data/seller_form_draft_store.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerWithdrawScreen extends ConsumerStatefulWidget {
  const SellerWithdrawScreen({super.key});

  @override
  ConsumerState<SellerWithdrawScreen> createState() => _SellerWithdrawScreenState();
}

class _SellerWithdrawScreenState extends ConsumerState<SellerWithdrawScreen> {
  SellerPayoutMethodType _method = SellerPayoutMethodType.bkash;
  final TextEditingController _account = TextEditingController();
  final TextEditingController _amount = TextEditingController();
  bool _seeded = false;
  bool _draftChecked = false;
  bool _listenersAttached = false;
  final Debouncer _draftDebouncer = Debouncer(duration: const Duration(milliseconds: 450));

  void _persistDraft() {
    _draftDebouncer.run(() {
      ref.read(sellerFormDraftStoreProvider).saveWithdrawDraft(<String, dynamic>{
        'method': _method.apiValue,
        'account': _account.text,
        'amount': _amount.text,
      });
    });
  }

  @override
  void dispose() {
    _draftDebouncer.dispose();
    _account.dispose();
    _amount.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_listenersAttached) {
      return;
    }
    _listenersAttached = true;
    _account.addListener(_persistDraft);
    _amount.addListener(_persistDraft);
  }

  Future<void> _submit() async {
    if (_account.text.trim().isEmpty || _amount.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please fill in all fields.')));
      return;
    }
    try {
      await ref.read(sellerBusinessControllerProvider.notifier).requestWithdrawal(
            type: _method,
            accountNumber: _account.text,
            amountText: _amount.text,
          );
      if (!mounted) return;
      HapticFeedback.mediumImpact();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Withdrawal request submitted. You will be notified when it is processed.')),
      );
      context.pop();
    } catch (_) {
      if (!mounted) return;
      final message = ref.read(sellerBusinessControllerProvider).failure?.message ?? 'Failed to submit withdrawal.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  SellerPayoutMethodType _methodFromApi(String? raw) {
    switch ((raw ?? '').toLowerCase()) {
      case 'nagad':
        return SellerPayoutMethodType.nagad;
      case 'bank_transfer':
      case 'bank':
        return SellerPayoutMethodType.bankTransfer;
      default:
        return SellerPayoutMethodType.bkash;
    }
  }

  @override
  Widget build(BuildContext context) {
    final drafts = ref.read(sellerFormDraftStoreProvider);
    if (!_draftChecked) {
      _draftChecked = true;
      final draft = drafts.loadWithdrawDraft();
      if (draft != null) {
        final m = _methodFromApi(draft['method']?.toString());
        _method = m;
        final acct = draft['account']?.toString();
        final amt = draft['amount']?.toString();
        if (acct != null && acct.isNotEmpty) {
          _account.text = acct;
        }
        if (amt != null && amt.isNotEmpty) {
          _amount.text = amt;
        }
        _seeded = true;
      }
    }

    final state = ref.watch(sellerBusinessControllerProvider);
    if (!_seeded && state.payoutMethods.isNotEmpty) {
      final defaultMethod = state.payoutMethods.firstWhere(
        (e) => e.isDefault,
        orElse: () => state.payoutMethods.first,
      );
      _method = defaultMethod.type;
      _account.text = defaultMethod.accountNumberMasked;
      _seeded = true;
    }
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Withdraw'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: kSellerAccent,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text('Available Balance', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.white70)),
                      const SizedBox(height: 6),
                      Text('৳ 45,230.00', style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w900)),
                    ],
                  ),
                ),
                Icon(Icons.account_balance_wallet_outlined, size: 48, color: Colors.white.withValues(alpha: 0.35)),
              ],
            ),
          ),
          const SizedBox(height: 22),
          Text('Withdraw Method', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 12),
          _methodTile('bKash', SellerPayoutMethodType.bkash, Icons.phone_android_rounded, const Color(0xFFE2136E)),
          const SizedBox(height: 8),
          _methodTile('Nagad', SellerPayoutMethodType.nagad, Icons.phone_android_rounded, const Color(0xFFF7941D)),
          const SizedBox(height: 8),
          _methodTile('Bank Transfer', SellerPayoutMethodType.bankTransfer, Icons.account_balance_rounded, const Color(0xFF1E3A5F)),
          const SizedBox(height: 22),
          Text('Account Number', style: Theme.of(context).textTheme.labelMedium?.copyWith(color: kSellerMuted, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          TextField(
            controller: _account,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(
              hintText: '01XXXXXXXXX',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          Text('Amount (৳)', style: Theme.of(context).textTheme.labelMedium?.copyWith(color: kSellerMuted, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          TextField(
            controller: _amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(
              hintText: 'Enter amount',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 28),
          FilledButton(
            onPressed: state.isSaving ? null : _submit,
            style: FilledButton.styleFrom(
              backgroundColor: kSellerAccent,
              minimumSize: const Size.fromHeight(54),
            ),
            child: state.isSaving ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Withdraw Now'),
          ),
        ],
      ),
    );
  }

  Widget _methodTile(String label, SellerPayoutMethodType m, IconData icon, Color brand) {
    final selected = _method == m;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: () {
          setState(() => _method = m);
          _persistDraft();
        },
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: selected ? kSellerAccent : const Color(0xFFE5E7EB)),
          ),
          child: Row(
            children: <Widget>[
              Icon(
                selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                color: selected ? kSellerAccent : kSellerMuted,
              ),
              const SizedBox(width: 4),
              Expanded(child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700))),
              Icon(icon, color: brand, size: 26),
            ],
          ),
        ),
      ),
    );
  }
}
