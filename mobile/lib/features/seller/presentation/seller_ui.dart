import 'package:flutter/material.dart';

const Color kSellerNavy = Color(0xFF0B1A60);
const Color kSellerMuted = Color(0xFF64748B);
const Color kSellerAccent = Color(0xFF5E49D1);

BoxDecoration sellerCardDecoration(ColorScheme cs) {
  return BoxDecoration(
    color: cs.surface,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
    boxShadow: <BoxShadow>[
      BoxShadow(
        color: const Color(0xFF0F172A).withValues(alpha: 0.05),
        blurRadius: 16,
        offset: const Offset(0, 6),
      ),
    ],
  );
}

String sellerNiceDate(DateTime d) {
  final local = d.toLocal();
  const months = <String>['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  final h = local.hour > 12 ? local.hour - 12 : (local.hour == 0 ? 12 : local.hour);
  final mm = local.minute.toString().padLeft(2, '0');
  final amPm = local.hour >= 12 ? 'PM' : 'AM';
  return '${local.day} ${months[local.month - 1]}, ${local.year}, $h:$mm $amPm';
}

String sellerShortDate(DateTime d) {
  final local = d.toLocal();
  const months = <String>['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return '${local.day} ${months[local.month - 1]}, ${local.year}';
}
