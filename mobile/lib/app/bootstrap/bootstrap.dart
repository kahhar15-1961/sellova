import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/auth/persistent_token_store.dart';
import '../../core/auth/token_store.dart';
import '../providers/app_providers.dart';

Future<List<Override>> buildBootstrapOverrides() async {
  final preferences = await SharedPreferences.getInstance();
  final tokenStore = PersistentTokenStore(preferences);
  return <Override>[
    tokenStoreProvider.overrideWithValue(tokenStore),
  ];
}

ProviderContainer buildBootstrapContainer({
  required List<Override> overrides,
}) {
  return ProviderContainer(overrides: overrides);
}

Future<void> warmupAuthState(ProviderContainer container) async {
  final tokenStore = container.read(tokenStoreProvider) as TokenStore;
  final refreshToken = await tokenStore.readRefreshToken();
  if (refreshToken == null || refreshToken.isEmpty) {
    return;
  }
  // Actual restore call happens from splash to keep initialization visible and traceable.
}
