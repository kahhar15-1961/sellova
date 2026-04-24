import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/auth/token_store.dart';
import '../../core/network/api_layer_builder.dart';
import '../../core/state/list_state_persistence.dart';

/// Backend origin for REST calls. Override at build time, e.g.
/// `flutter run --dart-define=API_BASE_URL=https://api.example.com`
///
/// When unset, defaults to `http://127.0.0.1:8000` (typical `php artisan serve`) so
/// API requests do not go to the Flutter web dev host (which would 404 on `/api/...`).
final baseUrlProvider = Provider<String>((_) {
  const fromEnv = String.fromEnvironment('API_BASE_URL', defaultValue: '');
  final raw = fromEnv.trim().isEmpty ? 'http://127.0.0.1:8000' : fromEnv.trim();
  return raw.endsWith('/') ? raw.substring(0, raw.length - 1) : raw;
});

final tokenStoreProvider = Provider<TokenStore>((_) {
  throw UnimplementedError('tokenStoreProvider must be overridden at bootstrap');
});

final sharedPreferencesProvider = Provider<SharedPreferences>((_) {
  throw UnimplementedError('sharedPreferencesProvider must be overridden at bootstrap');
});

final listStatePersistenceProvider = Provider<ListStatePersistence>((ref) {
  return ListStatePersistence(ref.watch(sharedPreferencesProvider));
});

final navigationStatePersistenceProvider = Provider<NavigationStatePersistence>((ref) {
  return NavigationStatePersistence(ref.watch(sharedPreferencesProvider));
});

final apiLayerProvider = Provider<ApiLayer>((ref) {
  final baseUrl = ref.watch(baseUrlProvider);
  final tokenStore = ref.watch(tokenStoreProvider);
  return buildApiLayer(baseUrl: baseUrl, tokenStore: tokenStore);
});

final globalLoadingProvider = StateProvider<bool>((_) => false);
final globalErrorProvider = StateProvider<String?>((_) => null);
