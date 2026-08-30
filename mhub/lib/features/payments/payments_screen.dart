import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/member_api.dart';
import '../../models/payment.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

class PaymentsScreen extends ConsumerWidget {
  const PaymentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(paymentsProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Payments')),
      body: AsyncValueView<List<Payment>>(
        value: async,
        onRefresh: () async => ref.refresh(paymentsProvider.future),
        data: (payments) {
          if (payments.isEmpty) {
            return const Center(child: Text('No payments yet.'));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: payments.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, i) {
              final p = payments[i];
              return MbuiCard(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(p.title,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w600, color: AppColors.gray900)),
                          const SizedBox(height: 2),
                          Text(
                            [p.method, if (p.createdAgo != null) p.createdAgo!].join(' · '),
                            style: const TextStyle(fontSize: 12, color: AppColors.gray500),
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(p.amountLabel,
                            style: const TextStyle(
                                fontWeight: FontWeight.bold, color: AppColors.gray900)),
                        const SizedBox(height: 4),
                        MbuiStatusBadge(p.status),
                      ],
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}
