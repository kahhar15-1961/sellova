import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/withdrawal_repository.dart';

final withdrawalDetailProvider = FutureProvider.family<WithdrawalDto, int>((ref, withdrawalId) async {
  return ref.read(withdrawalRepositoryProvider).getById(withdrawalId);
});
