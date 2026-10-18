import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gal/gal.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../data/currency_repository.dart';
import '../../data/member_api.dart';
import '../../models/payment.dart';
import '../../theme/tokens.dart';
import '../../widgets/amount_breakdown.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../orders/orders_repository.dart';

/// Mobile payment flow: method, transfer details, proof, then review.
class SubmitPaymentScreen extends ConsumerStatefulWidget {
  const SubmitPaymentScreen({
    super.key,
    required this.orderId,
    required this.orderTitle,
    required this.amountLabel,
    this.amountTzs,
    this.embedded = false,
  });

  final int orderId;
  final String orderTitle;
  final String amountLabel;

  /// Raw TZS amount, for the USD / CNY equivalents.
  final double? amountTzs;

  /// Shown as the "Pay manually" tab inside the pay screen (no own app bar).
  final bool embedded;

  @override
  ConsumerState<SubmitPaymentScreen> createState() =>
      _SubmitPaymentScreenState();
}

class _SubmitPaymentScreenState extends ConsumerState<SubmitPaymentScreen> {
  final _reference = TextEditingController();
  final _note = TextEditingController();
  String? _method;
  XFile? _proof;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _reference.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _pickProof() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1600,
      imageQuality: 85,
    );
    if (picked != null) setState(() => _proof = picked);
  }

  Future<void> _submit() async {
    setState(() => _error = null);
    if (_method == null) {
      setState(() => _error = 'Choose the payment method you used.');
      return;
    }
    if (_proof == null) {
      setState(() => _error = 'Attach a screenshot of your payment.');
      return;
    }

    setState(() => _busy = true);
    try {
      await ref
          .read(paymentsRepositoryProvider)
          .submit(
            orderId: widget.orderId,
            method: _method!,
            proofPath: _proof!.path,
            reference: _reference.text.trim(),
            note: _note.text.trim(),
          );
      ref.invalidate(orderDetailProvider(widget.orderId));
      ref.invalidate(ordersProvider);
      ref.invalidate(paymentsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Payment submitted. Awaiting review.')),
        );
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = e.message;
        });
      }
    }
  }

  /// Alipay, then WeChat Pay, then the rest in the admin's order.
  static List<PaymentMethod> _walletsFirst(List<PaymentMethod> list) {
    int rank(PaymentMethod m) => switch (_Wallet.of(m)) {
          _Wallet.alipay => 0,
          _Wallet.wechat => 1,
          null => 2,
        };
    final indexed = list.indexed.toList()
      ..sort((a, b) {
        final byRank = rank(a.$2).compareTo(rank(b.$2));
        return byRank != 0 ? byRank : a.$1.compareTo(b.$1);
      });
    return [for (final e in indexed) e.$2];
  }

  @override
  Widget build(BuildContext context) {
    final methods = ref.watch(paymentMethodsProvider);
    final amountTzs = widget.amountTzs ?? AmountBreakdown.parseTzs(widget.amountLabel);

    final body = AsyncValueView<List<PaymentMethod>>(
      value: methods,
      onRefresh: () async => ref.refresh(paymentMethodsProvider.future),
      data: (list) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          MbuiCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const _StepHeader(number: 1, title: 'Order'),
                const SizedBox(height: 6),
                Text(
                  widget.orderTitle,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.gray900,
                  ),
                ),
                const SizedBox(height: 4),
                if (amountTzs != null) ...[
                  const SizedBox(height: 8),
                  AmountBreakdown(amountTzs: amountTzs),
                ] else
                  Text(
                    'Amount due: ${widget.amountLabel}',
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.gray500,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const _StepHeader(number: 2, title: 'Payment method'),
          const SizedBox(height: 8),
          if (list.isEmpty)
            const MbuiCard(
              child: Text('No payment methods are available right now.'),
            )
          else
            for (final m in _walletsFirst(list))
              _MethodTile(
                method: m,
                amountTzs: amountTzs,
                selected: _method == m.code,
                onTap: () => setState(() => _method = m.code),
              ),
          const SizedBox(height: 16),
          MbuiCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const _StepHeader(number: 3, title: 'Transaction details'),
                const SizedBox(height: 12),
                const Text(
                  'Transaction reference',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: AppColors.gray700,
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _reference,
                  decoration: const InputDecoration(
                    hintText: 'e.g. mobile-money confirmation code',
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Note (optional)',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: AppColors.gray700,
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _note,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    hintText: 'Anything the reviewer should know',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const _StepHeader(number: 4, title: 'Payment screenshot'),
          const SizedBox(height: 8),
          MbuiCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_proof != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    child: Image.file(
                      File(_proof!.path),
                      height: 180,
                      fit: BoxFit.cover,
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                MbuiButton(
                  label: _proof == null ? 'Choose image' : 'Replace image',
                  variant: MbuiVariant.secondary,
                  icon: Icons.image_outlined,
                  onPressed: _busy ? null : _pickProof,
                ),
              ],
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.red50,
                borderRadius: BorderRadius.circular(AppRadius.md),
                border: Border.all(
                  color: AppColors.red600.withValues(alpha: 0.3),
                ),
              ),
              child: Text(
                _error!,
                style: const TextStyle(color: AppColors.red700, fontSize: 13),
              ),
            ),
          ],
          const SizedBox(height: 20),
          MbuiButton(
            label: 'Submit payment',
            loading: _busy,
            fullWidth: true,
            onPressed: _submit,
          ),
        ],
      ),
    );

    if (widget.embedded) return body;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Submit payment')),
      body: body,
    );
  }
}

