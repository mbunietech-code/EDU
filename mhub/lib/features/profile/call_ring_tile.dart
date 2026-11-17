import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/api_client.dart';
import '../../core/notifications/notification_center.dart';
import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';

/// Support admins: "Ring this phone for video help calls", which keeps ringing
/// after signing out (the server keeps this phone as a call device).
/// Hidden for members, admins who cannot answer calls, and desktop builds.
class CallRingTile extends ConsumerStatefulWidget {
  const CallRingTile({super.key});

  @override
  ConsumerState<CallRingTile> createState() => _CallRingTileState();
}

class _CallRingTileState extends ConsumerState<CallRingTile> {
  bool _visible = false;
  bool _on = false;
  bool _busy = true;
  String? _token;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final center = ref.read(notificationCenterProvider);
    if (!center.supportsCalls) return;
    try {
      _token = await center.callToken();
      if (_token == null) return;
      final res = await ref.read(apiClientProvider).get('/call-devices', query: {'token': _token});
      if (!mounted) return;
      setState(() {
        _visible = res['can_answer'] == true;
        _on = res['registered'] == true;
        _busy = false;
      });
      await _remember(_on);
    } catch (_) {
      // offline or an older server: leave the tile hidden
    }
  }

  Future<void> _remember(bool on) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(NotificationCenter.ringsCallsPref, on);
  }

  Future<void> _toggle(bool on) async {
    if (_token == null) return;
    setState(() => _busy = true);
    final api = ref.read(apiClientProvider);
    String message;
    try {
      if (on) {
        await api.post('/call-devices', data: {
          'token': _token,
          'platform': Platform.isIOS ? 'ios' : 'android',
          'device_name': Platform.isIOS ? 'iPhone' : 'Android phone',
        });
        message = 'This phone now rings for video help calls, even when signed out. A test ring was sent.';
      } else {
        await api.delete('/call-devices', data: {'token': _token});
        message = 'This phone no longer rings for calls when signed out.';
      }
      await _remember(on);
      if (mounted) setState(() => _on = on);
    } on ApiException catch (e) {
      message = e.message;
    }
    if (!mounted) return;
    setState(() => _busy = false);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    if (!_visible) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: MbuiCard(
        padding: EdgeInsets.zero,
        child: SwitchListTile(
          secondary: const Icon(Icons.ring_volume_outlined, color: AppColors.indigo600),
          title: const Text('Ring this phone for video help calls'),
          subtitle: const Text('Keeps ringing after you sign out. Answering needs a sign-in.',
              style: TextStyle(fontSize: 12, color: AppColors.gray500)),
          value: _on,
          onChanged: _busy ? null : _toggle,
        ),
      ),
    );
  }
}
