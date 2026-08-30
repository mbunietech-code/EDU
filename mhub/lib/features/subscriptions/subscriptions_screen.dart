import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/member_api.dart';
import '../../models/subscription.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

class SubscriptionsScreen extends ConsumerWidget {
  const SubscriptionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(subscriptionsProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Subscriptions')),
      body: AsyncValueView<List<Subscription>>(
        value: async,
        onRefresh: () async => ref.refresh(subscriptionsProvider.future),
        data: (subs) {
          if (subs.isEmpty) {
            return const Center(child: Text('You have no subscriptions.'));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: subs.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, i) {
              final s = subs[i];
              return MbuiCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(s.product,
                              style: const TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.gray900)),
                        ),
                        MbuiStatusBadge(s.status),
                      ],
                    ),
                    if (s.plan != null) ...[
                      const SizedBox(height: 4),
                      Text(s.plan!,
                          style: const TextStyle(fontSize: 13, color: AppColors.gray500)),
                    ],
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        const Icon(Icons.schedule, size: 15, color: AppColors.gray400),
                        const SizedBox(width: 6),
                        Text(s.expiresIn,
                            style: const TextStyle(fontSize: 13, color: AppColors.gray700)),
                        if (s.expiryDate != null) ...[
                          const Spacer(),
                          Text('Expires ${s.expiryDate}',
                              style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
                        ],
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
