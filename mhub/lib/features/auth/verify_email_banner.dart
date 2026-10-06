import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';
import 'auth_controller.dart';

/// Shown on the dashboard until the user verifies their email, like the web
/// "verify your email" notice: enter the 6-digit code, or get a new one.
class VerifyEmailBanner extends ConsumerWidget {
  const VerifyEmailBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).user;
    if (user == null || user.emailVerified) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.amber50,
        border: Border.all(color: const Color(0xFFFDE68A)),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.mark_email_unread_outlined, color: AppColors.amber700),
              SizedBox(width: 8),
              Expanded(
                child: Text('Verify your email',
                    style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.amber700)),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text('We sent a 6-digit code to ${user.email}. Enter it to verify your account.',
              style: const TextStyle(fontSize: 13, color: AppColors.gray700)),
          const SizedBox(height: 10),
          MbuiButton(
            label: 'Enter code',
            icon: Icons.password,
            onPressed: () => showModalBottomSheet<void>(
              context: context,
              isScrollControlled: true,
              builder: (_) => const _VerifySheet(),
            ),
          ),
        ],
      ),
    );
  }
}

class _VerifySheet extends ConsumerStatefulWidget {
  const _VerifySheet();

  @override
  ConsumerState<_VerifySheet> createState() => _VerifySheetState();
}

class _VerifySheetState extends ConsumerState<_VerifySheet> {
  final _code = TextEditingController();
  bool _busy = false;
  bool _resending = false;
  String? _error;
  String? _info;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final code = _code.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(code)) {
      setState(() => _error = 'Enter the 6-digit code from the email.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(apiClientProvider).post('/email/verify', data: {'code': code});
      await ref.read(authControllerProvider.notifier).refreshUser();
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Your email is verified.')));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.errors?['code']?.first ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    setState(() {
      _resending = true;
      _error = null;
      _info = null;
    });
    try {
      final data = await ref.read(apiClientProvider).post('/email/verification-notification') as Map<String, dynamic>;
      if (data['verified'] == true) {
        await ref.read(authControllerProvider.notifier).refreshUser();
        if (mounted) Navigator.pop(context);
        return;
      }
      if (mounted) setState(() => _info = data['message'] as String?);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.statusCode == 429 ? 'Please wait a minute before asking for another code.' : e.message);
      }
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('Verify your email', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
          const SizedBox(height: 4),
          const Text('The code expires after 15 minutes.', style: TextStyle(fontSize: 13, color: AppColors.gray500)),
          const SizedBox(height: 16),
          TextField(
            controller: _code,
            autofocus: true,
            keyboardType: TextInputType.number,
            maxLength: 6,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 22, letterSpacing: 8, fontWeight: FontWeight.w600),
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            onSubmitted: (_) => _verify(),
            decoration: InputDecoration(hintText: '000000', counterText: '', errorText: _error),
          ),
          if (_info != null) ...[
            const SizedBox(height: 8),
            Text(_info!, style: const TextStyle(fontSize: 13, color: AppColors.emerald700)),
          ],
          const SizedBox(height: 16),
          MbuiButton(label: 'Verify', fullWidth: true, loading: _busy, onPressed: _busy ? null : _verify),
          const SizedBox(height: 8),
          MbuiButton(
            label: 'Send a new code',
            variant: MbuiVariant.ghost,
            fullWidth: true,
            loading: _resending,
            onPressed: _resending ? null : _resend,
          ),
        ],
      ),
    );
  }
}
