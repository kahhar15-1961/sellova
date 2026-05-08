import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/presentation/auth_ui_constants.dart';
import '../application/payment_methods_controller.dart';
import '../data/profile_extras_repository.dart';

class PaymentMethodsScreen extends ConsumerStatefulWidget {
  const PaymentMethodsScreen({super.key});

  @override
  ConsumerState<PaymentMethodsScreen> createState() =>
      _PaymentMethodsScreenState();
}

class _PaymentMethodsScreenState extends ConsumerState<PaymentMethodsScreen> {
  PaymentMethodItem? _editingMethod;
  final TextEditingController _labelCtrl = TextEditingController();
  final TextEditingController _noteCtrl = TextEditingController();
  final TextEditingController _cardholderCtrl = TextEditingController();
  final TextEditingController _cardBrandCtrl = TextEditingController();
  final TextEditingController _last4Ctrl = TextEditingController();
  final TextEditingController _accountNameCtrl = TextEditingController();
  final TextEditingController _mobileCtrl = TextEditingController();
  final TextEditingController _bankNameCtrl = TextEditingController();
  final TextEditingController _accountNumberCtrl = TextEditingController();
  final TextEditingController _branchCtrl = TextEditingController();
  final TextEditingController _routingCtrl = TextEditingController();
  String _kind = 'card';
  bool _isDefault = false;
  bool _showForm = false;
  bool _saving = false;

  @override
  void dispose() {
    _labelCtrl.dispose();
    _noteCtrl.dispose();
    _cardholderCtrl.dispose();
    _cardBrandCtrl.dispose();
    _last4Ctrl.dispose();
    _accountNameCtrl.dispose();
    _mobileCtrl.dispose();
    _bankNameCtrl.dispose();
    _accountNumberCtrl.dispose();
    _branchCtrl.dispose();
    _routingCtrl.dispose();
    super.dispose();
  }

  void _resetForm() {
    _editingMethod = null;
    _labelCtrl.clear();
    _noteCtrl.clear();
    _cardholderCtrl.clear();
    _cardBrandCtrl.clear();
    _last4Ctrl.clear();
    _accountNameCtrl.clear();
    _mobileCtrl.clear();
    _bankNameCtrl.clear();
    _accountNumberCtrl.clear();
    _branchCtrl.clear();
    _routingCtrl.clear();
    _kind = 'card';
    _isDefault = false;
  }

  void _fillForm(PaymentMethodItem method) {
    _editingMethod = method;
    _kind = method.kind.toLowerCase();
    _isDefault = method.isDefault;
    _labelCtrl.text = method.label;
    _noteCtrl.text = (method.details['note'] ?? method.subtitle).toString();
    _cardholderCtrl.text =
        (method.details['cardholder_name'] ?? '').toString();
    _cardBrandCtrl.text = (method.details['card_brand'] ?? '').toString();
    _last4Ctrl.text = (method.details['last4'] ?? '').toString();
    _accountNameCtrl.text = (method.details['account_name'] ?? '').toString();
    _mobileCtrl.text = (method.details['mobile_number'] ?? '').toString();
    _bankNameCtrl.text = (method.details['bank_name'] ?? '').toString();
    _accountNumberCtrl.text = (method.details['account_number'] ?? '').toString();
    _branchCtrl.text = (method.details['branch'] ?? '').toString();
    _routingCtrl.text = (method.details['routing_number'] ?? '').toString();
    _showForm = true;
  }

  void _startCreate() {
    _resetForm();
    _showForm = true;
  }

  String get _kindLabel => switch (_kind) {
        'card' => 'Card',
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'bank' => 'Bank',
        _ => 'Method',
      };

  String get _submitLabel =>
      _editingMethod == null ? 'Save $_kindLabel' : 'Update $_kindLabel';

  String get _labelHint => switch (_kind) {
        'card' => 'Card nickname',
        'bkash' => 'bKash nickname',
        'nagad' => 'Nagad nickname',
        'bank' => 'Bank nickname',
        _ => 'Payment method',
      };

