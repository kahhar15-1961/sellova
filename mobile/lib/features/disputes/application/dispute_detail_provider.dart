import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/dispute_repository.dart';

final disputeDetailProvider = FutureProvider.family<DisputeDto, int>((ref, disputeId) async {
  return ref.read(disputeRepositoryProvider).getById(disputeId);
});
