import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/providers/repository_providers.dart';

final chatUnreadCountProvider = FutureProvider<int>((ref) async {
  return ref.read(orderRepositoryProvider).loadChatUnreadCount();
});

