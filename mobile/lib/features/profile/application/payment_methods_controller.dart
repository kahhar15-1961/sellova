import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/profile_extras_repository.dart';

final paymentMethodsControllerProvider =
    AsyncNotifierProvider<PaymentMethodsController, List<PaymentMethodItem>>(PaymentMethodsController.new);

class PaymentMethodsController extends AsyncNotifier<List<PaymentMethodItem>> {
  @override
  Future<List<PaymentMethodItem>> build() async {
    return ref.read(profileExtrasRepositoryProvider).loadPaymentMethods();
  }

  Future<void> addMockCard() async {
    final current = state.valueOrNull ?? <PaymentMethodItem>[];
    final count = current.length + 1;
    final next = await ref.read(profileExtrasRepositoryProvider).addPaymentMethod(
          kind: 'card',
          label: 'Card **** ${1000 + count * 7}',
          subtitle: 'Expires 12/30',
          isDefault: false,
        );
    state = AsyncData(next);
  }

  Future<void> setDefault(String id) async {
    final next = await ref.read(profileExtrasRepositoryProvider).setDefaultPaymentMethod(id);
    state = AsyncData(next);
  }

  Future<void> remove(String id) async {
    final next = await ref.read(profileExtrasRepositoryProvider).removePaymentMethod(id);
    state = AsyncData(next);
  }
}

