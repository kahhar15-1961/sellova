import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/util/debouncer.dart';
import '../../profile/application/wallet_controller.dart';
import '../../profile/data/wallet_repository.dart';
import '../application/seller_business_controller.dart';
import '../application/seller_demo_controller.dart';
import '../data/seller_form_draft_store.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerWithdrawScreen extends ConsumerStatefulWidget {
  const SellerWithdrawScreen({super.key});

  @override
  ConsumerState<SellerWithdrawScreen> createState() =>
      _SellerWithdrawScreenState();
}

class _SellerWithdrawScreenState extends ConsumerState<SellerWithdrawScreen> {
  SellerPayoutMethodType _method = SellerPayoutMethodType.bkash;
  final TextEditingController _account = TextEditingController();
  final TextEditingController _amount = TextEditingController();
  bool _seeded = false;
  bool _draftChecked = false;
  bool _listenersAttached = false;
  final Debouncer _draftDebouncer =
      Debouncer(duration: const Duration(milliseconds: 450));

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(() {
      ref.read(walletControllerProvider.notifier).load();
      ref.read(sellerBusinessControllerProvider.notifier).load();
    });
  }

  void _persistDraft() {
    _draftDebouncer.run(() {
      ref
          .read(sellerFormDraftStoreProvider)
          .saveWithdrawDraft(<String, dynamic>{
        'method': _method.apiValue,
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
    _amount.addListener(() {
      _persistDraft();
      if (mounted) {
        setState(() {});
      }
    });
  }

  Future<void> _submit({
    required double availableBalance,
    required double minimumWithdrawal,
    required int? walletId,
    required String currency,
    required SellerPayoutMethod? selectedMethod,
  }) async {
    if (selectedMethod == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Add a payout account before requesting withdrawal.')));
      return;
    }
    if (_amount.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please enter an amount.')));
      return;
    }
    final amount = double.tryParse(_amount.text.trim());
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Enter a valid withdrawal amount.')));
      return;
    }
    if (amount < minimumWithdrawal) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(
              'Minimum withdrawal is ${_moneyLabel(minimumWithdrawal)}.')));
      return;
    }
    if (amount > availableBalance) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text(
              'Withdrawal amount is higher than your available balance.')));
      return;
    }
    try {
      await ref
          .read(sellerBusinessControllerProvider.notifier)
          .requestWithdrawal(
            type: _method,
            accountNumber: selectedMethod.accountNumberMasked,
            amountText: _amount.text,
            walletId: walletId,
            currency: currency,
          );
      if (!mounted) return;
      HapticFeedback.mediumImpact();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text(
                'Withdrawal request submitted. You will be notified when it is processed.')),
      );
      context.pop();
    } catch (_) {
      if (!mounted) return;
      final message =
          ref.read(sellerBusinessControllerProvider).failure?.message ??
              'Failed to submit withdrawal.';
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
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
        final amt = draft['amount']?.toString();
        if (amt != null && amt.isNotEmpty) {
          _amount.text = amt;
        }
        _seeded = true;
      }
    }

    final state = ref.watch(sellerBusinessControllerProvider);
    final walletState = ref.watch(walletControllerProvider);
    final sellerWallet = _sellerWallet(walletState.wallets);
    final fallbackBalance = ref
        .watch(sellerOrdersProvider)
        .where((order) => order.status == SellerOrderStatus.delivered)
        .fold<double>(0, (sum, order) => sum + order.totalAmount);
    final availableBalance = sellerWallet == null
        ? fallbackBalance
        : (double.tryParse(sellerWallet.availableBalance) ?? 0);
    final currency = sellerWallet?.currency.isNotEmpty == true
        ? sellerWallet!.currency
        : state.withdrawalSettings.currency;
    final minimumWithdrawal = state.withdrawalSettings.minimumWithdrawalAmount;
    if (!_seeded && state.payoutMethods.isNotEmpty) {
      final defaultMethod = state.payoutMethods.firstWhere(
        (e) => e.isDefault,
        orElse: () => state.payoutMethods.first,
      );
      _method = defaultMethod.type;
      _account.text = defaultMethod.accountNumberMasked;
      _seeded = true;
    }
    final selectedMethod = _selectedPayoutMethod(state.payoutMethods);
    _syncAccountField(selectedMethod);
    final amount = double.tryParse(_amount.text.trim()) ?? 0;
    final disabledReason = _disabledReason(
      amount: amount,
      availableBalance: availableBalance,
      minimumWithdrawal: minimumWithdrawal,
      selectedMethod: selectedMethod,
      isSaving: state.isSaving,
    );
    return Scaffold(
      backgroundColor: const Color(0xFFFFFFFF),
      appBar: AppBar(
        title: const Text('Withdraw'),
        leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded),
            onPressed: () => context.pop()),
        actions: <Widget>[
          TextButton.icon(
            onPressed: () => context.push('/seller/withdraw/history'),
            icon: const Icon(Icons.history_rounded, size: 18),
            label: const Text('History'),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
        children: <Widget>[
          Container(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 28),
            decoration: BoxDecoration(
              gradient: kSellerPrimaryGradient,
              borderRadius: BorderRadius.circular(24),
              boxShadow: <BoxShadow>[sellerGradientShadow()],
            ),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'AVAILABLE BALANCE',
                        style:
                            Theme.of(context).textTheme.labelMedium?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.72),
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 1.2,
                                ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _moneyLabel(availableBalance),
                        style:
                            Theme.of(context).textTheme.headlineSmall?.copyWith(
                                  color: Colors.white,
                                  fontSize: 38,
                                  fontWeight: FontWeight.w900,
                                  height: 1,
                                ),
                      ),
                    ],
                  ),
                ),
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border:
                        Border.all(color: Colors.white.withValues(alpha: 0.12)),
                  ),
                  child: Icon(
                    Icons.account_balance_wallet_outlined,
                    size: 28,
                    color: Colors.white.withValues(alpha: 0.86),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            decoration: BoxDecoration(
              color: const Color(0xFFFCFCFD),
              borderRadius: BorderRadius.circular(11),
              border: Border.all(color: const Color(0xFFE3E4E8)),
            ),
            child: Row(
              children: <Widget>[
                const Icon(Icons.info_outline_rounded,
                    color: Color(0xFF5B50FF), size: 18),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Minimum withdrawal: ${_moneyLabel(minimumWithdrawal)}',
                    style: const TextStyle(
                      color: Color(0xFF3F3F46),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF4F4F5),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    currency,
                    style: const TextStyle(
                      color: Color(0xFFA1A1AA),
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 32),
          Text('Withdraw Method',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontSize: 15, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          _methodTile('bKash', SellerPayoutMethodType.bkash,
              Icons.phone_android_rounded),
          const SizedBox(height: 8),
          _methodTile('Nagad', SellerPayoutMethodType.nagad,
              Icons.phone_android_rounded),
          const SizedBox(height: 8),
          _methodTile('Bank Transfer', SellerPayoutMethodType.bankTransfer,
              Icons.account_balance_rounded),
          const SizedBox(height: 30),
          Text('Account Number',
              style: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(fontSize: 14, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          TextField(
            controller: _account,
            readOnly: true,
            decoration: InputDecoration(
              hintText: selectedMethod == null
                  ? 'No ${_method.label} account saved'
                  : 'Selected payout account',
              suffixIcon: TextButton(
                onPressed: () => context.push('/seller/bank-payment-methods'),
                child: const Text('Manage'),
              ),
            ),
          ),
          const SizedBox(height: 20),
          Text('Amount (৳)',
              style: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(fontSize: 14, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          TextField(
            controller: _amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(
              hintText: 'Enter amount',
            ),
          ),
          const SizedBox(height: 28),
          if (disabledReason != null) ...<Widget>[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFDE68A)),
              ),
              child: Text(
                disabledReason,
                style: const TextStyle(
                    color: Color(0xFF92400E), fontWeight: FontWeight.w700),
              ),
            ),
            const SizedBox(height: 12),
          ],
          FilledButton(
            onPressed: disabledReason != null
                ? null
                : () => _submit(
                      availableBalance: availableBalance,
                      minimumWithdrawal: minimumWithdrawal,
                      walletId: sellerWallet?.id,
                      currency: currency,
                      selectedMethod: selectedMethod,
                    ),
            style: FilledButton.styleFrom(
              backgroundColor: kSellerAccent,
              disabledBackgroundColor: const Color(0xFFE2E8F0),
              disabledForegroundColor: const Color(0xFF64748B),
              minimumSize: const Size.fromHeight(54),
            ),
            child: state.isSaving
                ? const SizedBox(
                    height: 22,
                    width: 22,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white))
                : const Text('Withdraw Now'),
          ),
        ],
      ),
    );
  }

  Widget _methodTile(String label, SellerPayoutMethodType m, IconData icon) {
    final selected = _method == m;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: () {
          setState(() {
            _method = m;
            _syncAccountField(null);
          });
          _persistDraft();
        },
        borderRadius: BorderRadius.circular(12),
        child: Container(
          constraints: const BoxConstraints(minHeight: 56),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color:
                  selected ? const Color(0xFF5146FF) : const Color(0xFFEDEEF1),
              width: selected ? 1.8 : 1,
            ),
            color: selected ? const Color(0xFFFBFBFF) : Colors.white,
          ),
          child: Row(
            children: <Widget>[
              Icon(
                selected
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_off_rounded,
                color: selected
                    ? const Color(0xFF5146FF)
                    : const Color(0xFFD1D5DB),
                size: 21,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: TextStyle(
                    color: selected
                        ? const Color(0xFF18181B)
                        : const Color(0xFF52525B),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Icon(
                icon,
                color: selected
                    ? const Color(0xFF5146FF)
                    : const Color(0xFFA1A1AA),
                size: 23,
              ),
            ],
          ),
        ),
      ),
    );
  }

  SellerPayoutMethod? _selectedPayoutMethod(List<SellerPayoutMethod> methods) {
    final active = methods.where((e) => e.isActive && e.type == _method);
    if (active.isEmpty) {
      return null;
    }
    return active.firstWhere((e) => e.isDefault, orElse: () => active.first);
  }

  void _syncAccountField(SellerPayoutMethod? selectedMethod) {
    final value = selectedMethod?.accountNumberMasked ?? '';
    if (_account.text != value) {
      _account.text = value;
    }
  }

  String? _disabledReason({
    required double amount,
    required double availableBalance,
    required double minimumWithdrawal,
    required SellerPayoutMethod? selectedMethod,
    required bool isSaving,
  }) {
    if (isSaving) {
      return 'Submitting withdrawal request...';
    }
    if (selectedMethod == null) {
      return 'No saved ${_method.label} payout account. Add one before withdrawing.';
    }
    if (availableBalance < minimumWithdrawal) {
      return 'Your available balance must be at least ${_moneyLabel(minimumWithdrawal)} to withdraw.';
    }
    if (_amount.text.trim().isEmpty) {
      return 'Enter an amount to continue.';
    }
    if (amount <= 0) {
      return 'Enter a valid withdrawal amount.';
    }
    if (amount < minimumWithdrawal) {
      return 'Amount must be at least ${_moneyLabel(minimumWithdrawal)}.';
    }
    if (amount > availableBalance) {
      return 'Amount cannot exceed your available balance.';
    }
    return null;
  }
}

WalletDto? _sellerWallet(List<WalletDto> wallets) {
  for (final wallet in wallets) {
    if (wallet.walletType == 'seller') {
      return wallet;
    }
  }
  return null;
}

String _moneyLabel(double value) {
  final rounded = value.toStringAsFixed(2);
  final parts = rounded.split('.');
  return '৳ ${_withCommas(int.parse(parts.first))}.${parts.last}';
}

String _withCommas(int value) {
  final raw = value.toString();
  final buffer = StringBuffer();
  for (var i = 0; i < raw.length; i += 1) {
    final remaining = raw.length - i;
    buffer.write(raw[i]);
    if (remaining > 1 && remaining % 3 == 1) {
      buffer.write(',');
    }
  }
  return buffer.toString();
}
