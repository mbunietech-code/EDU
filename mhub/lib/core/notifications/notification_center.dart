import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/notifications/notifications_repository.dart';
import '../../models/app_notification.dart';

/// Polls the backend for new notifications while the user is signed in and the
/// app is in the foreground, shows an OS notification for each new one, and
/// keeps the in-app unread badge fresh.
///
/// (Background push for a fully-closed app needs FCM — added for Android later;
///  firebase_core currently breaks the Windows/Linux desktop build.)
class NotificationCenter with WidgetsBindingObserver {
  NotificationCenter(this._ref);

  final Ref _ref;
  final _plugin = FlutterLocalNotificationsPlugin();

  Timer? _timer;
  bool _running = false;
  bool _initialised = false;
  Set<String> _seenIds = {};
  bool _primed = false;

  static const _pollInterval = Duration(seconds: 30);

  static const _androidChannel = AndroidNotificationChannel(
    'mhub_default',
    'MHub notifications',
    description: 'Orders, payments and account updates',
    importance: Importance.high,
  );

  Future<void> _ensureInit() async {
    if (_initialised) return;
    _initialised = true;

    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const darwinInit = DarwinInitializationSettings();
    final linuxInit = LinuxInitializationSettings(defaultActionName: 'Open');

    await _plugin.initialize(
      InitializationSettings(
        android: androidInit,
        iOS: darwinInit,
        macOS: darwinInit,
        linux: linuxInit,
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
    await _plugin
        .resolvePlatformSpecificImplementation<
            IOSFlutterLocalNotificationsPlugin>()
        ?.requestPermissions(alert: true, badge: true, sound: true);
  }

  /// Call after sign-in.
  Future<void> start() async {
    if (_running) return;
    _running = true;
    _primed = false;
    _seenIds = {};

    try {
      await _ensureInit();
    } catch (e) {
      debugPrint('[MHub] local notifications init failed: $e');
    }

    WidgetsBinding.instance.addObserver(this);
    _poll();
    _timer = Timer.periodic(_pollInterval, (_) => _poll());
  }

  /// Call on sign-out.
  void stop() {
    _running = false;
    _timer?.cancel();
    _timer = null;
    WidgetsBinding.instance.removeObserver(this);
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

      // Refresh the in-app badge / list.
      _ref.invalidate(notificationsProvider);

      final fresh = result.items
          .where((n) => !n.read && !_seenIds.contains(n.id))
          .toList();

      // On the very first poll we only record ids — don't replay old unread.
      if (_primed) {
        for (final n in fresh.reversed) {
          await _show(n);
        }
      }
      _primed = true;
      _seenIds = result.items.map((n) => n.id).toSet();
    } catch (_) {
      // offline / transient — try again next tick
    }
  }

  Future<void> _show(AppNotification n) async {
    try {
      await _plugin.show(
        n.id.hashCode,
        'MHub',
        n.message,
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
