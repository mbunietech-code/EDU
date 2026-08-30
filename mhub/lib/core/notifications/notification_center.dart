import 'dart:async';
import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/notifications/notifications_repository.dart';
import '../api_client.dart';

/// Runs in a background isolate when an FCM push arrives while the app is
/// terminated. FCM shows the tray notification itself for `notification`
/// payloads, so there's nothing to do here.
@pragma('vm:entry-point')
Future<void> _fcmBackgroundHandler(RemoteMessage message) async {}

/// One place for notifications:
///  - Android/iOS: Firebase Cloud Messaging for real push (even app closed).
///  - All platforms: while the app is open, polls `/api/notifications` so the
///    in-app badge/list stay live and shows an OS notification for new items.
class NotificationCenter with WidgetsBindingObserver {
  NotificationCenter(this._ref);

  final Ref _ref;
  final _plugin = FlutterLocalNotificationsPlugin();

  Timer? _timer;
  bool _running = false;
  bool _initialised = false;
  bool _fcmReady = false;
  bool _primed = false;
  Set<String> _seenIds = {};
  String? _fcmToken;

  static const _pollInterval = Duration(seconds: 30);

  static const _androidChannel = AndroidNotificationChannel(
    'mhub_default',
    'MHub notifications',
    description: 'Orders, payments and account updates',
    importance: Importance.high,
  );

  bool get _fcmSupported => !kIsWeb && (Platform.isAndroid || Platform.isIOS);

  Future<void> _ensureInit() async {
    if (_initialised) return;
    _initialised = true;

    try {
      await _plugin.initialize(
        InitializationSettings(
          android: const AndroidInitializationSettings('@mipmap/ic_launcher'),
          iOS: const DarwinInitializationSettings(),
          macOS: const DarwinInitializationSettings(),
          linux: LinuxInitializationSettings(defaultActionName: 'Open'),
        ),
      );
      await _plugin
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(_androidChannel);
      await _plugin
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();
    } catch (e) {
      debugPrint('[MHub] local notifications init failed: $e');
    }

    if (_fcmSupported) {
      await _initFcm();
    }
  }

  Future<void> _initFcm() async {
    try {
      await Firebase.initializeApp();
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      await messaging.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: true,
      );
      FirebaseMessaging.onBackgroundMessage(_fcmBackgroundHandler);
      FirebaseMessaging.onMessage.listen(_showFcmForeground);
      messaging.onTokenRefresh.listen((t) {
        _fcmToken = t;
        _registerToken();
      });
      _fcmToken = await messaging.getToken();
      _fcmReady = true;
    } catch (e) {
      debugPrint('[MHub] Firebase not configured / init failed: $e');
    }
  }

  void _showFcmForeground(RemoteMessage message) {
    final n = message.notification;
    if (n == null) return;
    _showLocal(n.hashCode, n.title ?? 'MHub', n.body ?? '');
    _ref.invalidate(notificationsProvider);
  }

  /// Call after sign-in / session restore.
  Future<void> start() async {
    if (_running) return;
    _running = true;
    _primed = false;
    _seenIds = {};

    await _ensureInit();
    await _registerToken();

    WidgetsBinding.instance.addObserver(this);
    _poll();
    _timer = Timer.periodic(_pollInterval, (_) => _poll());
  }

  /// Call on sign-out.
  Future<void> stop() async {
    _running = false;
    _timer?.cancel();
    _timer = null;
    WidgetsBinding.instance.removeObserver(this);
    await _unregisterToken();
  }

  Future<void> _registerToken() async {
    if (!_fcmReady || _fcmToken == null) return;
    try {
      await _ref.read(apiClientProvider).post('/device-tokens', data: {
        'token': _fcmToken,
        'platform': Platform.isIOS ? 'ios' : 'android',
      });
    } on ApiException catch (e) {
      debugPrint('[MHub] token register failed: ${e.message}');
    }
  }

  Future<void> _unregisterToken() async {
    if (_fcmToken == null) return;
    try {
      await _ref
          .read(apiClientProvider)
          .delete('/device-tokens', data: {'token': _fcmToken});
    } on ApiException catch (_) {}
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!_running) return;
    if (state == AppLifecycleState.resumed) {
      _poll();
      _timer ??= Timer.periodic(_pollInterval, (_) => _poll());
    } else {
      _timer?.cancel();
      _timer = null;
    }
  }

  Future<void> _poll() async {
    if (!_running) return;
    try {
      final result = await _ref.read(notificationsRepositoryProvider).list();
      _ref.invalidate(notificationsProvider);

      final fresh = result.items
          .where((n) => !n.read && !_seenIds.contains(n.id))
          .toList();

      // Don't show local notifications for items FCM already delivered.
      if (_primed && !_fcmReady) {
        for (final n in fresh.reversed) {
          _showLocal(n.id.hashCode, 'MHub', n.message);
        }
      }
      _primed = true;
      _seenIds = result.items.map((n) => n.id).toSet();
    } catch (_) {}
  }

  void _showLocal(int id, String title, String body) {
    try {
      _plugin.show(
        id,
        title,
        body,
        const NotificationDetails(
          android: AndroidNotificationDetails(
            'mhub_default',
            'MHub notifications',
            importance: Importance.high,
            priority: Priority.high,
          ),
          iOS: DarwinNotificationDetails(),
          macOS: DarwinNotificationDetails(),
          linux: LinuxNotificationDetails(),
        ),
      );
    } catch (e) {
      debugPrint('[MHub] show notification failed: $e');
    }
  }
}

final notificationCenterProvider = Provider<NotificationCenter>((ref) {
  return NotificationCenter(ref);
});
