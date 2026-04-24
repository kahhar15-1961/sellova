import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/checkout_draft_controller.dart';
import 'cart_ui.dart';

class PromoCodesScreen extends ConsumerStatefulWidget {
  const PromoCodesScreen({super.key});

  @override
  ConsumerState<PromoCodesScreen> createState() => _PromoCodesScreenState();
}

class _PromoCodesScreenState extends ConsumerState<PromoCodesScreen> {
  final TextEditingController _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final draft = ref.watch(checkoutDraftProvider);
    final offers = CheckoutDraftController.promoCatalog.entries.toList();

    void applyCode(String code) {
      final ok = ref.read(checkoutDraftProvider.notifier).applyPromoCode(code);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(ok ? 'Promo applied successfully.' : 'Promo is invalid or minimum spend not met.')),
      );
      if (ok) {
        _controller.text = code;
      }
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Text('Promo Codes', style: cartSectionHeading(Theme.of(context).textTheme)),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(hintText: 'Enter promo code'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: () => applyCode(_controller.text),
                    style: cartPrimaryButtonStyle(cs),
                    child: const Text('Apply'),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Text('Available Offers', style: cartSectionHeading(Theme.of(context).textTheme)),
              const SizedBox(height: 10),
              for (final entry in offers) ...<Widget>[
                _OfferCard(
                  code: entry.value.title,
                  desc: entry.value.description,
                  min: entry.value.minSpend,
                  badge: entry.value.badge,
                  selected: draft?.promoCode == entry.key,
                  onTap: () => applyCode(entry.key),
                ),
                const SizedBox(height: 10),
              ],
              if (draft?.promoCode != null) ...<Widget>[
                TextButton.icon(
                  onPressed: () => ref.read(checkoutDraftProvider.notifier).removePromoCode(),
                  icon: const Icon(Icons.delete_outline, size: 18),
                  label: const Text('Remove applied promo'),
                ),
              ],
              const Spacer(),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF5F3FF),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE9D5FF)),
                ),
                child: const Row(
                  children: <Widget>[
                    Icon(Icons.info_outline, color: Color(0xFF4F46E5)),
                    SizedBox(width: 10),
                    Expanded(child: Text('Only one promo code can be used per order.')),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OfferCard extends StatelessWidget {
  const _OfferCard({
    required this.code,
    required this.desc,
    required this.min,
    required this.badge,
    required this.selected,
    required this.onTap,
  });

  final String code;
  final String desc;
  final String min;
  final String badge;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(kCartRadius),
        child: Ink(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: cartCardDecoration(cs).copyWith(border: Border.all(color: selected ? cs.primary : const Color(0xFFC4B5FD), width: selected ? 1.5 : 1)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(child: Text(code, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900))),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(color: const Color(0xFFE8F7E8), borderRadius: BorderRadius.circular(999)),
                    child: Text(badge, style: const TextStyle(color: Color(0xFF2B8A3E), fontWeight: FontWeight.w800, fontSize: 12)),
                  ),
                  if (selected) ...<Widget>[
                    const SizedBox(width: 8),
                    Icon(Icons.check_circle_rounded, color: cs.primary),
                  ],
                ],
              ),
              const SizedBox(height: 6),
              Text(desc, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted)),
              const SizedBox(height: 2),
              Text(min, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kCartMuted)),
            ],
          ),
        ),
      ),
    );
  }
}
