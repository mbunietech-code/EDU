import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import 'config.dart';

/// Root navigator, so the update check can show a dialog from anywhere.
final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();

/// Checks GET /api/app/latest a few seconds after start-up and, when the
/// website offers a newer version for this platform, asks to update:
///  * Windows: downloads MHub-Setup-x.y.z.exe, starts it and closes the app
///    (the setup replaces the installed app and keeps the user's data).
///  * Android and others: opens the download link (the phone then offers
///    to install the new APK over the current one).
class AppUpdate {
  AppUpdate._();

  static bool _checked = false;

  static void scheduleCheck() {
    if (kIsWeb || _checked) return;
    _checked = true;
    Future.delayed(const Duration(seconds: 4), _check);
  }

  static String? get _platform {
    if (kIsWeb) return null;
    if (Platform.isAndroid) return 'android';
    if (Platform.isWindows) return 'windows';
    if (Platform.isIOS) return 'ios';
    if (Platform.isMacOS) return 'macos';
    if (Platform.isLinux) return 'linux';
    return null;
  }

  static Dio _dio() => Dio(BaseOptions(
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 30),
        headers: {
          'Accept': 'application/json',
          'User-Agent': 'MHubApp/${AppConfig.version} (+https://mbuniehub.com)',
          'X-Requested-With': 'com.mbuniehub.mhub',
        },
      ));

  /// A random id made once per install (not tied to the person or phone
  /// hardware), so the website can tell one device from many. Null if
  /// storage is unavailable.
  static Future<String?> _installId() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      var id = prefs.getString('mhub_install_id');
      if (id == null || id.length < 16) {
        const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        final rnd = Random.secure();
        id = List.generate(32, (_) => chars[rnd.nextInt(chars.length)]).join();
        await prefs.setString('mhub_install_id', id);
      }
      return id;
    } catch (_) {
      return null;
    }
  }

  /// True when [latest] (e.g. "1.2.2") is newer than [current] ("1.2.1").
  @visibleForTesting
  static bool isNewer(String latest, String current) {
    List<int> parts(String v) => v
        .split(RegExp(r'[^0-9]+'))
        .where((p) => p.isNotEmpty)
        .map(int.parse)
        .toList();
    final a = parts(latest), b = parts(current);
    for (var i = 0; i < 3; i++) {
      final x = i < a.length ? a[i] : 0, y = i < b.length ? b[i] : 0;
      if (x != y) return x > y;
    }
    return false;
  }

  static Future<void> _check() async {
    final platform = _platform;
    if (platform == null) return;
    try {
      // platform, version and an anonymous install id let the website's
      // Analytics count app opens per platform and per device.
      final res = await _dio().get('${AppConfig.apiUrl}/app/latest', queryParameters: {
        'platform': platform,
        'v': AppConfig.version,
        'device': await _installId(),
      });
      final info = (res.data?['platforms'] as Map?)?[platform] as Map?;
      final latest = info?['version'] as String?;
      final url = info?['url'] as String?;
      if (latest == null || url == null) return;
      if (!isNewer(latest, AppConfig.version)) return;
      final context = appNavigatorKey.currentContext;
      if (context == null || !context.mounted) return;
      await _ask(context, platform, latest, url);
    } catch (_) {
      // Offline or the site is busy: try again on the next start.
    }
  }

  static Future<void> _ask(BuildContext context, String platform, String latest, String url) async {
    final go = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        icon: const Icon(Icons.system_update),
        title: Text('MHub $latest is available'),
        content: Text('You have ${AppConfig.version}. Update now to get the newest features and fixes.\n\n'
            'Toleo jipya la MHub ($latest) lipo. Sasisha sasa.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Later')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Update')),
        ],
      ),
    );
    if (go != true || !context.mounted) return;

    if (platform == 'windows') {
      await _windowsInstall(context, latest, url);
    } else {
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    }
  }

  /// Downloads the setup with a progress dialog, runs it and exits.
  static Future<void> _windowsInstall(BuildContext context, String latest, String url) async {
    final progress = ValueNotifier<double?>(null);
    final cancel = CancelToken();
    final file = File('${Directory.systemTemp.path}\\MHub-Setup-$latest.exe');

    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (c) => AlertDialog(
        title: Text('Downloading MHub $latest'),
        content: ValueListenableBuilder<double?>(
          valueListenable: progress,
          builder: (_, p, _) => Column(mainAxisSize: MainAxisSize.min, children: [
            LinearProgressIndicator(value: p),
            const SizedBox(height: 8),
            Text(p == null ? 'Starting…' : '${(p * 100).round()}%'),
          ]),
        ),
        actions: [
          TextButton(
            onPressed: () {
              cancel.cancel();
              Navigator.pop(c);
            },
            child: const Text('Cancel'),
          ),
        ],
      ),
    );

    try {
      await _dio().download(
        url,
        file.path,
        cancelToken: cancel,
        options: Options(receiveTimeout: const Duration(minutes: 10)),
        onReceiveProgress: (got, total) {
          if (total > 0) progress.value = got / total;
        },
      );
      // The setup closes this app itself before replacing its files.
      await Process.start(file.path, const [], mode: ProcessStartMode.detached);
      exit(0);
    } catch (e) {
      if (cancel.isCancelled) return;
      if (context.mounted) {
        Navigator.of(context, rootNavigator: true).pop();
        // Fall back to the browser download.
        await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      }
    }
  }
}
