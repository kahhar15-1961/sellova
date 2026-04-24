import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';

class CheckoutAddress {
  const CheckoutAddress({
    required this.id,
    required this.title,
    required this.fullName,
    required this.phone,
    required this.line1,
    required this.line2,
    required this.area,
    required this.city,
    required this.postalCode,
    required this.country,
    required this.isDefault,
  });

  final String id;
  final String title;
  final String fullName;
  final String phone;
  final String line1;
  final String line2;
  final String area;
  final String city;
  final String postalCode;
  final String country;
  final bool isDefault;

  String get compactAddress {
    final parts = <String>[line1, area, city, postalCode, country].where((e) => e.trim().isNotEmpty);
    return parts.join(', ');
  }

  String get detailsAddress {
    final top = <String>[line1, if (line2.trim().isNotEmpty) line2].join(', ');
    final bottom = <String>[area, city, postalCode, country].where((e) => e.trim().isNotEmpty).join(', ');
    return <String>[top, bottom].where((e) => e.trim().isNotEmpty).join('\n');
  }

  CheckoutAddress copyWith({
    String? id,
    String? title,
    String? fullName,
    String? phone,
    String? line1,
    String? line2,
    String? area,
    String? city,
    String? postalCode,
    String? country,
    bool? isDefault,
  }) {
    return CheckoutAddress(
      id: id ?? this.id,
      title: title ?? this.title,
      fullName: fullName ?? this.fullName,
      phone: phone ?? this.phone,
      line1: line1 ?? this.line1,
      line2: line2 ?? this.line2,
      area: area ?? this.area,
      city: city ?? this.city,
      postalCode: postalCode ?? this.postalCode,
      country: country ?? this.country,
      isDefault: isDefault ?? this.isDefault,
    );
  }

  Map<String, dynamic> toJson() => <String, dynamic>{
        'id': id,
        'title': title,
        'full_name': fullName,
        'phone': phone,
        'line1': line1,
        'line2': line2,
        'area': area,
        'city': city,
        'postal_code': postalCode,
        'country': country,
        'is_default': isDefault,
      };

  factory CheckoutAddress.fromJson(Map<String, dynamic> json) {
    return CheckoutAddress(
      id: (json['id'] ?? '').toString(),
      title: (json['title'] ?? '').toString(),
      fullName: (json['full_name'] ?? '').toString(),
      phone: (json['phone'] ?? '').toString(),
      line1: (json['line1'] ?? '').toString(),
      line2: (json['line2'] ?? '').toString(),
      area: (json['area'] ?? '').toString(),
      city: (json['city'] ?? '').toString(),
      postalCode: (json['postal_code'] ?? '').toString(),
      country: (json['country'] ?? '').toString(),
      isDefault: json['is_default'] == true,
    );
  }
}

final savedAddressesProvider = NotifierProvider<SavedAddressesController, List<CheckoutAddress>>(SavedAddressesController.new);

class SavedAddressesController extends Notifier<List<CheckoutAddress>> {
  static const String _prefsKey = 'checkout_addresses_v1';

  @override
  List<CheckoutAddress> build() => _readFromPrefs();

  List<CheckoutAddress> _seed() {
    return const <CheckoutAddress>[
      CheckoutAddress(
        id: 'addr_home',
        title: 'Home',
        fullName: 'Mohammad Ashikur Rahman',
        phone: '01912-345678',
        line1: '123 Green Road',
        line2: '',
        area: 'Dhanmondi',
        city: 'Dhaka',
        postalCode: '1205',
        country: 'Bangladesh',
        isDefault: true,
      ),
      CheckoutAddress(
        id: 'addr_office',
        title: 'Office',
        fullName: 'Mohammad Ashikur Rahman',
        phone: '01876-543210',
        line1: 'Level 8, 42 Gulshan Avenue',
        line2: '',
        area: 'Gulshan 1',
        city: 'Dhaka',
        postalCode: '1212',
        country: 'Bangladesh',
        isDefault: false,
      ),
      CheckoutAddress(
        id: 'addr_parents',
        title: 'Parents Home',
        fullName: 'Mohammad Ashikur Rahman',
        phone: '01711-223344',
        line1: 'House 15, Road 7',
        line2: '',
        area: 'Mirpur DOHS',
        city: 'Dhaka',
        postalCode: '1216',
        country: 'Bangladesh',
        isDefault: false,
      ),
    ];
  }

  List<CheckoutAddress> _readFromPrefs() {
    final raw = ref.read(sharedPreferencesProvider).getString(_prefsKey);
    if (raw == null || raw.isEmpty) {
      final seeded = _seed();
      _persist(seeded);
      return seeded;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) {
        return _seed();
      }
      return decoded.whereType<Map>().map((e) => CheckoutAddress.fromJson(Map<String, dynamic>.from(e))).toList();
    } catch (_) {
      return _seed();
    }
  }

  Future<void> _persist(List<CheckoutAddress> next) async {
    final encoded = jsonEncode(next.map((e) => e.toJson()).toList());
    await ref.read(sharedPreferencesProvider).setString(_prefsKey, encoded);
  }

  CheckoutAddress? defaultAddress() {
    if (state.isEmpty) return null;
    return state.firstWhere((e) => e.isDefault, orElse: () => state.first);
  }

  CheckoutAddress? byId(String? id) {
    if (id == null || id.isEmpty) return null;
    for (final item in state) {
      if (item.id == id) return item;
    }
    return null;
  }

  Future<void> upsert(CheckoutAddress address) async {
    final next = List<CheckoutAddress>.from(state);
    final ix = next.indexWhere((e) => e.id == address.id);
    if (ix >= 0) {
      next[ix] = address;
    } else {
      next.add(address);
    }
    final normalized = _normalizeDefaults(next, preferredDefaultId: address.isDefault ? address.id : null);
    state = normalized;
    await _persist(normalized);
  }

  Future<void> setDefault(String id) async {
    final next = _normalizeDefaults(state, preferredDefaultId: id);
    state = next;
    await _persist(next);
  }

  Future<void> remove(String id) async {
    final next = state.where((e) => e.id != id).toList();
    final normalized = _normalizeDefaults(next);
    state = normalized;
    await _persist(normalized);
  }

  List<CheckoutAddress> _normalizeDefaults(List<CheckoutAddress> input, {String? preferredDefaultId}) {
    if (input.isEmpty) return input;
    final targetId = preferredDefaultId ??
        input.where((e) => e.isDefault).map((e) => e.id).cast<String?>().firstWhere((e) => e != null, orElse: () => input.first.id)!;
    return input.map((e) => e.copyWith(isDefault: e.id == targetId)).toList();
  }
}
