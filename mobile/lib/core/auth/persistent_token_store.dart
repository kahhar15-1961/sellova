import 'package:shared_preferences/shared_preferences.dart';

import 'token_store.dart';

class PersistentTokenStore implements TokenStore {
  PersistentTokenStore(this._preferences);

  static const _accessTokenKey = 'auth.access_token';
  static const _refreshTokenKey = 'auth.refresh_token';

  final SharedPreferences _preferences;

  @override
  Future<void> clear() async {
    await _preferences.remove(_accessTokenKey);
    await _preferences.remove(_refreshTokenKey);
  }

  @override
  Future<String?> readAccessToken() async {
    return _preferences.getString(_accessTokenKey);
  }

  @override
  Future<String?> readRefreshToken() async {
    return _preferences.getString(_refreshTokenKey);
  }

  @override
  Future<void> writeTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _preferences.setString(_accessTokenKey, accessToken);
    await _preferences.setString(_refreshTokenKey, refreshToken);
  }
}
