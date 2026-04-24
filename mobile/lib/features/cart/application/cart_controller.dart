import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/app_providers.dart';
import '../../products/data/product_repository.dart';
import '../domain/cart_line.dart';

final cartControllerProvider = NotifierProvider<CartController, List<CartLine>>(CartController.new);

class CartController extends Notifier<List<CartLine>> {
  static const String _prefsKey = 'cart_lines_v1';

  @override
  List<CartLine> build() {
    return _readFromPrefs();
  }

  List<CartLine> _readFromPrefs() {
    final raw = ref.read(sharedPreferencesProvider).getString(_prefsKey);
    if (raw == null || raw.isEmpty) {
      return const <CartLine>[];
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) {
        return const <CartLine>[];
      }
      return decoded
          .whereType<Map>()
          .map((e) => CartLine.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (_) {
      return const <CartLine>[];
    }
  }

  Future<void> _persist() async {
    final encoded = jsonEncode(state.map((CartLine e) => e.toJson()).toList());
    await ref.read(sharedPreferencesProvider).setString(_prefsKey, encoded);
  }

  int get itemCount => state.fold<int>(0, (sum, CartLine e) => sum + e.quantity);

  void addFromProduct(ProductDto product, {int quantity = 1}) {
    final id = product.id;
    if (id == null) {
      return;
    }
    final next = List<CartLine>.from(state);
    final ix = next.indexWhere((CartLine e) => e.productId == id);
    if (ix >= 0) {
      next[ix] = next[ix].copyWith(quantity: next[ix].quantity + quantity);
    } else {
      next.add(CartLine.fromProduct(product, quantity: quantity));
    }
    state = next;
    _persist();
  }

  void setQuantity(int productId, int quantity) {
    if (quantity < 1) {
      remove(productId);
      return;
    }
    final next = state.map((CartLine e) {
      if (e.productId == productId) {
        return e.copyWith(quantity: quantity);
      }
      return e;
    }).toList();
    state = next;
    _persist();
  }

  void increment(int productId) {
    final ix = state.indexWhere((CartLine e) => e.productId == productId);
    if (ix < 0) {
      return;
    }
    setQuantity(productId, state[ix].quantity + 1);
  }

  void decrement(int productId) {
    final ix = state.indexWhere((CartLine e) => e.productId == productId);
    if (ix < 0) {
      return;
    }
    setQuantity(productId, state[ix].quantity - 1);
  }

  void remove(int productId) {
    state = state.where((CartLine e) => e.productId != productId).toList();
    _persist();
  }

  void clear() {
    state = const <CartLine>[];
    _persist();
  }

  /// Removes quantities matching [orderedLines] (e.g. after checkout) without clearing unrelated cart rows.
  void decrementForCompletedOrder(Iterable<CartLine> orderedLines) {
    final next = List<CartLine>.from(state);
    for (final CartLine ordered in orderedLines) {
      final ix = next.indexWhere((CartLine e) => e.productId == ordered.productId);
      if (ix < 0) {
        continue;
      }
      final have = next[ix].quantity;
      final take = ordered.quantity;
      if (have <= take) {
        next.removeAt(ix);
      } else {
        next[ix] = next[ix].copyWith(quantity: have - take);
      }
    }
    state = next;
    _persist();
  }

  double subtotalAmount() {
    return state.fold<double>(0, (sum, CartLine e) => sum + e.lineTotal);
  }

  bool get hasPhysicalItem => state.any((CartLine e) => e.isPhysical);
}
