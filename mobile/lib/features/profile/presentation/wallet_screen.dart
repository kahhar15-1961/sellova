import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../cart/presentation/cart_ui.dart';
import '../../cart/application/payment_gateway_provider.dart';
import '../../orders/data/order_repository.dart';
import '../../shell/presentation/buyer_page_header.dart';
import '../application/wallet_controller.dart';
import '../data/wallet_repository.dart';
import '../../../core/realtime/notification_realtime_binding.dart';

class WalletScreen extends ConsumerStatefulWidget {
  const WalletScreen({super.key});

  @override
  ConsumerState<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends ConsumerState<WalletScreen> {
  final Map<int, TextEditingController> _topUpControllers =
      <int, TextEditingController>{};
  final Map<int, TextEditingController> _referenceControllers =
      <int, TextEditingController>{};
  final Map<int, String> _paymentMethods = <int, String>{};
  final Map<int, bool> _expanded = <int, bool>{};
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
        () => ref.read(walletControllerProvider.notifier).load());
    _pollTimer = Timer.periodic(const Duration(seconds: 60), (_) {
      if (!mounted) {
        return;
      }
      ref.read(walletControllerProvider.notifier).load();
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    for (final controller in _topUpControllers.values) {
      controller.dispose();
    }
    for (final controller in _referenceControllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  TextEditingController _controllerFor(int walletId) {
    return _topUpControllers.putIfAbsent(
        walletId, () => TextEditingController());
  }

  TextEditingController _referenceControllerFor(int walletId) {
    return _referenceControllers.putIfAbsent(
        walletId, () => TextEditingController());
  }

  String _paymentMethodFor(int walletId, List<_TopUpMethodOption> options) {
    final stored = _paymentMethods[walletId];
    if (stored != null && options.any((option) => option.value == stored)) {
      return stored;
    }
    final fallback = options.isNotEmpty ? options.first.value : 'manual';
    _paymentMethods[walletId] = fallback;
    return fallback;
  }

  List<_TopUpMethodOption> _topUpMethodOptions(
      List<PaymentGatewayItem> gateways) {
    final methods = <String, _TopUpMethodOption>{};
    String? manualLabel;

    for (final gateway in gateways) {
      if (gateway.walletManualTopUpEnabled) {
        manualLabel = gateway.walletManualTopUpLabel;
      }
      for (final method in gateway.supportedMethods) {
        final normalized = method.trim().toLowerCase();
        if (normalized.isEmpty) {
          continue;
        }
        final option = _TopUpMethodOption.fromMethod(normalized);
        if (option != null) {
          methods[normalized] = option;
        }
      }
    }

    if (manualLabel != null) {
      methods['manual'] = _TopUpMethodOption(
        value: 'manual',
        label: manualLabel,
        icon: Icons.handshake_outlined,
        color: const Color(0xFF7C3AED),
      );
    }

    final preferredOrder = <String>['card', 'bkash', 'nagad', 'bank', 'manual'];
    return preferredOrder
        .where(methods.containsKey)
        .map((method) => methods[method]!)
        .toList();
  }

  Future<void> _requestTopUp(
      WalletDto wallet, List<_TopUpMethodOption> options) async {
    final controller = _controllerFor(wallet.id);
    final referenceController = _referenceControllerFor(wallet.id);
    final amount = controller.text.trim();
    final paymentMethod = _paymentMethodFor(wallet.id, options);
    final paymentReference = referenceController.text.trim();
    if (amount.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter an amount first.')),
      );
      return;
    }
    if (paymentReference.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a payment reference.')),
      );
      return;
    }
    final confirmed = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('Request top up'),
            content: Text(
              'Amount: ${wallet.currency} $amount\n'
              'Method: ${paymentMethod.toUpperCase()}\n'
              'Reference: $paymentReference\n'
              'Your request will be reviewed before credit is added.',
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Submit'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed) {
      return;
    }
    await ref.read(walletControllerProvider.notifier).requestTopUp(
          walletId: wallet.id,
          amount: amount,
          paymentMethod: paymentMethod,
          paymentReference: paymentReference,
        );
    if (mounted) {
      controller.clear();
      referenceController.clear();
      setState(() => _expanded[wallet.id] = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(notificationRealtimeBindingProvider);
    final state = ref.watch(walletControllerProvider);
    final gatewayState = ref.watch(paymentGatewayCatalogProvider);
    final gatewayMethods = _topUpMethodOptions(
        gatewayState.valueOrNull ?? const <PaymentGatewayItem>[]);
    final gatewayError =
        gatewayState.hasError ? gatewayState.error.toString() : null;
    final wallets = _uniqueWallets(state.wallets);

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: SafeArea(
          bottom: false,
          child: Column(
            children: <Widget>[
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 12, 10, 0),
                child: BuyerPageHeader(
                  title: 'Wallet',
                  showSearch: false,
                  showFilter: false,
                  leading: BuyerHeaderActionButton(
                    icon: Icons.arrow_back_ios_new_rounded,
                    tooltip: 'Back',
                    onTap: () {
                      if (context.canPop()) {
                        context.pop();
                      } else {
                        context.go('/profile');
                      }
                    },
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () =>
                      ref.read(walletControllerProvider.notifier).load(),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                    children: <Widget>[
                      _WalletHeader(
                        isLoading: state.isLoading,
                        error: state.errorMessage,
                        success: state.successMessage,
                        onRetry: () =>
                            ref.read(walletControllerProvider.notifier).load(),
                      ),
                      const SizedBox(height: 14),
                      if (wallets.isEmpty && !state.isLoading)
                        const _WalletEmpty()
                      else
                        ...wallets.map(
                          (wallet) => Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: _WalletCard(
                              wallet: wallet,
                              expanded: _expanded[wallet.id] == true,
                              controller: _controllerFor(wallet.id),
                              referenceController:
                                  _referenceControllerFor(wallet.id),
                              paymentMethod:
                                  _paymentMethodFor(wallet.id, gatewayMethods),
                              onPaymentMethodChanged: (value) => setState(() {
                                _paymentMethods[wallet.id] = value;
                              }),
                              availableMethods: gatewayMethods,
                              gatewayError: gatewayError,
                              submitting: state.isSubmitting,
                              onToggle: () => setState(() {
                                _expanded[wallet.id] =
                                    !(_expanded[wallet.id] ?? false);
                              }),
                              onTopUp: wallet.topUpAllowed
                                  ? () => _requestTopUp(wallet, gatewayMethods)
                                  : null,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

List<WalletDto> _uniqueWallets(List<WalletDto> wallets) {
  final byTypeAndCurrency = <String, WalletDto>{};
  for (final wallet in wallets) {
    final key = '${wallet.walletType.trim().toLowerCase()}|${wallet.currency}';
    final existing = byTypeAndCurrency[key];
    if (existing == null ||
        _walletPriority(wallet) > _walletPriority(existing)) {
      byTypeAndCurrency[key] = wallet;
    }
  }
  return byTypeAndCurrency.values.toList()
    ..sort((a, b) {
      final typeCompare = _walletTypeRank(a).compareTo(_walletTypeRank(b));
      if (typeCompare != 0) {
        return typeCompare;
      }
      return a.currency.compareTo(b.currency);
    });
}

int _walletTypeRank(WalletDto wallet) {
  return switch (wallet.walletType.trim().toLowerCase()) {
    'buyer' => 0,
    'seller' => 1,
    _ => 2,
  };
}

int _walletPriority(WalletDto wallet) {
  final statusScore = wallet.status.trim().toLowerCase() == 'active' ? 100 : 0;
  final topUpScore = wallet.topUpAllowed ? 10 : 0;
  return statusScore + topUpScore + wallet.id;
}

class _WalletHeader extends StatelessWidget {
  const _WalletHeader({
    required this.isLoading,
    required this.error,
    required this.success,
    required this.onRetry,
  });

  final bool isLoading;
  final String? error;
  final String? success;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: cartCardDecoration(cs).copyWith(color: Colors.white),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text('Wallet overview',
              style: Theme.of(context)
                  .textTheme
                  .titleLarge
                  ?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          Text(
            'Check balances and submit top-up requests.',
            style: Theme.of(context)
                .textTheme
                .bodyMedium
                ?.copyWith(color: const Color(0xFF64748B)),
          ),
          if (isLoading) ...<Widget>[
            const SizedBox(height: 12),
            const LinearProgressIndicator(minHeight: 3),
          ],
          if (error != null) ...<Widget>[
            const SizedBox(height: 12),
            _InlineNotice(
              accent: const Color(0xFFB91C1C),
              background: const Color(0xFFFFF1F2),
              icon: Icons.error_outline,
              message: error!,
              actionLabel: 'Retry',
              onAction: onRetry,
            ),
          ],
          if (success != null) ...<Widget>[
            const SizedBox(height: 12),
            _InlineNotice(
              accent: const Color(0xFF15803D),
              background: const Color(0xFFF0FDF4),
              icon: Icons.check_circle_outline,
              message: success!,
            ),
          ],
        ],
      ),
    );
  }
}

class _WalletCard extends StatelessWidget {
  const _WalletCard({
    required this.wallet,
    required this.expanded,
    required this.controller,
    required this.referenceController,
    required this.paymentMethod,
    required this.onPaymentMethodChanged,
    required this.availableMethods,
    required this.gatewayError,
    required this.submitting,
    required this.onToggle,
    required this.onTopUp,
  });

  final WalletDto wallet;
  final bool expanded;
  final TextEditingController controller;
  final TextEditingController referenceController;
  final String paymentMethod;
  final ValueChanged<String> onPaymentMethodChanged;
  final List<_TopUpMethodOption> availableMethods;
  final String? gatewayError;
  final bool submitting;
  final VoidCallback onToggle;
  final VoidCallback? onTopUp;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: cartCardDecoration(cs).copyWith(color: Colors.white),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              CircleAvatar(
                radius: 22,
                backgroundColor: cs.primary.withValues(alpha: 0.12),
                child: Icon(Icons.account_balance_wallet_outlined,
                    color: cs.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(wallet.typeLabel,
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900)),
                    const SizedBox(height: 2),
                    Text('${wallet.currency} • ${wallet.status}',
                        style: Theme.of(context)
                            .textTheme
                            .bodySmall
                            ?.copyWith(color: const Color(0xFF64748B))),
                  ],
                ),
              ),
              TextButton(
                onPressed: wallet.topUpAllowed ? onToggle : null,
                child: Text(expanded
                    ? 'Hide'
                    : (wallet.topUpAllowed ? 'Request' : 'Locked')),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                child: _BalanceTile(
                    label: 'Available',
                    value: wallet.availableBalance,
                    currency: wallet.currency),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _BalanceTile(
                    label: 'Held',
                    value: wallet.heldBalance,
                    currency: wallet.currency),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _BalanceTile(
              label: 'Total',
              value: wallet.totalBalance,
              currency: wallet.currency,
              emphasize: true),
          if (expanded && wallet.topUpAllowed) ...<Widget>[
            const SizedBox(height: 14),
            TextField(
              controller: controller,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'Amount',
                hintText: '100.00',
                prefixIcon: Icon(Icons.add_card_outlined),
              ),
            ),
            const SizedBox(height: 10),
            if (availableMethods.isEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'No active payment routes are available right now.',
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      gatewayError ??
                          'Manual review will appear when enabled by admin.',
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: const Color(0xFF64748B)),
                    ),
                  ],
                ),
              )
            else
              DropdownButtonFormField<String>(
                initialValue: availableMethods
                        .any((option) => option.value == paymentMethod)
                    ? paymentMethod
                    : availableMethods.first.value,
                items: availableMethods
                    .map(
                      (option) => DropdownMenuItem<String>(
                        value: option.value,
                        child: Row(
                          children: <Widget>[
                            Icon(option.icon, size: 18, color: option.color),
                            const SizedBox(width: 10),
                            Text(option.label),
                          ],
                        ),
                      ),
                    )
                    .toList(),
                onChanged: (value) {
                  if (value != null) {
                    onPaymentMethodChanged(value);
                  }
                },
                decoration: const InputDecoration(
                  labelText: 'Payment method',
                  prefixIcon: Icon(Icons.payments_outlined),
                ),
              ),
            const SizedBox(height: 10),
            TextField(
              controller: referenceController,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(
                labelText: 'Reference',
                hintText: 'Txn ID / bank ref',
                prefixIcon: Icon(Icons.receipt_long_outlined),
              ),
            ),
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed:
                  submitting || availableMethods.isEmpty ? null : onTopUp,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14)),
              ),
              icon: submitting
                  ? SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        valueColor: AlwaysStoppedAnimation<Color>(cs.onPrimary),
                      ),
                    )
                  : const Icon(Icons.add),
              label: Text(submitting ? 'Submitting...' : 'Request top up'),
            ),
            if (gatewayError != null &&
                availableMethods.isNotEmpty) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                gatewayError!,
                style: Theme.of(context)
                    .textTheme
                    .bodySmall
                    ?.copyWith(color: const Color(0xFFB45309)),
              ),
            ],
          ],
          const SizedBox(height: 10),
          Text(
            'Requests are reviewed before any credit is added.',
            style: Theme.of(context)
                .textTheme
                .bodySmall
                ?.copyWith(color: const Color(0xFF64748B)),
          ),
          if (wallet.recentTopUpRequests.isNotEmpty) ...<Widget>[
            const SizedBox(height: 14),
            Text('Top-up requests',
                style: Theme.of(context)
                    .textTheme
                    .titleSmall
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            for (final request in wallet.recentTopUpRequests.take(3))
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _RequestTile(request: request),
              ),
          ],
          if (wallet.recentEntries.isNotEmpty) ...<Widget>[
            const SizedBox(height: 14),
            Text('Activity',
                style: Theme.of(context)
                    .textTheme
                    .titleSmall
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            for (final entry in wallet.recentEntries.take(4))
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _EntryTile(entry: entry),
              ),
          ],
        ],
      ),
    );
  }
}

