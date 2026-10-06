import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
                onTap: () => _showAccess(context, ref, s),
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

/// Login details for the subscription's account (same rule as the web).
void _showAccess(BuildContext context, WidgetRef ref, Subscription s) {
  showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (sheet) => FutureBuilder<String?>(
      future: ref.read(subscriptionsRepositoryProvider).credentials(s.id),
      builder: (context, snap) {
        final Widget body;
        if (snap.connectionState != ConnectionState.done) {
          body = const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator()));
        } else if (snap.hasError) {
          body = const Text('Could not load the login details. Try again.');
        } else if (snap.data == null || snap.data!.isEmpty) {
          body = const Text('Login details show here while the subscription is active.',
              style: TextStyle(color: AppColors.gray500));
        } else {
          body = Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.gray50,
                  border: Border.all(color: AppColors.gray200),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: SelectableText(snap.data!, style: const TextStyle(fontFamily: 'monospace', fontSize: 14)),
              ),
              const SizedBox(height: 10),
              MbuiButton(
                label: 'Copy',
                icon: Icons.copy,
                variant: MbuiVariant.secondary,
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: snap.data!));
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Copied.')));
                  }
                },
              ),
            ],
          );
        }

        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('${s.product} login', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                const SizedBox(height: 12),
                body,
              ],
            ),
          ),
        );
      },
    ),
  );
}
