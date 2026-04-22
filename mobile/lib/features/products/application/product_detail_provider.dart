import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/product_repository.dart';

final productDetailProvider = FutureProvider.family<ProductDto, int>((ref, productId) async {
  return ref.read(productRepositoryProvider).getById(productId);
});
