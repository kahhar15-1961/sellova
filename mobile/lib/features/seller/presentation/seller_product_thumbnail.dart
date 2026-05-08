import 'package:flutter/material.dart';

import '../domain/seller_models.dart';

class SellerProductThumbnail extends StatelessWidget {
  const SellerProductThumbnail({
    super.key,
    required this.product,
    this.size = 56,
    this.borderRadius = 14,
  });

  final SellerProduct? product;
  final double size;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    final url = _bestImageUrl(product);
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: SizedBox(
        width: size,
        height: size,
        child: url == null
            ? _placeholder(context)
            : Image.network(
                url,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => _placeholder(context),
              ),
      ),
    );
  }

  static String? _bestImageUrl(SellerProduct? product) {
    if (product == null) {
      return null;
    }
    for (final value in <String>[
      ...product.imageUrls,
      if ((product.imageUrl ?? '').trim().isNotEmpty) product.imageUrl!,
    ]) {
      final trimmed = value.trim();
      if (trimmed.isNotEmpty) {
        return trimmed;
      }
    }
    return null;
  }

  Widget _placeholder(BuildContext context) {
    return ColoredBox(
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Icon(
        Icons.inventory_2_outlined,
        color: Theme.of(context).colorScheme.onPrimaryContainer,
      ),
    );
  }
}
