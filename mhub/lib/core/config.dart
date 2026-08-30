import 'dart:io';

import 'package:flutter/foundation.dart';

/// App-wide configuration.
///
/// The API base URL can be overridden at build/run time:
///   flutter run --dart-define=MHUB_API_BASE=https://mbuniehub.com
class AppConfig {
  const AppConfig._();

  static const String appName = 'MHub';

  /// Value passed with --dart-define, empty when not provided.
  static const String _definedBase = String.fromEnvironment('MHUB_API_BASE');

  /// Resolved base URL of the Laravel backend (no trailing slash).
  static String get apiBase {
    if (_definedBase.isNotEmpty) return _stripSlash(_definedBase);

    // Sensible local defaults per platform for development.
    if (kIsWeb) return 'http://localhost';
    if (Platform.isAndroid) {
      // Android emulator maps host loopback to 10.0.2.2.
      return 'http://10.0.2.2';
    }
    return 'http://localhost';
  }

  static String get apiUrl => '$apiBase/api';

  static String _stripSlash(String v) =>
      v.endsWith('/') ? v.substring(0, v.length - 1) : v;
}
