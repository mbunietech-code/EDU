import 'dart:ui' show PointerDeviceKind;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/app_update.dart';
import 'core/config.dart';
import 'features/auth/auth_controller.dart';
import 'features/auth/login_screen.dart';
import 'features/shell/home_shell.dart';
import 'theme/app_theme.dart';

class MHubApp extends ConsumerWidget {
  const MHubApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MaterialApp(
      navigatorKey: appNavigatorKey,
      scrollBehavior: const _AppScrollBehavior(),
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      // Light-only, to match the website.
      theme: AppTheme.light(),
      themeMode: ThemeMode.light,
      home: const _AuthGate(),
      // PC: the website-style sidebar around every page.
      builder: (context, child) => DesktopFrame(child: child ?? const SizedBox.shrink()),
    );
  }
}

class _AuthGate extends ConsumerWidget {
  const _AuthGate();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(authControllerProvider.select((s) => s.status));

    switch (status) {
      case AuthStatus.unknown:
        return const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        );
      case AuthStatus.authenticated:
        return const HomeShell();
      case AuthStatus.unauthenticated:
        return const LoginScreen();
    }
  }
}

/// Lets lists and tables scroll by dragging with a mouse or touchpad too
/// (Flutter only allows touch dragging by default), which matters on PCs
/// without a scroll wheel or with a touch screen. Scrollbars stay on desktop.
class _AppScrollBehavior extends MaterialScrollBehavior {
  const _AppScrollBehavior();

  @override
  Set<PointerDeviceKind> get dragDevices => const {
        PointerDeviceKind.touch,
        PointerDeviceKind.mouse,
        PointerDeviceKind.trackpad,
        PointerDeviceKind.stylus,
        PointerDeviceKind.invertedStylus,
        PointerDeviceKind.unknown,
      };
}
