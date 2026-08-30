import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api_client.dart';
import '../../data/member_api.dart';
import '../../models/payment.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../orders/orders_repository.dart';

/// Mirrors the web "Submit payment" page: choose a method, see the pay-to
/// details, attach a screenshot of the transfer, and send it for review.
class SubmitPaymentScreen extends ConsumerStatefulWidget {
  const SubmitPaymentScreen({
    super.key,
    required this.orderId,
    required this.orderTitle,
    required this.amountLabel,
  });

  final int orderId;
  final String orderTitle;
  final String amountLabel;

  @override
  ConsumerState<SubmitPaymentScreen> createState() => _SubmitPaymentScreenState();
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
      await ref.read(paymentsRepositoryProvider).submit(
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

  @override
  Widget build(BuildContext context) {
    final methods = ref.watch(paymentMethodsProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Submit payment')),
      body: AsyncValueView<List<PaymentMethod>>(
        value: methods,
        onRefresh: () async => ref.refresh(paymentMethodsProvider.future),
        data: (list) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const MbuiSectionLabel('Order'),
                  const SizedBox(height: 6),
                  Text(widget.orderTitle,
                      style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.gray900)),
                  const SizedBox(height: 4),
                  Text('Amount due: ${widget.amountLabel}',
                      style: const TextStyle(fontSize: 13, color: AppColors.gray500)),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const MbuiSectionLabel('Payment method'),
            const SizedBox(height: 8),
            if (list.isEmpty)
              const MbuiCard(
                child: Text('No payment methods are available right now.'),
              )
            else
              for (final m in list) _MethodTile(
                method: m,
                selected: _method == m.code,
                onTap: () => setState(() => _method = m.code),
              ),
            const SizedBox(height: 16),
            MbuiCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Transaction reference',
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                          color: AppColors.gray700)),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _reference,
                    decoration: const InputDecoration(
                      hintText: 'e.g. mobile-money confirmation code',
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text('Note (optional)',
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                          color: AppColors.gray700)),
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
            const MbuiSectionLabel('Payment screenshot'),
            const SizedBox(height: 8),
            MbuiCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_proof != null) ...[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      child: Image.file(File(_proof!.path),
                          height: 180, fit: BoxFit.cover),
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
                  border: Border.all(color: AppColors.red600.withValues(alpha: 0.3)),
                ),
                child: Text(_error!,
                    style: const TextStyle(color: AppColors.red700, fontSize: 13)),
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
      ),
    );
  }
}

class _MethodTile extends StatelessWidget {
  const _MethodTile({
    required this.method,
    required this.selected,
    required this.onTap,
  });

  final PaymentMethod method;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
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
                  selected ? Icons.radio_button_checked : Icons.radio_button_off,
                  color: selected ? AppColors.indigo600 : AppColors.gray400,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(method.name,
                      style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppColors.gray900)),
                ),
              ],
            ),
            if (selected) ...[
              if (method.description != null && method.description!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(method.description!,
                    style: const TextStyle(fontSize: 13, color: AppColors.gray600)),
              ],
              if (method.accountNumber != null && method.accountNumber!.isNotEmpty) ...[
                const SizedBox(height: 8),
                _DetailRow(label: 'Pay to', value: method.accountNumber!),
              ],
              if (method.instructions != null && method.instructions!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(method.instructions!,
                    style: const TextStyle(fontSize: 13, color: AppColors.gray600)),
              ],
              if (method.qrImageUrl != null) ...[
                const SizedBox(height: 12),
                ClipRRect(
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  child: Image.network(method.qrImageUrl!,
                      height: 160,
                      errorBuilder: (_, _, _) => const SizedBox.shrink()),
                ),
              ],
            ],
          ],
        ),
      ),
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
          child: Text(label,
              style: const TextStyle(fontSize: 13, color: AppColors.gray500)),
        ),
        Expanded(
          child: SelectableText(value,
              style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.gray900)),
        ),
      ],
    );
  }
}
