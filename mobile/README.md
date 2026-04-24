# sellova_mobile

Sellova Flutter app for local development and API flow testing.

## Getting Started

Run from repo root:

```bash
scripts/run_mobile_dev.sh
```

Custom API host (for example backend on port 8080):

```bash
scripts/run_mobile_dev.sh http://127.0.0.1:8080
```

Target a specific device (example: Chrome):

```bash
FLUTTER_DEVICE=chrome scripts/run_mobile_dev.sh
```

Hot reload after code changes:

- Press `r` in the running `flutter run` terminal for hot reload
- Press `R` for hot restart

Note: changes to `pubspec.yaml` or native Android/iOS code still require a full restart.

A few resources to get you started if this is your first Flutter project:

- [Learn Flutter](https://docs.flutter.dev/get-started/learn-flutter)
- [Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Flutter learning resources](https://docs.flutter.dev/reference/learning-resources)

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.
