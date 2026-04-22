import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app/app.dart';
import 'app/bootstrap/bootstrap.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final overrides = await buildBootstrapOverrides();
  final container = buildBootstrapContainer(overrides: overrides);
  await warmupAuthState(container);

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const SellovaApp(),
    ),
  );
}
