import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/order.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../../widgets/status_chip.dart';
import '../payments/submit_payment_screen.dart';
import 'orders_repository.dart';

class OrderDetailScreen extends ConsumerWidget {
  const OrderDetailScreen({super.key, required this.orderId, required this.title});

  final int orderId;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(orderDetailProvider(orderId));

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: AppBar(title: Text(title)),
      body: AsyncValueView<Order>(
        value: async,
        onRefresh: () async => ref.refresh(orderDetailProvider(orderId).future),
        data: (o) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(o.title,
                      style: Theme.of(context)
                          .textTheme
                          .titleLarge
                          ?.copyWith(fontWeight: FontWeight.bold)),
                ),
                StatusChip(o.status),
              ],
            ),
            const SizedBox(height: 12),
            _row('Order number', o.orderNumber),
            if (o.plan != null) _row('Plan', o.plan!),
            _row('Amount', o.amountLabel),
            if (o.createdAgo != null) _row('Placed', o.createdAgo!),
            if (o.isRejected && o.rejectionReason != null) ...[
              const SizedBox(height: 12),
              _Banner(
                color: const Color(0xFFFEE2E2),
                textColor: const Color(0xFF991B1B),
                icon: Icons.cancel_outlined,
                text: o.rejectionReason!,
              ),
            ],
            if (o.isPending && o.paymentInstructions != null) ...[
              const SizedBox(height: 12),
              _Banner(
                color: const Color(0xFFFEF9C3),
                textColor: const Color(0xFF854D0E),
                icon: Icons.info_outline,
                text: o.paymentInstructions!,
              ),
            ],
            if (o.payments.isNotEmpty) ...[
              const SizedBox(height: 20),
              Text('Payments', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              for (final p in o.payments)
                Card(
                  child: ListTile(
                    title: Text(p.amountLabel),
                    trailing: StatusChip(p.status),
                  ),
                ),
            ],
            if (o.isPending && !o.payments.any((p) => p.status == 'pending')) ...[
              const SizedBox(height: 24),
              MbuiButton(
                label: o.payments.isEmpty ? 'Submit payment' : 'Submit another payment',
                icon: Icons.receipt_long_outlined,
                fullWidth: true,
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => SubmitPaymentScreen(
                      orderId: o.id,
                      orderTitle: o.title,
                      amountLabel: o.amountLabel,
                    ),
                  ),
                ),
              ),
            ],
            if (o.canCancel) ...[
              const SizedBox(height: 28),
              _CancelButton(orderId: o.id),
              const SizedBox(height: 6),
              const Text(
                'You can only cancel while the order is still pending. '
                'Confirmed orders cannot be cancelled here.',
                style: TextStyle(fontSize: 12, color: Colors.grey),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 130,
              child: Text(label, style: const TextStyle(color: Colors.grey)),
            ),
            Expanded(child: Text(value)),
          ],
        ),
      );
}

class _CancelButton extends ConsumerStatefulWidget {
  const _CancelButton({required this.orderId});

  final int orderId;

  @override
  ConsumerState<_CancelButton> createState() => _CancelButtonState();
}

class _CancelButtonState extends ConsumerState<_CancelButton> {
  bool _busy = false;

  Future<void> _confirm() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Cancel order?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep order'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Cancel order'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    setState(() => _busy = true);
    try {
      await ref.read(ordersRepositoryProvider).cancel(widget.orderId);
      ref.invalidate(ordersProvider);
      ref.invalidate(orderDetailProvider(widget.orderId));
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Order cancelled.')));
        Navigator.of(context).pop();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: _busy ? null : _confirm,
      style: OutlinedButton.styleFrom(
        foregroundColor: Theme.of(context).colorScheme.error,
        side: BorderSide(color: Theme.of(context).colorScheme.error),
        minimumSize: const Size.fromHeight(48),
      ),
      icon: _busy
          ? const SizedBox(
              height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
          : const Icon(Icons.cancel_outlined),
      label: const Text('Cancel this order'),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({
    required this.color,
    required this.textColor,
    required this.icon,
    required this.text,
  });

  final Color color;
  final Color textColor;
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: textColor, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(text, style: TextStyle(color: textColor))),
        ],
      ),
    );
  }
}