/// Wallets whose QR code is scanned in their own app.
enum _Wallet {
  alipay('Alipay', 'alipays://platformapi/startapp?saId=10000007',
      'open Alipay, tap Scan, then tap the album icon and choose the QR code.'),
  wechat('WeChat', 'weixin://',
      'open WeChat, tap + then Scan, then tap the album icon and choose the QR code.');

  const _Wallet(this.app, this.openUrl, this.howToScan);

  final String app;
  final String openUrl;
  final String howToScan;

  static _Wallet? of(PaymentMethod m) {
    final key = '${m.code} ${m.name}'.toLowerCase();
    if (key.contains('alipay')) return _Wallet.alipay;
    if (key.contains('wechat') || key.contains('weixin')) return _Wallet.wechat;
    return null;
  }
}

class _MethodTile extends ConsumerWidget {
  const _MethodTile({
    required this.method,
    required this.amountTzs,
    required this.selected,
    required this.onTap,
  });

  final PaymentMethod method;
  final double? amountTzs;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final wallet = _Wallet.of(method);
    final cny = wallet != null && amountTzs != null
        ? ref.watch(currencyRatesValueProvider).cnyLabel(amountTzs!)
        : null;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: MbuiCard(
        onTap: onTap,
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  selected
                      ? Icons.radio_button_checked
                      : Icons.radio_button_off,
                  color: selected ? AppColors.indigo600 : AppColors.gray400,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    method.name,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: AppColors.gray900,
                    ),
                  ),
                ),
              ],
            ),
            if (selected) ...[
              if (method.description != null &&
                  method.description!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  method.description!,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.gray600,
                  ),
                ),
              ],
              if (method.accountNumber != null &&
                  method.accountNumber!.isNotEmpty) ...[
                const SizedBox(height: 8),
                _DetailRow(label: 'Pay to', value: method.accountNumber!),
              ],
              if (method.instructions != null &&
                  method.instructions!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  method.instructions!,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.gray600,
                  ),
                ),
              ],
              if (cny != null) ...[
                const SizedBox(height: 12),
                _AmountToEnter(cny: cny, app: wallet!.app),
              ],
              if (method.qrImageUrl != null) ...[
                const SizedBox(height: 12),
                ClipRRect(
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  child: Image.network(
                    method.qrImageUrl!,
                    height: 160,
                    errorBuilder: (_, _, _) => const SizedBox.shrink(),
                  ),
                ),
                if (wallet != null) ...[
                  const SizedBox(height: 10),
                  _QrActions(qrUrl: method.qrImageUrl!, wallet: wallet),
                ],
              ],
            ],
          ],
        ),
      ),
    );
  }
}

