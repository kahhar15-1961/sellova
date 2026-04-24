import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

typedef TelemetryAttributes = Map<String, Object?>;

/// Lightweight analytics / observability hook. Override [telemetryProvider] in tests or bootstrap.
abstract class Telemetry {
  void record(String event, [TelemetryAttributes attributes = const <String, Object?>{}]);
}

class NoOpTelemetry implements Telemetry {
  const NoOpTelemetry();

  @override
  void record(String event, [TelemetryAttributes attributes = const <String, Object?>{}]) {}
}

class DebugTelemetry implements Telemetry {
  const DebugTelemetry();

  @override
  void record(String event, [TelemetryAttributes attributes = const <String, Object?>{}]) {
    if (kDebugMode) {
      debugPrint('telemetry: $event $attributes');
    }
  }
}

final telemetryProvider = Provider<Telemetry>((_) => const NoOpTelemetry());
