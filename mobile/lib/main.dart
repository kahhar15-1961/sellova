import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app/app.dart';
import 'app/bootstrap/bootstrap.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);
  try {
    await Firebase.initializeApp();
  } catch (_) {}
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