  IconData get _kindIcon => switch (_kind) {
        'card' => Icons.credit_card_outlined,
        'bkash' || 'nagad' => Icons.phone_android_outlined,
        'bank' => Icons.account_balance_outlined,
        _ => Icons.payments_outlined,
      };

  Color get _kindColor => switch (_kind) {
        'bkash' => const Color(0xFFE2136E),
        'nagad' => const Color(0xFFFF7A00),
        'bank' => const Color(0xFF1D4ED8),
        _ => const Color(0xFF0F172A),
      };

  Future<void> _submit() async {
    final label = _labelCtrl.text.trim();
    final note = _noteCtrl.text.trim();
    final isEditing = _editingMethod != null;
    if (label.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a method name.')),
      );
      return;
    }

    final details = switch (_kind) {
      'card' => <String, dynamic>{
          'cardholder_name': _cardholderCtrl.text.trim(),
          'card_brand': _cardBrandCtrl.text.trim(),
          'last4': _last4Ctrl.text.trim(),
          'note': note,
        },
      'bkash' || 'nagad' => <String, dynamic>{
          'account_name': _accountNameCtrl.text.trim(),
          'mobile_number': _mobileCtrl.text.trim(),
          'note': note,
        },
      'bank' => <String, dynamic>{
          'account_name': _accountNameCtrl.text.trim(),
          'bank_name': _bankNameCtrl.text.trim(),
          'account_number': _accountNumberCtrl.text.trim(),
          'branch': _branchCtrl.text.trim(),
          'routing_number': _routingCtrl.text.trim(),
          'note': note,
        },
      _ => <String, dynamic>{'note': note},
    };

