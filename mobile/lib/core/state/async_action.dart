import 'package:flutter_riverpod/flutter_riverpod.dart';

typedef AsyncAction<T> = Future<T> Function();

Future<void> runAsyncAction(
  WidgetRef ref, {
  required StateProvider<bool> loadingProvider,
  required StateProvider<String?> errorProvider,
  required AsyncAction<void> action,
}) async {
  ref.read(loadingProvider.notifier).state = true;
  ref.read(errorProvider.notifier).state = null;
  try {
    await action();
  } catch (error) {
    ref.read(errorProvider.notifier).state = error.toString();
    rethrow;
  } finally {
    ref.read(loadingProvider.notifier).state = false;
  }
}
