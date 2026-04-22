import '../auth/auth_session_manager.dart';
import '../auth/token_store.dart';
import '../errors/api_error_mapper.dart';
import '../../features/auth/data/auth_repository.dart';
import '../../features/disputes/data/dispute_repository.dart';
import '../../features/orders/data/order_repository.dart';
import '../../features/products/data/product_repository.dart';
import '../../features/profile/data/profile_repository.dart';
import '../../features/withdrawals/data/withdrawal_repository.dart';
import 'api_client.dart';

class ApiLayer {
  ApiLayer({
    required this.apiClient,
    required this.authRepository,
    required this.profileRepository,
    required this.productRepository,
    required this.orderRepository,
    required this.disputeRepository,
    required this.withdrawalRepository,
  });

  final ApiClient apiClient;
  final AuthRepository authRepository;
  final ProfileRepository profileRepository;
  final ProductRepository productRepository;
  final OrderRepository orderRepository;
  final DisputeRepository disputeRepository;
  final WithdrawalRepository withdrawalRepository;
}

ApiLayer buildApiLayer({
  required String baseUrl,
  required TokenStore tokenStore,
}) {
  final sessionManager = AuthSessionManager(
    baseUrl: baseUrl,
    tokenStore: tokenStore,
  );
  final errorMapper = ApiErrorMapper();
  final apiClient = ApiClient(
    baseUrl: baseUrl,
    tokenStore: tokenStore,
    sessionManager: sessionManager,
    errorMapper: errorMapper,
  );

  return ApiLayer(
    apiClient: apiClient,
    authRepository: AuthRepository(apiClient: apiClient, tokenStore: tokenStore),
    profileRepository: ProfileRepository(apiClient),
    productRepository: ProductRepository(apiClient),
    orderRepository: OrderRepository(apiClient),
    disputeRepository: DisputeRepository(apiClient),
    withdrawalRepository: WithdrawalRepository(apiClient),
  );
}