    setState(() => _saving = true);
    try {
      final notifier = ref.read(paymentMethodsControllerProvider.notifier);
      if (_editingMethod == null) {
        await notifier.addPaymentMethod(
          kind: _kind,
          label: label,
          subtitle: _summaryTextForKind(details),
          details: details,
          isDefault: _isDefault,
        );
      } else {
        await notifier.updatePaymentMethod(
          id: _editingMethod!.id,
          kind: _kind,
          label: label,
          subtitle: _summaryTextForKind(details),
          details: details,
          isDefault: _isDefault,
        );
      }
      if (!mounted) {
        return;
      }
      setState(() {
        _resetForm();
        _showForm = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isEditing ? 'Payment method updated.' : 'Payment method saved.',
          ),
        ),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Save failed: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _setDefault(String id) async {
    try {
      await ref.read(paymentMethodsControllerProvider.notifier).setDefault(id);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Default method updated.')),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Unable to update default: $e')),
      );
    }
  }

  String _summaryTextForKind(Map<String, dynamic> details) {
    final note = (details['note'] ?? '').toString();
    return switch (_kind) {
      'card' => [
          details['card_brand'],
          details['cardholder_name'],
          if ((details['last4'] ?? '').toString().isNotEmpty)
            '•••• ${(details['last4'] ?? '').toString()}',
        ].where((e) => e != null && e.toString().trim().isNotEmpty).map((e) => e.toString()).join(' · '),
      'bkash' || 'nagad' => [
          details['account_name'],
          if ((details['mobile_number'] ?? '').toString().isNotEmpty)
            (details['mobile_number'] ?? '').toString(),
        ].where((e) => e != null && e.toString().trim().isNotEmpty).map((e) => e.toString()).join(' · '),
      'bank' => [
          details['bank_name'],
          if ((details['account_number'] ?? '').toString().isNotEmpty)
            'A/C ${(details['account_number'] ?? '').toString()}',
        ].where((e) => e != null && e.toString().trim().isNotEmpty).map((e) => e.toString()).join(' · '),
      _ => note,
    };
  }

  Future<void> _remove(String id) async {
    try {
      await ref.read(paymentMethodsControllerProvider.notifier).remove(id);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Payment method removed.')),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Unable to remove method: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final asyncMethods = ref.watch(paymentMethodsControllerProvider);

    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('Payment Methods'),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: asyncMethods.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _MethodListView(
            title: 'Payment methods',
            subtitle: 'Cards, wallets, and bank accounts used at checkout.',
            onAddTap: () => setState(() {
              if (_showForm) {
                _resetForm();
                _showForm = false;
              } else {
                _startCreate();
              }
            }),
            showForm: _showForm,
            saving: _saving,
            kind: _kind,
            kindLabel: _kindLabel,
            labelCtrl: _labelCtrl,
            noteCtrl: _noteCtrl,
            cardholderCtrl: _cardholderCtrl,
            cardBrandCtrl: _cardBrandCtrl,
            last4Ctrl: _last4Ctrl,
            accountNameCtrl: _accountNameCtrl,
            mobileCtrl: _mobileCtrl,
            bankNameCtrl: _bankNameCtrl,
            accountNumberCtrl: _accountNumberCtrl,
            branchCtrl: _branchCtrl,
            routingCtrl: _routingCtrl,
            isDefault: _isDefault,
            onKindChanged: (value) => setState(() => _kind = value),
            onDefaultChanged: (value) => setState(() => _isDefault = value),
            onSubmit: _submit,
            methods: const <PaymentMethodItem>[],
            errorText: 'Load failed: $error',
            onSetDefault: _setDefault,
            onRemove: _remove,
            onEdit: (method) => setState(() => _fillForm(method)),
            kindIcon: _kindIcon,
            kindColor: _kindColor,
            labelHint: _labelHint,
            submitLabel: _submitLabel,
        ),
          data: (methods) => _MethodListView(
            title: 'Payment methods',
            subtitle: 'Cards, wallets, and bank accounts used at checkout.',
            onAddTap: () => setState(() {
              if (_showForm) {
                _resetForm();
                _showForm = false;
              } else {
                _startCreate();
              }
            }),
            showForm: _showForm,
            saving: _saving,
            kind: _kind,
            kindLabel: _kindLabel,
            labelCtrl: _labelCtrl,
            noteCtrl: _noteCtrl,
            cardholderCtrl: _cardholderCtrl,
            cardBrandCtrl: _cardBrandCtrl,
            last4Ctrl: _last4Ctrl,
            accountNameCtrl: _accountNameCtrl,
            mobileCtrl: _mobileCtrl,
            bankNameCtrl: _bankNameCtrl,
            accountNumberCtrl: _accountNumberCtrl,
            branchCtrl: _branchCtrl,
            routingCtrl: _routingCtrl,
            isDefault: _isDefault,
            onKindChanged: (value) => setState(() => _kind = value),
            onDefaultChanged: (value) => setState(() => _isDefault = value),
            onSubmit: _submit,
            methods: methods,
            onSetDefault: _setDefault,
            onRemove: _remove,
            onEdit: (method) => setState(() => _fillForm(method)),
            kindIcon: _kindIcon,
            kindColor: _kindColor,
            labelHint: _labelHint,
            submitLabel: _submitLabel,
        ),
        ),
      ),
    );
  }
}

class _MethodListView extends StatelessWidget {
  const _MethodListView({
    required this.title,
    required this.subtitle,
    required this.onAddTap,
    required this.showForm,
    required this.saving,
    required this.kind,
    required this.kindLabel,
    required this.labelCtrl,
    required this.noteCtrl,
    required this.cardholderCtrl,
    required this.cardBrandCtrl,
    required this.last4Ctrl,
    required this.accountNameCtrl,
    required this.mobileCtrl,
    required this.bankNameCtrl,
    required this.accountNumberCtrl,
    required this.branchCtrl,
    required this.routingCtrl,
    required this.isDefault,
    required this.onKindChanged,
    required this.onDefaultChanged,
    required this.onSubmit,
    required this.methods,
    required this.onSetDefault,
    required this.onRemove,
    required this.onEdit,
    required this.kindIcon,
    required this.kindColor,
    required this.labelHint,
    required this.submitLabel,
    this.errorText,
  });

