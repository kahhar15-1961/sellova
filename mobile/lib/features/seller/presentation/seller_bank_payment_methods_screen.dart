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
  ConsumerState<SellerBankPaymentMethodsScreen> createState() =>
      _SellerBankPaymentMethodsScreenState();
}

class _SellerBankPaymentMethodsScreenState
    extends ConsumerState<SellerBankPaymentMethodsScreen> {
  final _bkashNameCtrl = TextEditingController();
  final _bkashWalletCtrl = TextEditingController();
  final _nagadNameCtrl = TextEditingController();
  final _nagadWalletCtrl = TextEditingController();
  final _bankHolderCtrl = TextEditingController();
  final _bankNameCtrl = TextEditingController();
  final _bankAccountCtrl = TextEditingController();
  final _branchCtrl = TextEditingController();
  final _routingCtrl = TextEditingController();
  SellerPayoutMethodType _type = SellerPayoutMethodType.bkash;
  String _bkashAccountType = 'Personal';
  String _nagadAccountType = 'Personal';
  bool _asDefault = true;
  bool _draftChecked = false;
  bool _listenersAttached = false;
  final Debouncer _draftDebouncer =
      Debouncer(duration: const Duration(milliseconds: 450));

  @override
  void dispose() {
    _draftDebouncer.dispose();
    _bkashNameCtrl.dispose();
    _bkashWalletCtrl.dispose();
    _nagadNameCtrl.dispose();
    _nagadWalletCtrl.dispose();
    _bankHolderCtrl.dispose();
    _bankNameCtrl.dispose();
    _bankAccountCtrl.dispose();
    _branchCtrl.dispose();
    _routingCtrl.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_listenersAttached) {
      return;
    }
    _listenersAttached = true;
    void persist() => _draftDebouncer.run(_saveDraft);

    _bkashNameCtrl.addListener(persist);
    _bkashWalletCtrl.addListener(persist);
    _nagadNameCtrl.addListener(persist);
    _nagadWalletCtrl.addListener(persist);
    _bankHolderCtrl.addListener(persist);
    _bankNameCtrl.addListener(persist);
    _bankAccountCtrl.addListener(persist);
    _branchCtrl.addListener(persist);
    _routingCtrl.addListener(persist);
  }

  void _saveDraft() {
    ref.read(sellerFormDraftStoreProvider).saveBankPaymentDraft(
      <String, dynamic>{
        'type': _type.apiValue,
        'bkash': <String, dynamic>{
          'account_name': _bkashNameCtrl.text,
          'wallet_number': _bkashWalletCtrl.text,
          'mobile_account_type': _bkashAccountType,
        },
        'nagad': <String, dynamic>{
          'account_name': _nagadNameCtrl.text,
          'wallet_number': _nagadWalletCtrl.text,
          'mobile_account_type': _nagadAccountType,
        },
        'bank': <String, dynamic>{
          'account_name': _bankHolderCtrl.text,
          'bank_name': _bankNameCtrl.text,
          'bank_account_number': _bankAccountCtrl.text,
          'branch_name': _branchCtrl.text,
          'routing_number': _routingCtrl.text,
        },
        'as_default': _asDefault,
      },
    );
  }

  void _loadDraftOnce() {
    if (_draftChecked) {
      return;
    }
    _draftChecked = true;
    final draft = ref.read(sellerFormDraftStoreProvider).loadBankPaymentDraft();
    if (draft == null) {
      return;
    }
    _type = _typeFromApi((draft['type'] ?? '').toString());
    final bkash = _mapFrom(draft['bkash']);
    final nagad = _mapFrom(draft['nagad']);
    final bank = _mapFrom(draft['bank']);

    _bkashNameCtrl.text = (bkash['account_name'] ?? '').toString();
    _bkashWalletCtrl.text = (bkash['wallet_number'] ?? '').toString();
    _bkashAccountType = _validMobileAccountType(
        (bkash['mobile_account_type'] ?? '').toString());
    _nagadNameCtrl.text = (nagad['account_name'] ?? '').toString();
    _nagadWalletCtrl.text = (nagad['wallet_number'] ?? '').toString();
    _nagadAccountType = _validMobileAccountType(
        (nagad['mobile_account_type'] ?? '').toString());
    _bankHolderCtrl.text = (bank['account_name'] ?? '').toString();
    _bankNameCtrl.text = (bank['bank_name'] ?? '').toString();
    _bankAccountCtrl.text = (bank['bank_account_number'] ?? '').toString();
    _branchCtrl.text = (bank['branch_name'] ?? '').toString();
    _routingCtrl.text = (bank['routing_number'] ?? '').toString();

    if (bkash.isEmpty && nagad.isEmpty && bank.isEmpty) {
      final legacyName =
          (draft['account_name'] ?? draft['name'] ?? '').toString();
      final legacyNumber =
          (draft['wallet_number'] ?? draft['number'] ?? '').toString();
      final legacyType = _validMobileAccountType(
          (draft['mobile_account_type'] ?? '').toString());
      if (_type == SellerPayoutMethodType.bkash) {
        _bkashNameCtrl.text = legacyName;
        _bkashWalletCtrl.text = legacyNumber;
        _bkashAccountType = legacyType;
      } else if (_type == SellerPayoutMethodType.nagad) {
        _nagadNameCtrl.text = legacyName;
        _nagadWalletCtrl.text = legacyNumber;
        _nagadAccountType = legacyType;
      } else {
        _bankHolderCtrl.text = legacyName;
        _bankNameCtrl.text =
            (draft['bank_name'] ?? draft['bank'] ?? '').toString();
        _bankAccountCtrl.text =
            (draft['bank_account_number'] ?? draft['number'] ?? '').toString();
        _branchCtrl.text = (draft['branch_name'] ?? '').toString();
        _routingCtrl.text = (draft['routing_number'] ?? '').toString();
      }
    }
    if (draft['as_default'] is bool) {
      _asDefault = draft['as_default']! as bool;
    } else if (draft['asDefault'] is bool) {
      _asDefault = draft['asDefault']! as bool;
    }
  }

  Map<String, dynamic> _mapFrom(Object? value) {
    if (value is Map<String, dynamic>) {
      return value;
    }
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }
    return <String, dynamic>{};
  }

  String _validMobileAccountType(String value) {
    return switch (value) {
      'Agent' => 'Agent',
      'Merchant' => 'Merchant',
      _ => 'Personal',
    };
  }

  SellerPayoutMethodType _typeFromApi(String raw) {
    return switch (raw) {
      'nagad' => SellerPayoutMethodType.nagad,
      'bank_transfer' => SellerPayoutMethodType.bankTransfer,
      _ => SellerPayoutMethodType.bkash,
    };
  }

  void _selectType(SellerPayoutMethodType next) {
    if (_type == next) {
      return;
    }
    setState(() {
      _type = next;
      _asDefault =
          ref.read(sellerBusinessControllerProvider).payoutMethods.isEmpty;
    });
    _saveDraft();
  }

  Future<void> _saveMethod() async {
    final error = _validationMessage();
    if (error != null) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error)));
      return;
    }

    final isBank = _type == SellerPayoutMethodType.bankTransfer;
    final nameCtrl = _currentNameController;
    final accountCtrl = isBank ? _bankAccountCtrl : _currentWalletController;
    await ref
        .read(sellerBusinessControllerProvider.notifier)
        .addOrUpdatePayoutMethod(
          type: _type,
          accountName: nameCtrl.text,
          accountNumber: accountCtrl.text,
          bankName: isBank ? _bankNameCtrl.text : null,
          branchName: isBank ? _branchCtrl.text : null,
          routingNumber: isBank ? _routingCtrl.text : null,
          accountType: isBank ? null : _currentMobileAccountType,
          asDefault: _asDefault,
        );

    if (!mounted) return;
    final state = ref.read(sellerBusinessControllerProvider);
    final msg =
        state.failure?.message ?? state.successMessage ?? 'Request completed.';
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    if (state.failure == null) {
      await ref.read(sellerFormDraftStoreProvider).clearBankPaymentDraft();
      _clearForm();
    }
  }

  String? _validationMessage() {
    if (_currentNameController.text.trim().isEmpty) {
      return 'Please enter the account holder name.';
    }
    if (_type == SellerPayoutMethodType.bankTransfer) {
      if (_bankNameCtrl.text.trim().isEmpty) {
        return 'Please enter the bank name.';
      }
      if (_bankAccountCtrl.text.trim().isEmpty) {
        return 'Please enter the bank account number.';
      }
      if (_branchCtrl.text.trim().isEmpty) {
        return 'Please enter the branch name.';
      }
      return null;
    }
    final walletNumber = _currentWalletController.text.trim();
    if (walletNumber.isEmpty) {
      return 'Please enter the ${_type.label} wallet number.';
    }
    if (walletNumber.length < 8) {
      return 'Please enter a valid ${_type.label} wallet number.';
    }
    return null;
  }

  void _clearForm() {
    if (_type == SellerPayoutMethodType.bankTransfer) {
      _bankHolderCtrl.clear();
      _bankNameCtrl.clear();
      _bankAccountCtrl.clear();
      _branchCtrl.clear();
      _routingCtrl.clear();
    } else if (_type == SellerPayoutMethodType.nagad) {
      _nagadNameCtrl.clear();
      _nagadWalletCtrl.clear();
      _nagadAccountType = 'Personal';
    } else {
      _bkashNameCtrl.clear();
      _bkashWalletCtrl.clear();
      _bkashAccountType = 'Personal';
    }
    setState(() {
      _asDefault =
          ref.read(sellerBusinessControllerProvider).payoutMethods.isEmpty;
    });
  }

  TextEditingController get _currentNameController {
    return switch (_type) {
      SellerPayoutMethodType.bkash => _bkashNameCtrl,
      SellerPayoutMethodType.nagad => _nagadNameCtrl,
      SellerPayoutMethodType.bankTransfer => _bankHolderCtrl,
    };
  }

  TextEditingController get _currentWalletController {
    return _type == SellerPayoutMethodType.nagad
        ? _nagadWalletCtrl
        : _bkashWalletCtrl;
  }

  String get _currentMobileAccountType {
    return _type == SellerPayoutMethodType.nagad
        ? _nagadAccountType
        : _bkashAccountType;
  }

  void _setCurrentMobileAccountType(String value) {
    setState(() {
      if (_type == SellerPayoutMethodType.nagad) {
        _nagadAccountType = value;
      } else {
        _bkashAccountType = value;
      }
    });
    _saveDraft();
  }

  @override
  Widget build(BuildContext context) {
    _loadDraftOnce();
    final state = ref.watch(sellerBusinessControllerProvider);
    final theme = Theme.of(context);
    final methods = state.payoutMethods
        .where((method) => method.accountNumberMasked.trim().isNotEmpty)
        .toList();

    return Scaffold(
      backgroundColor: const Color(0xFFF6F8FC),
      appBar: AppBar(
        title: const Text('Payout Methods'),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Refresh',
            onPressed: state.isLoading
                ? null
                : () =>
                    ref.read(sellerBusinessControllerProvider.notifier).load(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 28),
        children: <Widget>[
          if (state.isLoading) ...<Widget>[
            const LinearProgressIndicator(minHeight: 2),
            const SizedBox(height: 12),
          ],
          if (methods.isNotEmpty) ...<Widget>[
            _SectionHeader(
              title: 'Connected methods',
              subtitle:
                  '${methods.length} payout destination${methods.length == 1 ? '' : 's'} connected',
            ),
            const SizedBox(height: 10),
            ...methods.map(
              (method) => _SavedMethodTile(
                method: method,
                busy: state.isSaving,
                onDelete: () => _confirmDelete(method),
              ),
            ),
            const SizedBox(height: 22),
          ],
          const _SectionHeader(
            title: 'Add method',
            subtitle: 'Choose the destination that matches how you withdraw.',
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              Expanded(
                child: _MethodOption(
                  label: 'bKash',
                  icon: Icons.phone_iphone_rounded,
                  color: const Color(0xFFE2136E),
                  selected: _type == SellerPayoutMethodType.bkash,
                  onTap: () => _selectType(SellerPayoutMethodType.bkash),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _MethodOption(
                  label: 'Nagad',
                  icon: Icons.account_balance_wallet_rounded,
                  color: const Color(0xFFF7941D),
                  selected: _type == SellerPayoutMethodType.nagad,
                  onTap: () => _selectType(SellerPayoutMethodType.nagad),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _MethodOption(
                  label: 'Bank',
                  icon: Icons.account_balance_rounded,
                  color: const Color(0xFF1E3A5F),
                  selected: _type == SellerPayoutMethodType.bankTransfer,
                  onTap: () => _selectType(SellerPayoutMethodType.bankTransfer),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: sellerCardDecoration(theme.colorScheme),
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 180),
              child: _type == SellerPayoutMethodType.bankTransfer
                  ? _BankForm(
                      key: const ValueKey<String>('bank'),
                      nameCtrl: _bankHolderCtrl,
                      bankNameCtrl: _bankNameCtrl,
                      accountCtrl: _bankAccountCtrl,
                      branchCtrl: _branchCtrl,
                      routingCtrl: _routingCtrl,
                    )
                  : _MobileWalletForm(
                      key: ValueKey<String>(_type.apiValue),
                      type: _type,
                      nameCtrl: _currentNameController,
                      walletCtrl: _currentWalletController,
                      accountType: _currentMobileAccountType,
                      onAccountTypeChanged: (value) {
                        if (value == null) return;
                        _setCurrentMobileAccountType(value);
                      },
                    ),
            ),
          ),
          const SizedBox(height: 10),
          SwitchListTile.adaptive(
            value: _asDefault,
            onChanged: (value) {
              setState(() => _asDefault = value);
              _saveDraft();
            },
            title: const Text('Set as default payout method'),
            subtitle:
                const Text('New withdrawal requests will use this first.'),
            contentPadding: const EdgeInsets.symmetric(horizontal: 4),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: state.isSaving ? null : _saveMethod,
            style: FilledButton.styleFrom(
              backgroundColor: kSellerAccent,
              minimumSize: const Size.fromHeight(54),
            ),
            icon: state.isSaving
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.verified_rounded),
            label: Text('Save ${_type.label} method'),
          ),
        ],
      ),
    );
  }

  Future<void> _confirmDelete(SellerPayoutMethod method) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Remove payout method?'),
        content: Text(
          'Remove ${method.type.label} ${method.accountNumberMasked} from your seller payouts.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Remove'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      return;
    }
    await ref
        .read(sellerBusinessControllerProvider.notifier)
        .deletePayoutMethod(method.id);
    if (!mounted) return;
    final state = ref.read(sellerBusinessControllerProvider);
    final msg =
        state.failure?.message ?? state.successMessage ?? 'Request completed.';
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          title,
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 3),
        Text(
          subtitle,
          style: Theme.of(context)
              .textTheme
              .bodySmall
              ?.copyWith(color: kSellerMuted),
        ),
      ],
    );
  }
}

