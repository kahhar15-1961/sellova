import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/errors/api_exception.dart';
import '../application/checkout_draft_controller.dart';
import '../data/promo_repository.dart';
import 'cart_ui.dart';

class PromoCodesScreen extends ConsumerStatefulWidget {
  const PromoCodesScreen({super.key});

  @override
  ConsumerState<PromoCodesScreen> createState() => _PromoCodesScreenState();
}

class _PromoCodesScreenState extends ConsumerState<PromoCodesScreen> {
  final TextEditingController _controller = TextEditingController();
  Future<List<PromoOfferDto>>? _offersFuture;
  String? _inlineMessage;
  bool _applying = false;

  @override
  void initState() {
    super.initState();
    _offersFuture = _loadOffers();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<List<PromoOfferDto>> _loadOffers() async {
    final draft = ref.read(checkoutDraftProvider);
    if (draft == null) {
      return const <PromoOfferDto>[];
    }
    return ref.read(promoRepositoryProvider).listOffers(
          subtotal: draft.subtotal,
          currency: draft.lines.isEmpty ? 'USD' : draft.lines.first.currency,
          shippingMethod: draft.shippingMethod.name,
        );
  }

  Future<void> _refreshOffers() async {
    setState(() {
      _offersFuture = _loadOffers();
    });
  }

  Future<void> _applyPromo(String rawCode) async {
    if (_applying) {
      return;
    }

    final draft = ref.read(checkoutDraftProvider);
    if (draft == null) {
      setState(() => _inlineMessage = 'Checkout draft is missing. Please return to checkout.');
      return;
    }

    final normalized = rawCode.trim().toUpperCase();
    if (normalized.isEmpty) {
      setState(() => _inlineMessage = 'Enter a promo code first.');
      return;
    }

    setState(() {
      _applying = true;
      _inlineMessage = null;
    });

    try {
      final result = await ref.read(promoRepositoryProvider).validate(
            code: normalized,
            subtotal: draft.subtotal,
            shippingFee: draft.shippingFee,
            currency: draft.lines.isEmpty ? 'USD' : draft.lines.first.currency,
            shippingMethod: draft.shippingMethod.name,
          );
      ref.read(checkoutDraftProvider.notifier).applyPromoCode(result.code, result.discountAmount);
      setState(() {
        _controller.text = result.code;
        _inlineMessage = '${result.code} applied. You are saving ${_money(draft, result.discountAmount)}.';
      });
      await _refreshOffers();
    } on Object catch (e) {
      setState(() => _inlineMessage = _friendlyError(e));
    } finally {
      if (mounted) {
        setState(() => _applying = false);
      }
    }
  }

  String _friendlyError(Object error) {
    if (error is ApiException) {
      final reasonCode = (error.context['reason_code'] ?? '').toString();
      final message = error.message.toLowerCase();
      switch (reasonCode) {
        case 'promo_code_not_found':
          return 'That promo code was not found in the current backend database.';
        case 'promo_code_minimum_spend_not_met':
          return 'Minimum spend has not been met for that code.';
        case 'promo_code_currency_mismatch':
          return 'This promo is not available for your currency.';
        case 'promo_code_usage_limit_reached':
          return 'This promo code has reached its usage limit.';
        case 'promo_code_inactive':
          return 'This promo code is currently inactive.';
        case 'promo_code_not_applicable':
          return 'That promo code does not apply to this checkout.';
      }
      if (message.contains('minimum spend')) {
        return 'Minimum spend has not been met for that code.';
      }
      if (message.contains('not found')) {
        return 'That promo code was not found in the current backend database.';
      }
      if (message.contains('inactive')) {
        return 'This promo code is currently inactive.';
      }
    }
    final text = error.toString();
    if (text.contains('minimum spend')) {
      return 'Minimum spend has not been met for that code.';
    }
    if (text.contains('not found')) {
      return 'That promo code was not found in the current backend database.';
    }
    if (text.contains('inactive')) {
      return 'This promo code is currently inactive.';
    }
    return 'Unable to apply promo code. Please try again.';
  }

  static String _money(CheckoutDraft d, double amount) {
    final c = d.lines.isEmpty ? 'USD' : d.lines.first.currency.toUpperCase();
    final t = amount.toStringAsFixed(2);
    return c == 'USD' ? '\$$t' : '$c $t';
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final draft = ref.watch(checkoutDraftProvider);
    if (draft == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (context.mounted) {
          Navigator.of(context).maybePop();
        }
      });
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final totalBeforePromo = draft.subtotal + draft.shippingFee;
    final offersFuture = _offersFuture ?? Future<List<PromoOfferDto>>.value(const <PromoOfferDto>[]);

    return Scaffold(
      backgroundColor: const Color(0xFFF7F8FC),
      appBar: AppBar(
        title: Text('Promotions', style: cartSectionHeading(Theme.of(context).textTheme)),
        centerTitle: true,
        surfaceTintColor: Colors.transparent,
        backgroundColor: cs.surface.withValues(alpha: 0.96),
        elevation: 0,
        actions: <Widget>[
          TextButton(
            onPressed: draft.promoCode == null
                ? null
                : () {
                    ref.read(checkoutDraftProvider.notifier).removePromoCode();
                    setState(() => _inlineMessage = 'Promo removed from checkout.');
                  },
            child: const Text('Clear'),
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF4F6FC), Color(0xFFF8F9FE)],
          ),
        ),
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: cartCardDecoration(cs, radius: kCartRadiusLarge).copyWith(
                  color: Colors.white,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: cs.primary.withValues(alpha: 0.09),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Icon(Icons.local_offer_outlined, color: cs.primary),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                'Apply a promo code',
                                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                      fontWeight: FontWeight.w900,
                                      color: kCartNavy,
                                    ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Enter a valid code or pick from the active offers below.',
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: <Widget>[
                        _StatPill(
                          label: 'Subtotal',
                          value: _money(draft, draft.subtotal),
                        ),
                        const SizedBox(width: 10),
                        _StatPill(
                          label: 'Shipping',
                          value: _money(draft, draft.shippingFee),
                        ),
                        const SizedBox(width: 10),
                        _StatPill(
                          label: 'Before promo',
                          value: _money(draft, totalBeforePromo),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: cartCardDecoration(cs).copyWith(color: Colors.white),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: TextField(
                            controller: _controller,
                            textCapitalization: TextCapitalization.characters,
                            onSubmitted: _applyPromo,
                            decoration: const InputDecoration(
                              hintText: 'Enter promo code',
                              prefixIcon: Icon(Icons.confirmation_number_outlined),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        FilledButton(
                          onPressed: _applying ? null : () => _applyPromo(_controller.text),
                          style: cartPrimaryButtonStyle(cs),
                          child: _applying
                              ? SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.2,
                                    valueColor: AlwaysStoppedAnimation<Color>(cs.onPrimary),
                                  ),
                                )
                              : const Text('Apply'),
                        ),
                      ],
                    ),
                    if (_inlineMessage != null) ...<Widget>[
                      const SizedBox(height: 12),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: draft.promoCode == null ? const Color(0xFFFFFBEB) : const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: draft.promoCode == null ? const Color(0xFFFCD34D) : const Color(0xFF86EFAC),
                          ),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Icon(
                              draft.promoCode == null ? Icons.info_outline : Icons.check_circle_outline,
                              color: draft.promoCode == null ? const Color(0xFFD97706) : const Color(0xFF15803D),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _inlineMessage!,
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                      height: 1.35,
                                      color: draft.promoCode == null ? const Color(0xFF92400E) : const Color(0xFF166534),
                                    ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    if (draft.promoCode != null) ...<Widget>[
                      const SizedBox(height: 12),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF5F3FF),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFD8B4FE)),
                        ),
                        child: Row(
                          children: <Widget>[
                            Icon(Icons.discount_outlined, color: cs.primary),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                '${draft.promoCode} is applied. Discount: ${_money(draft, draft.promoDiscount)}',
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                      fontWeight: FontWeight.w700,
                                      color: kCartNavy,
                                    ),
                              ),
                            ),
                            TextButton(
                              onPressed: () => ref.read(checkoutDraftProvider.notifier).removePromoCode(),
                              child: const Text('Remove'),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
              child: Row(
                children: <Widget>[
                  Text('Active offers', style: cartSectionHeading(Theme.of(context).textTheme)),
                  const Spacer(),
                  Text(
                    'Backend validated',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: kCartMuted,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: FutureBuilder<List<PromoOfferDto>>(
                future: offersFuture,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return _PromoError(
                      message: snapshot.error.toString(),
                      onRetry: _refreshOffers,
                    );
                  }
                  final offers = snapshot.data ?? const <PromoOfferDto>[];
                  if (offers.isEmpty) {
                    return const _PromoEmpty();
                  }

                  return ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                    itemCount: offers.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final offer = offers[index];
                      final selected = draft.promoCode == offer.code;
                      return _OfferCard(
                        offer: offer,
                        selected: selected,
                        onTap: () => _applyPromo(offer.code),
                        highlightColor: selected ? cs.primary : const Color(0xFFC4B5FD),
                      );
                    },
                  );
                },
              ),
            ),
            Padding(
              padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.paddingOf(context).bottom),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Row(
                  children: <Widget>[
                    const Icon(Icons.security_outlined, color: Color(0xFF334155), size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Promo discounts are validated on the backend before the order is placed.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: const Color(0xFF475569),
                              height: 1.35,
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
    );
  }
}

