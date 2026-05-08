import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../../orders/data/order_repository.dart';

final paymentGatewayCatalogProvider =
    FutureProvider<List<PaymentGatewayItem>>((ref) async {
  return ref.read(orderRepositoryProvider).listPaymentGateways();
});
