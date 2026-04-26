import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/profile_extras_repository.dart';

final myReviewsControllerProvider = AsyncNotifierProvider<MyReviewsController, List<MyReviewItem>>(MyReviewsController.new);

class MyReviewsController extends AsyncNotifier<List<MyReviewItem>> {
  @override
  Future<List<MyReviewItem>> build() async {
    return ref.read(profileExtrasRepositoryProvider).loadMyReviews();
  }

  Future<void> reload() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => ref.read(profileExtrasRepositoryProvider).loadMyReviews());
  }
}

