import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../data/admin_api.dart';
import '../../models/admin.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

class AdminOrdersScreen extends ConsumerWidget {
  const AdminOrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminOrdersProvider);
    final filter = ref.watch(adminOrderFilterProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Orders')),
      body: Column(
        children: [
          const SizedBox(height: 8),
          AdminFilterBar(
            options: const {
              null: 'All',
              'pending': 'Pending',
              'confirmed': 'Confirmed',
              'rejected': 'Rejected',
              'cancelled': 'Cancelled',
            },
            selected: filter,
            onSelected: (v) =>
                ref.read(adminOrderFilterProvider.notifier).state = v,
          ),
          const SizedBox(height: 4),
          Expanded(
            child: AsyncValueView<AdminList<AdminOrder>>(
              value: async,
              onRefresh: () async => ref.refresh(adminOrdersProvider.future),
              data: (list) {
                if (list.items.isEmpty) {
                  return const Center(
                    child: Text('No orders here.',
                        style: TextStyle(color: AppColors.gray500)),
                  );
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: list.items.length,
                  itemBuilder: (context, i) {
                    final o = list.items[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => AdminOrderDetailScreen(orderId: o.id),
                          ),
                        ),
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(o.title,
                                      style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.gray900)),
                                ),
                                MbuiStatusBadge(o.status),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text('${o.customerName} · ${o.orderNumber}',
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.gray500)),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                Text(o.amountLabel,
                                    style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                        color: AppColors.gray700)),
                                const Spacer(),
                                if (o.createdAgo != null)
                                  Text(o.createdAgo!,
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

class AdminOrderDetailScreen extends ConsumerWidget {
  const AdminOrderDetailScreen({super.key, required this.orderId});
  final int orderId;

  Future<void> _edit(BuildContext context, WidgetRef ref, AdminOrder o) async {
    final controller = TextEditingController(text: o.paymentInstructions ?? '');
    final newValue = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit payment instructions'),
        content: TextField(
          controller: controller,
          autofocus: true,
          minLines: 2,
          maxLines: 6,
          decoration: const InputDecoration(hintText: 'Shown to the customer'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    if (newValue == null) return;

    try {
      await ref.read(adminRepositoryProvider).updateOrder(o.id, newValue);
      ref.invalidate(adminOrderProvider(o.id));
      ref.invalidate(adminOrdersProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Order updated.')));
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _downloadReceipt(BuildContext context, WidgetRef ref, int id) async {
    try {
      final url = await ref.read(adminRepositoryProvider).orderReceiptUrl(id);
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminOrderProvider(orderId));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Order'),
        actions: [
          async.maybeWhen(
            data: (o) => IconButton(
              icon: const Icon(Icons.edit_outlined),
              tooltip: 'Edit payment instructions',
              onPressed: () => _edit(context, ref, o),
            ),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: AsyncValueView<AdminOrder>(
        value: async,
        onRefresh: () async => ref.refresh(adminOrderProvider(orderId).future),
        data: (o) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(child: MbuiTitle(o.title)),
                MbuiStatusBadge(o.status),
              ],
            ),
            const SizedBox(height: 16),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Order number', o.orderNumber),
                  AdminRow('Customer', o.customerName),
                  if (o.customerEmail != null) AdminRow('Email', o.customerEmail!),
                  if (o.plan != null) AdminRow('Plan', o.plan!),
                  AdminRow('Amount', o.amountLabel),
                  if (o.createdAgo != null) AdminRow('Placed', o.createdAgo!),
                ],
              ),
            ),
            if (o.rejectionReason != null) ...[
              const SizedBox(height: 12),
              MbuiCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const MbuiSectionLabel('Rejection reason'),
                    const SizedBox(height: 6),
                    Text(o.rejectionReason!,
                        style: const TextStyle(fontSize: 13, color: AppColors.red700)),
                  ],
                ),
              ),
            ],
            if (o.payments.isNotEmpty) ...[
              const SizedBox(height: 16),
              const MbuiSectionLabel('Payments'),
              const SizedBox(height: 8),
              for (final p in o.payments)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${p.amountLabel} · ${p.method}',
                                  style: const TextStyle(
                                      fontSize: 13, fontWeight: FontWeight.w600)),
                              if (p.reference != null)
                                Text('Ref: ${p.reference}',
                                    style: const TextStyle(
                                        fontSize: 12, color: AppColors.gray500)),
                            ],
                          ),
                        ),
                        MbuiStatusBadge(p.status),
                      ],
                    ),
                  ),
                ),
            ],
            if (o.isConfirmed) ...[
              const SizedBox(height: 24),
              MbuiButton(
                label: 'Download receipt',
                variant: MbuiVariant.secondary,
                icon: Icons.receipt_long_outlined,
                fullWidth: true,
                onPressed: () => _downloadReceipt(context, ref, o.id),
              ),
            ],
            if (!o.isConfirmed && o.status != 'rejected' && o.status != 'cancelled') ...[
              const SizedBox(height: 24),
              _RejectOrderButton(orderId: o.id),
              const SizedBox(height: 6),
              const Text(
                'Approving happens by approving the customer\'s payment on the '
                'Payments screen. Use this only to disapprove the order.',
                style: TextStyle(fontSize: 12, color: AppColors.gray500),
              ),
            ],
            const SizedBox(height: 12),
            _DeleteOrderButton(orderId: o.id),
          ],
        ),
      ),
    );
  }
}

class _RejectOrderButton extends ConsumerStatefulWidget {
  const _RejectOrderButton({required this.orderId});
  final int orderId;

  @override
  ConsumerState<_RejectOrderButton> createState() => _RejectOrderButtonState();
}

class _RejectOrderButtonState extends ConsumerState<_RejectOrderButton> {
  bool _busy = false;

  Future<void> _reject() async {
    final reason = await promptReason(
      context,
      title: 'Disapprove order',
      actionLabel: 'Disapprove',
      hint: 'Explain why (the customer sees this)',
    );
    if (reason == null || reason.isEmpty) return;

    setState(() => _busy = true);
    try {
      await ref.read(adminRepositoryProvider).rejectOrder(widget.orderId, reason);
      ref.invalidate(adminOrderProvider(widget.orderId));
      ref.invalidate(adminOrdersProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Order disapproved.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MbuiButton(
      label: 'Disapprove order',
      variant: MbuiVariant.danger,
      icon: Icons.cancel_outlined,
      fullWidth: true,
      loading: _busy,
      onPressed: _reject,
    );
  }
}

class _DeleteOrderButton extends ConsumerStatefulWidget {
  const _DeleteOrderButton({required this.orderId});
  final int orderId;

  @override
  ConsumerState<_DeleteOrderButton> createState() => _DeleteOrderButtonState();
}

class _DeleteOrderButtonState extends ConsumerState<_DeleteOrderButton> {
  bool _busy = false;

  Future<void> _delete() async {
    final reason = await promptReason(
      context,
      title: 'Delete order',
      actionLabel: 'Delete',
      hint: 'Why are you deleting this order? This cannot be undone.',
    );
    if (reason == null || reason.isEmpty) return;

    setState(() => _busy = true);
    try {
      await ref.read(adminRepositoryProvider).deleteOrder(widget.orderId, reason);
      ref.invalidate(adminOrdersProvider);
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Order deleted.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MbuiButton(
      label: 'Delete order',
      variant: MbuiVariant.danger,
      icon: Icons.delete_outline,
      fullWidth: true,
      loading: _busy,
      onPressed: _delete,
    );
  }
}
