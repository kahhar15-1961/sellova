import 'package:flutter/material.dart';

import 'auth_ui_constants.dart';

/// Shared filled input style for auth screens (login / sign-up / forgot).
InputDecoration authInputDecoration({
  required String hint,
  Widget? suffix,
}) {
  return InputDecoration(
    hintText: hint,
    filled: true,
    fillColor: const Color(0xFFF8F8FC),
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(kAuthFieldRadius),
      borderSide: const BorderSide(color: Color(0xFFE2E4EF)),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(kAuthFieldRadius),
      borderSide: const BorderSide(color: Color(0xFFE2E4EF)),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(kAuthFieldRadius),
      borderSide: const BorderSide(color: kAuthAccentPurple, width: 1.4),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(kAuthFieldRadius),
      borderSide: BorderSide(color: Colors.red.shade300),
    ),
    suffixIcon: suffix,
  );
}
