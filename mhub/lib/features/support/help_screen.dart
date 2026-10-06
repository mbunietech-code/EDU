import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../core/config.dart';
import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';
import '../auth/auth_controller.dart';

/// Contact form plus the public pages (FAQ, About, Terms, Privacy).
class HelpScreen extends ConsumerStatefulWidget {
  const HelpScreen({super.key});

  @override
  ConsumerState<HelpScreen> createState() => _HelpScreenState();
}

class _HelpScreenState extends ConsumerState<HelpScreen> {
  late final _name = TextEditingController(text: ref.read(authControllerProvider).user?.name ?? '');
  late final _email = TextEditingController(text: ref.read(authControllerProvider).user?.email ?? '');
  final _subject = TextEditingController();
  final _message = TextEditingController();
  bool _busy = false;
  String? _error;

  static const _pages = [
    ('Frequently asked questions', Icons.help_outline, '/faq'),
    ('About MbunieEduHub', Icons.info_outline, '/about'),
    ('Terms of service', Icons.description_outlined, '/terms'),
    ('Privacy policy', Icons.privacy_tip_outlined, '/privacy'),
  ];

  Future<void> _send() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final res = await ref.read(apiClientProvider).post('/contact', data: {
        'name': _name.text.trim(),
        'email': _email.text.trim(),
        'subject': _subject.text.trim(),
        'message': _message.text.trim(),
      }) as Map<String, dynamic>;
      _subject.clear();
      _message.clear();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message'] as String? ?? 'Message sent.')));
      }
    } on ApiException catch (e) {
      final all = e.errors?.values.expand((v) => v).toList() ?? const <String>[];
      _error = all.isNotEmpty ? all.first : e.message;
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Help & contact')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          MbuiCard(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Column(
              children: [
                for (final (label, icon, path) in _pages)
                  ListTile(
                    leading: Icon(icon, color: AppColors.indigo600),
                    title: Text(label),
                    trailing: const Icon(Icons.open_in_new, size: 18),
                    onTap: () => launchUrl(Uri.parse('${AppConfig.apiBase}$path'), mode: LaunchMode.externalApplication),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          const MbuiSectionLabel('Send us a message'),
          const SizedBox(height: 8),
          MbuiCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(controller: _name, decoration: const InputDecoration(labelText: 'Your name')),
                const SizedBox(height: 12),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email'),
                ),
                const SizedBox(height: 12),
                TextField(controller: _subject, decoration: const InputDecoration(labelText: 'Subject')),
                const SizedBox(height: 12),
                TextField(
                  controller: _message,
                  minLines: 4,
                  maxLines: 8,
                  decoration: const InputDecoration(labelText: 'Message', alignLabelWithHint: true),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(_error!, style: const TextStyle(color: AppColors.red600)),
                ],
                const SizedBox(height: 16),
                MbuiButton(label: 'Send message', icon: Icons.send, loading: _busy, onPressed: _send),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// "Forgot password?" from the login screen: emails a reset link.
Future<void> showForgotPasswordDialog(BuildContext context, WidgetRef ref, {String email = ''}) async {
  final controller = TextEditingController(text: email);
  final ok = await showDialog<bool>(
    context: context,
    builder: (_) => AlertDialog(
      title: const Text('Reset your password'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('Enter your account email. We will send you a link to choose a new password.'),
          const SizedBox(height: 12),
          TextField(
            controller: controller,
            keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(labelText: 'Email'),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Send link')),
      ],
    ),
  );
  if (ok != true || controller.text.trim().isEmpty) return;

  String message;
  try {
    final res = await ref.read(apiClientProvider).post('/forgot-password', data: {'email': controller.text.trim()})
        as Map<String, dynamic>;
    message = res['message'] as String? ?? 'Check your email for the reset link.';
  } on ApiException catch (e) {
    message = e.message;
  }
  if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
}
