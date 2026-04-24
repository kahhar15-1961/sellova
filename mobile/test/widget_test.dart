import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:sellova_mobile/app/app.dart';
import 'package:sellova_mobile/app/bootstrap/bootstrap.dart';

void main() {
  testWidgets('SellovaApp boots with bootstrap container', (WidgetTester tester) async {
    SharedPreferences.setMockInitialValues(<String, Object>{});
    final overrides = await buildBootstrapOverrides();
    final container = buildBootstrapContainer(overrides: overrides);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: const SellovaApp(),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));

    expect(find.byType(SellovaApp), findsOneWidget);
  });
}