class _OfferCard extends StatelessWidget {
  const _OfferCard({
    required this.offer,
    required this.selected,
    required this.onTap,
    required this.highlightColor,
  });

  final PromoOfferDto offer;
  final bool selected;
  final VoidCallback onTap;
  final Color highlightColor;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(kCartRadius),
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: cartCardDecoration(cs).copyWith(
            color: Colors.white,
            border: Border.all(color: selected ? highlightColor : const Color(0xFFE2E8F0), width: selected ? 1.4 : 1),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      offer.code,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w900,
                            color: kCartNavy,
                          ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF0FDF4),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      offer.badge,
                      style: const TextStyle(
                        color: Color(0xFF15803D),
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  if (selected) ...<Widget>[
                    const SizedBox(width: 8),
                    Icon(Icons.check_circle_rounded, color: cs.primary),
                  ],
                ],
              ),
              const SizedBox(height: 8),
              Text(
                offer.description,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted, height: 1.35),
              ),
              const SizedBox(height: 12),
              Row(
                children: <Widget>[
                  _MetaChip(label: 'Min spend', value: offer.minSpendLabel),
                  const SizedBox(width: 8),
                  _MetaChip(label: 'Status', value: offer.eligible ? 'Eligible' : 'Available'),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatPill extends StatelessWidget {
  const _StatPill({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: kCartMuted,
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: kCartNavy,
                    fontWeight: FontWeight.w900,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: kCartMuted,
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 3),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: kCartNavy,
                    fontWeight: FontWeight.w800,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PromoError extends StatelessWidget {
  const _PromoError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.cloud_off_outlined, size: 40, color: Color(0xFF64748B)),
            const SizedBox(height: 10),
            Text(
              'Unable to load promo offers',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                    color: kCartNavy,
                  ),
            ),
            const SizedBox(height: 6),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kCartMuted),
            ),
            const SizedBox(height: 14),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

class _PromoEmpty extends StatelessWidget {
  const _PromoEmpty();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.local_offer_outlined, size: 40, color: Color(0xFF64748B)),
            const SizedBox(height: 10),
            Text(
              'No active promotions right now',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                    color: kCartNavy,
                  ),
            ),
            const SizedBox(height: 6),
            Text(
              'Please check back later or enter a code manually.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kCartMuted),
            ),
          ],
        ),
      ),
    );
  }
}
