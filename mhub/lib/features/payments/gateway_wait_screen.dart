import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../data/member_api.dart';
import '../../models/gateway_payment.dart';
import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';
import '../orders/orders_repository.dart';

/// Waits for an automatic payment: the PIN prompt on the phone (mobile
/// money) or the card / PayPal page in the browser. Mirrors the web
/// "Check your phone" page and confirms by itself.
class GatewayWaitScreen extends ConsumerStatefulWidget {
  const GatewayWaitScreen({super.key, required this.initial, this.openPageNow = false});

  final GatewayPayment initial;

  /// Open the card / PayPal page in the browser straight away.
  final bool openPageNow;

  @override
  ConsumerState<GatewayWaitScreen> createState() => _GatewayWaitScreenState();
}

class _GatewayWaitScreenState extends ConsumerState<GatewayWaitScreen> with WidgetsBindingObserver {
  late GatewayPayment _payment = widget.initial;
  Timer? _timer;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _timer = Timer.periodic(const Duration(seconds: 4), (_) => _poll());
    if (widget.openPageNow && _payment.redirectUrl != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _openPage());
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  // Coming back from the browser: check right away.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _poll();
  }

  Future<void> _poll() async {
    if (_checking || !mounted || _payment.isSuccessful || _payment.isFailed) return;
    _checking = true;
    try {
      final next = await ref.read(paymentsRepositoryProvider).gatewayStatus(_payment.id);
      if (!mounted) return;
      setState(() => _payment = next);
      if (next.isSuccessful || next.isFailed) {
        _timer?.cancel();
        ref.invalidate(orderDetailProvider(next.orderId));
        ref.invalidate(ordersProvider);
        ref.invalidate(paymentsProvider);
      }
    } on ApiException {
      // Temporary network trouble: the next tick tries again.
    } finally {
      _checking = false;
    }
  }

  Future<void> _openPage() async {
    final url = _payment.redirectUrl ?? widget.initial.redirectUrl;
    if (url == null) return;
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }

  void _done() => Navigator.of(context).pop(_payment.isSuccessful);

  @override
  Widget build(BuildContext context) {
    final p = _payment;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(p.label)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          MbuiCard(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                _StatusIcon(payment: p),
                const SizedBox(height: 20),
                Text(_title(p),
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.gray900)),
                const SizedBox(height: 10),
                Text(_body(p),
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 14, height: 1.45, color: AppColors.gray600)),
                const SizedBox(height: 24),
                if (p.isPending && p.usesRedirect && p.redirectUrl != null)
                  MbuiButton(
                    label: p.gateway == 'paypal' ? 'Open PayPal' : 'Open secure card page',
                    icon: Icons.open_in_new,
                    fullWidth: true,
                    onPressed: _openPage,
                  ),
                if (p.isSuccessful)
                  MbuiButton(label: 'View my order', fullWidth: true, variant: MbuiVariant.success, onPressed: _done),
                if (p.isFailed || p.isExpired)
                  MbuiButton(label: 'Try again', fullWidth: true, onPressed: _done),
                const SizedBox(height: 16),
                Text('Ref ${p.reference}', style: const TextStyle(fontSize: 11, color: AppColors.gray400)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _title(GatewayPayment p) {
    if (p.isSuccessful) return 'Payment received';
    if (p.isFailed) return 'Payment not completed';
    if (p.isExpired) return 'No confirmation yet';
    if (p.gateway == 'paypal') return 'Pay on PayPal';
    if (p.usesRedirect) return 'Complete your card payment';
    return 'Check your phone';
  }

  String _body(GatewayPayment p) {
    if (p.isSuccessful) {
      return 'Thank you! Your order is confirmed and your receipt has been sent to your email.';
    }
    if (p.isFailed) return p.message ?? 'The payment was cancelled or declined.';
    if (p.isExpired) {
      return 'We did not get a reply in time. If money left your account, stay on this page a moment: '
          'it keeps checking. Otherwise, try again.';
    }
    if (p.usesRedirect) {
      return 'Pay ${p.chargedLabel ?? p.amountLabel} on the secure page that opened in your browser, '
          'then come back here. Your order is confirmed automatically.';
    }
    return 'A payment request of ${p.amountLabel} was sent to ${p.phone ?? 'your phone'}. '
        'Enter your mobile money PIN to approve it. This page updates by itself.';
  }
}

class _StatusIcon extends StatelessWidget {
  const _StatusIcon({required this.payment});
  final GatewayPayment payment;

  @override
  Widget build(BuildContext context) {
    if (payment.isPending) {
      return const SizedBox(
        width: 52,
        height: 52,
        child: CircularProgressIndicator(strokeWidth: 4, color: AppColors.indigo600),
      );
    }

    final (icon, bg, fg) = payment.isSuccessful
        ? (Icons.check_rounded, AppColors.emerald50, AppColors.emerald600)
        : payment.isFailed
            ? (Icons.close_rounded, AppColors.red50, AppColors.red600)
            : (Icons.schedule_rounded, AppColors.amber50, AppColors.amber700);

    return Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(color: bg, shape: BoxShape.circle),
      child: Icon(icon, color: fg, size: 30),
    );
  }
}