  final String title;
  final String subtitle;
  final VoidCallback onAddTap;
  final bool showForm;
  final bool saving;
  final String kind;
  final String kindLabel;
  final TextEditingController labelCtrl;
  final TextEditingController noteCtrl;
  final TextEditingController cardholderCtrl;
  final TextEditingController cardBrandCtrl;
  final TextEditingController last4Ctrl;
  final TextEditingController accountNameCtrl;
  final TextEditingController mobileCtrl;
  final TextEditingController bankNameCtrl;
  final TextEditingController accountNumberCtrl;
  final TextEditingController branchCtrl;
  final TextEditingController routingCtrl;
  final bool isDefault;
  final ValueChanged<String> onKindChanged;
  final ValueChanged<bool> onDefaultChanged;
  final VoidCallback onSubmit;
  final List<PaymentMethodItem> methods;
  final ValueChanged<String> onSetDefault;
  final ValueChanged<String> onRemove;
  final ValueChanged<PaymentMethodItem> onEdit;
  final IconData kindIcon;
  final Color kindColor;
  final String labelHint;
  final String submitLabel;
  final String? errorText;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 26),
      children: <Widget>[
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE5E7EB)),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: const Color(0xFF0F172A).withValues(alpha: 0.04),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                title,
                style: theme.textTheme.titleLarge
                    ?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 6),
              Text(
                subtitle,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: const Color(0xFF64748B)),
              ),
              const SizedBox(height: 14),
              FilledButton.icon(
                style: FilledButton.styleFrom(
                  backgroundColor: kAuthAccentPurple,
                  minimumSize: const Size.fromHeight(48),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                ),
                onPressed: onAddTap,
                icon: Icon(showForm ? Icons.close : Icons.add),
                label: Text(showForm ? 'Close form' : 'Add method'),
              ),
              if (showForm) ...<Widget>[
                const SizedBox(height: 10),
                Text(
                  'Keep your saved payment details current.',
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: const Color(0xFF64748B)),
                ),
              ],
              if (showForm) ...<Widget>[
                const SizedBox(height: 16),
                SegmentedButton<String>(
                  segments: const <ButtonSegment<String>>[
                    ButtonSegment<String>(value: 'card', label: Text('Card')),
                    ButtonSegment<String>(value: 'bkash', label: Text('bKash')),
                    ButtonSegment<String>(value: 'nagad', label: Text('Nagad')),
                    ButtonSegment<String>(value: 'bank', label: Text('Bank')),
                  ],
                  selected: <String>{kind},
                  onSelectionChanged: (v) => onKindChanged(v.first),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: labelCtrl,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: 'Nickname',
                    hintText: labelHint,
                    prefixIcon: Icon(kindIcon, color: kindColor),
                  ),
                ),
              const SizedBox(height: 12),
              TextField(
                  controller: noteCtrl,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => onSubmit(),
                  decoration: const InputDecoration(
                    labelText: 'Note',
                    hintText: 'Short internal note',
                    prefixIcon: Icon(Icons.notes_outlined),
                  ),
                ),
                const SizedBox(height: 12),
                ..._buildDetailFields(
                  kind: kind,
                  cardholderCtrl: cardholderCtrl,
                  cardBrandCtrl: cardBrandCtrl,
                  last4Ctrl: last4Ctrl,
                  accountNameCtrl: accountNameCtrl,
                  mobileCtrl: mobileCtrl,
                  bankNameCtrl: bankNameCtrl,
                  accountNumberCtrl: accountNumberCtrl,
                  branchCtrl: branchCtrl,
                  routingCtrl: routingCtrl,
                ),
                const SizedBox(height: 10),
                SwitchListTile(
                  value: isDefault,
                  onChanged: onDefaultChanged,
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Set as default'),
                ),
                const SizedBox(height: 10),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: kAuthAccentPurple,
                    minimumSize: const Size.fromHeight(52),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16)),
                  ),
                  onPressed: saving ? null : onSubmit,
                  child: saving
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(submitLabel),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        Text(
          'Saved methods',
          style: theme.textTheme.bodyMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 12),
        if (errorText != null) ...<Widget>[
          Container(
            padding: const EdgeInsets.all(12),
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(
              color: Colors.red.shade50,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.red.shade100),
            ),
            child: Text(errorText!),
          ),
        ],
        if (methods.isEmpty)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Text(
              'No payment methods yet.',
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: const Color(0xFF64748B)),
            ),
          )
        else
          ...methods.map(
            (m) => _SavedMethodCard(
              method: m,
              onSetDefault: onSetDefault,
              onRemove: onRemove,
              onEdit: onEdit,
            ),
          ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFF5F3FF),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE9D5FF)),
          ),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(Icons.lock_outline, color: kAuthAccentPurple),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                    'Methods are stored on your account and reused at checkout.'),
              ),
            ],
          ),
        ),
      ],
    );
  }

  List<Widget> _buildDetailFields({
    required String kind,
    required TextEditingController cardholderCtrl,
    required TextEditingController cardBrandCtrl,
    required TextEditingController last4Ctrl,
    required TextEditingController accountNameCtrl,
    required TextEditingController mobileCtrl,
    required TextEditingController bankNameCtrl,
    required TextEditingController accountNumberCtrl,
    required TextEditingController branchCtrl,
    required TextEditingController routingCtrl,
  }) {
    Widget field(String label, String hint, TextEditingController ctrl, {TextInputType? keyboardType}) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextField(
          controller: ctrl,
          keyboardType: keyboardType,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: label,
            hintText: hint,
          ),
        ),
      );
    }

    return switch (kind) {
      'card' => <Widget>[
          field('Cardholder name', 'As printed on card', cardholderCtrl),
          field('Card brand', 'Visa / MasterCard / Amex', cardBrandCtrl),
          field('Last 4 digits', '4242', last4Ctrl, keyboardType: TextInputType.number),
        ],
      'bkash' || 'nagad' => <Widget>[
          field('Account name', 'Account holder name', accountNameCtrl),
          field('Mobile number', '01xxxxxxxxx', mobileCtrl, keyboardType: TextInputType.phone),
        ],
      'bank' => <Widget>[
          field('Account name', 'Account holder name', accountNameCtrl),
          field('Bank name', 'Bank name', bankNameCtrl),
          field('Account number', '000123456789', accountNumberCtrl, keyboardType: TextInputType.number),
          field('Branch', 'Branch name', branchCtrl),
          field('Routing number', 'Routing number', routingCtrl, keyboardType: TextInputType.number),
        ],
      _ => const <Widget>[],
    };
  }
}

