import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/admin_api.dart';
import '../../models/admin.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

class AdminPaymentsScreen extends ConsumerWidget {
  const AdminPaymentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminPaymentsProvider);
    final filter = ref.watch(adminPaymentFilterProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Payments')),
      body: Column(
        children: [
          const SizedBox(height: 8),
          AdminFilterBar(
            options: const {
              null: 'All',
              'pending': 'Pending',
              'approved': 'Approved',
              'rejected': 'Rejected',
            },
            selected: filter,
            onSelected: (v) =>
                ref.read(adminPaymentFilterProvider.notifier).state = v,
          ),
          const SizedBox(height: 4),
          Expanded(
            child: AsyncValueView<AdminList<AdminPayment>>(
              value: async,
              onRefresh: () async => ref.refresh(adminPaymentsProvider.future),
              data: (list) {
                if (list.items.isEmpty) {
                  return const Center(
                    child: Text('Nothing to review.',
                        style: TextStyle(color: AppColors.gray500)),
                  );
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: list.items.length,
                  itemBuilder: (context, i) {
                    final p = list.items[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                AdminPaymentDetailScreen(paymentId: p.id),
                          ),
                        ),
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(p.title,
                                      style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.gray900)),
                                ),
                                MbuiStatusBadge(p.status),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text('${p.customerName} · ${p.method}',
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.gray500)),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                Text(p.amountLabel,
                                    style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                        color: AppColors.gray700)),
                                const Spacer(),
                                if (p.createdAgo != null)
                                  Text(p.createdAgo!,
                                      style: const TextStyle(
                                          fontSize: 12, color: AppColors.gray400)),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class AdminPaymentDetailScreen extends ConsumerStatefulWidget {
  const AdminPaymentDetailScreen({super.key, required this.paymentId});
  final int paymentId;

  @override
  ConsumerState<AdminPaymentDetailScreen> createState() =>
      _AdminPaymentDetailScreenState();
}

class _AdminPaymentDetailScreenState
    extends ConsumerState<AdminPaymentDetailScreen> {
  bool _busy = false;

  Future<void> _approve() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Approve payment?'),
        content: const Text(
          'This confirms the order and activates the subscription / delivers '
          'the tool. It cannot be undone here.',
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.emerald600),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Approve'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    setState(() => _busy = true);
    try {
      await ref.read(adminRepositoryProvider).approvePayment(widget.paymentId);
      _refresh();
      _toast('Payment approved.');
    } on ApiException catch (e) {
      _toast(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reject() async {
    final reason = await promptReason(
      context,
      title: 'Reject payment',
      actionLabel: 'Reject',
      hint: 'Reason (optional, the customer sees this)',
      required: false,
    );
    if (reason == null) return;

    setState(() => _busy = true);
    try {
      await ref
          .read(adminRepositoryProvider)
          .rejectPayment(widget.paymentId, reason.isEmpty ? null : reason);
      _refresh();
      _toast('Payment rejected.');
    } on ApiException catch (e) {
      _toast(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _refresh() {
    ref.invalidate(adminPaymentProvider(widget.paymentId));
    ref.invalidate(adminPaymentsProvider);
    ref.invalidate(adminOrdersProvider);
  }

  void _toast(String msg) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(adminPaymentProvider(widget.paymentId));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Payment review')),
      body: AsyncValueView<AdminPayment>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminPaymentProvider(widget.paymentId).future),
        data: (p) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(child: MbuiTitle(p.amountLabel)),
                MbuiStatusBadge(p.status),
              ],
            ),
            const SizedBox(height: 16),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Product', p.title),
                  AdminRow('Customer', p.customerName),
                  if (p.customerEmail != null) AdminRow('Email', p.customerEmail!),
                  AdminRow('Method', p.method),
                  if (p.reference != null) AdminRow('Reference', p.reference!),
                  if (p.orderNumber != null) AdminRow('Order', p.orderNumber!),
                  if (p.createdAgo != null) AdminRow('Submitted', p.createdAgo!),
                  if (p.adminNote != null) AdminRow('Note', p.adminNote!),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const MbuiSectionLabel('Payment proof'),
            const SizedBox(height: 8),
            if (p.proofs.isEmpty)
              const MbuiCard(child: Text('No proof was attached.'))
            else
              for (final proof in p.proofs)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _ProofImage(path: proof.path, caption: proof.caption),
                ),
            if (p.isPending) ...[
              const SizedBox(height: 24),
              MbuiButton(
                label: 'Approve payment',
                variant: MbuiVariant.success,
                icon: Icons.check_circle_outline,
                fullWidth: true,
                loading: _busy,
                onPressed: _approve,
              ),
              const SizedBox(height: 10),
              MbuiButton(
                label: 'Reject payment',
                variant: MbuiVariant.danger,
                icon: Icons.cancel_outlined,
                fullWidth: true,
                loading: _busy,
                onPressed: _reject,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ProofImage extends ConsumerStatefulWidget {
  const _ProofImage({required this.path, this.caption});
  final String path;
  final String? caption;

  @override
  ConsumerState<_ProofImage> createState() => _ProofImageState();
}

class _ProofImageState extends ConsumerState<_ProofImage> {
  Uint8List? _bytes;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data =
          await ref.read(adminRepositoryProvider).proofBytes(widget.path);
      if (mounted) setState(() => _bytes = Uint8List.fromList(data));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MbuiCard(
      padding: const EdgeInsets.all(8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text('Could not load proof: $_error',
                  style: const TextStyle(fontSize: 12, color: AppColors.red700)),
            )
          else if (_bytes == null)
            const SizedBox(
              height: 160,
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            )
          else
            GestureDetector(
              onTap: () => showDialog(
                context: context,
                builder: (_) => Dialog(
                  child: InteractiveViewer(child: Image.memory(_bytes!)),
                ),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.md),
                child: Image.memory(_bytes!,
                    width: double.infinity, fit: BoxFit.cover),
              ),
            ),
          if (widget.caption != null && widget.caption!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.all(8),
              child: Text(widget.caption!,
                  style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
            ),
        ],
      ),
    );
  }
}
