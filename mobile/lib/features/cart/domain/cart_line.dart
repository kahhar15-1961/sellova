import '../../products/data/product_repository.dart';

/// One row in the shopping cart (client-side until order API is live).
class CartLine {
  const CartLine({
    required this.productId,
    required this.title,
    required this.imageUrl,
    required this.productType,
    required this.currency,
    required this.unitPriceRaw,
    required this.quantity,
  });

  final int productId;
  final String title;
  final String? imageUrl;
  final String productType;
  final String currency;
  final String unitPriceRaw;
  final int quantity;

  bool get isPhysical => productType.toLowerCase().trim() == 'physical';

  double get unitAmount => double.tryParse(unitPriceRaw) ?? 0;

  double get lineTotal => unitAmount * quantity;

  String get displayUnitPrice {
    final c = currency.toUpperCase();
    return c.isEmpty ? unitPriceRaw : '$c $unitPriceRaw';
  }

  String get displayLineTotal {
    final c = currency.toUpperCase();
    final t = lineTotal.toStringAsFixed(2);
    return c.isEmpty ? t : '$c $t';
  }

  CartLine copyWith({int? quantity}) {
    return CartLine(
      productId: productId,
      title: title,
      imageUrl: imageUrl,
      productType: productType,
      currency: currency,
      unitPriceRaw: unitPriceRaw,
      quantity: quantity ?? this.quantity,
    );
  }

  Map<String, dynamic> toJson() => <String, dynamic>{
        'product_id': productId,
        'title': title,
        'image_url': imageUrl,
        'product_type': productType,
        'currency': currency,
        'unit_price_raw': unitPriceRaw,
        'quantity': quantity,
      };

  factory CartLine.fromJson(Map<String, dynamic> json) {
    return CartLine(
      productId: (json['product_id'] as num).toInt(),
      title: (json['title'] ?? '').toString(),
      imageUrl: json['image_url']?.toString(),
      productType: (json['product_type'] ?? 'physical').toString(),
      currency: (json['currency'] ?? 'USD').toString(),
      unitPriceRaw: (json['unit_price_raw'] ?? '0').toString(),
      quantity: (json['quantity'] as num?)?.toInt() ?? 1,
    );
  }

  factory CartLine.fromProduct(ProductDto p, {int quantity = 1}) {
    final id = p.id;
    if (id == null) {
      throw StateError('Product has no id');
    }
    final rawPrice = p.raw['base_price'] ?? p.raw['price'] ?? '0';
    return CartLine(
      productId: id,
      title: p.title,
      imageUrl: p.primaryImageUrl,
      productType: p.productType.isEmpty ? 'physical' : p.productType,
      currency: (p.raw['currency'] ?? 'USD').toString(),
      unitPriceRaw: rawPrice.toString(),
      quantity: quantity,
    );
  }
}
