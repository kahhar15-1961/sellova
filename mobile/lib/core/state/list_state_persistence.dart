import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

class PersistedListUiState {
  const PersistedListUiState({
    required this.query,
    required this.sort,
    required this.filters,
    required this.currentTab,
    required this.scrollOffset,
    required this.page,
    required this.perPage,
    required this.items,
    required this.savedAtEpochMs,
  });

  final String query;
  final String sort;
  final Map<String, dynamic> filters;
  final int? currentTab;
  final double scrollOffset;
  final int page;
  final int perPage;
  final List<Map<String, dynamic>> items;
  final int savedAtEpochMs;

  DateTime get savedAt => DateTime.fromMillisecondsSinceEpoch(savedAtEpochMs);

  Map<String, dynamic> toJson() => <String, dynamic>{
        'query': query,
        'sort': sort,
        'filters': filters,
        'current_tab': currentTab,
        'scroll_offset': scrollOffset,
        'page': page,
        'per_page': perPage,
        'items': items,
        'saved_at_ms': savedAtEpochMs,
      };

  factory PersistedListUiState.fromJson(Map<String, dynamic> json) {
    return PersistedListUiState(
      query: (json['query'] ?? '').toString(),
      sort: (json['sort'] ?? 'latest').toString(),
      filters: json['filters'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['filters'] as Map<String, dynamic>)
          : <String, dynamic>{},
      currentTab: (json['current_tab'] as num?)?.toInt(),
      scrollOffset: (json['scroll_offset'] as num?)?.toDouble() ?? 0,
      page: (json['page'] as num?)?.toInt() ?? 1,
      perPage: (json['per_page'] as num?)?.toInt() ?? 10,
      items: (json['items'] as List<dynamic>? ?? const <dynamic>[])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      savedAtEpochMs: (json['saved_at_ms'] as num?)?.toInt() ??
          DateTime.now().millisecondsSinceEpoch,
    );
  }
}

class ListStatePersistence {
  const ListStatePersistence(this._preferences);

  final SharedPreferences _preferences;

  static String _key(String moduleKey) => 'list_state.$moduleKey';

  static const Duration staleTtl = Duration(minutes: 20);
  static const List<String> moduleKeys = <String>[
    'products',
    'orders',
    'disputes',
    'withdrawals',
  ];

  Future<void> save(String moduleKey, PersistedListUiState state) async {
    final nowStamped = PersistedListUiState(
      query: state.query,
      sort: state.sort,
      filters: state.filters,
      currentTab: state.currentTab,
      scrollOffset: state.scrollOffset,
      page: state.page,
      perPage: state.perPage,
      items: state.items,
      savedAtEpochMs: DateTime.now().millisecondsSinceEpoch,
    );
    await _preferences.setString(
        _key(moduleKey), jsonEncode(nowStamped.toJson()));
  }

  PersistedListUiState? load(String moduleKey) {
    final raw = _preferences.getString(_key(moduleKey));
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return PersistedListUiState.fromJson(decoded);
      }
    } catch (_) {
      return null;
    }
    return null;
  }

  Future<void> clear(String moduleKey) async {
    await _preferences.remove(_key(moduleKey));
  }

  bool isStale(
    String moduleKey, {
    Duration ttl = staleTtl,
  }) {
    final state = load(moduleKey);
    if (state == null) {
      return false;
    }
    final age = DateTime.now().difference(state.savedAt);
    return age > ttl;
  }

  Future<void> clearAllBrowsingState() async {
    for (final key in moduleKeys) {
      await clear(key);
    }
    await clearProductBrowsingState();
  }

  Future<void> clearProductBrowsingState() async {
    await clear('products');
    const categoryListPrefix = 'list_state.category_detail_';
    const storefrontListPrefix = 'list_state.storefront_';
    for (final key in _preferences.getKeys()) {
      if (key.startsWith(categoryListPrefix) ||
          key.startsWith(storefrontListPrefix)) {
        await _preferences.remove(key);
      }
    }
  }
}

class NavigationStatePersistence {
  const NavigationStatePersistence(this._preferences);

  final SharedPreferences _preferences;
  static const _key = 'navigation.last_route';
  static const _home = '/home';

  String? loadLastRoute() => _preferences.getString(_key);

  Future<void> saveLastRoute(String route) async {
    await _preferences.setString(_key, route);
  }

  Future<void> resetToHome() async {
    await _preferences.setString(_key, _home);
  }
}
