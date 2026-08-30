import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/config.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  AppConfig.debugPrintTarget();
  runApp(const ProviderScope(child: MHubApp()));
}