class _TopUpMethodOption {
  const _TopUpMethodOption({
    required this.value,
    required this.label,
    required this.icon,
    required this.color,
  });

  final String value;
  final String label;
  final IconData icon;
  final Color color;

  static _TopUpMethodOption? fromMethod(String method) {
    return switch (method) {
      'card' => const _TopUpMethodOption(
          value: 'card',
          label: 'Card',
          icon: Icons.credit_card_outlined,
          color: Color(0xFF0F172A),
        ),
      'bkash' => const _TopUpMethodOption(
          value: 'bkash',
          label: 'bKash',
          icon: Icons.phone_android_outlined,
          color: Color(0xFFE2136E),
        ),
      'nagad' => const _TopUpMethodOption(
          value: 'nagad',
          label: 'Nagad',
          icon: Icons.phone_android_outlined,
          color: Color(0xFFFF7A00),
        ),
      'bank' => const _TopUpMethodOption(
          value: 'bank',
          label: 'Bank transfer',
          icon: Icons.account_balance_outlined,
          color: Color(0xFF1D4ED8),
        ),
      _ => null,
    };
  }
}

class _RequestTile extends StatelessWidget {
  const _RequestTile({required this.request});

  final WalletTopUpRequestDto request;

  String _methodLabel() {
    return switch (request.paymentMethod.toLowerCase()) {
      'bkash' => 'bKash',
      'nagad' => 'Nagad',
      'bank' => 'Bank transfer',
      'card' => 'Card',
      'manual' => 'Manual review',
      _ => request.paymentMethod.isEmpty
          ? 'Method'
          : request.paymentMethod.toUpperCase(),
    };
  }

