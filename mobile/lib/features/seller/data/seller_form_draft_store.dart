import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../app/providers/app_providers.dart';

final sellerFormDraftStoreProvider = Provider<SellerFormDraftStore>((ref) {
  return SellerFormDraftStore(ref.watch(sharedPreferencesProvider));
});

class SellerFormDraftStore {
  SellerFormDraftStore(this._prefs);

  final SharedPreferences _prefs;
  static const String _prefix = 'seller_draft.v1.';

  String _k(String key) => '$_prefix$key';

  Future<void> saveStoreSettingsDraft(String name, String description) async {
    await _prefs.setString(
      _k('store_settings'),
      jsonEncode(<String, dynamic>{
        'store_name': name,
        'store_description': description,
        'saved_at': DateTime.now().toIso8601String(),
      }),
    );
  }

  Map<String, dynamic>? loadStoreSettingsDraft() {
    final raw = _prefs.getString(_k('store_settings'));
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    } catch (_) {}
    return null;
  }

  Future<void> clearStoreSettingsDraft() async {
    await _prefs.remove(_k('store_settings'));
  }

  Future<void> saveShippingDraft(Map<String, dynamic> payload) async {
    await _prefs.setString(_k('shipping_settings'), jsonEncode(payload));
  }

  Map<String, dynamic>? loadShippingDraft() {
    final raw = _prefs.getString(_k('shipping_settings'));
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    } catch (_) {}
    return null;
  }

  Future<void> clearShippingDraft() async {
    await _prefs.remove(_k('shipping_settings'));
  }

  Future<void> saveWithdrawDraft(Map<String, dynamic> payload) async {
    await _prefs.setString(_k('withdraw'), jsonEncode(payload));
  }

  Map<String, dynamic>? loadWithdrawDraft() {
    final raw = _prefs.getString(_k('withdraw'));
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    } catch (_) {}
    return null;
  }

  Future<void> clearWithdrawDraft() async {
    await _prefs.remove(_k('withdraw'));
  }

  Future<void> saveBankPaymentDraft(Map<String, dynamic> payload) async {
    await _prefs.setString(_k('bank_payment'), jsonEncode(payload));
  }

  Map<String, dynamic>? loadBankPaymentDraft() {
    final raw = _prefs.getString(_k('bank_payment'));
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    } catch (_) {}
    return null;
  }

  Future<void> clearBankPaymentDraft() async {
    await _prefs.remove(_k('bank_payment'));
  }

  Future<void> saveRespondDisputeDraft(int disputeId, Map<String, dynamic> payload) async {
    await _prefs.setString(_k('respond_dispute.$disputeId'), jsonEncode(payload));
  }

  Map<String, dynamic>? loadRespondDisputeDraft(int disputeId) {
    final raw = _prefs.getString(_k('respond_dispute.$disputeId'));
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    } catch (_) {}
    return null;
  }

  Future<void> clearRespondDisputeDraft(int disputeId) async {
    await _prefs.remove(_k('respond_dispute.$disputeId'));
  }
}
