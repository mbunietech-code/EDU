import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/order.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/status_chip.dart';
import 'order_detail_screen.dart';
import 'orders_repository.dart';

class OrdersScreen extends ConsumerWidget {
  const OrdersScreen({super.key, this.title = 'My Orders'});

  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(ordersProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: AppBar(title: Text(title)),
      body: AsyncValueView<List<Order>>(
        value: async,
        onRefresh: () async => ref.refresh(ordersProvider.future),
        data: (orders) {
          if (orders.isEmpty) {
            return const Center(child: Text('You have no orders yet.'));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: orders.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, i) {
              final o = orders[i];
              return Card(
                child: ListTile(
                  title: Text(o.title,
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(
                    [
                      if (o.plan != null) o.plan!,
                      o.orderNumber,
                      if (o.createdAgo != null) o.createdAgo!,
                    ].join(' · '),
                  ),
                  trailing: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(o.amountLabel,
                          style: const TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 4),
                      StatusChip(o.status),
                    ],
                  ),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => OrderDetailScreen(orderId: o.id, title: o.title),
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
