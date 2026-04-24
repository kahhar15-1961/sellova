import 'package:flutter/material.dart';

class AppTheme {
  static ThemeData get light {
    const seed = Color(0xFF4F46E5);
    final colorScheme = ColorScheme.fromSeed(
      seedColor: seed,
      brightness: Brightness.light,
      surface: const Color(0xFFF7F7FB),
    );

    final textTheme = Typography.material2021().black.copyWith(
          headlineSmall: const TextStyle(
            fontSize: 42 - 10,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.3,
            height: 1.15,
          ),
          titleLarge: const TextStyle(
            fontSize: 24 - 2,
            fontWeight: FontWeight.w700,
            height: 1.2,
          ),
          titleMedium: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            height: 1.25,
          ),
          titleSmall: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
            height: 1.3,
          ),
          bodyLarge: const TextStyle(
            fontSize: 16,
            height: 1.45,
          ),
          bodyMedium: const TextStyle(
            fontSize: 15,
            height: 1.4,
          ),
          bodySmall: const TextStyle(
            fontSize: 13,
            height: 1.35,
          ),
          labelLarge: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.1,
          ),
          labelMedium: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        );

    return ThemeData(
      colorScheme: colorScheme,
      useMaterial3: true,
      scaffoldBackgroundColor: const Color(0xFFF5F5FA),
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        foregroundColor: colorScheme.onSurface,
        titleTextStyle: textTheme.titleLarge?.copyWith(
          color: colorScheme.onSurface,
          fontWeight: FontWeight.w700,
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: colorScheme.primaryContainer.withValues(alpha: 0.85),
        iconTheme: WidgetStateProperty.resolveWith<IconThemeData>((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(
            size: 22,
            color: selected ? colorScheme.primary : colorScheme.onSurfaceVariant,
          );
        }),
        labelTextStyle: WidgetStateProperty.resolveWith<TextStyle>((states) {
          final selected = states.contains(WidgetState.selected);
          return textTheme.labelMedium!.copyWith(
            fontWeight: FontWeight.w700,
            color: selected ? colorScheme.primary : colorScheme.onSurfaceVariant,
          );
        }),
      ),
      cardTheme: CardThemeData(
        color: colorScheme.surface.withValues(alpha: 0.84),
        elevation: 0,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(
            color: colorScheme.outlineVariant.withValues(alpha: 0.45),
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        showCheckmark: false,
        backgroundColor: colorScheme.surface.withValues(alpha: 0.9),
        selectedColor: colorScheme.primaryContainer.withValues(alpha: 0.8),
        disabledColor: colorScheme.surfaceContainerHighest.withValues(alpha: 0.45),
        side: BorderSide(color: colorScheme.outlineVariant.withValues(alpha: 0.45)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
        labelStyle: textTheme.labelMedium?.copyWith(color: colorScheme.onSurface),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      ),
      inputDecorationTheme: InputDecorationTheme(
        isDense: true,
        filled: true,
        fillColor: colorScheme.surface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: colorScheme.outlineVariant.withValues(alpha: 0.55)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: colorScheme.outlineVariant.withValues(alpha: 0.55)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: colorScheme.primary, width: 1.4),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        elevation: 0,
        backgroundColor: colorScheme.inverseSurface,
        contentTextStyle: textTheme.bodyMedium?.copyWith(color: colorScheme.onInverseSurface),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      dividerTheme: DividerThemeData(
        color: colorScheme.outlineVariant.withValues(alpha: 0.5),
        thickness: 1,
        space: 1,
      ),
    );
  }
}
