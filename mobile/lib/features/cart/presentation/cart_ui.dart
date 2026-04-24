import 'package:flutter/material.dart';

/// Aligns with product catalog / detail (`_kNavy` family).
const Color kCartNavy = Color(0xFF0B1A60);
const Color kCartMuted = Color(0xFF64748B);
const double kCartRadius = 16;
const double kCartRadiusLarge = 20;
const double kCartBtnHeight = 52;

const Color kCartPageBgTop = Color(0xFFF4F6FC);
const Color kCartPageBgBottom = Color(0xFFF8F9FE);

/// Elevated surface used across cart & checkout (soft shadow + hairline border).
BoxDecoration cartCardDecoration(
  ColorScheme cs, {
  double radius = kCartRadius,
  Color? color,
  bool elevated = true,
}) {
  return BoxDecoration(
    color: color ?? cs.surface,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.38)),
    boxShadow: elevated
        ? <BoxShadow>[
            BoxShadow(
              color: const Color(0xFF0F172A).withValues(alpha: 0.055),
              blurRadius: 20,
              offset: const Offset(0, 8),
            ),
          ]
        : const <BoxShadow>[],
  );
}

/// Shell behind the checkout stepper (slightly lifted from page).
BoxDecoration cartStepperShellDecoration(ColorScheme cs) {
  return BoxDecoration(
    color: cs.surface,
    borderRadius: BorderRadius.circular(kCartRadiusLarge),
    border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.32)),
    boxShadow: <BoxShadow>[
      BoxShadow(
        color: cs.primary.withValues(alpha: 0.06),
        blurRadius: 24,
        offset: const Offset(0, 10),
      ),
      BoxShadow(
        color: const Color(0xFF0F172A).withValues(alpha: 0.04),
        blurRadius: 16,
        offset: const Offset(0, 4),
      ),
    ],
  );
}

TextStyle cartSectionHeading(TextTheme textTheme) {
  final base = textTheme.titleSmall ?? const TextStyle(fontSize: 16, height: 1.3);
  return base.copyWith(
    fontWeight: FontWeight.w800,
    color: kCartNavy,
    letterSpacing: -0.2,
  );
}

/// Primary CTA — slightly taller corner radius for a premium pill.
ButtonStyle cartPrimaryButtonStyle(ColorScheme cs) {
  return FilledButton.styleFrom(
    minimumSize: const Size.fromHeight(kCartBtnHeight),
    elevation: 0,
    shadowColor: Colors.transparent,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    padding: const EdgeInsets.symmetric(horizontal: 20),
  );
}

/// Shared 3-step header for checkout (Shipping → Payment → Review).
class CheckoutStepper extends StatelessWidget {
  const CheckoutStepper({super.key, required this.activeStep});

  final int activeStep;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    const labels = <String>['Shipping', 'Payment', 'Review'];

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(10, 18, 10, 16),
      decoration: cartStepperShellDecoration(cs),
      child: Stack(
        alignment: Alignment.topCenter,
        children: <Widget>[
          Positioned(
            left: 0,
            right: 0,
            top: 15,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 36),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: Container(
                      height: 2,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(2),
                        gradient: LinearGradient(
                          colors: <Color>[
                            activeStep >= 1 ? cs.primary.withValues(alpha: 0.45) : cs.outlineVariant.withValues(alpha: 0.35),
                            activeStep >= 1 ? cs.primary.withValues(alpha: 0.45) : cs.outlineVariant.withValues(alpha: 0.35),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Container(
                      height: 2,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(2),
                        color: activeStep >= 2 ? cs.primary.withValues(alpha: 0.45) : cs.outlineVariant.withValues(alpha: 0.35),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              for (int i = 0; i < 3; i++)
                Expanded(
                  child: Column(
                    children: <Widget>[
                      _StepDisc(
                        index: i,
                        activeStep: activeStep,
                        colorScheme: cs,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        labels[i],
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                              fontWeight: i == activeStep ? FontWeight.w800 : FontWeight.w600,
                              color: i == activeStep ? kCartNavy : kCartMuted,
                              letterSpacing: i == activeStep ? 0.1 : 0,
                            ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StepDisc extends StatelessWidget {
  const _StepDisc({
    required this.index,
    required this.activeStep,
    required this.colorScheme,
  });

  final int index;
  final int activeStep;
  final ColorScheme colorScheme;

  @override
  Widget build(BuildContext context) {
    final done = index < activeStep;
    final active = index == activeStep;

    if (done) {
      return Center(
        child: Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: colorScheme.primary,
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: colorScheme.primary.withValues(alpha: 0.35),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Icon(Icons.check_rounded, size: 18, color: colorScheme.onPrimary),
        ),
      );
    }

    if (active) {
      return Center(
        child: Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: kCartNavy,
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: kCartNavy.withValues(alpha: 0.35),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Text(
            '${index + 1}',
            style: TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w900,
              fontSize: 14,
              height: 1,
            ),
          ),
        ),
      );
    }

    return Center(
      child: Container(
        width: 32,
        height: 32,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.35),
          border: Border.all(color: colorScheme.outlineVariant.withValues(alpha: 0.65)),
        ),
        child: Text(
          '${index + 1}',
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: kCartMuted,
                fontWeight: FontWeight.w800,
                height: 1,
              ),
        ),
      ),
    );
  }
}