class _MethodOption extends StatelessWidget {
  const _MethodOption({
    required this.label,
    required this.icon,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
        decoration: BoxDecoration(
          color: selected ? color.withValues(alpha: 0.1) : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? color : const Color(0xFFDCE4F0),
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Column(
          children: <Widget>[
            Icon(icon, color: selected ? color : kSellerMuted),
            const SizedBox(height: 6),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: selected ? color : const Color(0xFF334155),
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MobileWalletForm extends StatelessWidget {
  const _MobileWalletForm({
    super.key,
    required this.type,
    required this.nameCtrl,
    required this.walletCtrl,
    required this.accountType,
    required this.onAccountTypeChanged,
  });

  final SellerPayoutMethodType type;
  final TextEditingController nameCtrl;
  final TextEditingController walletCtrl;
  final String accountType;
  final ValueChanged<String?> onAccountTypeChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          '${type.label} wallet details',
          style: Theme.of(context)
              .textTheme
              .titleSmall
              ?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: nameCtrl,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(
            labelText: 'Account holder name',
            prefixIcon: Icon(Icons.person_outline_rounded),
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: walletCtrl,
          keyboardType: TextInputType.phone,
          textInputAction: TextInputAction.done,
          decoration: InputDecoration(
            labelText: '${type.label} wallet number',
            prefixIcon: const Icon(Icons.phone_android_rounded),
            border: const OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          initialValue: accountType,
          decoration: const InputDecoration(
            labelText: 'Wallet account type',
            prefixIcon: Icon(Icons.badge_outlined),
            border: OutlineInputBorder(),
          ),
          items: const <DropdownMenuItem<String>>[
            DropdownMenuItem<String>(
                value: 'Personal', child: Text('Personal')),
            DropdownMenuItem<String>(value: 'Agent', child: Text('Agent')),
            DropdownMenuItem<String>(
                value: 'Merchant', child: Text('Merchant')),
          ],
          onChanged: onAccountTypeChanged,
        ),
      ],
    );
  }
}

class _BankForm extends StatelessWidget {
  const _BankForm({
    super.key,
    required this.nameCtrl,
    required this.bankNameCtrl,
    required this.accountCtrl,
    required this.branchCtrl,
    required this.routingCtrl,
  });

  final TextEditingController nameCtrl;
  final TextEditingController bankNameCtrl;
  final TextEditingController accountCtrl;
  final TextEditingController branchCtrl;
  final TextEditingController routingCtrl;

  @override
  Widget build(BuildContext context) {
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Bank transfer details',
          style: Theme.of(context)
              .textTheme
              .titleSmall
              ?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: nameCtrl,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(
            labelText: 'Account holder name',
            prefixIcon: Icon(Icons.person_outline_rounded),
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: bankNameCtrl,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(
            labelText: 'Bank name',
            prefixIcon: Icon(Icons.account_balance_rounded),
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: accountCtrl,
          keyboardType: TextInputType.number,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(
            labelText: 'Bank account number',
            prefixIcon: Icon(Icons.numbers_rounded),
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: branchCtrl,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(
            labelText: 'Branch name',
            prefixIcon: Icon(Icons.location_city_rounded),
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: routingCtrl,
          keyboardType: TextInputType.number,
          textInputAction: TextInputAction.done,
          decoration: const InputDecoration(
            labelText: 'Routing number (optional)',
            prefixIcon: Icon(Icons.route_rounded),
            border: OutlineInputBorder(),
          ),
        ),
      ],
    );
  }
}

class _SavedMethodTile extends StatelessWidget {
  const _SavedMethodTile({
    required this.method,
    required this.busy,
    required this.onDelete,
  });

  final SellerPayoutMethod method;
  final bool busy;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final brand = switch (method.type) {
      SellerPayoutMethodType.bkash => const Color(0xFFE2136E),
      SellerPayoutMethodType.nagad => const Color(0xFFF7941D),
      SellerPayoutMethodType.bankTransfer => const Color(0xFF1E3A5F),
    };
    final icon = method.type == SellerPayoutMethodType.bankTransfer
        ? Icons.account_balance_rounded
        : Icons.phone_android_rounded;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: sellerCardDecoration(Theme.of(context).colorScheme),
      child: Row(
        children: <Widget>[
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: brand.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(icon, color: brand),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  method.type.label,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 2),
                Text(
                  method.type == SellerPayoutMethodType.bankTransfer
                      ? [
                          if ((method.bankName ?? '').isNotEmpty)
                            method.bankName!,
                          method.accountNumberMasked,
                        ].join(' - ')
                      : method.accountNumberMasked,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: kSellerMuted),
                ),
                if (method.accountName.trim().isNotEmpty)
                  Text(
                    method.accountName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: kSellerMuted),
                  ),
              ],
            ),
          ),
          if (method.isDefault)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
              decoration: BoxDecoration(
                color: const Color(0xFFECFDF5),
                borderRadius: BorderRadius.circular(999),
              ),
              child: const Text(
                'Default',
                style: TextStyle(
                  color: Color(0xFF15803D),
                  fontWeight: FontWeight.w800,
                  fontSize: 12,
                ),
              ),
            ),
          PopupMenuButton<String>(
            enabled: !busy,
            tooltip: 'Payout method actions',
            onSelected: (value) {
              if (value == 'delete') {
                onDelete();
              }
            },
            itemBuilder: (context) => const <PopupMenuEntry<String>>[
              PopupMenuItem<String>(
                value: 'delete',
                child: Text('Remove'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
