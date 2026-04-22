import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/order_repository.dart';

final orderDetailProvider = FutureProvider.family<OrderDto, int>((ref, orderId) async {
  return ref.read(orderRepositoryProvider).getById(orderId);
});