class _SavedMethodCard extends StatelessWidget {
  const _SavedMethodCard({
    required this.method,
    required this.onSetDefault,
    required this.onRemove,
    required this.onEdit,
  });

  final PaymentMethodItem method;
  final ValueChanged<String> onSetDefault;
  final ValueChanged<String> onRemove;
  final ValueChanged<PaymentMethodItem> onEdit;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final icon = _MethodIcon(kind: method.kind);
    final kindLabel = switch (method.kind.toLowerCase()) {
      'bkash' => 'bKash',
      'nagad' => 'Nagad',
      'bank' => 'Bank',
      _ => 'Card',
    };
    final details = method.details;
    final summary = switch (method.kind.toLowerCase()) {
      'card' => [
          details['card_brand'],
          details['cardholder_name'],
          if ((details['last4'] ?? '').toString().isNotEmpty)
            '•••• ${(details['last4'] ?? '').toString()}',
        ].where((e) => e != null && e.toString().trim().isNotEmpty).map((e) => e.toString()).join(' · '),
      'bkash' || 'nagad' => [
          details['account_name'],
          if ((details['mobile_number'] ?? '').toString().isNotEmpty)
            (details['mobile_number'] ?? '').toString(),
        ].where((e) => e != null && e.toString().trim().isNotEmpty).map((e) => e.toString()).join(' · '),
      'bank' => [
          details['bank_name'],
          if ((details['account_number'] ?? '').toString().isNotEmpty)
            'A/C ${(details['account_number'] ?? '').toString()}',
        ].where((e) => e != null && e.toString().trim().isNotEmpty).map((e) => e.toString()).join(' · '),
      _ => method.subtitle,
    };
    final masked = _maskedPreview(method.kind, details);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: cs.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: method.isDefault
              ? const Color(0xFFC7D2FE)
              : const Color(0xFFE5E7EB),
        ),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.03),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        leading: icon,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    method.label,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                if (method.isDefault)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEEF2FF),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: const Text(
                      'Default',
                      style: TextStyle(
                        color: Color(0xFF4338CA),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                _KindBadge(label: kindLabel),
                if (masked != null) _KindBadge(label: masked, subdued: true),
              ],
            ),
          ],
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Text(
            summary.isEmpty ? kindLabel : summary,
            style: theme.textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B)),
          ),
        ),
        trailing: PopupMenuButton<String>(
          onSelected: (v) {
            if (v == 'default') {
              onSetDefault(method.id);
            } else if (v == 'edit') {
              onEdit(method);
            } else if (v == 'remove') {
              onRemove(method.id);
            }
          },
          itemBuilder: (_) => <PopupMenuEntry<String>>[
            const PopupMenuItem<String>(value: 'edit', child: Text('Edit')),
            if (!method.isDefault)
              const PopupMenuItem<String>(
                  value: 'default', child: Text('Set default')),
            const PopupMenuItem<String>(value: 'remove', child: Text('Remove')),
          ],
        ),
      ),
    );
  }

  String? _maskedPreview(String kind, Map<String, dynamic> details) {
    final normalized = kind.toLowerCase();
    return switch (normalized) {
      'card' => _maskLast4((details['last4'] ?? '').toString(), 'Card'),
      'bkash' || 'nagad' => _maskNumber((details['mobile_number'] ?? '').toString(), 'Mobile'),
      'bank' => _maskNumber((details['account_number'] ?? '').toString(), 'A/C'),
      _ => null,
    };
  }

  String? _maskLast4(String value, String prefix) {
    if (value.isEmpty) {
      return null;
    }
    final last4 = value.length <= 4 ? value : value.substring(value.length - 4);
    return '$prefix •••• $last4';
  }

  String? _maskNumber(String value, String prefix) {
    if (value.isEmpty) {
      return null;
    }
    final digits = value.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.isEmpty) {
      return null;
    }
    final tail = digits.length <= 4 ? digits : digits.substring(digits.length - 4);
    return '$prefix •••• $tail';
  }
}

