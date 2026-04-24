import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/cart_controller.dart';
import '../application/checkout_draft_controller.dart';
import '../domain/cart_line.dart';
import 'cart_ui.dart';

class CartScreen extends ConsumerStatefulWidget {
  const CartScreen({super.key});

  @override
  ConsumerState<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends ConsumerState<CartScreen> {
  bool _editMode = false;

  @override
  Widget build(BuildContext context) {
    final lines = ref.watch(cartControllerProvider);
    final cart = ref.read(cartControllerProvider.notifier);
    final cs = Theme.of(context).colorScheme;
    final subtotal = cart.subtotalAmount();
    final shipping = lines.any((CartLine e) => e.isPhysical) ? 20.0 : 0.0;
    final total = subtotal + shipping;
    final currency = lines.isNotEmpty ? lines.first.currency.toUpperCase() : 'USD';

    return Scaffold(
      backgroundColor: Colors.transparent,
      extendBody: true,
      appBar: AppBar(
        backgroundColor: cs.surface.withValues(alpha: 0.92),
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shadowColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
        ),
        title: Text(
          'My Cart (${cart.itemCount})',
          style: cartSectionHeading(Theme.of(context).textTheme).copyWith(fontSize: 17),
        ),
        centerTitle: true,
        actions: <Widget>[
          TextButton.icon(
            onPressed: () => setState(() => _editMode = !_editMode),
            icon: Icon(_editMode ? Icons.check_rounded : Icons.edit_outlined, size: 18),
            label: Text(_editMode ? 'Done' : 'Edit', style: const TextStyle(fontWeight: FontWeight.w700)),
          ),
        ],
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[kCartPageBgTop, kCartPageBgBottom],
          ),
        ),
        child: lines.isEmpty
            ? _CartEmpty(onBrowse: () => context.go('/home'))
            : Column(
                children: <Widget>[
                  Expanded(
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                      itemCount: lines.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        return _CartLineCard(
                          line: lines[index],
                          editMode: _editMode,
                          onQtyChanged: (q) => cart.setQuantity(lines[index].productId, q),
                          onRemove: () => cart.remove(lines[index].productId),
                        );
                      },
                    ),
                  ),
                  Container(
                    padding: EdgeInsets.fromLTRB(20, 18, 20, 18 + MediaQuery.paddingOf(context).bottom),
                    decoration: BoxDecoration(
                      color: cs.surface,
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
                      border: Border(top: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.35))),
                      boxShadow: <BoxShadow>[
                        BoxShadow(
                          color: const Color(0xFF0F172A).withValues(alpha: 0.08),
                          blurRadius: 28,
                          offset: const Offset(0, -6),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        _SummaryRow(label: 'Subtotal', value: _fmtMoney(currency, subtotal)),
                        const SizedBox(height: 10),
                        _SummaryRow(
                          label: 'Shipping Fee',
                          value: _fmtMoney(currency, shipping),
                          caption: shipping == 0 ? 'No shipping for digital / service items' : null,
                        ),
                        const Divider(height: 26),
                        _SummaryRow(
                          label: 'Total',
                          value: _fmtMoney(currency, total),
                          emphasize: true,
                        ),
                        const SizedBox(height: 18),
                        FilledButton(
                          onPressed: () {
                            ref.read(checkoutDraftProvider.notifier).beginFromCart(lines);
                            context.push('/checkout/shipping');
                          },
                          style: cartPrimaryButtonStyle(cs),
                          child: const Text('Proceed to Checkout'),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  static String _fmtMoney(String currency, double amount) {
    final c = currency.toUpperCase();
    final t = amount.toStringAsFixed(2);
    return c.isEmpty ? t : '$c $t';
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.label,
    required this.value,
    this.emphasize = false,
    this.caption,
  });

  final String label;
  final String value;
  final bool emphasize;
  final String? caption;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                label,
                style: emphasize
                    ? theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800, color: kCartNavy)
                    : theme.textTheme.bodyMedium?.copyWith(color: kCartMuted, fontWeight: FontWeight.w600),
              ),
            ),
            Text(
              value,
              style: emphasize
                  ? theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: kCartNavy)
                  : theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
          ],
        ),
        if (caption != null) ...<Widget>[
          const SizedBox(height: 4),
          Text(caption!, style: theme.textTheme.bodySmall?.copyWith(color: kCartMuted)),
        ],
      ],
    );
  }
}

class _CartLineCard extends StatelessWidget {
  const _CartLineCard({
    required this.line,
    required this.editMode,
    required this.onQtyChanged,
    required this.onRemove,
  });

  final CartLine line;
  final bool editMode;
  final ValueChanged<int> onQtyChanged;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final theme = Theme.of(context);
    return Container(
      decoration: cartCardDecoration(cs),
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: SizedBox(
              width: 72,
              height: 72,
              child: line.imageUrl != null && line.imageUrl!.toLowerCase().startsWith('http')
                  ? Image.network(line.imageUrl!, fit: BoxFit.cover)
                  : ColoredBox(
                      color: cs.surfaceContainerHighest,
                      child: Icon(Icons.inventory_2_outlined, color: cs.outline),
                    ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  line.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800, color: kCartNavy),
                ),
                const SizedBox(height: 6),
                Text(
                  line.displayLineTotal,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: cs.primary,
                  ),
                ),
                if (editMode) ...<Widget>[
                  const SizedBox(height: 8),
                  TextButton.icon(
                    onPressed: onRemove,
                    icon: const Icon(Icons.delete_outline, size: 18),
                    label: const Text('Remove'),
                  ),
                ],
              ],
            ),
          ),
          if (!editMode) _QtyControl(
            quantity: line.quantity,
            onMinus: () => onQtyChanged(line.quantity - 1),
            onPlus: () => onQtyChanged(line.quantity + 1),
          ),
        ],
      ),
    );
  }
}

class _QtyControl extends StatelessWidget {
  const _QtyControl({
    required this.quantity,
    required this.onMinus,
    required this.onPlus,
  });

  final int quantity;
  final VoidCallback onMinus;
  final VoidCallback onPlus;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: cs.surfaceContainerHighest.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.4)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: onMinus,
            icon: Icon(Icons.remove, size: 18, color: cs.onSurfaceVariant),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6),
            child: Text(
              '$quantity',
              style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900, color: kCartNavy),
            ),
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: onPlus,
            icon: Icon(Icons.add, size: 18, color: cs.primary),
          ),
        ],
      ),
    );
  }
}

class _CartEmpty extends StatelessWidget {
  const _CartEmpty({required this.onBrowse});

  final VoidCallback onBrowse;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 96,
              height: 96,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[
                    cs.primaryContainer.withValues(alpha: 0.65),
                    cs.surface,
                  ],
                ),
                border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: cs.primary.withValues(alpha: 0.12),
                    blurRadius: 24,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: Icon(Icons.shopping_bag_outlined, size: 40, color: cs.primary.withValues(alpha: 0.85)),
            ),
            const SizedBox(height: 24),
            Text(
              'Your cart is empty',
              style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900, color: kCartNavy, letterSpacing: -0.3),
            ),
            const SizedBox(height: 10),
            Text(
              'Browse the catalog and add items — your selections appear here with secure checkout.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(color: kCartMuted, height: 1.45),
            ),
            const SizedBox(height: 28),
            FilledButton(
              onPressed: onBrowse,
              style: cartPrimaryButtonStyle(cs),
              child: const Text('Continue shopping'),
            ),
          ],
        ),
      ),
    );
  }
}
