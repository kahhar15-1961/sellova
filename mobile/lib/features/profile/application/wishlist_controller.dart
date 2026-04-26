import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/profile_extras_repository.dart';

final wishlistControllerProvider = AsyncNotifierProvider<WishlistController, List<WishlistItem>>(WishlistController.new);

class WishlistController extends AsyncNotifier<List<WishlistItem>> {
  @override
  Future<List<WishlistItem>> build() async {
    return ref.read(profileExtrasRepositoryProvider).loadWishlist();
  }

  Future<void> remove(int productId) async {
    final next = await ref.read(profileExtrasRepositoryProvider).removeWishlistItem(productId);
    state = AsyncData(next);
  }
}

