import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/category_repository.dart';

final categoryListProvider = FutureProvider<List<CategoryDto>>((ref) async {
  return ref.watch(categoryRepositoryProvider).list();
});