/// "Amount to enter in Alipay: ¥25.50" with a copy button.
class _AmountToEnter extends StatelessWidget {
  const _AmountToEnter({required this.cny, required this.app});
  final String cny;
  final String app;

  @override
  Widget build(BuildContext context) {
    final digits = cny.replaceAll(RegExp(r'[^0-9.]'), '');
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 8, 4, 8),
      decoration: BoxDecoration(
        color: AppColors.amber50,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Amount to enter in $app',
                    style: const TextStyle(fontSize: 12, color: AppColors.amber700)),
                Text(cny,
                    style: const TextStyle(
                        fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.amber700)),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Copy amount',
            icon: const Icon(Icons.copy, size: 18, color: AppColors.amber700),
            onPressed: () {
              Clipboard.setData(ClipboardData(text: digits));
              ScaffoldMessenger.of(context)
                  .showSnackBar(SnackBar(content: Text('Copied $digits')));
            },
          ),
        ],
      ),
    );
  }
}

/// A QR code can't be scanned by the phone showing it: save it to Photos,
/// then pick it from the album inside the wallet app.
class _QrActions extends StatefulWidget {
  const _QrActions({required this.qrUrl, required this.wallet});
  final String qrUrl;
  final _Wallet wallet;

  @override
  State<_QrActions> createState() => _QrActionsState();
}

class _QrActionsState extends State<_QrActions> {
  bool _saving = false;

  void _say(String text, {Duration duration = const Duration(seconds: 4)}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(text), duration: duration));
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final res = await Dio().get<List<int>>(
        widget.qrUrl,
        options: Options(responseType: ResponseType.bytes),
      );
      if (!await Gal.hasAccess() && !await Gal.requestAccess()) {
        _say('Allow MHub to save photos, then try again.');
        return;
      }
      await Gal.putImageBytes(
        Uint8List.fromList(res.data!),
        name: 'mbuniehub-${widget.wallet.name}-qr',
      );
      _say('QR code saved to Photos. Now ${widget.wallet.howToScan}',
          duration: const Duration(seconds: 8));
    } catch (_) {
      _say('Could not save the QR code. Check your connection and try again.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _open() async {
    var opened = false;
    try {
      opened = await launchUrl(Uri.parse(widget.wallet.openUrl),
          mode: LaunchMode.externalApplication);
    } catch (_) {}
    if (!opened && mounted) _say('${widget.wallet.app} is not installed on this phone.');
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Paying from this phone? Save the QR code, then ${widget.wallet.howToScan}',
          style: const TextStyle(fontSize: 12.5, height: 1.4, color: AppColors.gray600),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: MbuiButton(
                label: 'Save QR code',
                icon: Icons.download_outlined,
                variant: MbuiVariant.secondary,
                loading: _saving,
                onPressed: _save,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: MbuiButton(
                label: 'Open ${widget.wallet.app}',
                icon: Icons.open_in_new,
                variant: MbuiVariant.secondary,
                onPressed: _open,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _StepHeader extends StatelessWidget {
  const _StepHeader({required this.number, required this.title});
  final int number;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        CircleAvatar(
          radius: 13,
          backgroundColor: AppColors.indigo600,
          child: Text(
            '$number',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        const SizedBox(width: 8),
        Text(
          title,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w800,
            color: AppColors.gray900,
          ),
        ),
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 70,
          child: Text(
            label,
            style: const TextStyle(fontSize: 13, color: AppColors.gray500),
          ),
        ),
        Expanded(
          child: SelectableText(
            value,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColors.gray900,
            ),
          ),
        ),
      ],
    );
  }
}
