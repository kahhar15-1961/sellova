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

  Future<void> addPaymentMethod({
    required String kind,
    required String label,
    required String subtitle,
    required Map<String, dynamic> details,
    bool isDefault = false,
  }) async {
    final next = await ref.read(profileExtrasRepositoryProvider).addPaymentMethod(
          kind: kind,
          label: label,
          subtitle: subtitle,
          details: details,
          isDefault: isDefault,
        );
    state = AsyncData(next);
  }

  Future<void> updatePaymentMethod({
    required String id,
    required String kind,
    required String label,
    required String subtitle,
    required Map<String, dynamic> details,
    bool isDefault = false,
  }) async {
    final next = await ref
        .read(profileExtrasRepositoryProvider)
        .updatePaymentMethod(
          id: id,
          kind: kind,
          label: label,
          subtitle: subtitle,
          details: details,
          isDefault: isDefault,
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