  @override
  Widget build(BuildContext context) {
    final status = request.status.toLowerCase();
    final color = switch (status) {
      'approved' => const Color(0xFF15803D),
      'rejected' => const Color(0xFFB91C1C),
      _ => const Color(0xFFB45309),
    };
    final bg = switch (status) {
      'approved' => const Color(0xFFF0FDF4),
      'rejected' => const Color(0xFFFFF1F2),
      _ => const Color(0xFFFFFBEB),
    };

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.18)),
      ),
      child: Row(
        children: <Widget>[
          Icon(Icons.receipt_long_outlined, size: 18, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  '${request.currency} ${request.requestedAmount}',
                  style: Theme.of(context)
                      .textTheme
                      .bodyMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 2),
                Text(
                  '${_methodLabel()}${request.paymentReference.isEmpty ? '' : ' · ${request.paymentReference}'}',
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: const Color(0xFF64748B)),
                ),
                const SizedBox(height: 2),
                Text(
                  status == 'approved'
                      ? 'Approved'
                      : status == 'rejected'
                          ? 'Rejected'
                          : 'Requested',
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: const Color(0xFF64748B)),
                ),
                if (status == 'rejected' &&
                    (request.rejectionReason ?? '').isNotEmpty) ...<Widget>[
                  const SizedBox(height: 2),
                  Text(
                    request.rejectionReason!,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: const Color(0xFFB91C1C)),
                  ),
                ],
              ],
            ),
          ),
          Text(
            status.toUpperCase(),
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: color,
                  fontWeight: FontWeight.w800,
                ),
          ),
        ],
      ),
    );
  }
}

