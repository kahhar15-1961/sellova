import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/data/auth_repository.dart';
import '../../features/disputes/data/dispute_repository.dart';
import '../../features/orders/data/order_repository.dart';
import '../../features/products/data/product_repository.dart';
import '../../features/profile/data/profile_repository.dart';
import '../../features/withdrawals/data/withdrawal_repository.dart';
import 'app_providers.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return ref.watch(apiLayerProvider).authRepository;
});

final profileRepositoryProvider = Provider<ProfileRepository>((ref) {
  return ref.watch(apiLayerProvider).profileRepository;
});

final productRepositoryProvider = Provider<ProductRepository>((ref) {
  return ref.watch(apiLayerProvider).productRepository;
});

final orderRepositoryProvider = Provider<OrderRepository>((ref) {
  return ref.watch(apiLayerProvider).orderRepository;
});

final disputeRepositoryProvider = Provider<DisputeRepository>((ref) {
  return ref.watch(apiLayerProvider).disputeRepository;
});

final withdrawalRepositoryProvider = Provider<WithdrawalRepository>((ref) {
  return ref.watch(apiLayerProvider).withdrawalRepository;
});