class _KindBadge extends StatelessWidget {
  const _KindBadge({required this.label, this.subdued = false});

  final String label;
  final bool subdued;

  @override
  Widget build(BuildContext context) {
    final background = subdued ? const Color(0xFFF1F5F9) : const Color(0xFFEFF6FF);
    final foreground = subdued ? const Color(0xFF475569) : const Color(0xFF1D4ED8);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: foreground,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _MethodIcon extends StatelessWidget {
  const _MethodIcon({required this.kind});

  final String kind;

  @override
  Widget build(BuildContext context) {
    final normalized = kind.toLowerCase();
    if (normalized == 'bkash') {
      return const CircleAvatar(
        backgroundColor: Color(0xFFE2136E),
        child: Text('B', style: TextStyle(color: Colors.white)),
      );
    }
    if (normalized == 'nagad') {
      return const CircleAvatar(
        backgroundColor: Color(0xFFFF7A00),
        child: Text('N', style: TextStyle(color: Colors.white)),
      );
    }
    if (normalized == 'bank') {
      return const CircleAvatar(
        backgroundColor: Color(0xFF1D4ED8),
        child:
            Icon(Icons.account_balance_outlined, color: Colors.white, size: 20),
      );
    }
    return const CircleAvatar(
      backgroundColor: Color(0xFF1D4ED8),
      child: Icon(Icons.credit_card_outlined, color: Colors.white, size: 20),
    );
  }
}