class _BalanceTile extends StatelessWidget {
  const _BalanceTile({
    required this.label,
    required this.value,
    required this.currency,
    this.emphasize = false,
  });

  final String label;
  final String value;
  final String currency;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final amount = _money(currency, value);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: const Color(0xFF64748B), fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(
            amount,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: emphasize ? FontWeight.w900 : FontWeight.w800,
                  color: const Color(0xFF0B1A60),
                ),
          ),
        ],
      ),
    );
  }
}

class _EntryTile extends StatelessWidget {
  const _EntryTile({required this.entry});

  final WalletLedgerEntryDto entry;

  @override
  Widget build(BuildContext context) {
    final credit = entry.entrySide.toLowerCase() == 'credit';
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: <Widget>[
          Icon(
            credit ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
            color: credit ? const Color(0xFF15803D) : const Color(0xFFB45309),
            size: 18,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                    entry.description.isEmpty
                        ? entry.entryType
                        : entry.description,
                    style: Theme.of(context)
                        .textTheme
                        .bodyMedium
                        ?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(entry.createdAt,
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: const Color(0xFF64748B))),
              ],
            ),
          ),
          Text(
            '${credit ? '+' : '-'}${_money(entry.currency, entry.amount)}',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: credit
                      ? const Color(0xFF15803D)
                      : const Color(0xFFB45309),
                ),
          ),
        ],
      ),
    );
  }
}

class _InlineNotice extends StatelessWidget {
  const _InlineNotice({
    required this.accent,
    required this.background,
    required this.icon,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final Color accent;
  final Color background;
  final IconData icon;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: accent.withValues(alpha: 0.2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, color: accent, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(message)),
          if (actionLabel != null && onAction != null) ...<Widget>[
            const SizedBox(width: 10),
            TextButton(onPressed: onAction, child: Text(actionLabel!)),
          ],
        ],
      ),
    );
  }
}

class _WalletEmpty extends StatelessWidget {
  const _WalletEmpty();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: <Widget>[
          const Icon(Icons.account_balance_wallet_outlined,
              size: 40, color: Color(0xFF64748B)),
          const SizedBox(height: 10),
          Text('No wallet found yet',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          Text(
            'Your wallet appears when needed.',
            textAlign: TextAlign.center,
            style: Theme.of(context)
                .textTheme
                .bodySmall
                ?.copyWith(color: const Color(0xFF64748B)),
          ),
        ],
      ),
    );
  }
}

String _money(String currency, String value) {
  final n = num.tryParse(value) ?? 0;
  final formatted = n.toStringAsFixed(2);
  return currency.toUpperCase() == 'USD'
      ? '\$$formatted'
      : '$currency $formatted';
}
