import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/auth/token_store.dart';
import '../../core/network/api_layer_builder.dart';
import '../../core/state/list_state_persistence.dart';

final baseUrlProvider = Provider<String>((_) => const String.fromEnvironment('API_BASE_URL'));

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
