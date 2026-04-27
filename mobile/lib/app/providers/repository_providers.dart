import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/data/auth_repository.dart';
import '../../features/categories/data/category_repository.dart';
import '../../features/disputes/data/dispute_repository.dart';
import '../../features/orders/data/order_repository.dart';
import '../../features/orders/data/returns_repository.dart';
import '../../features/products/data/product_repository.dart';
import '../../features/profile/data/profile_repository.dart';
import '../../features/profile/data/profile_extras_repository.dart';
import '../../features/seller/data/seller_business_datasource.dart';
import '../../features/seller/data/seller_repository.dart';
import '../../features/withdrawals/data/withdrawal_repository.dart';
import '../../core/realtime/chat_realtime_client.dart';
import 'app_providers.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return ref.watch(apiLayerProvider).authRepository;
});

final categoryRepositoryProvider = Provider<CategoryRepository>((ref) {
  return ref.watch(apiLayerProvider).categoryRepository;
});

final profileRepositoryProvider = Provider<ProfileRepository>((ref) {
  return ref.watch(apiLayerProvider).profileRepository;
});

final profileExtrasRepositoryProvider =
    Provider<ProfileExtrasRepository>((ref) {
  return ProfileExtrasRepository(
    apiClient: ref.watch(apiLayerProvider).apiClient,
    preferences: ref.watch(sharedPreferencesProvider),
  );
});

final productRepositoryProvider = Provider<ProductRepository>((ref) {
  return ref.watch(apiLayerProvider).productRepository;
});

final orderRepositoryProvider = Provider<OrderRepository>((ref) {
  return ref.watch(apiLayerProvider).orderRepository;
});

final returnsRepositoryProvider = Provider<ReturnsRepository>((ref) {
  return ReturnsRepository(ref.watch(apiLayerProvider).apiClient);
});

final disputeRepositoryProvider = Provider<DisputeRepository>((ref) {
  return ref.watch(apiLayerProvider).disputeRepository;
});

final withdrawalRepositoryProvider = Provider<WithdrawalRepository>((ref) {
  return ref.watch(apiLayerProvider).withdrawalRepository;
});

final sellerRepositoryProvider = Provider<SellerRepository>((ref) {
  return SellerRepository(ref.watch(apiLayerProvider).apiClient);
});

final sellerBusinessDataSourceProvider =
    Provider<SellerBusinessDataSource>((ref) {
  return SellerRepositoryBusinessAdapter(ref.watch(sellerRepositoryProvider));
});

final chatRealtimeClientProvider = Provider<ChatRealtimeClient>((ref) {
  return ChatRealtimeClient(
    baseUrl: ref.watch(baseUrlProvider),
    tokenStore: ref.watch(tokenStoreProvider),
  );
});
