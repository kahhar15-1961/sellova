import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/disputes/application/dispute_list_controller.dart';
import '../features/orders/application/order_list_controller.dart';
import '../features/products/application/product_list_controller.dart';
import '../features/withdrawals/application/withdrawal_list_controller.dart';
import 'providers/app_providers.dart';
import 'routing/app_router.dart';
import 'theme/app_theme.dart';
import '../core/widgets/global_async_overlay.dart';

class SellovaApp extends ConsumerStatefulWidget {
  const SellovaApp({super.key});

  @override
  ConsumerState<SellovaApp> createState() => _SellovaAppState();
}

class _SellovaAppState extends ConsumerState<SellovaApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) {
      return;
    }
    Future<void>.microtask(_refreshVisibleModuleIfStale);
  }

  Future<void> _refreshVisibleModuleIfStale() async {
    final router = ref.read(appRouterProvider);
    final location = router.routeInformationProvider.value.uri.toString();
    if (location.startsWith('/home') || location.startsWith('/products')) {
      await ref.read(productListControllerProvider.notifier).refreshIfStale();
      return;
    }
    if (location.startsWith('/orders')) {
      await ref.read(orderListControllerProvider.notifier).refreshIfStale();
      return;
    }
    if (location.startsWith('/disputes')) {
      await ref.read(disputeListControllerProvider.notifier).refreshIfStale();
      return;
    }
    if (location.startsWith('/withdrawals')) {
      await ref.read(withdrawalListControllerProvider.notifier).refreshIfStale();
    }
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(appRouterProvider);
    final loading = ref.watch(globalLoadingProvider);

    ref.listen<String?>(globalErrorProvider, (_, next) {
      if (next == null) {
        return;
      }
      final messenger = ScaffoldMessenger.maybeOf(context);
      messenger?.showSnackBar(SnackBar(content: Text(next)));
    });

    return MaterialApp.router(
      title: 'Sellova',
      theme: AppTheme.light,
      routerConfig: router,
      builder: (BuildContext context, Widget? child) {
        return GlobalAsyncOverlay(
          isLoading: loading,
          child: child ?? const SizedBox.shrink(),
        );
      },
    );
  }
}
