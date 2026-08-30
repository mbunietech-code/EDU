import 'package:flutter/foundation.dart';

/// App-wide configuration.
///
/// The API base URL defaults to the live backend. Override it at build/run
/// time to point at a local dev server:
///   flutter run --dart-define=MHUB_API_BASE=http://192.168.118.5:8123
class AppConfig {
  const AppConfig._();

  static const String appName = 'MHub';

  /// The production backend.
  static const String defaultBase = 'https://mbuniehub.com';

  /// Value passed with --dart-define, empty when not provided.
  static const String _definedBase = String.fromEnvironment('MHUB_API_BASE');

  /// Resolved base URL of the Laravel backend (no trailing slash).
  static String get apiBase {
    final base = _definedBase.isNotEmpty ? _definedBase : defaultBase;
    return _stripSlash(base);
  }

  /// True when running against the bundled production backend.
  static bool get isProduction => apiBase == defaultBase;

  static String get apiUrl => '$apiBase/api';

  /// Debug builds print the resolved base once at startup.
  static void debugPrintTarget() {
    if (kDebugMode) {
      debugPrint('[MHub] API base: $apiBase');
    }
  }

  static String _stripSlash(String v) =>
      v.endsWith('/') ? v.substring(0, v.length - 1) : v;
}
