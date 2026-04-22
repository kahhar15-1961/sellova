import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers/app_providers.dart';
import 'routing/app_router.dart';
import 'theme/app_theme.dart';
import '../core/widgets/global_async_overlay.dart';

class SellovaApp extends ConsumerWidget {
  const SellovaApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(appRouterProvider);
    final loading = ref.watch(globalLoadingProvider);

    ref.listen<String?>(globalErrorProvider, (_, next) {
      if (next == null) {
        return;
      }
      final messenger = ScaffoldMessenger.maybeOf(context);
      messenger?.showSnackBar(SnackBar(content: Text(next)));
    });

    return GlobalAsyncOverlay(
      isLoading: loading,
      child: MaterialApp.router(
        title: 'Sellova',
        theme: AppTheme.light,
        routerConfig: router,
      ),
    );
  }
}
