import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/app_notification.dart';
import '../../widgets/async_value_view.dart';
import '../orders/order_detail_screen.dart';
import 'notifications_repository.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  IconData _iconFor(String type) {
    final t = type.toLowerCase();
    if (t.contains('order')) return Icons.receipt_long_outlined;
    if (t.contains('payment')) return Icons.payments_outlined;
    if (t.contains('subscription')) return Icons.autorenew;
    if (t.contains('chat') || t.contains('message')) return Icons.chat_bubble_outline;
    if (t.contains('error')) return Icons.error_outline;
    return Icons.notifications_none;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(notificationsProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: () async {
              await ref.read(notificationsRepositoryProvider).markAllRead();
              ref.invalidate(notificationsProvider);
            },
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: AsyncValueView<({List<AppNotification> items, int unread})>(
        value: async,
        onRefresh: () async => ref.refresh(notificationsProvider.future),
        data: (result) {
          if (result.items.isEmpty) {
            return const Center(child: Text("You're all caught up."));
          }
          return ListView.separated(
            itemCount: result.items.length,
            separatorBuilder: (_, _) => const Divider(height: 1),
            itemBuilder: (context, i) {
              final n = result.items[i];
              return ListTile(
                leading: CircleAvatar(
                  backgroundColor: n.read
                      ? Theme.of(context).colorScheme.surfaceContainerHighest
                      : Theme.of(context).colorScheme.primaryContainer,
                  child: Icon(_iconFor(n.type), size: 20),
                ),
                title: Text(
                  n.message,
                  style: TextStyle(
                    fontWeight: n.read ? FontWeight.normal : FontWeight.w600,
                  ),
                ),
                subtitle: n.createdAgo != null ? Text(n.createdAgo!) : null,
                trailing: n.read
                    ? null
                    : Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.primary,
                          shape: BoxShape.circle,
                        ),
                      ),
                onTap: () async {
                  if (!n.read) {
                    await ref.read(notificationsRepositoryProvider).markRead(n.id);
                    ref.invalidate(notificationsProvider);
                  }
                  if (n.orderId != null && context.mounted) {
                    Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => OrderDetailScreen(
                        orderId: n.orderId!,
                        title: 'Order',
                      ),
                    ));
                  }
                },
              );
            },
          );
        },
      ),
    );
  }
}
